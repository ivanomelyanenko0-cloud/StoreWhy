<?php
/**
 * CognitoLab Journal: a shared, PII-free log of what changed on the site.
 *
 * Plugins report their own changes with
 * do_action( 'cognitolab_journal_event', $source, $event, $args ).
 * Nobody has to listen: without a journal-bundling plugin the hook is a
 * no-op, which is what keeps every plugin fully independent.
 *
 * Rows hold IDs, types and before/after values of settings only - never
 * customer names, emails, addresses or order contents.
 *
 * @package CognitoLabJournal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Version of the event hook contract (hook name + argument shape). Emitting
 * plugins may check it before relying on newer fields.
 */
define( 'CLJOURNAL_CONTRACT_VERSION', 1 );
define( 'CLJOURNAL_DB_VERSION', 1 );
define( 'CLJOURNAL_CRON_HOOK', 'cljournal_prune' );

/**
 * @return string Fully prefixed table name.
 */
function cljournal_table() {
	global $wpdb;
	return $wpdb->prefix . 'cljournal_events';
}

/**
 * Creates or upgrades the table when the stored schema version is behind.
 * Runs on init rather than on activation: a plugin activated in the current
 * request has not booted the journal yet, and the newest bundled copy may
 * belong to a different plugin than the one being activated.
 */
function cljournal_maybe_install() {
	if ( (int) get_option( 'cljournal_db_version', 0 ) >= CLJOURNAL_DB_VERSION ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = cljournal_table();
	$charset = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			occurred_at datetime NOT NULL,
			source varchar(64) NOT NULL,
			event varchar(64) NOT NULL,
			object_type varchar(32) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			data longtext NULL,
			PRIMARY KEY  (id),
			KEY occurred_at (occurred_at),
			KEY object (object_type, object_id)
		) {$charset};"
	);

	update_option( 'cljournal_db_version', CLJOURNAL_DB_VERSION );
}
add_action( 'init', 'cljournal_maybe_install', 5 );

/**
 * Keeps the list of installed journal consumers current, for uninstall.
 */
function cljournal_sync_consumers() {
	$known   = get_option( 'cljournal_consumers', array() );
	$known   = is_array( $known ) ? $known : array();
	$current = isset( $GLOBALS['cljournal_consumers'] ) && is_array( $GLOBALS['cljournal_consumers'] ) ? $GLOBALS['cljournal_consumers'] : array();
	$merged  = array_values( array_unique( array_merge( $known, $current ) ) );

	if ( $merged !== $known ) {
		update_option( 'cljournal_consumers', $merged, false );
	}
}
add_action( 'admin_init', 'cljournal_sync_consumers' );

/**
 * Records an event. Writes are buffered and flushed once at shutdown, and
 * repeats of the same event on the same object within one request collapse
 * into one row (saving a post fires many meta updates).
 *
 * @param string $source Who reports it: 'wordpress', 'woocommerce', or a plugin slug.
 * @param string $event  Machine name, e.g. 'bar_unpublished'.
 * @param array  $args   {
 *     Optional.
 *
 *     @type string $object_type 'product', 'page', 'bar', 'site', ...
 *     @type int    $object_id   Object ID, 0 for site-wide events.
 *     @type array  $data        Small scalar details (before/after, labels). No personal data.
 *     @type int    $time        Unix timestamp, defaults to now.
 * }
 */
function cljournal_record( $source, $event, $args = array() ) {
	$args = wp_parse_args(
		is_array( $args ) ? $args : array(),
		array(
			'object_type' => 'site',
			'object_id'   => 0,
			'data'        => array(),
			'time'        => time(),
		)
	);

	$row = array(
		'occurred_at' => gmdate( 'Y-m-d H:i:s', (int) $args['time'] ),
		'source'      => substr( sanitize_key( $source ), 0, 64 ),
		'event'       => substr( sanitize_key( $event ), 0, 64 ),
		'object_type' => substr( sanitize_key( $args['object_type'] ), 0, 32 ),
		'object_id'   => absint( $args['object_id'] ),
		'actor_id'    => get_current_user_id(),
		'data'        => is_array( $args['data'] ) ? $args['data'] : array(),
	);

	if ( '' === $row['source'] || '' === $row['event'] ) {
		return;
	}

	/**
	 * Filters an event before it is buffered. Return false to drop it.
	 *
	 * @param array|false $row Event row.
	 */
	$row = apply_filters( 'cljournal_record_event', $row );
	if ( ! is_array( $row ) ) {
		return;
	}

	$key = $row['source'] . '|' . $row['event'] . '|' . $row['object_type'] . '|' . $row['object_id'];
	cljournal_buffer( $key, $row );
}

/**
 * Hook entry point for plugins: do_action( 'cognitolab_journal_event', $source, $event, $args ).
 *
 * @param string $source Plugin slug.
 * @param string $event  Event name.
 * @param array  $args   See cljournal_record().
 */
function cljournal_handle_event( $source, $event, $args = array() ) {
	cljournal_record( $source, $event, $args );
}
add_action( 'cognitolab_journal_event', 'cljournal_handle_event', 10, 3 );

/**
 * Request-scoped write buffer.
 *
 * @param string|null $key Dedup key; null returns and clears the buffer.
 * @param array|null  $row Row to store under $key.
 * @return array Buffered rows when called with no arguments.
 */
function cljournal_buffer( $key = null, $row = null ) {
	static $rows = array();
	static $hooked = false;

	if ( null === $key ) {
		$out  = $rows;
		$rows = array();
		return $out;
	}

	if ( isset( $rows[ $key ] ) ) {
		// Keep the first "before" and the latest "after" when an event repeats.
		$first = $rows[ $key ]['data'];
		$row['data'] = array_merge( $row['data'], array_intersect_key( $first, array( 'before' => true ) ) );
	}
	$rows[ $key ] = $row;

	if ( ! $hooked ) {
		add_action( 'shutdown', 'cljournal_flush', 1 );
		$hooked = true;
	}
	return array();
}

/**
 * Writes buffered events in one multi-row INSERT.
 */
function cljournal_flush() {
	$rows = cljournal_buffer();
	if ( empty( $rows ) || (int) get_option( 'cljournal_db_version', 0 ) < 1 ) {
		return;
	}

	global $wpdb;
	$placeholders = array();
	$values       = array();

	foreach ( $rows as $row ) {
		$placeholders[] = '(%s, %s, %s, %s, %d, %d, %s)';
		array_push(
			$values,
			$row['occurred_at'],
			$row['source'],
			$row['event'],
			$row['object_type'],
			$row['object_id'],
			$row['actor_id'],
			wp_json_encode( $row['data'] )
		);
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- multi-row insert; every value goes through the placeholders built above.
	$wpdb->query(
		$wpdb->prepare(
			'INSERT INTO %i (occurred_at, source, event, object_type, object_id, actor_id, data) VALUES ' . implode( ', ', $placeholders ),
			array_merge( array( cljournal_table() ), $values )
		)
	);
	// phpcs:enable
}

/**
 * Reads events, newest first.
 *
 * @param array $args {
 *     Optional.
 *
 *     @type string $after       UTC 'Y-m-d H:i:s', inclusive.
 *     @type string $before      UTC 'Y-m-d H:i:s', exclusive.
 *     @type string $source      Limit to one source.
 *     @type string $object_type Limit to one object type.
 *     @type int    $object_id   Limit to one object (needs object_type).
 *     @type int    $limit       Default 100, max 500.
 *     @type int    $offset      Default 0.
 * }
 * @return array[] Rows with 'data' decoded to an array.
 */
function cljournal_query( $args = array() ) {
	global $wpdb;

	$args  = wp_parse_args( $args, array( 'limit' => 100, 'offset' => 0 ) );
	$where = array( '1=1' );
	$vals  = array();

	if ( ! empty( $args['after'] ) ) {
		$where[] = 'occurred_at >= %s';
		$vals[]  = (string) $args['after'];
	}
	if ( ! empty( $args['before'] ) ) {
		$where[] = 'occurred_at < %s';
		$vals[]  = (string) $args['before'];
	}
	if ( ! empty( $args['source'] ) ) {
		$where[] = 'source = %s';
		$vals[]  = sanitize_key( $args['source'] );
	}
	if ( ! empty( $args['object_type'] ) ) {
		$where[] = 'object_type = %s';
		$vals[]  = sanitize_key( $args['object_type'] );
		if ( ! empty( $args['object_id'] ) ) {
			$where[] = 'object_id = %d';
			$vals[]  = absint( $args['object_id'] );
		}
	}

	$vals[] = min( 500, max( 1, (int) $args['limit'] ) );
	$vals[] = max( 0, (int) $args['offset'] );

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- live log; the WHERE clauses are fixed strings with placeholders, values are passed separately.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM %i WHERE ' . implode( ' AND ', $where ) . ' ORDER BY occurred_at DESC, id DESC LIMIT %d OFFSET %d',
			array_merge( array( cljournal_table() ), $vals )
		),
		ARRAY_A
	);
	// phpcs:enable

	foreach ( (array) $rows as $i => $row ) {
		$decoded             = json_decode( (string) $row['data'], true );
		$rows[ $i ]['data']  = is_array( $decoded ) ? $decoded : array();
	}
	return (array) $rows;
}

/**
 * @return string[] Distinct sources present in the journal.
 */
function cljournal_sources() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live log.
	return array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT source FROM %i ORDER BY source', cljournal_table() ) ) );
}

/**
 * Daily retention cleanup.
 */
function cljournal_prune() {
	global $wpdb;

	/**
	 * Filters how many days of events are kept.
	 *
	 * @param int $days Default 90.
	 */
	$days = max( 1, (int) apply_filters( 'cljournal_retention_days', 90 ) );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- retention cleanup of this plugin's own table.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE occurred_at < %s', cljournal_table(), gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) );
}
add_action( CLJOURNAL_CRON_HOOK, 'cljournal_prune' );

/**
 * Schedules the cleanup once.
 */
function cljournal_schedule_prune() {
	if ( ! wp_next_scheduled( CLJOURNAL_CRON_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', CLJOURNAL_CRON_HOOK );
	}
}
add_action( 'init', 'cljournal_schedule_prune' );
