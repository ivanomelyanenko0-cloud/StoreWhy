<?php
/**
 * Plugin Name:          StoreWhy
 * Plugin URI:           https://cognitolab.net/products/storewhy
 * Description:          WooCommerce numbers that come with context: daily sales, views and add-to-cart next to what changed on your site that day.
 * Version:              1.0.0
 * Requires at least:    6.3
 * Requires PHP:         7.4
 * Author:               CognitoLab
 * Author URI:           https://cognitolab.net
 * License:              GPLv2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          storewhy
 * Domain Path:          /languages
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.0
 *
 * Internal identifiers use the STWY_/stwy_ prefix. The shared change journal
 * uses the company prefix (cljournal_).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'STWY_VERSION', '1.0.0' );
define( 'STWY_PLUGIN_FILE', __FILE__ );
define( 'STWY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STWY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once STWY_PLUGIN_DIR . 'lib/cognitolab-journal/loader.php';
cljournal_register_consumer( 'storewhy' );

require_once STWY_PLUGIN_DIR . 'includes/settings.php';
require_once STWY_PLUGIN_DIR . 'includes/metrics.php';
require_once STWY_PLUGIN_DIR . 'includes/collector.php';
require_once STWY_PLUGIN_DIR . 'includes/tracking.php';
require_once STWY_PLUGIN_DIR . 'includes/describe.php';
require_once STWY_PLUGIN_DIR . 'includes/chart.php';
require_once STWY_PLUGIN_DIR . 'includes/admin-page.php';

/**
 * Orders are read only through wc_get_orders(), so HPOS is supported.
 */
function stwy_declare_wc_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'stwy_declare_wc_compatibility' );

/**
 * @param string[] $links Plugin action links.
 * @return string[]
 */
function stwy_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=storewhy' ) ) . '">' . esc_html__( 'Dashboard', 'storewhy' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'stwy_action_links' );
