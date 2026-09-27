<?php
/**
 * Uninstall handler.
 *
 * Daily metrics are StoreWhy's own data and are always removed. The change
 * journal table is shared with other CognitoLab plugins, so it is dropped
 * only when no other plugin that bundles the journal is left.
 *
 * @package StoreWhy
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

function stwy_uninstall_site() {
	global $wpdb;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dropping this plugin's own tables on uninstall.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}stwy_daily" );
	delete_option( 'stwy_db_version' );
	delete_option( 'stwy_settings' );
	delete_option( 'stwy_backfilled' );

	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( '', array(), 'storewhy' );
	}

	$consumers = get_option( 'cljournal_consumers', array() );
	$consumers = array_values( array_diff( is_array( $consumers ) ? $consumers : array(), array( 'storewhy' ) ) );

	if ( $consumers ) {
		update_option( 'cljournal_consumers', $consumers, false );
		return;
	}

	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cljournal_events" );
	// phpcs:enable
	delete_option( 'cljournal_consumers' );
	delete_option( 'cljournal_db_version' );
	wp_clear_scheduled_hook( 'cljournal_prune' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $stwy_site_id ) {
		switch_to_blog( $stwy_site_id );
		stwy_uninstall_site();
		restore_current_blog();
	}
} else {
	stwy_uninstall_site();
}
