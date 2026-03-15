<?php
/**
 * Top Pages full view — Always Analytics.
 *
 * @package Always_Analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : wp_date( 'Y-m-d' );
$to   = isset( $_GET['to'] )   ? sanitize_text_field( wp_unslash( $_GET['to'] ) )   : wp_date( 'Y-m-d' );

if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) { $from = wp_date( 'Y-m-d' ); }
if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) )   { $to   = wp_date( 'Y-m-d' ); }

global $wpdb;
$table = $wpdb->prefix . 'aa_hits';

$tz_offset_seconds = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
$from_utc = gmdate( 'Y-m-d H:i:s', strtotime( $from . ' 00:00:00' ) - $tz_offset_seconds );
$to_utc   = gmdate( 'Y-m-d H:i:s', strtotime( $to   . ' 23:59:59' ) - $tz_offset_seconds );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$rows = $wpdb->get_results( $wpdb->prepare(
	"SELECT page_url, page_title, post_id,
	        COUNT(*) as views,
	        COUNT(DISTINCT visitor_hash) as unique_visitors,
	        COUNT(DISTINCT session_id) as sessions
	 FROM {$table}
	 WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
	 GROUP BY page_url, page_title, post_id
	 ORDER BY views DESC",
	$from_utc, $to_utc
) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$totals = $wpdb->get_row( $wpdb->prepare(
	"SELECT COUNT(*) as total_views, COUNT(DISTINCT visitor_hash) as total_visitors,
	        COUNT(DISTINCT session_id) as total_sessions
	 FROM {$table}
	 WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0",
	$from_utc, $to_utc
) );

$total_views    = (int) ( $totals->total_views    ?? 0 );
$total_visitors = (int) ( $totals->total_visitors ?? 0 );
$total_sessions = (int) ( $totals->total_sessions ?? 0 );
$max_views      = ! empty( $rows ) ? (int) $rows[0]->views : 1;

$label_from   = wp_date( 'd/m/Y', strtotime( $from ) );
$label_to     = wp_date( 'd/m/Y', strtotime( $to ) );
$period_label = ( $from === $to ) ? $label_from : $label_from . ' → ' . $label_to;
?>
<div class="wrap aa-wrap">

	<!-- Header -->
	<div class="aa-header aa-header--subpage">
		<div class="aa-header--subpage__nav">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=always-analytics&from=' . urlencode( $from ) . '&to=' . urlencode( $to ) ) ); ?>" class="aa-back-btn">
				← <?php esc_html_e( 'Tableau de bord', 'always-analytics' ); ?>
			</a>
			<div>
				<h1 class="aa-header--subpage__title">
					<?php esc_html_e( 'Top Pages', 'always-analytics' ); ?>
				</h1>
				<p class="aa-header--subpage__meta">
					<?php echo esc_html( $period_label ); ?> &middot;
					<strong><?php echo count( $rows ); ?></strong> <?php esc_html_e( 'pages', 'always-analytics' ); ?>
				</p>
			</div>
		</div>
		<div class="aa-detail-date-filter">
			<input type="date" id="tp-from" value="<?php echo esc_attr( $from ); ?>" />
			<span class="aa-date-arrow">→</span>
			<input type="date" id="tp-to" value="<?php echo esc_attr( $to ); ?>" />
			<button id="tp-apply" class="button button-primary"><?php esc_html_e( 'Appliquer', 'always-analytics' ); ?></button>
		</div>
	</div>

	<!-- KPIs -->
	<div class="aa-kpis--3col">
		<div class="aa-kpi-card">
			<div class="aa-kpi-value"><?php echo number_format_i18n( $total_views ); ?></div>
			<div class="aa-kpi-label"><?php esc_html_e( 'Pages vues totales', 'always-analytics' ); ?></div>
		</div>
		<div class="aa-kpi-card">
			<div class="aa-kpi-value"><?php echo number_format_i18n( $total_visitors ); ?></div>
			<div class="aa-kpi-label"><?php esc_html_e( 'Visiteurs uniques', 'always-analytics' ); ?></div>
		</div>
		<div class="aa-kpi-card">
			<div class="aa-kpi-value"><?php echo number_format_i18n( count( $rows ) ); ?></div>
			<div class="aa-kpi-label"><?php esc_html_e( 'Pages distinctes', 'always-analytics' ); ?></div>
		</div>
	</div>

	<!-- Table -->
	<div class="aa-card">
		<div class="aa-card-header">
			<h2><?php esc_html_e( 'Toutes les pages', 'always-analytics' ); ?></h2>
			<input type="search" id="tp-search"
				   placeholder="<?php esc_attr_e( 'Rechercher une page…', 'always-analytics' ); ?>"
				   class="aa-search-input aa-search-input--wide" />
		</div>
		<div class="aa-card-body aa-card-body--flush">
			<?php if ( empty( $rows ) ) : ?>
				<p class="aa-no-data"><?php esc_html_e( 'Aucune donnée pour cette période.', 'always-analytics' ); ?></p>
			<?php else : ?>
			<table class="aa-table aa-full-table" id="tp-table">
				<thead>
					<tr>
						<th>#</th>
						<th><?php esc_html_e( 'Page', 'always-analytics' ); ?></th>
						<th class="aa-col-num"><?php esc_html_e( 'Vues', 'always-analytics' ); ?></th>
						<th class="aa-col-num"><?php esc_html_e( 'Visiteurs uniques', 'always-analytics' ); ?></th>
						<th class="aa-col-num"><?php esc_html_e( 'Sessions', 'always-analytics' ); ?></th>
						<th><?php esc_html_e( 'Popularité', 'always-analytics' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $i => $row ) :
						$pct   = $max_views > 0 ? round( ( $row->views / $max_views ) * 100 ) : 0;
						$title = ! empty( $row->page_title ) ? $row->page_title : $row->page_url;
						$rank  = $i + 1;
					?>
					<tr class="tp-row">
						<td class="aa-table-rank"><?php echo esc_html( $rank ); ?></td>
						<td>
							<div class="aa-page-title"><?php echo esc_html( $title ); ?></div>
							<div class="aa-page-url">
								<a href="<?php echo esc_url( $row->page_url ); ?>" target="_blank">
									<?php echo esc_html( $row->page_url ); ?>
								</a>
							</div>
						</td>
						<td class="aa-table-num"><?php echo number_format_i18n( (int) $row->views ); ?></td>
						<td class="aa-table-num--secondary"><?php echo number_format_i18n( (int) $row->unique_visitors ); ?></td>
						<td class="aa-table-num--secondary"><?php echo number_format_i18n( (int) $row->sessions ); ?></td>
						<td>
							<div class="aa-popularity">
								<div class="aa-popularity__bar">
									<div class="aa-popularity__fill" style="width:<?php echo esc_attr( $pct ); ?>%;"></div>
								</div>
								<span class="aa-popularity__pct"><?php echo esc_html( $pct ); ?>%</span>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
	</div>

	<div class="aa-footer">
		<p>Always Analytics v<?php echo esc_html( AA_VERSION ); ?></p>
	</div>
</div>
