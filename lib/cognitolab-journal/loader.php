<?php
/**
 * CognitoLab Journal loader.
 *
 * The journal ships byte-identical inside every CognitoLab plugin that uses
 * it. Each bundled copy registers its version here, and on plugins_loaded
 * only the newest copy is required, so two plugins never define the same
 * functions and the newest schema always wins. Keep this file tiny and
 * backward compatible: an old copy of it may run next to a new journal.
 *
 * @package CognitoLabJournal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $GLOBALS['cljournal_candidates'] ) || ! is_array( $GLOBALS['cljournal_candidates'] ) ) {
	$GLOBALS['cljournal_candidates'] = array();
}
$GLOBALS['cljournal_candidates']['0.1.0'] = __DIR__;

if ( ! function_exists( 'cljournal_boot' ) ) {
	/**
	 * Loads the newest registered journal copy. Runs once per request.
	 */
	function cljournal_boot() {
		if ( defined( 'CLJOURNAL_VERSION' ) || empty( $GLOBALS['cljournal_candidates'] ) ) {
			return;
		}

		$candidates = $GLOBALS['cljournal_candidates'];
		uksort( $candidates, 'version_compare' );
		$dir = end( $candidates );

		define( 'CLJOURNAL_VERSION', (string) key( $candidates ) );
		require_once $dir . '/journal.php';
		require_once $dir . '/observers.php';
	}
	add_action( 'plugins_loaded', 'cljournal_boot', 1 );
}

if ( ! function_exists( 'cljournal_register_consumer' ) ) {
	/**
	 * Called by each plugin that bundles the journal, so uninstall can tell
	 * whether another CognitoLab plugin still needs the shared table.
	 *
	 * @param string $slug Plugin slug.
	 */
	function cljournal_register_consumer( $slug ) {
		if ( ! isset( $GLOBALS['cljournal_consumers'] ) || ! is_array( $GLOBALS['cljournal_consumers'] ) ) {
			$GLOBALS['cljournal_consumers'] = array();
		}
		$GLOBALS['cljournal_consumers'][] = sanitize_key( $slug );
	}
}
