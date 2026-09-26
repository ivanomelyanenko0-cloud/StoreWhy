<?php
/**
 * Anonymous interest counters: product views and add-to-cart.
 *
 * Views are counted by a tiny script on the product page, not in PHP, so
 * full-page caches (which skip PHP entirely) do not hide them. The request
 * carries only the product ID: no cookie, no IP stored, nothing per visitor.
 *
 * @package StoreWhy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param string $cart_item_key Cart item key.
 * @param int    $product_id    Product ID.
 */
function stwy_on_add_to_cart( $cart_item_key, $product_id ) {
	stwy_bump( (int) $product_id, 'add_to_cart' );
}
add_action( 'woocommerce_add_to_cart', 'stwy_on_add_to_cart', 10, 2 );

/**
 * Enqueues the view beacon on single product pages. Store staff are not
 * counted, so testing the shop does not inflate the numbers.
 */
function stwy_enqueue_view_beacon() {
	if ( ! function_exists( 'is_product' ) || ! is_product() || current_user_can( 'edit_products' ) ) {
		return;
	}

	wp_enqueue_script( 'stwy-view', STWY_PLUGIN_URL . 'assets/js/view-beacon.js', array(), STWY_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_localize_script(
		'stwy-view',
		'stwyView',
		array(
			'url'       => esc_url_raw( rest_url( 'storewhy/v1/view' ) ),
			'productId' => (int) get_queried_object_id(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'stwy_enqueue_view_beacon' );

/**
 * Public endpoint: pages may be cached, so a nonce would be stale. The
 * endpoint only ever adds 1 to a published product's counter.
 */
function stwy_register_rest_routes() {
	register_rest_route(
		'storewhy/v1',
		'/view',
		array(
			'methods'             => 'POST',
			'callback'            => 'stwy_rest_view',
			'permission_callback' => '__return_true',
			'args'                => array(
				'product_id' => array(
					'type'              => 'integer',
					'required'          => true,
					'minimum'           => 1,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'stwy_register_rest_routes' );

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function stwy_rest_view( WP_REST_Request $request ) {
	$product_id = (int) $request->get_param( 'product_id' );
	$agent      = (string) $request->get_header( 'user_agent' );

	if ( ! stwy_is_bot( $agent ) && 'product' === get_post_type( $product_id ) && 'publish' === get_post_status( $product_id ) ) {
		stwy_bump( $product_id, 'views' );
	}
	return new WP_REST_Response( null, 204 );
}

/**
 * @param string $agent User-Agent header.
 * @return bool
 */
function stwy_is_bot( $agent ) {
	if ( '' === $agent ) {
		return true;
	}
	return (bool) preg_match( '/bot|crawl|spider|slurp|preview|headless|lighthouse|facebookexternalhit|curl|wget|python-requests/i', $agent );
}
