<?php
/**
 * Engagement full view — Always Analytics.
 *
 * @package Always_Analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$always_analytics_back_url = admin_url( 'admin.php?page=always-analytics' );
?>
<div class="wrap always-analytics-wrap">

	<!-- Header -->
	<div class="always-analytics-header">
		<h1>
			<img src="<?php echo esc_url( ALWAYS_ANALYTICS_PLUGIN_URL . 'always-analytics.svg' ); ?>" alt="" class="always-analytics-logo-img--sm">
			<?php esc_html_e( 'Engagement', 'always-analytics' ); ?>
			<a href="<?php echo esc_url( $always_analytics_back_url ); ?>" class="always-analytics-back-btn always-analytics-back-btn--inline">
				← <?php esc_html_e( 'Back', 'always-analytics' ); ?>
			</a>
		</h1>
		<div class="always-analytics-header-actions">
			<div class="always-analytics-date-filter">
				<select id="eng-period">
					<option value="today"  selected><?php esc_html_e( 'Today', 'always-analytics' ); ?></option>
					<option value="yesterday"><?php esc_html_e( 'Yesterday', 'always-analytics' ); ?></option>
					<option value="7days"><?php esc_html_e( 'Last 7 days', 'always-analytics' ); ?></option>
					<option value="30days"><?php esc_html_e( 'Last 30 days', 'always-analytics' ); ?></option>
					<option value="90days"><?php esc_html_e( 'Last 90 days', 'always-analytics' ); ?></option>
					<option value="year"><?php esc_html_e( 'This year', 'always-analytics' ); ?></option>
				</select>
			</div>
		</div>
	</div>

	<!-- KPI Cards -->
	<div class="always-analytics-kpis" id="eng-kpis">
		<div class="always-analytics-kpi-card">
			<div class="always-analytics-kpi-value" id="eng-kpi-rate">—</div>
			<div class="always-analytics-kpi-label"><?php esc_html_e( 'Engagement rate', 'always-analytics' ); ?></div>
		</div>
		<div class="always-analytics-kpi-card">
			<div class="always-analytics-kpi-value" id="eng-kpi-duration">—</div>
			<div class="always-analytics-kpi-label"><?php esc_html_e( 'Average session duration', 'always-analytics' ); ?></div>
		</div>
		<div class="always-analytics-kpi-card">
			<div class="always-analytics-kpi-value" id="eng-kpi-pages">—</div>
			<div class="always-analytics-kpi-label"><?php esc_html_e( 'Pages per session', 'always-analytics' ); ?></div>
		</div>
		<div class="always-analytics-kpi-card">
			<div class="always-analytics-kpi-value" id="eng-kpi-scroll">—</div>
			<div class="always-analytics-kpi-label"><?php esc_html_e( 'Average scroll depth', 'always-analytics' ); ?></div>
		</div>
		<div class="always-analytics-kpi-card">
			<div class="always-analytics-kpi-value" id="eng-kpi-deepread">—</div>
			<div class="always-analytics-kpi-label"><?php esc_html_e( 'Deep readers ≥ 75%', 'always-analytics' ); ?></div>
		</div>
	</div>

	<!-- Time chart -->
	<div class="always-analytics-card always-analytics-chart-card always-analytics-engagement-chart-wrap">
		<div class="always-analytics-card-header">
			<h2><?php esc_html_e( 'Engagement over time', 'always-analytics' ); ?></h2>
			<div class="always-analytics-chart-toggles">
				<button class="always-analytics-toggle active" data-eng-dataset="engaged"><?php esc_html_e( 'Engaged sessions', 'always-analytics' ); ?></button>
				<button class="always-analytics-toggle" data-eng-dataset="avg_dur"><?php esc_html_e( 'Average duration', 'always-analytics' ); ?></button>
				<button class="always-analytics-toggle" data-eng-dataset="avg_scroll"><?php esc_html_e( 'Average scroll depth', 'always-analytics' ); ?></button>
			</div>
		</div>
		<div class="always-analytics-chart-container">
			<canvas id="eng-chart"></canvas>
		</div>
	</div>

	
	<div id="eng-reader-profiles" class="always-analytics-rp-section">
		<div class="always-analytics-skeleton" style="height:140px;border-radius:var(--always-analytics-radius);"></div>
	</div>

	
	<div class="always-analytics-grid always-analytics-grid--half">
		<div class="always-analytics-card">
			<div class="always-analytics-card-header">
				<h2><?php esc_html_e( 'Scroll depth', 'always-analytics' ); ?></h2>
			</div>
			<div class="always-analytics-card-body" id="eng-scroll-dist">
				<div class="always-analytics-skeleton always-analytics-skeleton--scroll-dist"></div>
			</div>
		</div>
	</div>
	<!-- Engagement score per page -->
	<div class="always-analytics-card">
		<div class="always-analytics-card-header">
			<h2><?php esc_html_e( 'Wilson Engagement Score', 'always-analytics' ); ?></h2>
			
			<button class="always-analytics-score-info-toggle" id="eng-score-toggle" aria-expanded="true"
					aria-controls="eng-score-explainer">
				<?php esc_html_e( 'Method', 'always-analytics' ); ?>
				<svg class="always-analytics-score-info-chevron is-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
			</button>
		</div>

		
		<div class="always-analytics-score-explainer" id="eng-score-explainer">
			<div class="always-analytics-score-explainer__inner">
				<div class="always-analytics-score-explainer__signals">
					<div class="always-analytics-score-explainer__signal">
						<div class="always-analytics-score-explainer__signal-header">
							<span class="always-analytics-score-explainer__signal-icon always-analytics-signal-icon--duration">
								<!-- Lucide: timer -->
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" x2="14" y1="2" y2="2"/><line x1="12" x2="15" y1="14" y2="11"/><circle cx="12" cy="14" r="8"/></svg>
							</span>
							<span class="always-analytics-score-explainer__signal-name"><?php esc_html_e( 'Duration', 'always-analytics' ); ?></span>
						</div>
						<span class="always-analytics-score-explainer__signal-weight">~24 %</span>
						<p><?php esc_html_e( 'Average time spent on the page. Longer active time generally indicates stronger engagement.', 'always-analytics' ); ?></p>
					</div>
					<div class="always-analytics-score-explainer__signal">
						<div class="always-analytics-score-explainer__signal-header">
							<span class="always-analytics-score-explainer__signal-icon always-analytics-signal-icon--scroll">
								<!-- Lucide: arrow-down-to-line -->
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17V3"/><path d="m6 11 6 6 6-6"/><path d="M19 21H5"/></svg>
							</span>
							<span class="always-analytics-score-explainer__signal-name"><?php esc_html_e( 'Scroll', 'always-analytics' ); ?></span>
						</div>
						<span class="always-analytics-score-explainer__signal-weight">~22 %</span>
						<p><?php esc_html_e( 'Average scroll depth. Reading to the end can indicate that the content remained relevant.', 'always-analytics' ); ?></p>
					</div>
					<div class="always-analytics-score-explainer__signal">
						<div class="always-analytics-score-explainer__signal-header">
							<span class="always-analytics-score-explainer__signal-icon always-analytics-signal-icon--engagement">
								<!-- Lucide: mouse-pointer-click -->
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 9 5 12 1.8-5.2L21 14Z"/><path d="M7.2 2.2 8 5.1"/><path d="m5.1 8-2.9-.8"/><path d="M14 4.1 12 6"/><path d="m6 12-1.9 2"/></svg>
							</span>
							<span class="always-analytics-score-explainer__signal-name"><?php esc_html_e( 'Engagement', 'always-analytics' ); ?></span>
						</div>
						<span class="always-analytics-score-explainer__signal-weight">~22 %</span>
						<p><?php esc_html_e( 'Share of active sessions with measured duration that were not bounces.', 'always-analytics' ); ?></p>
					</div>
					<div class="always-analytics-score-explainer__signal">
						<div class="always-analytics-score-explainer__signal-header">
							<span class="always-analytics-score-explainer__signal-icon always-analytics-signal-icon--return">
								<!-- Lucide: repeat -->
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m17 2 4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
							</span>
							<span class="always-analytics-score-explainer__signal-name"><?php esc_html_e( 'Back', 'always-analytics' ); ?></span>
						</div>
						<span class="always-analytics-score-explainer__signal-weight">~20 %</span>
						<p><?php esc_html_e( 'Share of visitors returning to this page, which can indicate perceived value.', 'always-analytics' ); ?></p>
					</div>
					<div class="always-analytics-score-explainer__signal">
						<div class="always-analytics-score-explainer__signal-header">
							<span class="always-analytics-score-explainer__signal-icon always-analytics-signal-icon--depth">
								<!-- Lucide: layers -->
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>
							</span>
							<span class="always-analytics-score-explainer__signal-name"><?php esc_html_e( 'Depth', 'always-analytics' ); ?></span>
						</div>
						<span class="always-analytics-score-explainer__signal-weight">~13 %</span>
						<p><?php esc_html_e( 'Average number of pages viewed per session after this page.', 'always-analytics' ); ?></p>
					</div>
				</div>

				<div class="always-analytics-score-explainer__wilson">
					<div class="always-analytics-score-explainer__wilson-head">
						<span class="always-analytics-score-explainer__signal-icon always-analytics-signal-icon--wilson">
							<!-- Lucide: shield-check -->
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
						</span>
						<strong><?php esc_html_e( 'Wilson adjustment', 'always-analytics' ); ?></strong>
						<span class="always-analytics-score-explainer__wilson-badge"><?php esc_html_e( 'Global modifier', 'always-analytics' ); ?></span>
					</div>
					<p><?php esc_html_e( 'The Wilson interval applies a statistical confidence factor. Smaller samples are pulled closer to the mean. This modifier is shown separately because its algorithm may evolve independently from the engagement signals.', 'always-analytics' ); ?></p>
				</div>
			</div>
		</div>

		<!-- Table -->
		<div class="always-analytics-card-body always-analytics-card-body--flush">
			<table class="always-analytics-full-table always-analytics-eng-table" id="eng-pages-table">
				<thead>
					<tr>
						<th class="always-analytics-eng-th-page"><?php esc_html_e( 'Page', 'always-analytics' ); ?></th>
						<th class="always-analytics-eng-th-duration" title="<?php esc_attr_e( 'Average time spent on the page', 'always-analytics' ); ?>"><?php esc_html_e( 'Average duration', 'always-analytics' ); ?></th>
						<th class="always-analytics-eng-th-scroll" title="<?php esc_attr_e( 'Average scroll depth on the page', 'always-analytics' ); ?>"><?php esc_html_e( 'Average scroll', 'always-analytics' ); ?></th>
						<th class="always-analytics-eng-th-profile" title="<?php esc_attr_e( 'Dominant reader profile on this page', 'always-analytics' ); ?>"><?php esc_html_e( 'Reader profile', 'always-analytics' ); ?></th>
						<th class="always-analytics-eng-th-wilson" title="<?php esc_attr_e( 'Wilson adjustment for statistical reliability. Larger samples increase the confidence of the overall score.', 'always-analytics' ); ?>">
							<?php esc_html_e( 'Reliability', 'always-analytics' ); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr><td colspan="5" class="always-analytics-no-data"><?php esc_html_e( 'Loading…', 'always-analytics' ); ?></td></tr>
				</tbody>
			</table>
		</div>
		<!-- Show more -->
		<div class="always-analytics-show-more-wrap" id="eng-show-more-wrap" style="display:none;">
			<button class="always-analytics-show-more-btn" id="eng-show-more-btn">
				<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
				<?php esc_html_e( 'Show 10 more', 'always-analytics' ); ?>
				<span class="always-analytics-show-more-remaining" id="eng-show-more-remaining"></span>
			</button>
		</div>
	</div>

</div><!-- .wrap -->
