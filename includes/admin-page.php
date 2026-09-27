<?php
/**
 * Admin screen under WooCommerce: daily store metrics side by side with
 * what changed on the site that day.
 *
 * @package StoreWhy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the submenu.
 */
function stwy_register_admin_page() {
	add_submenu_page(
		'woocommerce',
		__( 'StoreWhy', 'storewhy' ),
		__( 'StoreWhy', 'storewhy' ),
		'manage_woocommerce',
		'storewhy',
		'stwy_render_admin_page'
	);
}
add_action( 'admin_menu', 'stwy_register_admin_page', 60 );

/**
 * @param string $hook Current admin page hook.
 */
function stwy_admin_assets( $hook ) {
	if ( 'woocommerce_page_storewhy' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'stwy-admin', STWY_PLUGIN_URL . 'assets/css/admin.css', array(), STWY_VERSION );
}
add_action( 'admin_enqueue_scripts', 'stwy_admin_assets' );

/**
 * Handles the "Rebuild" button.
 */
function stwy_handle_rebuild() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'storewhy' ) );
	}
	check_admin_referer( 'stwy_rebuild' );

	$queued = stwy_queue_rebuild( 30 );
	wp_safe_redirect( add_query_arg( array( 'page' => 'storewhy', 'stwy_queued' => $queued ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_stwy_rebuild', 'stwy_handle_rebuild' );

/**
 * Saves settings.
 */
function stwy_handle_settings() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'storewhy' ) );
	}
	check_admin_referer( 'stwy_settings' );
	stwy_update_settings( array( 'track_views' => ! empty( $_POST['track_views'] ) ) );
	wp_safe_redirect( add_query_arg( array( 'page' => 'storewhy', 'stwy_saved' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_stwy_settings', 'stwy_handle_settings' );

/**
 * @param int $days Days back.
 * @return array[] Journal rows grouped by local 'Y-m-d'.
 */
function stwy_events_by_day( $days ) {
	if ( ! function_exists( 'cljournal_query' ) ) {
		return array();
	}

	$from = wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS ) . ' 00:00:00';
	$rows = cljournal_query(
		array(
			'after' => get_gmt_from_date( $from ),
			'limit' => 500,
		)
	);

	$out = array();
	foreach ( $rows as $row ) {
		$out[ get_date_from_gmt( $row['occurred_at'], 'Y-m-d' ) ][] = $row;
	}
	return $out;
}

/**
 * Renders the page.
 */
function stwy_render_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$days   = 30;
	$store  = stwy_store_days( $days );
	$events = stwy_events_by_day( $days );
	$top    = stwy_top_products( $days, 10 );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice after a nonce-checked redirect.
	$queued = isset( $_GET['stwy_queued'] ) ? absint( $_GET['stwy_queued'] ) : null;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice after a nonce-checked redirect.
	$saved    = ! empty( $_GET['stwy_saved'] );
	$settings = stwy_get_settings();
	$has_data = (bool) array_filter(
		$store,
		static function ( $row ) {
			return (int) $row['orders'] || (int) $row['views'] || (int) $row['add_to_cart'];
		}
	);
	?>
	<div class="wrap stwy-wrap">
		<h1><?php esc_html_e( 'StoreWhy', 'storewhy' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Your store, day by day, next to what changed on the site. Views and add-to-cart are counted anonymously: no cookies, no personal data.', 'storewhy' ); ?></p>

		<?php if ( null !== $queued ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php
				/* translators: %d: number of days queued. */
				echo esc_html( sprintf( _n( '%d day queued for rebuilding. Numbers appear within a few minutes.', '%d days queued for rebuilding. Numbers appear within a few minutes.', $queued, 'storewhy' ), $queued ) );
				?>
			</p></div>
		<?php endif; ?>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'storewhy' ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! $has_data ) : ?>
			<div class="notice notice-info"><p><?php esc_html_e( 'StoreWhy is filling in the last 30 days from your orders in the background. Numbers appear here within a few minutes; product views start counting from today.', 'storewhy' ); ?></p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Last 30 days', 'storewhy' ); ?></h2>
		<?php if ( defined( 'STWYP_VERSION' ) ) : ?>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=storewhy-insights' ) ); ?>"><?php esc_html_e( 'Open Insights: what stood out, by product and category, over up to 12 months →', 'storewhy' ); ?></a></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'StoreWhy Pro spots unusual days for each product and category over up to 12 months and lists what changed around them.', 'storewhy' ); ?></p>
		<?php endif; ?>
		<?php stwy_render_charts( $store, $events ); ?>
		<table class="widefat striped stwy-days">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Day', 'storewhy' ); ?></th>
					<th class="num"><?php esc_html_e( 'Orders', 'storewhy' ); ?></th>
					<th class="num"><?php esc_html_e( 'Revenue', 'storewhy' ); ?></th>
					<th class="num"><?php esc_html_e( 'Product views', 'storewhy' ); ?></th>
					<th class="num"><?php esc_html_e( 'Add to cart', 'storewhy' ); ?></th>
					<th><?php esc_html_e( 'What changed that day', 'storewhy' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $store as $day => $row ) : ?>
					<?php $day_events = isset( $events[ $day ] ) ? $events[ $day ] : array(); ?>
					<tr class="<?php echo $day_events ? 'stwy-has-changes' : ''; ?>">
						<td><?php echo esc_html( wp_date( 'D, ' . get_option( 'date_format' ), strtotime( $day . ' 12:00:00' ) ) ); ?></td>
						<td class="num"><?php echo esc_html( number_format_i18n( (int) $row['orders'] ) ); ?></td>
						<td class="num"><?php echo wp_kses_post( wc_price( (float) $row['revenue'] ) ); ?></td>
						<td class="num"><?php echo esc_html( number_format_i18n( (int) $row['views'] ) ); ?></td>
						<td class="num"><?php echo esc_html( number_format_i18n( (int) $row['add_to_cart'] ) ); ?></td>
						<td>
							<?php if ( $day_events ) : ?>
								<details>
									<summary>
										<?php
										/* translators: %d: number of changes. */
										echo esc_html( sprintf( _n( '%d change', '%d changes', count( $day_events ), 'storewhy' ), count( $day_events ) ) );
										?>
									</summary>
									<ul>
										<?php foreach ( array_slice( $day_events, 0, 15 ) as $event ) : ?>
											<li><?php echo esc_html( get_date_from_gmt( $event['occurred_at'], get_option( 'time_format' ) ) . ' — ' . stwy_describe_event( $event ) ); ?></li>
										<?php endforeach; ?>
									</ul>
								</details>
							<?php else : ?>
								<span class="stwy-muted">—</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Top products, last 30 days', 'storewhy' ); ?></h2>
		<?php if ( empty( $top ) ) : ?>
			<p><?php esc_html_e( 'No product data yet.', 'storewhy' ); ?></p>
		<?php else : ?>
			<table class="widefat striped stwy-top">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'storewhy' ); ?></th>
						<th class="num"><?php esc_html_e( 'Views', 'storewhy' ); ?></th>
						<th class="num"><?php esc_html_e( 'Add to cart', 'storewhy' ); ?></th>
						<th class="num"><?php esc_html_e( 'Units sold', 'storewhy' ); ?></th>
						<th class="num"><?php esc_html_e( 'Revenue', 'storewhy' ); ?></th>
						<th class="num"><?php esc_html_e( 'Views → purchase', 'storewhy' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $top as $p ) : ?>
						<?php
						$views = (int) $p['views'];
						$units = (int) $p['units'];
						?>
						<tr>
							<td><a href="<?php echo esc_url( (string) get_edit_post_link( (int) $p['product_id'], 'raw' ) ); ?>"><?php echo esc_html( get_the_title( (int) $p['product_id'] ) ); ?></a></td>
							<td class="num"><?php echo esc_html( number_format_i18n( $views ) ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( (int) $p['add_to_cart'] ) ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( $units ) ); ?></td>
							<td class="num"><?php echo wp_kses_post( wc_price( (float) $p['revenue'] ) ); ?></td>
							<td class="num"><?php echo $views > 0 ? esc_html( number_format_i18n( 100 * $units / $views, 1 ) . '%' ) : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Settings', 'storewhy' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="stwy_settings" />
			<?php wp_nonce_field( 'stwy_settings' ); ?>
			<p><label><input type="checkbox" name="track_views" value="1" <?php checked( $settings['track_views'] ); ?> /> <?php esc_html_e( 'Count product page views (anonymous: no cookies, nothing stored about visitors)', 'storewhy' ); ?></label></p>
			<?php submit_button( __( 'Save settings', 'storewhy' ), 'primary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="stwy-actions">
			<input type="hidden" name="action" value="stwy_rebuild" />
			<?php wp_nonce_field( 'stwy_rebuild' ); ?>
			<p class="description"><?php esc_html_e( 'Numbers update automatically when orders change. If something looks off, recount the last 30 days from your orders:', 'storewhy' ); ?></p>
			<?php submit_button( __( 'Recount last 30 days', 'storewhy' ), 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}
