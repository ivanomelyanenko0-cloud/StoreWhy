<?php
/**
 * Human-readable text for journal events.
 *
 * Lives in the plugin rather than in the shared journal library because
 * translations need this plugin's own text domain.
 *
 * @package StoreWhy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param array $row Journal row from cljournal_query().
 * @return string Plain text, not escaped.
 */
function stwy_describe_event( array $row ) {
	$data  = isset( $row['data'] ) && is_array( $row['data'] ) ? $row['data'] : array();
	$get   = static function ( $key ) use ( $data ) {
		return isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ? (string) $data[ $key ] : '';
	};
	$title = $get( 'title' );
	if ( '' === $title && ! empty( $row['object_id'] ) ) {
		$title = get_the_title( (int) $row['object_id'] );
	}
	if ( '' === $title && ! empty( $row['object_id'] ) ) {
		$title = '#' . (int) $row['object_id'];
	}

	switch ( $row['source'] . '/' . $row['event'] ) {
		case 'wordpress/plugin_activated':
			/* translators: %s: plugin file. */
			return sprintf( __( 'Plugin activated: %s', 'storewhy' ), $get( 'plugin' ) );
		case 'wordpress/plugin_deactivated':
			/* translators: %s: plugin file. */
			return sprintf( __( 'Plugin deactivated: %s', 'storewhy' ), $get( 'plugin' ) );
		case 'wordpress/plugin_updated':
			/* translators: %s: plugin file. */
			return sprintf( __( 'Plugin updated: %s', 'storewhy' ), $get( 'item' ) );
		case 'wordpress/theme_updated':
			/* translators: %s: theme slug. */
			return sprintf( __( 'Theme updated: %s', 'storewhy' ), $get( 'item' ) );
		case 'wordpress/theme_switched':
			/* translators: 1: old theme, 2: new theme. */
			return sprintf( __( 'Theme switched from "%1$s" to "%2$s"', 'storewhy' ), $get( 'before' ), $get( 'after' ) );
		case 'wordpress/core_updated':
			/* translators: %s: WordPress version. */
			return sprintf( __( 'WordPress updated to %s', 'storewhy' ), $get( 'after' ) );
		case 'wordpress/page_published':
			/* translators: %s: page title. */
			return sprintf( __( 'Page published: "%s"', 'storewhy' ), $title );
		case 'wordpress/page_unpublished':
			/* translators: %s: page title. */
			return sprintf( __( 'Page taken offline: "%s"', 'storewhy' ), $title );

		case 'woocommerce/product_published':
			/* translators: %s: product name. */
			return sprintf( __( 'Product published: "%s"', 'storewhy' ), $title );
		case 'woocommerce/product_unpublished':
			/* translators: %s: product name. */
			return sprintf( __( 'Product taken offline: "%s"', 'storewhy' ), $title );
		case 'woocommerce/price_changed':
			/* translators: 1: product name, 2: old price, 3: new price. */
			return sprintf( __( 'Price of "%1$s" changed: %2$s → %3$s', 'storewhy' ), $title, stwy_or_dash( $get( 'before' ) ), stwy_or_dash( $get( 'after' ) ) );
		case 'woocommerce/sale_price_changed':
			/* translators: 1: product name, 2: old sale price, 3: new sale price. */
			return sprintf( __( 'Sale price of "%1$s" changed: %2$s → %3$s', 'storewhy' ), $title, stwy_or_dash( $get( 'before' ) ), stwy_or_dash( $get( 'after' ) ) );
		case 'woocommerce/stock_status_changed':
			/* translators: 1: product name, 2: old status, 3: new status. */
			return sprintf( __( 'Stock status of "%1$s": %2$s → %3$s', 'storewhy' ), $title, stwy_or_dash( $get( 'before' ) ), stwy_or_dash( $get( 'after' ) ) );

		case 'heralda/bar_published':
			/* translators: %s: bar title. */
			return sprintf( __( 'Heralda bar turned on: "%s"', 'storewhy' ), $title );
		case 'heralda/bar_unpublished':
			/* translators: %s: bar title. */
			return sprintf( __( 'Heralda bar turned off: "%s"', 'storewhy' ), $title );
		case 'heralda/bar_schedule_changed':
			/* translators: %s: bar title. */
			return sprintf( __( 'Heralda bar schedule changed: "%s"', 'storewhy' ), $title );
		case 'heralda/bar_targeting_changed':
			/* translators: %s: bar title. */
			return sprintf( __( 'Heralda bar pages changed: "%s"', 'storewhy' ), $title );
		case 'heralda/bar_cta_changed':
			/* translators: %s: bar title. */
			return sprintf( __( 'Heralda bar button changed: "%s"', 'storewhy' ), $title );

		case 'proofblocks/block_added':
			/* translators: 1: block name, 2: page title. */
			return sprintf( __( 'ProofBlocks %1$s added on "%2$s"', 'storewhy' ), stwy_block_label( $get( 'block' ) ), $title );
		case 'proofblocks/block_removed':
			/* translators: 1: block name, 2: page title. */
			return sprintf( __( 'ProofBlocks %1$s removed from "%2$s"', 'storewhy' ), stwy_block_label( $get( 'block' ) ), $title );

		case 'watermark-guru/settings_changed':
			$fields = isset( $data['fields'] ) && is_array( $data['fields'] ) ? implode( ', ', array_map( 'strval', $data['fields'] ) ) : '';
			/* translators: %s: list of setting names. */
			return sprintf( __( 'Watermark settings changed (%s)', 'storewhy' ), $fields );

		case 'tillkeeper/agent_write':
			/* translators: 1: ability name, 2: object. */
			return sprintf( __( 'AI agent change via %1$s on %2$s', 'storewhy' ), $get( 'ability' ), $title );
	}

	/* translators: 1: source, 2: event name. */
	return sprintf( __( '%1$s: %2$s', 'storewhy' ), $row['source'], str_replace( '_', ' ', $row['event'] ) );
}

/**
 * @param string $value Value.
 * @return string Value or an em dash for empty.
 */
function stwy_or_dash( $value ) {
	return '' === $value ? '—' : $value;
}

/**
 * @param string $block Block name like 'proofblocks/banner'.
 * @return string Short label.
 */
function stwy_block_label( $block ) {
	$labels = array(
		'proofblocks/banner'        => __( 'Banner', 'storewhy' ),
		'proofblocks/counter'       => __( 'Counter', 'storewhy' ),
		'proofblocks/pricing-table' => __( 'Pricing Table', 'storewhy' ),
	);
	return isset( $labels[ $block ] ) ? $labels[ $block ] : $block;
}
