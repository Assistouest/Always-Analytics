<?php
/**
 * WordPress native dashboard widget.
 *
 * @package Always_Analytics
 */

namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress native dashboard widget.
 */
class Always_Analytics_Dashboard {

	/**
	 * Register dashboard widgets.
	 */
	public function register_widgets() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'always_analytics_overview',
			__( 'Always Analytics', 'always-analytics' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Render the overview widget on the WP dashboard.
	 */
	public function render_widget() {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- This private dashboard reads plugin-owned analytics tables; values are prepared and fresh figures are required rather than SQL-result caching.

		$tz_offset_seconds = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		$tz_str            = sprintf( '%+03d:00', (int) round( $tz_offset_seconds / HOUR_IN_SECONDS ) );

		$today          = wp_date( 'Y-m-d' );
		$today_utc_from = gmdate( 'Y-m-d H:i:s', strtotime( $today . ' 00:00:00' ) - $tz_offset_seconds );
		$today_utc_to   = gmdate( 'Y-m-d H:i:s', strtotime( $today . ' 23:59:59' ) - $tz_offset_seconds );

		$today_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
                COUNT(DISTINCT visitor_hash) as visitors,
                COUNT(*) as page_views
             FROM {$wpdb->prefix}always_analytics_hits
             WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0",
				$today_utc_from,
				$today_utc_to
			)
		);

		$period_days     = 14;
		$period_start    = wp_date( 'Y-m-d', strtotime( '-' . ( $period_days - 1 ) . ' days' ) );
		$period_utc_from = gmdate( 'Y-m-d H:i:s', strtotime( $period_start . ' 00:00:00' ) - $tz_offset_seconds );
		$period_utc_to   = gmdate( 'Y-m-d H:i:s', strtotime( $today . ' 23:59:59' ) - $tz_offset_seconds );

		$period_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
                COUNT(DISTINCT visitor_hash) as visitors,
                COUNT(*) as page_views
             FROM {$wpdb->prefix}always_analytics_hits
             WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0",
				$period_utc_from,
				$period_utc_to
			)
		);

		$yesterday          = wp_date( 'Y-m-d', strtotime( '-1 day' ) );
		$yesterday_utc_from = gmdate( 'Y-m-d H:i:s', strtotime( $yesterday . ' 00:00:00' ) - $tz_offset_seconds );
		$yesterday_utc_to   = gmdate( 'Y-m-d H:i:s', strtotime( $yesterday . ' 23:59:59' ) - $tz_offset_seconds );

		$yesterday_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT visitor_hash) as visitors FROM {$wpdb->prefix}always_analytics_hits
             WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0",
				$yesterday_utc_from,
				$yesterday_utc_to
			)
		);

		$daily_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(CONVERT_TZ(hit_at, '+00:00', %s)) as date,
                    COUNT(DISTINCT visitor_hash) as visitors
             FROM {$wpdb->prefix}always_analytics_hits
             WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
             GROUP BY DATE(CONVERT_TZ(hit_at, '+00:00', %s))
             ORDER BY date ASC",
				$tz_str,
				$period_utc_from,
				$period_utc_to,
				$tz_str
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$sparkline_values = array();
		for ( $i = $period_days - 1; $i >= 0; $i-- ) {
			$d     = wp_date( 'Y-m-d', strtotime( "-{$i} days" ) );
			$found = false;
			foreach ( $daily_data as $row ) {
				if ( $row->date === $d ) {
					$sparkline_values[] = (int) $row->visitors;
					$found              = true;
					break;
				}
			}
			if ( ! $found ) {
				$sparkline_values[] = 0;
			}
		}

		$today_v     = (int) ( $today_stats->visitors ?? 0 );
		$yesterday_v = (int) ( $yesterday_stats->visitors ?? 0 );
		if ( $yesterday_v > 0 ) {
			$trend_pct = round( ( ( $today_v - $yesterday_v ) / $yesterday_v ) * 100 );
		} else {
			$trend_pct = $today_v > 0 ? 100 : 0;
		}
		$trend_up    = $trend_pct >= 0;
		$trend_label = ( $trend_up ? '+' : '' ) . $trend_pct . '%';
		$trend_color = $trend_up ? '#00a67e' : '#d63638';
		$trend_bg    = $trend_up ? '#f0faf7' : '#fcf0f1';
		$trend_icon  = $trend_up ? '▲' : '▼';

		$max    = max( 1, max( $sparkline_values ) );
		$n      = count( $sparkline_values );
		$points = array();
		foreach ( $sparkline_values as $i => $val ) {
			$x        = round( $i * ( 100 / ( $n - 1 ) ), 2 );
			$y        = round( 38 - ( $val / $max * 34 ), 2 );
			$points[] = "{$x},{$y}";
		}
		$polyline = implode( ' ', $points );
		$area     = '0,40 ' . $polyline . ' 100,40';

		$last_point            = end( $points );
		list( $dot_x, $dot_y ) = explode( ',', $last_point );

		?>
		<style>
		/* ── Always Analytics Dashboard Widget ───────────────────── */
		#always_analytics_overview .inside {
			padding: 0 !important;
			margin: 0 !important;
		}
		.always-analytics-widget {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
			color: #1d2327;
			overflow: hidden;
		}

		/* KPI row */
		.always-analytics-kpi-row {
			display: flex;
			border-bottom: 1px solid #f0f0f1;
		}
		.always-analytics-kpi {
			flex: 1;
			padding: 16px 14px 14px;
			text-align: center;
		}
		.always-analytics-kpi:first-child {
			border-right: 1px solid #f0f0f1;
		}
		.always-analytics-kpi-label {
			font-size: 10px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.07em;
			color: #8c8f94;
			margin-bottom: 7px;
		}
		.always-analytics-kpi-value {
			font-size: 30px;
			font-weight: 800;
			line-height: 1;
			color: #1d2327;
			letter-spacing: -1px;
		}
		.always-analytics-kpi-meta {
			margin-top: 6px;
			font-size: 11px;
			color: #8c8f94;
		}
		.always-analytics-badge {
			display: inline-flex;
			align-items: center;
			gap: 2px;
			font-size: 11px;
			font-weight: 700;
			padding: 2px 7px;
			border-radius: 20px;
		}

		/* Period bar */
		.always-analytics-period-bar {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 8px 14px;
			background: #f6f7f7;
			border-bottom: 1px solid #f0f0f1;
		}
		.always-analytics-period-name {
			font-size: 11px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			color: #646970;
		}
		.always-analytics-period-nums {
			font-size: 12px;
			color: #2271b1;
			font-weight: 500;
		}
		.always-analytics-period-nums strong {
			font-weight: 700;
		}
		.always-analytics-sep { color: #c3c4c7; margin: 0 5px; }

		/* Sparkline area */
		.always-analytics-chart-wrap {
			padding: 12px 14px 4px;
		}
		.always-analytics-chart-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 6px;
		}
		.always-analytics-chart-title {
			font-size: 10px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.07em;
			color: #8c8f94;
		}
		.always-analytics-sparkline {
			display: block;
			width: 100%;
			height: 54px;
		}

		/* Axis labels */
		.always-analytics-axis {
			display: flex;
			justify-content: space-between;
			padding: 2px 14px 0;
			margin-bottom: 2px;
		}
		.always-analytics-axis span {
			font-size: 9px;
			color: #c3c4c7;
			font-weight: 500;
		}

		/* CTA */
		.always-analytics-footer {
			padding: 10px 14px 14px;
		}
		.always-analytics-footer a.button {
			display: block;
			width: 100%;
			box-sizing: border-box;
			text-align: center;
			border-radius: 6px !important;
			font-size: 13px !important;
			font-weight: 600 !important;
			padding: 7px 14px !important;
			height: auto !important;
			line-height: 1.5 !important;
		}
		</style>

		<div class="always-analytics-widget">

			<!-- Today's key metrics. -->
			<div class="always-analytics-kpi-row">
				<div class="always-analytics-kpi">
					<div class="always-analytics-kpi-label"><?php esc_html_e( 'Visitors today', 'always-analytics' ); ?></div>
					<div class="always-analytics-kpi-value"><?php echo esc_html( number_format_i18n( $today_v ) ); ?></div>
					<div class="always-analytics-kpi-meta">
						<span class="always-analytics-badge" style="background:<?php echo esc_attr( $trend_bg ); ?>;color:<?php echo esc_attr( $trend_color ); ?>;">
							<?php echo esc_html( $trend_icon ); ?>&nbsp;<?php echo esc_html( $trend_label ); ?>
						</span>
						&nbsp;<?php esc_html_e( 'vs yesterday', 'always-analytics' ); ?>
					</div>
				</div>
				<div class="always-analytics-kpi">
					<div class="always-analytics-kpi-label"><?php esc_html_e( 'Page views today', 'always-analytics' ); ?></div>
					<div class="always-analytics-kpi-value"><?php echo esc_html( number_format_i18n( $today_stats->page_views ?? 0 ) ); ?></div>
					<?php if ( $today_v > 0 ) : ?>
					<div class="always-analytics-kpi-meta">
						<?php echo esc_html( number_format_i18n( round( ( $today_stats->page_views ?? 0 ) / $today_v, 1 ) ) ); ?>
						&nbsp;<?php esc_html_e( 'pages/visitor', 'always-analytics' ); ?>
					</div>
					<?php else : ?>
					<div class="always-analytics-kpi-meta">&nbsp;</div>
					<?php endif; ?>
				</div>
			</div>

			
			<div class="always-analytics-period-bar">
				<span class="always-analytics-period-name"><?php esc_html_e( 'Last 14 days', 'always-analytics' ); ?></span>
				<span class="always-analytics-period-nums">
					<strong><?php echo esc_html( number_format_i18n( $period_stats->visitors ?? 0 ) ); ?></strong>
					<?php esc_html_e( 'visitors', 'always-analytics' ); ?>
					<span class="always-analytics-sep">·</span>
					<strong><?php echo esc_html( number_format_i18n( $period_stats->page_views ?? 0 ) ); ?></strong>
					<?php esc_html_e( 'pages', 'always-analytics' ); ?>
				</span>
			</div>

			<!-- Sparkline -->
			<div class="always-analytics-chart-wrap">
				<div class="always-analytics-chart-header">
					<span class="always-analytics-chart-title"><?php esc_html_e( 'Daily visitors', 'always-analytics' ); ?></span>
				</div>
				<svg class="always-analytics-sparkline" viewBox="0 0 100 40" preserveAspectRatio="none">
					<defs>
						<linearGradient id="always-analytics-fill-14" x1="0" y1="0" x2="0" y2="1">
							<stop offset="0%" stop-color="#2271b1" stop-opacity="0.15"/>
							<stop offset="100%" stop-color="#2271b1" stop-opacity="0"/>
						</linearGradient>
					</defs>
					<polygon points="<?php echo esc_attr( $area ); ?>" fill="url(#always-analytics-fill-14)"/>
					<polyline points="<?php echo esc_attr( $polyline ); ?>"
								fill="none" stroke="#2271b1" stroke-width="1.8"
								stroke-linecap="round" stroke-linejoin="round"/>
					<circle cx="<?php echo esc_attr( $dot_x ); ?>"
							cy="<?php echo esc_attr( $dot_y ); ?>"
							r="2.5" fill="#2271b1"/>
				</svg>
			</div>

			<!-- Axis labels -->
			<div class="always-analytics-axis">
				<?php
				$label_indices = array( 0, 4, 8, 13 );
				foreach ( $label_indices as $idx ) :
					$days_back = $period_days - 1 - $idx;
					if ( 0 === $days_back ) {
						$label = esc_html__( 'Today', 'always-analytics' );
					} else {
						$label = wp_date( 'j/m', strtotime( "-{$days_back} days" ) );
					}
					?>
					<span><?php echo esc_html( $label ); ?></span>
				<?php endforeach; ?>
			</div>

			<!-- CTA -->
			<div class="always-analytics-footer">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=always-analytics' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'View full statistics →', 'always-analytics' ); ?>
				</a>
			</div>

		</div>
		<?php
	}
}
