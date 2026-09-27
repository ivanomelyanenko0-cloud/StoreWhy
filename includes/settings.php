<?php
/**
 * Settings.
 *
 * @package StoreWhy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'STWY_SETTINGS_OPTION', 'stwy_settings' );

/**
 * @return array{track_views: bool}
 */
function stwy_get_settings() {
	$defaults = array( 'track_views' => true );
	$stored   = get_option( STWY_SETTINGS_OPTION, array() );
	$stored   = is_array( $stored ) ? $stored : array();

	return array_merge( $defaults, array_intersect_key( $stored, $defaults ) );
}

/**
 * @param array $input Raw form data.
 */
function stwy_update_settings( array $input ) {
	update_option( STWY_SETTINGS_OPTION, array( 'track_views' => ! empty( $input['track_views'] ) ), false );
}
