<?php
/**
 * Observers: record changes that are visible through core WordPress hooks,
 * so the journal works without any edits to the plugins it watches.
 * Anything a plugin cannot expose this way it reports itself through the
 * 'cognitolab_journal_event' hook.
 *
 * @package CognitoLabJournal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * WordPress.
 */

/**
 * @param string $plugin Plugin basename.
 */
function cljournal_on_plugin_activated( $plugin ) {
	cljournal_record( 'wordpress', 'plugin_activated', array( 'data' => array( 'plugin' => (string) $plugin ) ) );
}
add_action( 'activated_plugin', 'cljournal_on_plugin_activated' );

/**
 * @param string $plugin Plugin basename.
 */
function cljournal_on_plugin_deactivated( $plugin ) {
	cljournal_record( 'wordpress', 'plugin_deactivated', array( 'data' => array( 'plugin' => (string) $plugin ) ) );
}
add_action( 'deactivated_plugin', 'cljournal_on_plugin_deactivated' );

/**
 * @param string   $new_name  New theme name.
 * @param WP_Theme $new_theme New theme.
 * @param WP_Theme $old_theme Previous theme.
 */
function cljournal_on_switch_theme( $new_name, $new_theme = null, $old_theme = null ) {
	cljournal_record(
		'wordpress',
		'theme_switched',
		array(
			'data' => array(
				'before' => $old_theme instanceof WP_Theme ? $old_theme->get( 'Name' ) : '',
				'after'  => (string) $new_name,
			),
		)
	);
}
add_action( 'switch_theme', 'cljournal_on_switch_theme', 10, 3 );

/**
 * @param WP_Upgrader $upgrader Upgrader instance.
 * @param array       $options  Update details.
 */
function cljournal_on_upgrade( $upgrader, $options ) {
	if ( empty( $options['action'] ) || 'update' !== $options['action'] || empty( $options['type'] ) ) {
		return;
	}

	if ( 'core' === $options['type'] ) {
		cljournal_record( 'wordpress', 'core_updated', array( 'data' => array( 'after' => get_bloginfo( 'version' ) ) ) );
		return;
	}

	$items = array();
	if ( 'plugin' === $options['type'] && ! empty( $options['plugins'] ) ) {
		$items = (array) $options['plugins'];
	} elseif ( 'theme' === $options['type'] && ! empty( $options['themes'] ) ) {
		$items = (array) $options['themes'];
	}

	foreach ( $items as $item ) {
		cljournal_record(
			'wordpress',
			'plugin' === $options['type'] ? 'plugin_updated' : 'theme_updated',
			array( 'data' => array( 'item' => (string) $item ) )
		);
	}
}
add_action( 'upgrader_process_complete', 'cljournal_on_upgrade', 10, 2 );

/*
 * Post-based objects: WooCommerce products, Heralda bars, pages.
 */

/**
 * @param string  $new_status New status.
 * @param string  $old_status Old status.
 * @param WP_Post $post       Post.
 */
function cljournal_on_transition_post_status( $new_status, $old_status, $post ) {
	if ( $new_status === $old_status || ! $post instanceof WP_Post ) {
		return;
	}

	$was_live = 'publish' === $old_status;
	$is_live  = 'publish' === $new_status;
	if ( $was_live === $is_live ) {
		return;
	}

	$map = array(
		'product'  => array( 'woocommerce', 'product', 'product_published', 'product_unpublished' ),
		'hrld_bar' => array( 'heralda', 'bar', 'bar_published', 'bar_unpublished' ),
		'page'     => array( 'wordpress', 'page', 'page_published', 'page_unpublished' ),
	);
	if ( ! isset( $map[ $post->post_type ] ) ) {
		return;
	}

	list( $source, $type, $on, $off ) = $map[ $post->post_type ];
	cljournal_record(
		$source,
		$is_live ? $on : $off,
		array(
			'object_type' => $type,
			'object_id'   => $post->ID,
			'data'        => array(
				'title'  => get_the_title( $post ),
				'before' => $old_status,
				'after'  => $new_status,
			),
		)
	);
}
add_action( 'transition_post_status', 'cljournal_on_transition_post_status', 10, 3 );

/**
 * Meta keys worth recording, per post type: source, object type, event.
 *
 * @return array
 */
function cljournal_watched_meta() {
	return array(
		'product'           => array(
			'_regular_price' => array( 'woocommerce', 'product', 'price_changed' ),
			'_sale_price'    => array( 'woocommerce', 'product', 'sale_price_changed' ),
			'_stock_status'  => array( 'woocommerce', 'product', 'stock_status_changed' ),
		),
		'product_variation' => array(
			'_regular_price' => array( 'woocommerce', 'product', 'price_changed' ),
			'_sale_price'    => array( 'woocommerce', 'product', 'sale_price_changed' ),
			'_stock_status'  => array( 'woocommerce', 'product', 'stock_status_changed' ),
		),
		'hrld_bar'          => array(
			'_hrld_schedule_start' => array( 'heralda', 'bar', 'bar_schedule_changed' ),
			'_hrld_schedule_end'   => array( 'heralda', 'bar', 'bar_schedule_changed' ),
			'_hrld_target_pages'   => array( 'heralda', 'bar', 'bar_targeting_changed' ),
			'_hrld_cta_url'        => array( 'heralda', 'bar', 'bar_cta_changed' ),
			'_hrld_cta_text'       => array( 'heralda', 'bar', 'bar_cta_changed' ),
		),
	);
}

/**
 * Fires before a meta value is updated, so the old value is still readable.
 *
 * @param int    $meta_id    Meta ID.
 * @param int    $object_id  Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value New value.
 */
function cljournal_on_update_postmeta( $meta_id, $object_id, $meta_key, $meta_value ) {
	cljournal_maybe_record_meta( $object_id, $meta_key, get_post_meta( $object_id, $meta_key, true ), $meta_value );
}
add_action( 'update_post_meta', 'cljournal_on_update_postmeta', 10, 4 );

/**
 * A first-time value (e.g. a sale price set on a product that never had one).
 *
 * @param int    $meta_id    Meta ID.
 * @param int    $object_id  Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value New value.
 */
function cljournal_on_add_postmeta( $meta_id, $object_id, $meta_key, $meta_value ) {
	cljournal_maybe_record_meta( $object_id, $meta_key, '', $meta_value );
}
add_action( 'added_post_meta', 'cljournal_on_add_postmeta', 10, 4 );

/**
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param mixed  $before  Old value.
 * @param mixed  $after   New value.
 */
function cljournal_maybe_record_meta( $post_id, $key, $before, $after ) {
	if ( ! is_scalar( $after ) || ! is_scalar( $before ) || (string) $before === (string) $after ) {
		return;
	}

	$post_type = get_post_type( $post_id );
	$watched   = cljournal_watched_meta();
	if ( ! $post_type || ! isset( $watched[ $post_type ][ $key ] ) ) {
		return;
	}

	// A brand-new post fills its meta right after creation; that is not a change.
	if ( 'auto-draft' === get_post_status( $post_id ) || isset( cljournal_new_posts()[ (int) $post_id ] ) ) {
		return;
	}

	list( $source, $type, $event ) = $watched[ $post_type ][ $key ];

	// Variations are reported against their parent product.
	$object_id = 'product_variation' === $post_type ? (int) wp_get_post_parent_id( $post_id ) : (int) $post_id;

	cljournal_record(
		$source,
		$event,
		array(
			'object_type' => $type,
			'object_id'   => $object_id,
			'data'        => array(
				'field'  => $key,
				'before' => (string) $before,
				'after'  => (string) $after,
			),
		)
	);
}

/**
 * Posts created during this request.
 *
 * @param int $post_id Post ID to add, or 0 to only read.
 * @return true[] Post ID => true.
 */
function cljournal_new_posts( $post_id = 0 ) {
	static $ids = array();
	if ( $post_id ) {
		$ids[ (int) $post_id ] = true;
	}
	return $ids;
}

/**
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 * @param bool    $update  Whether this is an update.
 */
function cljournal_on_insert_post( $post_id, $post, $update ) {
	if ( $update ) {
		return;
	}
	cljournal_new_posts( $post_id );

	// Published straight away (e.g. via REST or code): report its blocks as added.
	if ( 'publish' === $post->post_status ) {
		cljournal_record_block_diff( $post_id, $post, array(), cljournal_count_proofblocks( $post->post_content ) );
	}
}
add_action( 'wp_insert_post', 'cljournal_on_insert_post', 10, 3 );

/*
 * ProofBlocks: blocks appearing on or disappearing from live content.
 */

/**
 * Counts ProofBlocks blocks by name, including nested ones.
 *
 * @param string $content Post content.
 * @return int[] Block name => count.
 */
function cljournal_count_proofblocks( $content ) {
	$counts = array();
	if ( false === strpos( (string) $content, '<!-- wp:proofblocks/' ) ) {
		return $counts;
	}

	$stack = parse_blocks( $content );
	while ( $stack ) {
		$block = array_pop( $stack );
		if ( ! empty( $block['blockName'] ) && 0 === strpos( $block['blockName'], 'proofblocks/' ) ) {
			$counts[ $block['blockName'] ] = isset( $counts[ $block['blockName'] ] ) ? $counts[ $block['blockName'] ] + 1 : 1;
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner ) {
				$stack[] = $inner;
			}
		}
	}
	return $counts;
}

/**
 * Only published content counts as "shown", so unpublishing a page that
 * holds a banner is reported as that banner disappearing.
 *
 * @param int     $post_id     Post ID.
 * @param WP_Post $post_after  Post after the update.
 * @param WP_Post $post_before Post before the update.
 */
function cljournal_on_post_updated( $post_id, $post_after, $post_before ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	$before = 'publish' === $post_before->post_status ? cljournal_count_proofblocks( $post_before->post_content ) : array();
	$after  = 'publish' === $post_after->post_status ? cljournal_count_proofblocks( $post_after->post_content ) : array();
	cljournal_record_block_diff( $post_id, $post_after, $before, $after );
}
add_action( 'post_updated', 'cljournal_on_post_updated', 10, 3 );

/**
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post, for the title.
 * @param int[]   $before  Block counts before.
 * @param int[]   $after   Block counts after.
 */
function cljournal_record_block_diff( $post_id, $post, array $before, array $after ) {
	if ( $before === $after ) {
		return;
	}

	foreach ( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) as $name ) {
		$was = isset( $before[ $name ] ) ? $before[ $name ] : 0;
		$now = isset( $after[ $name ] ) ? $after[ $name ] : 0;
		if ( $was === $now ) {
			continue;
		}
		cljournal_record(
			'proofblocks',
			$now > $was ? 'block_added' : 'block_removed',
			array(
				'object_type' => 'page',
				'object_id'   => $post_id,
				'data'        => array(
					'block'  => $name,
					'title'  => get_the_title( $post ),
					'before' => $was,
					'after'  => $now,
				),
			)
		);
	}
}

/*
 * Option-based plugins.
 */

/**
 * Watermark Guru: record which settings changed, not their values.
 *
 * @param mixed $old_value Previous settings.
 * @param mixed $value     New settings.
 */
function cljournal_on_wmguru_settings( $old_value, $value ) {
	$old_value = is_array( $old_value ) ? $old_value : array();
	$value     = is_array( $value ) ? $value : array();

	$changed = array();
	foreach ( array_unique( array_merge( array_keys( $old_value ), array_keys( $value ) ) ) as $key ) {
		$a = isset( $old_value[ $key ] ) ? $old_value[ $key ] : null;
		$b = isset( $value[ $key ] ) ? $value[ $key ] : null;
		if ( $a !== $b ) {
			$changed[] = (string) $key;
		}
	}

	if ( $changed ) {
		cljournal_record( 'watermark-guru', 'settings_changed', array( 'data' => array( 'fields' => $changed ) ) );
	}
}
add_action( 'update_option_wmguru_settings', 'cljournal_on_wmguru_settings', 10, 2 );

/**
 * Tillkeeper: every agent change that actually ran (directly, after a
 * preview, or once a person approved it) becomes an event. Reads, previews
 * and requests still waiting for approval changed nothing, so they are
 * skipped.
 *
 * @param mixed $old_value Previous log.
 * @param mixed $value     New log.
 */
function cljournal_on_tillkeeper_log( $old_value, $value ) {
	if ( ! is_array( $value ) ) {
		return;
	}

	$seen = array();
	foreach ( is_array( $old_value ) ? $old_value : array() as $entry ) {
		if ( isset( $entry['id'] ) ) {
			$seen[ (string) $entry['id'] ] = true;
		}
	}

	foreach ( $value as $entry ) {
		if ( ! is_array( $entry ) || ! isset( $entry['id'] ) || isset( $seen[ (string) $entry['id'] ] ) ) {
			continue;
		}
		if ( ! isset( $entry['kind'], $entry['outcome'] ) || 'write' !== $entry['kind'] || ! in_array( $entry['outcome'], array( 'ok', 'applied', 'approved' ), true ) ) {
			continue;
		}
		cljournal_record(
			'tillkeeper',
			'agent_write',
			array(
				'object_type' => isset( $entry['object_type'] ) && '' !== $entry['object_type'] ? (string) $entry['object_type'] : 'site',
				'object_id'   => isset( $entry['object_id'] ) ? (int) $entry['object_id'] : 0,
				'data'        => array(
					'ability'  => isset( $entry['ability'] ) ? (string) $entry['ability'] : '',
					'approved' => 'approved' === $entry['outcome'],
				),
			)
		);
	}
}
add_action( 'update_option_tlkp_activity_log', 'cljournal_on_tillkeeper_log', 10, 2 );

/**
 * The first write after install or after the log was cleared creates the
 * option instead of updating it.
 *
 * @param string $option Option name.
 * @param mixed  $value  New log.
 */
function cljournal_on_tillkeeper_log_added( $option, $value ) {
	cljournal_on_tillkeeper_log( array(), $value );
}
add_action( 'add_option_tlkp_activity_log', 'cljournal_on_tillkeeper_log_added', 10, 2 );
