<?php
/**
 * Server-rendered SVG charts: revenue and product views per day, as two
 * charts over the same days (two measures, two scales - never one chart with
 * two axes). Days when something changed on the site get a dashed guide
 * across both, with the number of changes on a marker above.
 *
 * Every day is a full-height hover target with a native tooltip; the table
 * below the charts is the text version of the same data.
 *
 * @package StoreWhy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'STWY_CHART_W', 900 );
define( 'STWY_CHART_LEFT', 64 );
define( 'STWY_CHART_RIGHT', 8 );

/**
 * @param float $value Largest value.
 * @return float A round axis maximum at or above it.
 */
function stwy_nice_max( $value ) {
	if ( $value <= 0 ) {
		return 1;
	}
	$pow  = pow( 10, floor( log10( $value ) ) );
	$frac = $value / $pow;
	foreach ( array( 1, 2, 2.5, 5, 10 ) as $step ) {
		if ( $frac <= $step ) {
			return $step * $pow;
		}
	}
	return 10 * $pow;
}

/**
 * Bar with a 4px rounded top and a square base.
 *
 * @param float $x      Left.
 * @param float $y      Top.
 * @param float $w      Width.
 * @param float $base   Baseline y.
 * @return string SVG path data.
 */
function stwy_bar_path( $x, $y, $w, $base ) {
	$r = min( 4, $w / 2, max( 0, $base - $y ) );
	return sprintf(
		'M%1$.1f %2$.1f V%3$.1f Q%1$.1f %4$.1f %5$.1f %4$.1f H%6$.1f Q%7$.1f %4$.1f %7$.1f %3$.1f V%2$.1f Z',
		$x,
		$base,
		$y + $r,
		$y,
		$x + $r,
		$x + $w - $r,
		$x + $w
	);
}

/**
 * @param array[] $store  Rows keyed by 'Y-m-d' (a day, or the first day of a week), newest first.
 * @param array[] $events Journal rows grouped by the same keys.
 * @param array   $args   {
 *     Optional.
 *
 *     @type string $unit   'day' (default) or 'week'.
 *     @type array  $titles Chart titles keyed 'revenue' and 'views'.
 * }
 */
function stwy_render_charts( array $store, array $events, array $args = array() ) {
	$unit = isset( $args['unit'] ) && 'week' === $args['unit'] ? 'week' : 'day';
	$titles = array_merge(
		'week' === $unit
			? array(
				'revenue' => __( 'Revenue per week', 'storewhy' ),
				'views'   => __( 'Product views per week', 'storewhy' ),
			)
			: array(
				'revenue' => __( 'Revenue per day', 'storewhy' ),
				'views'   => __( 'Product views per day', 'storewhy' ),
			),
		isset( $args['titles'] ) && is_array( $args['titles'] ) ? $args['titles'] : array()
	);

	$days = array_reverse( $store, true );
	$n    = count( $days );
	if ( ! $n ) {
		return;
	}

	$plot_w = STWY_CHART_W - STWY_CHART_LEFT - STWY_CHART_RIGHT;
	$band   = $plot_w / $n;

	$series = array(
		array(
			'key'    => 'revenue',
			'title'  => $titles['revenue'],
			'format' => static function ( $v ) {
				return html_entity_decode( wp_strip_all_tags( wc_price( $v ) ), ENT_QUOTES, 'UTF-8' );
			},
		),
		array(
			'key'    => 'views',
			'title'  => $titles['views'],
			'format' => static function ( $v ) {
				return number_format_i18n( (int) $v );
			},
		),
	);
	?>
	<div class="stwy-charts">
		<?php foreach ( $series as $index => $s ) : ?>
			<?php
			$top    = 0 === $index ? 30 : 10; // Room for the change markers above the first chart.
			$height = 0 === $index ? 170 : 150;
			$base   = $height - 10;
			$max    = stwy_nice_max( max( array_map( static function ( $row ) use ( $s ) { return (float) $row[ $s['key'] ]; }, $days ) ) );
			$scale  = ( $base - $top ) / $max;
			?>
			<figure class="stwy-chart">
				<figcaption><?php echo esc_html( $s['title'] ); ?></figcaption>
				<svg viewBox="0 0 <?php echo esc_attr( STWY_CHART_W . ' ' . $height ); ?>" role="img" aria-label="<?php echo esc_attr( $s['title'] ); ?>">
					<line class="stwy-grid" x1="<?php echo esc_attr( STWY_CHART_LEFT ); ?>" x2="<?php echo esc_attr( STWY_CHART_W - STWY_CHART_RIGHT ); ?>" y1="<?php echo esc_attr( $top ); ?>" y2="<?php echo esc_attr( $top ); ?>" />
					<line class="stwy-axis" x1="<?php echo esc_attr( STWY_CHART_LEFT ); ?>" x2="<?php echo esc_attr( STWY_CHART_W - STWY_CHART_RIGHT ); ?>" y1="<?php echo esc_attr( $base ); ?>" y2="<?php echo esc_attr( $base ); ?>" />
					<text class="stwy-tick" x="<?php echo esc_attr( STWY_CHART_LEFT - 8 ); ?>" y="<?php echo esc_attr( $top + 4 ); ?>" text-anchor="end"><?php echo esc_html( call_user_func( $s['format'], $max ) ); ?></text>
					<text class="stwy-tick" x="<?php echo esc_attr( STWY_CHART_LEFT - 8 ); ?>" y="<?php echo esc_attr( $base + 4 ); ?>" text-anchor="end">0</text>

					<?php $i = 0; ?>
					<?php foreach ( $days as $day => $row ) : ?>
						<?php
						$x0      = STWY_CHART_LEFT + $i * $band;
						$bar_w   = max( 1.5, min( 24, $band * 0.7 ) );
						$bx      = $x0 + ( $band - $bar_w ) / 2;
						$value   = (float) $row[ $s['key'] ];
						$y       = $base - $value * $scale;
						$changes = isset( $events[ $day ] ) ? $events[ $day ] : array();
						$label   = wp_date( get_option( 'date_format' ), strtotime( $day . ' 12:00:00' ) );
						if ( 'week' === $unit ) {
							/* translators: %s: date the week starts. */
							$label = sprintf( __( 'Week of %s', 'storewhy' ), $label );
						}
						$tip     = $label . ': ' . call_user_func( $s['format'], $value );
						if ( $changes ) {
							/* translators: %d: number of changes on the site that day. */
							$tip .= "\n" . sprintf( _n( '%d change on the site', '%d changes on the site', count( $changes ), 'storewhy' ), count( $changes ) );
						}
						++$i;
						?>
						<g class="stwy-day">
							<title><?php echo esc_html( $tip ); ?></title>
							<rect class="stwy-hit" x="<?php echo esc_attr( round( $x0, 1 ) ); ?>" y="0" width="<?php echo esc_attr( round( $band, 1 ) ); ?>" height="<?php echo esc_attr( $height ); ?>" />
							<?php if ( $changes ) : ?>
								<line class="stwy-change" x1="<?php echo esc_attr( round( $x0 + $band / 2, 1 ) ); ?>" x2="<?php echo esc_attr( round( $x0 + $band / 2, 1 ) ); ?>" y1="<?php echo esc_attr( 0 === $index ? 22 : 0 ); ?>" y2="<?php echo esc_attr( $base ); ?>" />
							<?php endif; ?>
							<?php if ( $value > 0 ) : ?>
								<path class="stwy-bar" d="<?php echo esc_attr( stwy_bar_path( $bx, $y, $bar_w, $base ) ); ?>" />
							<?php endif; ?>
							<?php if ( $changes && 0 === $index ) : ?>
								<circle class="stwy-marker" cx="<?php echo esc_attr( round( $x0 + $band / 2, 1 ) ); ?>" cy="12" r="10" />
								<text class="stwy-marker-count" x="<?php echo esc_attr( round( $x0 + $band / 2, 1 ) ); ?>" y="16" text-anchor="middle"><?php echo esc_html( (string) min( 99, count( $changes ) ) ); ?></text>
							<?php endif; ?>
						</g>
					<?php endforeach; ?>
				</svg>
			</figure>
		<?php endforeach; ?>

		<?php $step = max( 5, (int) ceil( $n / 7 ) ); ?>
		<div class="stwy-xaxis" style="padding-left: <?php echo esc_attr( round( 100 * STWY_CHART_LEFT / STWY_CHART_W, 2 ) ); ?>%; padding-right: <?php echo esc_attr( round( 100 * STWY_CHART_RIGHT / STWY_CHART_W, 2 ) ); ?>%;">
			<?php $i = 0; ?>
			<?php foreach ( array_keys( $days ) as $day ) : ?>
				<span><?php echo ( 0 === $i % $step || $i === $n - 1 ) ? esc_html( wp_date( 'j M', strtotime( $day . ' 12:00:00' ) ) ) : ''; ?></span>
				<?php ++$i; ?>
			<?php endforeach; ?>
		</div>
		<p class="stwy-legend"><span class="stwy-legend-change"></span> <?php echo esc_html( 'week' === $unit ? __( 'Something changed on the site that week (number of changes). Hover a bar for details.', 'storewhy' ) : __( 'Something changed on the site that day (number of changes). Hover a day for details.', 'storewhy' ) ); ?></p>
	</div>
	<?php
}
