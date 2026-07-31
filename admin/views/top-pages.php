<?php
/**
 * Top Pages full view — Always Analytics.
 *
 * @package Always_Analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You are not allowed to view this report.', 'always-analytics' ) );
}

// Read-only date-range filter for this report; no state is changed, so no nonce is required.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter; access is capability-gated and the value is sanitized and strictly validated below.
$always_analytics_from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : wp_date( 'Y-m-d' );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter; access is capability-gated and the value is sanitized and strictly validated below.
$always_analytics_to = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : wp_date( 'Y-m-d' );

$always_analytics_is_valid_date = static function ( $value ) {
	$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
	return false !== $date && $date->format( 'Y-m-d' ) === $value;
};

if ( ! $always_analytics_is_valid_date( $always_analytics_from ) ) {
	$always_analytics_from = wp_date( 'Y-m-d' );
}
if ( ! $always_analytics_is_valid_date( $always_analytics_to ) ) {
	$always_analytics_to = wp_date( 'Y-m-d' );
}

global $wpdb;

$always_analytics_tz_offset_seconds = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
$always_analytics_from_utc          = gmdate( 'Y-m-d H:i:s', strtotime( $always_analytics_from . ' 00:00:00' ) - $always_analytics_tz_offset_seconds );
$always_analytics_to_utc            = gmdate( 'Y-m-d H:i:s', strtotime( $always_analytics_to . ' 23:59:59' ) - $always_analytics_tz_offset_seconds );

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only report over plugin-owned analytics data; all values are prepared and the screen must show the selected live interval.
$always_analytics_rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT page_url, page_title, post_id,
	        COUNT(*) as views,
	        COUNT(DISTINCT visitor_hash) as unique_visitors,
	        COUNT(DISTINCT session_id) as sessions
	 FROM {$wpdb->prefix}always_analytics_hits
	 WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
	 GROUP BY page_url, page_title, post_id
	 ORDER BY views DESC",
		$always_analytics_from_utc,
		$always_analytics_to_utc
	)
);

$always_analytics_totals = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT COUNT(*) as total_views, COUNT(DISTINCT visitor_hash) as total_visitors,
	        COUNT(DISTINCT session_id) as total_sessions
	 FROM {$wpdb->prefix}always_analytics_hits
	 WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0",
		$always_analytics_from_utc,
		$always_analytics_to_utc
	)
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

$always_analytics_total_views    = (int) ( $always_analytics_totals->total_views ?? 0 );
$always_analytics_total_visitors = (int) ( $always_analytics_totals->total_visitors ?? 0 );
$always_analytics_max_views      = ! empty( $always_analytics_rows ) ? (int) $always_analytics_rows[0]->views : 1;

$always_analytics_label_from   = wp_date( 'd/m/Y', strtotime( $always_analytics_from ) );
$always_analytics_label_to     = wp_date( 'd/m/Y', strtotime( $always_analytics_to ) );
$always_analytics_period_label = ( $always_analytics_from === $always_analytics_to ) ? $always_analytics_label_from : $always_analytics_label_from . ' → ' . $always_analytics_label_to;
?>
<div class="wrap always-analytics-wrap">

	<!-- Header -->
	<div class="always-analytics-header always-analytics-header--subpage">
		<div class="always-analytics-header--subpage__nav">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=always-analytics&from=' . rawurlencode( $always_analytics_from ) . '&to=' . rawurlencode( $always_analytics_to ) ) ); ?>" class="always-analytics-back-btn">
				← <?php esc_html_e( 'Dashboard', 'always-analytics' ); ?>
			</a>
			<div>
				<h1 class="always-analytics-header--subpage__title">
					<?php esc_html_e( 'Top Pages', 'always-analytics' ); ?>
				</h1>
				<p class="always-analytics-header--subpage__meta">
					<?php echo esc_html( $always_analytics_period_label ); ?> &middot;
					<?php
					/* translators: %s: number of pages. */
					echo esc_html( sprintf( _n( '%s page', '%s pages', count( $always_analytics_rows ), 'always-analytics' ), number_format_i18n( count( $always_analytics_rows ) ) ) );
					?>
				</p>
			</div>
		</div>
		<div class="always-analytics-detail-date-filter">
			<input type="date" id="tp-from" value="<?php echo esc_attr( $always_analytics_from ); ?>" />
			<span class="always-analytics-date-arrow">→</span>
			<input type="date" id="tp-to" value="<?php echo esc_attr( $always_analytics_to ); ?>" />
			<button id="tp-apply" class="button button-primary"><?php esc_html_e( 'Apply', 'always-analytics' ); ?></button>
		</div>
	</div>

	<!-- KPIs -->
	<div class="always-analytics-kpis--3col">
		<div class="always-analytics-kpi-card">
			<div class="always-analytics-kpi-value"><?php echo esc_html( number_format_i18n( $always_analytics_total_views ) ); ?></div>
			<div class="always-analytics-kpi-label"><?php esc_html_e( 'Total page views', 'always-analytics' ); ?></div>
		</div>
		<div class="always-analytics-kpi-card">
			<div class="always-analytics-kpi-value"><?php echo esc_html( number_format_i18n( $always_analytics_total_visitors ) ); ?></div>
			<div class="always-analytics-kpi-label"><?php esc_html_e( 'Unique visitors', 'always-analytics' ); ?></div>
		</div>
		<div class="always-analytics-kpi-card">
			<div class="always-analytics-kpi-value"><?php echo esc_html( number_format_i18n( count( $always_analytics_rows ) ) ); ?></div>
			<div class="always-analytics-kpi-label"><?php esc_html_e( 'Distinct pages', 'always-analytics' ); ?></div>
		</div>
	</div>

	<!-- Table -->
	<div class="always-analytics-card">
		<div class="always-analytics-card-header">
			<h2><?php esc_html_e( 'All pages', 'always-analytics' ); ?></h2>
			<input type="search" id="tp-search"
					placeholder="<?php esc_attr_e( 'Search pages…', 'always-analytics' ); ?>"
					class="always-analytics-search-input always-analytics-search-input--wide" />
		</div>
		<div class="always-analytics-card-body always-analytics-card-body--flush">
			<?php if ( empty( $always_analytics_rows ) ) : ?>
				<p class="always-analytics-no-data"><?php esc_html_e( 'No data is available for this period.', 'always-analytics' ); ?></p>
			<?php else : ?>
			<table class="always-analytics-table always-analytics-full-table" id="tp-table">
				<thead>
					<tr>
						<th>#</th>
						<th><?php esc_html_e( 'Page', 'always-analytics' ); ?></th>
						<th class="always-analytics-col-num"><?php esc_html_e( 'Views', 'always-analytics' ); ?></th>
						<th class="always-analytics-col-num"><?php esc_html_e( 'Unique visitors', 'always-analytics' ); ?></th>
						<th class="always-analytics-col-num"><?php esc_html_e( 'Sessions', 'always-analytics' ); ?></th>
						<th><?php esc_html_e( 'Popularity', 'always-analytics' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $always_analytics_rows as $always_analytics_i => $always_analytics_row ) :
						$always_analytics_pct   = $always_analytics_max_views > 0 ? round( ( $always_analytics_row->views / $always_analytics_max_views ) * 100 ) : 0;
						$always_analytics_title = ! empty( $always_analytics_row->page_title ) ? $always_analytics_row->page_title : $always_analytics_row->page_url;
						$always_analytics_rank  = $always_analytics_i + 1;
						?>
					<tr class="tp-row">
						<td class="always-analytics-table-rank"><?php echo esc_html( $always_analytics_rank ); ?></td>
						<td>
							<div class="always-analytics-page-title"><?php echo esc_html( $always_analytics_title ); ?></div>
							<div class="always-analytics-page-url">
								<a href="<?php echo esc_url( $always_analytics_row->page_url ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $always_analytics_row->page_url ); ?>
								</a>
							</div>
						</td>
						<td class="always-analytics-table-num"><?php echo esc_html( number_format_i18n( (int) $always_analytics_row->views ) ); ?></td>
						<td class="always-analytics-table-num--secondary"><?php echo esc_html( number_format_i18n( (int) $always_analytics_row->unique_visitors ) ); ?></td>
						<td class="always-analytics-table-num--secondary"><?php echo esc_html( number_format_i18n( (int) $always_analytics_row->sessions ) ); ?></td>
						<td>
							<div class="always-analytics-popularity">
								<div class="always-analytics-popularity__bar">
									<div class="always-analytics-popularity__fill" style="width:<?php echo esc_attr( $always_analytics_pct ); ?>%;"></div>
								</div>
								<span class="always-analytics-popularity__pct"><?php echo esc_html( $always_analytics_pct ); ?>%</span>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
	</div>
</div>
