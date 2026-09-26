<?php
/**
 * Daily metrics: one row per day for the whole store (product_id 0) and one
 * per product. Aggregates only - no order IDs, no customer data.
 *
 * @package StoreWhy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'STWY_DB_VERSION', 1 );

/**
 * @return string Table name.
 */
function stwy_table() {
	global $wpdb;
	return $wpdb->prefix . 'stwy_daily';
}

/**
 * Creates or upgrades the table when behind.
 */
function stwy_maybe_install() {
	if ( (int) get_option( 'stwy_db_version', 0 ) >= STWY_DB_VERSION ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = stwy_table();
	$charset = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$table} (
			day date NOT NULL,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			orders int(10) unsigned NOT NULL DEFAULT 0,
			units int(10) unsigned NOT NULL DEFAULT 0,
			revenue decimal(19,4) NOT NULL DEFAULT 0,
			add_to_cart int(10) unsigned NOT NULL DEFAULT 0,
			views int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (day, product_id),
			KEY product_id (product_id)
		) {$charset};"
	);

	update_option( 'stwy_db_version', STWY_DB_VERSION );
}
add_action( 'init', 'stwy_maybe_install', 5 );

/**
 * Adds 1 to a counter for a product and for the store total, today (site time).
 *
 * @param int    $product_id Product ID.
 * @param string $column     'views' or 'add_to_cart'.
 */
function stwy_bump( $product_id, $column ) {
	if ( ! in_array( $column, array( 'views', 'add_to_cart' ), true ) || (int) get_option( 'stwy_db_version', 0 ) < 1 ) {
		return;
	}

	global $wpdb;
	$day = wp_date( 'Y-m-d' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic counter.
	$wpdb->query(
		$wpdb->prepare(
			'INSERT INTO %i (day, product_id, %i) VALUES (%s, %d, 1), (%s, 0, 1) ON DUPLICATE KEY UPDATE %i = %i + 1',
			stwy_table(),
			$column,
			$day,
			absint( $product_id ),
			$day,
			$column,
			$column
		)
	);
}

/**
 * Recomputes the order-based columns of one day from WooCommerce orders.
 * Views and add-to-cart are live counters and are left untouched.
 *
 * @param string $day 'Y-m-d' in site time.
 * @return bool False on a bad date or missing WooCommerce.
 */
function stwy_collect_day( $day ) {
	$tz    = wp_timezone();
	$start = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $day, $tz );
	if ( ! $start || ! function_exists( 'wc_get_orders' ) || (int) get_option( 'stwy_db_version', 0 ) < 1 ) {
		return false;
	}
	$end = $start->modify( '+1 day' );

	/**
	 * Filters which order statuses count as sales.
	 *
	 * @param string[] $statuses Default: paid statuses plus on-hold.
	 */
	$statuses = (array) apply_filters( 'stwy_counted_order_statuses', array_merge( wc_get_is_paid_statuses(), array( 'on-hold' ) ) );

	$store    = array(
		'orders'  => 0,
		'units'   => 0,
		'revenue' => 0.0,
	);
	$products = array();
	$page     = 1;

	do {
		$orders = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'status'       => $statuses,
				'date_created' => $start->getTimestamp() . '...' . ( $end->getTimestamp() - 1 ),
				'limit'        => 100,
				'paged'        => $page,
				'return'       => 'objects',
			)
		);

		foreach ( $orders as $order ) {
			++$store['orders'];
			$store['revenue'] += (float) $order->get_total() - (float) $order->get_total_refunded();

			$seen = array();
			foreach ( $order->get_items() as $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}
				$pid = (int) $item->get_product_id();
				$qty = max( 0, (int) $item->get_quantity() + (int) $order->get_qty_refunded_for_item( $item->get_id() ) );
				$store['units'] += $qty;
				if ( $pid <= 0 ) {
					// Deleted product: counts toward the store total only.
					continue;
				}
				if ( ! isset( $products[ $pid ] ) ) {
					$products[ $pid ] = array(
						'orders'  => 0,
						'units'   => 0,
						'revenue' => 0.0,
					);
				}
				if ( ! isset( $seen[ $pid ] ) ) {
					++$products[ $pid ]['orders'];
					$seen[ $pid ] = true;
				}
				$products[ $pid ]['units']   += $qty;
				$products[ $pid ]['revenue'] += (float) $item->get_total() - (float) $order->get_total_refunded_for_item( $item->get_id() );
			}
		}
		++$page;
	} while ( count( $orders ) === 100 );

	global $wpdb;
	$table = stwy_table();
	$date  = $start->format( 'Y-m-d' );

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- aggregate table; multi-row values go through the placeholders built below.
	$wpdb->query( $wpdb->prepare( 'UPDATE %i SET orders = 0, units = 0, revenue = 0 WHERE day = %s', $table, $date ) );

	$rows = array( 0 => $store ) + $products;
	foreach ( array_chunk( $rows, 200, true ) as $chunk ) {
		$placeholders = array();
		$values       = array();
		foreach ( $chunk as $pid => $m ) {
			$placeholders[] = '(%s, %d, %d, %d, %f)';
			array_push( $values, $date, $pid, $m['orders'], $m['units'], round( $m['revenue'], 4 ) );
		}
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (day, product_id, orders, units, revenue) VALUES ' . implode( ', ', $placeholders ) . ' ON DUPLICATE KEY UPDATE orders = VALUES(orders), units = VALUES(units), revenue = VALUES(revenue)',
				array_merge( array( $table ), $values )
			)
		);
	}
	// phpcs:enable

	return true;
}

/**
 * @param int $days Number of days back from today, inclusive of today.
 * @return array[] Store rows keyed by 'Y-m-d', newest first, zero-filled.
 */
function stwy_store_days( $days ) {
	global $wpdb;
	$from = wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live counters.
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE product_id = 0 AND day >= %s', stwy_table(), $from ), ARRAY_A );

	$by_day = array();
	foreach ( (array) $rows as $row ) {
		$by_day[ $row['day'] ] = $row;
	}

	$out = array();
	for ( $i = 0; $i < $days; $i++ ) {
		$day         = wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
		$out[ $day ] = isset( $by_day[ $day ] ) ? $by_day[ $day ] : array(
			'day'         => $day,
			'orders'      => 0,
			'units'       => 0,
			'revenue'     => 0,
			'add_to_cart' => 0,
			'views'       => 0,
		);
	}
	return $out;
}

/**
 * @param int $days  Period length in days.
 * @param int $limit Max products.
 * @return array[] Per-product totals, best sellers first.
 */
function stwy_top_products( $days, $limit = 10 ) {
	global $wpdb;
	$from = wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live counters.
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			'SELECT product_id, SUM(views) AS views, SUM(add_to_cart) AS add_to_cart, SUM(units) AS units, SUM(revenue) AS revenue FROM %i WHERE product_id > 0 AND day >= %s GROUP BY product_id ORDER BY revenue DESC, views DESC LIMIT %d',
			stwy_table(),
			$from,
			max( 1, (int) $limit )
		),
		ARRAY_A
	);
}
