<?php
/**
 * Scheduling: a nightly run that fills in yesterday's orders, plus one-off
 * per-day jobs for rebuilding history. Uses WooCommerce's Action Scheduler,
 * so heavy work never runs during a page view.
 *
 * @package StoreWhy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'STWY_AS_GROUP', 'storewhy' );

/**
 * Schedules the nightly run once. Checked on admin_init to keep the
 * Action Scheduler lookup off front-end requests.
 */
function stwy_schedule_nightly() {
	if ( ! function_exists( 'as_has_scheduled_action' ) || as_has_scheduled_action( 'stwy_nightly_collect', array(), STWY_AS_GROUP ) ) {
		return;
	}

	$next = new DateTimeImmutable( 'tomorrow 00:30', wp_timezone() );
	as_schedule_recurring_action( $next->getTimestamp(), DAY_IN_SECONDS, 'stwy_nightly_collect', array(), STWY_AS_GROUP );
}
add_action( 'admin_init', 'stwy_schedule_nightly' );

/**
 * Nightly: yesterday is final by now; today is refreshed as a partial day.
 */
function stwy_nightly_collect() {
	stwy_collect_day( wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ) );
	stwy_collect_day( wp_date( 'Y-m-d' ) );
}
add_action( 'stwy_nightly_collect', 'stwy_nightly_collect' );

/**
 * @param string $day 'Y-m-d'.
 */
function stwy_collect_day_job( $day ) {
	stwy_collect_day( $day );
}
add_action( 'stwy_collect_day', 'stwy_collect_day_job' );

/**
 * Queues one job per day for the last $days days, today included.
 *
 * @param int $days Days back.
 * @return int Jobs queued.
 */
function stwy_queue_rebuild( $days = 30 ) {
	if ( ! function_exists( 'as_enqueue_async_action' ) ) {
		return 0;
	}

	$queued = 0;
	for ( $i = 0; $i < $days; $i++ ) {
		$day = wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
		if ( ! as_has_scheduled_action( 'stwy_collect_day', array( $day ), STWY_AS_GROUP ) ) {
			as_enqueue_async_action( 'stwy_collect_day', array( $day ), STWY_AS_GROUP );
			++$queued;
		}
	}
	return $queued;
}

/**
 * Queues a recount of one day, unless one is already waiting.
 *
 * @param string $day 'Y-m-d'.
 */
function stwy_queue_day( $day ) {
	if ( function_exists( 'as_enqueue_async_action' ) && ! as_has_scheduled_action( 'stwy_collect_day', array( $day ), STWY_AS_GROUP ) ) {
		as_enqueue_async_action( 'stwy_collect_day', array( $day ), STWY_AS_GROUP );
	}
}

/**
 * Keeps the numbers current without waiting for the nightly run: any new
 * order, status change or refund re-counts the day the order was placed.
 *
 * @param int $order_id Order ID.
 */
function stwy_on_order_changed( $order_id ) {
	$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
	if ( ! $order || ! $order->get_date_created() ) {
		return;
	}
	stwy_queue_day( wp_date( 'Y-m-d', $order->get_date_created()->getTimestamp() ) );
}
add_action( 'woocommerce_new_order', 'stwy_on_order_changed' );
add_action( 'woocommerce_order_status_changed', 'stwy_on_order_changed' );
add_action( 'woocommerce_order_refunded', 'stwy_on_order_changed' );

/**
 * The first time an admin opens the dashboard after installing, the last
 * 30 days are filled in from existing orders.
 */
function stwy_first_run_backfill() {
	if ( get_option( 'stwy_backfilled' ) || ! function_exists( 'as_enqueue_async_action' ) || (int) get_option( 'stwy_db_version', 0 ) < 1 ) {
		return;
	}
	stwy_queue_rebuild( 30 );
	update_option( 'stwy_backfilled', time(), false );
}
add_action( 'admin_init', 'stwy_first_run_backfill' );
