<?php
/**
 * Dashboard view — Always Analytics.
 *
 * @package Always_Analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap always-analytics-wrap">
	<div class="always-analytics-header">
		<h1>
			<img src="<?php echo esc_url( ALWAYS_ANALYTICS_PLUGIN_URL . 'always-analytics.svg' ); ?>"
				alt="Always Analytics Logo" class="always-analytics-logo-img">
			<?php esc_html_e( 'Always Analytics', 'always-analytics' ); ?>
		</h1>
		<div class="always-analytics-header-actions">
			<div class="always-analytics-date-filter">
				<select id="always-analytics-period">
					<option value="today" selected><?php esc_html_e( 'Today', 'always-analytics' ); ?></option>
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
	<div class="always-analytics-kpis" id="always-analytics-kpis">
		<div class="always-analytics-kpi-card" data-metric="unique_visitors">
			<div class="always-analytics-kpi-value" id="kpi-visitors"></div>
			<div class="always-analytics-kpi-label"><?php esc_html_e( 'Unique visitors', 'always-analytics' ); ?></div>
			<div class="always-analytics-kpi-change" id="kpi-visitors-change"></div>
		</div>
		<div class="always-analytics-kpi-card" data-metric="page_views">
			<div class="always-analytics-kpi-value" id="kpi-pageviews"></div>
			<div class="always-analytics-kpi-label"><?php esc_html_e( 'Page views', 'always-analytics' ); ?></div>
			<div class="always-analytics-kpi-change" id="kpi-pageviews-change"></div>
		</div>
		<div class="always-analytics-kpi-card" data-metric="sessions">
			<div class="always-analytics-kpi-value" id="kpi-sessions">—</div>
			<div class="always-analytics-kpi-label"><?php esc_html_e( 'Sessions', 'always-analytics' ); ?></div>
			<div class="always-analytics-kpi-change" id="kpi-sessions-change"></div>
		</div>
		<div class="always-analytics-kpi-card" data-metric="avg_duration">
			<div class="always-analytics-kpi-value" id="kpi-duration">—</div>
			<div class="always-analytics-kpi-label"><?php esc_html_e( 'Average duration', 'always-analytics' ); ?></div>
			<div class="always-analytics-kpi-change" id="kpi-duration-change"></div>
		</div>
		<div class="always-analytics-kpi-card" data-metric="engagement_rate">
			<div class="always-analytics-kpi-value" id="kpi-bounce">—</div>
			<div class="always-analytics-kpi-label"><?php esc_html_e( 'Engagement', 'always-analytics' ); ?></div>
			<div class="always-analytics-kpi-change" id="kpi-bounce-change"></div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=always-analytics-engagement' ) ); ?>"
				class="always-analytics-kpi-more-link">
				<?php esc_html_e( 'More details →', 'always-analytics' ); ?>
			</a>
		</div>
	</div>


	<!-- Main Chart -->
	<div class="always-analytics-card always-analytics-chart-card">
		<div class="always-analytics-card-header">
			<h2><?php esc_html_e( 'Visits', 'always-analytics' ); ?></h2>
			<div class="always-analytics-chart-toggles">
				<button class="always-analytics-toggle active" data-dataset="visitors"><?php esc_html_e( 'Visitors', 'always-analytics' ); ?></button>
				<button class="always-analytics-toggle" data-dataset="page_views"><?php esc_html_e( 'Page views', 'always-analytics' ); ?></button>
				<button class="always-analytics-toggle" data-dataset="sessions"><?php esc_html_e( 'Sessions', 'always-analytics' ); ?></button>
			</div>
			<button id="always-analytics-add-campaign-btn" class="always-analytics-btn-campaign" title="<?php esc_attr_e( 'Add event', 'always-analytics' ); ?>">
				<svg width="13" height="13" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.5 1v11M1 6.5h11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
				<?php esc_html_e( 'Event', 'always-analytics' ); ?>
			</button>
		</div>
		<div class="always-analytics-chart-container">
			<canvas id="always-analytics-visits-chart"></canvas>
		</div>
	</div>

	<!-- Campaign modal (add) -->
	<div id="always-analytics-campaign-modal" class="always-analytics-modal" aria-modal="true" role="dialog">
		<div class="always-analytics-modal-overlay"></div>
		<div class="always-analytics-modal-box">
			<div class="always-analytics-modal-header">
				<h3><?php esc_html_e( 'Add event', 'always-analytics' ); ?></h3>
				<button class="always-analytics-modal-close" aria-label="<?php esc_attr_e( 'Close', 'always-analytics' ); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
			</div>
			<div class="always-analytics-modal-body">
				<div class="always-analytics-field">
					<label for="always-analytics-camp-date"><?php esc_html_e( 'Date', 'always-analytics' ); ?> <span class="always-analytics-req">*</span></label>
					<input type="date" id="always-analytics-camp-date" />
					<small><?php esc_html_e( 'Maximum one event per day.', 'always-analytics' ); ?></small>
				</div>
				<div class="always-analytics-field">
					<label for="always-analytics-camp-label"><?php esc_html_e( 'Label', 'always-analytics' ); ?> <span class="always-analytics-req">*</span></label>
					<input type="text" id="always-analytics-camp-label" placeholder="<?php esc_attr_e( 'Example: backlink campaign, header redesign…', 'always-analytics' ); ?>" maxlength="100" />
				</div>
				<div class="always-analytics-field">
					<label for="always-analytics-camp-desc"><?php esc_html_e( 'Description (optional)', 'always-analytics' ); ?></label>
					<textarea id="always-analytics-camp-desc" rows="2" placeholder="<?php esc_attr_e( 'Details, URL, notes…', 'always-analytics' ); ?>"></textarea>
				</div>
				<div class="always-analytics-field always-analytics-field--color">
					<label><?php esc_html_e( 'Color', 'always-analytics' ); ?></label>
					<div class="always-analytics-color-swatches">
						<span class="always-analytics-swatch always-analytics-swatch--active" data-color="#6c63ff" style="background:#6c63ff;" title="Violet"></span>
						<span class="always-analytics-swatch" data-color="#10b981" style="background:#10b981;" title="Vert"></span>
						<span class="always-analytics-swatch" data-color="#f59e0b" style="background:#f59e0b;" title="Orange"></span>
						<span class="always-analytics-swatch" data-color="#ef4444" style="background:#ef4444;" title="Rouge"></span>
						<span class="always-analytics-swatch" data-color="#3b82f6" style="background:#3b82f6;" title="Bleu"></span>
						<span class="always-analytics-swatch" data-color="#ec4899" style="background:#ec4899;" title="Rose"></span>
						<input type="color" id="always-analytics-camp-color" value="#6c63ff" class="always-analytics-color-custom" title="<?php esc_attr_e( 'Custom color', 'always-analytics' ); ?>" />
					</div>
				</div>
				<div id="always-analytics-camp-error" class="always-analytics-camp-error always-analytics-camp-error--hidden"></div>
			</div>
			<div class="always-analytics-modal-footer">
				<button id="always-analytics-camp-save" class="button button-primary"><?php esc_html_e( 'Save', 'always-analytics' ); ?></button>
				<button class="button always-analytics-modal-cancel"><?php esc_html_e( 'Cancel', 'always-analytics' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Grid: Recent visitors + Events -->
	<div class="always-analytics-grid always-analytics-grid--visitors-campaigns">

		<div class="always-analytics-card">
			<div class="always-analytics-card-header">
				<h2><?php esc_html_e( 'Recent visitors', 'always-analytics' ); ?></h2>
			</div>
			<div class="always-analytics-card-body">
				<table class="always-analytics-table always-analytics-table--compact" id="always-analytics-recent-visitors">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Visitor and visited URLs', 'always-analytics' ); ?></th>
							<th class="always-analytics-col-right"><?php esc_html_e( 'Activity', 'always-analytics' ); ?></th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
		</div>

		<div class="always-analytics-card" id="always-analytics-campaigns-card">
			<div class="always-analytics-card-header">
				<h2><?php esc_html_e( 'Events', 'always-analytics' ); ?></h2>
				<button id="always-analytics-add-campaign-btn2" class="always-analytics-btn-campaign always-analytics-btn-campaign--sm" title="<?php esc_attr_e( 'Add event', 'always-analytics' ); ?>">
					<svg width="11" height="11" viewBox="0 0 13 13" fill="none"><path d="M6.5 1v11M1 6.5h11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
					<?php esc_html_e( 'Add', 'always-analytics' ); ?>
				</button>
			</div>
			<div class="always-analytics-card-body">
				<div id="always-analytics-campaigns-list">
					<p class="always-analytics-no-data always-analytics-no-data--hidden"><?php esc_html_e( 'No events have been recorded.', 'always-analytics' ); ?></p>
				</div>
			</div>
		</div>

	</div>

	<!-- Campaign edit modal -->
	<div id="always-analytics-campaign-edit-modal" class="always-analytics-modal" aria-modal="true" role="dialog">
		<div class="always-analytics-modal-overlay"></div>
		<div class="always-analytics-modal-box">
			<div class="always-analytics-modal-header">
				<h3><?php esc_html_e( 'Edit event', 'always-analytics' ); ?></h3>
				<button class="always-analytics-modal-close" aria-label="<?php esc_attr_e( 'Close', 'always-analytics' ); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
			</div>
			<div class="always-analytics-modal-body">
				<input type="hidden" id="always-analytics-edit-camp-id" value="" />
				<div class="always-analytics-field">
					<label for="always-analytics-edit-camp-date"><?php esc_html_e( 'Date', 'always-analytics' ); ?> <span class="always-analytics-req">*</span></label>
					<input type="date" id="always-analytics-edit-camp-date" />
				</div>
				<div class="always-analytics-field">
					<label for="always-analytics-edit-camp-label"><?php esc_html_e( 'Label', 'always-analytics' ); ?> <span class="always-analytics-req">*</span></label>
					<input type="text" id="always-analytics-edit-camp-label" maxlength="100" />
				</div>
				<div class="always-analytics-field">
					<label for="always-analytics-edit-camp-desc"><?php esc_html_e( 'Description', 'always-analytics' ); ?></label>
					<textarea id="always-analytics-edit-camp-desc" rows="2"></textarea>
				</div>
				<div class="always-analytics-field always-analytics-field--color">
					<label><?php esc_html_e( 'Color', 'always-analytics' ); ?></label>
					<div class="always-analytics-color-swatches" id="always-analytics-edit-swatches">
						<span class="always-analytics-swatch" data-color="#6c63ff" style="background:#6c63ff;"></span>
						<span class="always-analytics-swatch" data-color="#10b981" style="background:#10b981;"></span>
						<span class="always-analytics-swatch" data-color="#f59e0b" style="background:#f59e0b;"></span>
						<span class="always-analytics-swatch" data-color="#ef4444" style="background:#ef4444;"></span>
						<span class="always-analytics-swatch" data-color="#3b82f6" style="background:#3b82f6;"></span>
						<span class="always-analytics-swatch" data-color="#ec4899" style="background:#ec4899;"></span>
						<input type="color" id="always-analytics-edit-camp-color" value="#6c63ff" class="always-analytics-color-custom" />
					</div>
				</div>
				<div id="always-analytics-edit-camp-error" class="always-analytics-camp-error always-analytics-camp-error--hidden"></div>
			</div>
			<div class="always-analytics-modal-footer">
				<button id="always-analytics-edit-camp-save" class="button button-primary"><?php esc_html_e( 'Save', 'always-analytics' ); ?></button>
				<button class="button always-analytics-modal-cancel"><?php esc_html_e( 'Cancel', 'always-analytics' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Tracking Sources -->
	<div class="always-analytics-card always-analytics-sources-card" id="always-analytics-sources-card">
		<div class="always-analytics-card-header">
			<h2><?php esc_html_e( 'Tracking sources', 'always-analytics' ); ?></h2>
			<span class="always-analytics-sources-badge" id="always-analytics-sources-total-badge"></span>
		</div>
		<div class="always-analytics-card-body always-analytics-sources-body">
			<div class="always-analytics-sources-bar-wrap">
				<div class="always-analytics-sources-bar" id="always-analytics-sources-bar" title="<?php esc_attr_e( 'Hit distribution by source', 'always-analytics' ); ?>"></div>
				<div class="always-analytics-sources-bar-legend" id="always-analytics-sources-bar-legend"></div>
			</div>
			<table class="always-analytics-table always-analytics-sources-table" id="always-analytics-sources-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Source', 'always-analytics' ); ?></th>
						<th class="always-analytics-num"><?php esc_html_e( 'Hits', 'always-analytics' ); ?></th>
						<th class="always-analytics-num"><?php esc_html_e( 'Unique visitors', 'always-analytics' ); ?></th>
						<th class="always-analytics-num"><?php esc_html_e( 'Sessions', 'always-analytics' ); ?></th>
						<th class="always-analytics-num"><?php esc_html_e( 'New visitors', 'always-analytics' ); ?></th>
						<th class="always-analytics-num"><?php esc_html_e( '% of total', 'always-analytics' ); ?></th>
						<th class="always-analytics-num"><?php esc_html_e( 'Merged (pre-consent)', 'always-analytics' ); ?></th>
						<th><?php esc_html_e( 'Trend', 'always-analytics' ); ?></th>
					</tr>
				</thead>
				<tbody id="always-analytics-sources-tbody"></tbody>
			</table>
			<div class="always-analytics-sources-info" id="always-analytics-sources-info">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
				<span id="always-analytics-sources-info-text"></span>
			</div>
		</div>
	</div>

	<!-- Top Pages -->

	<div class="always-analytics-card">
		<div class="always-analytics-card-header">
			<h2><?php esc_html_e( 'Most viewed content', 'always-analytics' ); ?></h2>
			<a id="always-analytics-all-pages-link" href="<?php echo esc_url( admin_url( 'admin.php?page=always-analytics-top-pages' ) ); ?>" class="always-analytics-see-all-btn">
				<?php esc_html_e( 'View all', 'always-analytics' ); ?>
			</a>
		</div>
		<div class="always-analytics-card-body">
			<table class="always-analytics-table" id="always-analytics-top-pages">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Page', 'always-analytics' ); ?></th>
						<th><?php esc_html_e( 'Views', 'always-analytics' ); ?></th>
						<th><?php esc_html_e( 'Visitors', 'always-analytics' ); ?></th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
	</div>

	<!-- Grid: Referrers + Devices -->
	<div class="always-analytics-grid">
		<div class="always-analytics-card always-analytics-referrers-card">
			<div class="always-analytics-card-header always-analytics-ref-header">
				<h2><?php esc_html_e( 'Referrers', 'always-analytics' ); ?></h2>
				<div class="always-analytics-ref-tabs" role="tablist">
					<span class="always-analytics-ref-tab always-analytics-ref-tab--active" data-cat="all"    role="tab" tabindex="0"><?php esc_html_e( 'All', 'always-analytics' ); ?></span>
					<span class="always-analytics-ref-tab" data-cat="search" role="tab" tabindex="0"><?php esc_html_e( 'Search engines', 'always-analytics' ); ?></span>
					<span class="always-analytics-ref-tab" data-cat="social" role="tab" tabindex="0"><?php esc_html_e( 'Social networks', 'always-analytics' ); ?></span>
					<span class="always-analytics-ref-tab" data-cat="ai"     role="tab" tabindex="0"><?php esc_html_e( 'AI', 'always-analytics' ); ?></span>
					<span class="always-analytics-ref-tab" data-cat="site"   role="tab" tabindex="0"><?php esc_html_e( 'Websites', 'always-analytics' ); ?></span>
				</div>
			</div>
			<div class="always-analytics-ref-body">
				<div id="always-analytics-referrers-list"></div>
			</div>
		</div>

		<div class="always-analytics-card always-analytics-devices-card">
			<div class="always-analytics-card-header always-analytics-dev-header">
				<h2><?php esc_html_e( 'Devices', 'always-analytics' ); ?></h2>
				<div class="always-analytics-dev-tabs" role="tablist">
					<span class="always-analytics-dev-tab always-analytics-dev-tab--active" data-device="all"     role="tab" tabindex="0"><?php esc_html_e( 'All', 'always-analytics' ); ?></span>
					<span class="always-analytics-dev-tab" data-device="desktop" role="tab" tabindex="0">
						<svg class="always-analytics-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 20h8M12 18v2"/></svg>
						<?php esc_html_e( 'Desktop', 'always-analytics' ); ?>
					</span>
					<span class="always-analytics-dev-tab" data-device="mobile"  role="tab" tabindex="0">
						<svg class="always-analytics-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><circle cx="12" cy="17" r="1"/></svg>
						<?php esc_html_e( 'Mobile', 'always-analytics' ); ?>
					</span>
					<span class="always-analytics-dev-tab" data-device="tablet"  role="tab" tabindex="0">
						<svg class="always-analytics-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="2" width="18" height="20" rx="2"/><circle cx="12" cy="17" r="1"/></svg>
						<?php esc_html_e( 'Tablet', 'always-analytics' ); ?>
					</span>
				</div>
			</div>
			<div class="always-analytics-dev-body">
				<div id="always-analytics-devices-list"></div>
			</div>
		</div>
	</div>

	
	<div class="always-analytics-grid always-analytics-grid--links">
		<div class="always-analytics-card" id="always-analytics-internal-links-card">
			<div class="always-analytics-card-header">
				<h2>
					<svg class="always-analytics-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;vertical-align:-3px;margin-right:6px;color:var(--always-analytics-primary);"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
					<?php esc_html_e( 'Internal links', 'always-analytics' ); ?>
				</h2>
			</div>
			<div class="always-analytics-card-body">
				<div class="always-analytics-links-kpis">
					<div class="always-analytics-links-kpi">
						<span class="always-analytics-links-kpi-value" id="always-analytics-int-total">—</span>
						<span class="always-analytics-links-kpi-label"><?php esc_html_e( 'Clicks', 'always-analytics' ); ?></span>
					</div>
					<div class="always-analytics-links-kpi">
						<span class="always-analytics-links-kpi-value" id="always-analytics-int-unique">—</span>
						<span class="always-analytics-links-kpi-label"><?php esc_html_e( 'Unique links', 'always-analytics' ); ?></span>
					</div>
					<div class="always-analytics-links-kpi">
						<span class="always-analytics-links-kpi-value" id="always-analytics-int-sessions">—</span>
						<span class="always-analytics-links-kpi-label"><?php esc_html_e( 'Sessions', 'always-analytics' ); ?></span>
					</div>
				</div>
				<table class="always-analytics-table always-analytics-table--compact" id="always-analytics-internal-links-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Clicked internal link', 'always-analytics' ); ?></th>
							<th class="always-analytics-col-right"><?php esc_html_e( 'Clicks', 'always-analytics' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr><td colspan="2" class="always-analytics-no-data"><?php esc_html_e( 'No data is available for this period.', 'always-analytics' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>

		<div class="always-analytics-card" id="always-analytics-outbound-links-card">
			<div class="always-analytics-card-header">
				<h2>
					<svg class="always-analytics-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;vertical-align:-3px;margin-right:6px;color:var(--always-analytics-warning);"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
					<?php esc_html_e( 'Outbound links', 'always-analytics' ); ?>
				</h2>
			</div>
			<div class="always-analytics-card-body">
				<div class="always-analytics-links-kpis">
					<div class="always-analytics-links-kpi">
						<span class="always-analytics-links-kpi-value" id="always-analytics-out-total">—</span>
						<span class="always-analytics-links-kpi-label"><?php esc_html_e( 'Clicks', 'always-analytics' ); ?></span>
					</div>
					<div class="always-analytics-links-kpi">
						<span class="always-analytics-links-kpi-value" id="always-analytics-out-domains"></span>
						<span class="always-analytics-links-kpi-label"><?php esc_html_e( 'Domains', 'always-analytics' ); ?></span>
					</div>
					<div class="always-analytics-links-kpi">
						<span class="always-analytics-links-kpi-value" id="always-analytics-out-sessions">—</span>
						<span class="always-analytics-links-kpi-label"><?php esc_html_e( 'Sessions', 'always-analytics' ); ?></span>
					</div>
				</div>
				<table class="always-analytics-table always-analytics-table--compact" id="always-analytics-outbound-links-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Outbound domain', 'always-analytics' ); ?></th>
							<th class="always-analytics-col-right"><?php esc_html_e( 'Clicks', 'always-analytics' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr><td colspan="2" class="always-analytics-no-data"><?php esc_html_e( 'No data is available for this period.', 'always-analytics' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
