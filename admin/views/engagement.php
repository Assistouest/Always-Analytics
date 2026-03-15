<?php
/**
 * Engagement full view — Always Analytics.
 *
 * @package Always_Analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$back_url = admin_url( 'admin.php?page=always-analytics' );
?>
<div class="wrap aa-wrap">

	<!-- Header -->
	<div class="aa-header">
		<h1>
			<img src="<?php echo esc_url( AA_PLUGIN_URL . 'always-analytics.svg' ); ?>" alt="" class="aa-logo-img--sm">
			<?php esc_html_e( 'Engagement', 'always-analytics' ); ?>
			<a href="<?php echo esc_url( $back_url ); ?>" class="aa-back-btn aa-back-btn--inline">
				← <?php esc_html_e( 'Retour', 'always-analytics' ); ?>
			</a>
		</h1>
		<div class="aa-header-actions">
			<div class="aa-date-filter">
				<select id="eng-period">
					<option value="today"  selected><?php esc_html_e( "Aujourd'hui", 'always-analytics' ); ?></option>
					<option value="yesterday"><?php esc_html_e( 'Hier', 'always-analytics' ); ?></option>
					<option value="7days"><?php esc_html_e( '7 derniers jours', 'always-analytics' ); ?></option>
					<option value="30days"><?php esc_html_e( '30 derniers jours', 'always-analytics' ); ?></option>
					<option value="90days"><?php esc_html_e( '90 derniers jours', 'always-analytics' ); ?></option>
					<option value="year"><?php esc_html_e( 'Cette année', 'always-analytics' ); ?></option>
				</select>
			</div>
		</div>
	</div>

	<!-- KPI Cards -->
	<div class="aa-kpis" id="eng-kpis">
		<div class="aa-kpi-card">
			<div class="aa-kpi-value" id="eng-kpi-rate">—</div>
			<div class="aa-kpi-label"><?php esc_html_e( "Taux d'engagement", 'always-analytics' ); ?></div>
		</div>
		<div class="aa-kpi-card">
			<div class="aa-kpi-value" id="eng-kpi-duration">—</div>
			<div class="aa-kpi-label"><?php esc_html_e( 'Durée moy. session', 'always-analytics' ); ?></div>
		</div>
		<div class="aa-kpi-card">
			<div class="aa-kpi-value" id="eng-kpi-pages">—</div>
			<div class="aa-kpi-label"><?php esc_html_e( 'Pages / session', 'always-analytics' ); ?></div>
		</div>
		<div class="aa-kpi-card">
			<div class="aa-kpi-value" id="eng-kpi-scroll">—</div>
			<div class="aa-kpi-label"><?php esc_html_e( 'Scroll moyen', 'always-analytics' ); ?></div>
		</div>
		<div class="aa-kpi-card">
			<div class="aa-kpi-value" id="eng-kpi-deepread">—</div>
			<div class="aa-kpi-label"><?php esc_html_e( 'Lecteurs profonds ≥ 75%', 'always-analytics' ); ?></div>
		</div>
	</div>

	<!-- Time chart -->
	<div class="aa-card aa-chart-card aa-engagement-chart-wrap">
		<div class="aa-card-header">
			<h2><?php esc_html_e( 'Engagement dans le temps', 'always-analytics' ); ?></h2>
			<div class="aa-chart-toggles">
				<button class="aa-toggle active" data-eng-dataset="engaged"><?php esc_html_e( 'Sessions engagées', 'always-analytics' ); ?></button>
				<button class="aa-toggle" data-eng-dataset="avg_dur"><?php esc_html_e( 'Durée moy.', 'always-analytics' ); ?></button>
				<button class="aa-toggle" data-eng-dataset="avg_scroll"><?php esc_html_e( 'Scroll moyen', 'always-analytics' ); ?></button>
			</div>
		</div>
		<div class="aa-chart-container">
			<canvas id="eng-chart"></canvas>
		</div>
	</div>

	<!-- Profils de lecture — pleine largeur, 4 cards en ligne -->
	<div id="eng-reader-profiles" class="aa-rp-section">
		<div class="aa-skeleton" style="height:140px;border-radius:var(--aa-radius);"></div>
	</div>

	<!-- Profondeur de scroll — 50% largeur -->
	<div class="aa-grid aa-grid--half">
		<div class="aa-card">
			<div class="aa-card-header">
				<h2><?php esc_html_e( 'Profondeur de scroll', 'always-analytics' ); ?></h2>
			</div>
			<div class="aa-card-body" id="eng-scroll-dist">
				<div class="aa-skeleton aa-skeleton--scroll-dist"></div>
			</div>
		</div>
	</div>
	<!-- Engagement score per page -->
	<div class="aa-card">
		<div class="aa-card-header">
			<h2><?php esc_html_e( 'Wilson Engagement Score', 'always-analytics' ); ?></h2>
			<!-- Bouton visible uniquement sur mobile via CSS -->
			<button class="aa-score-info-toggle" id="eng-score-toggle" aria-expanded="true"
					aria-controls="eng-score-explainer">
				<?php esc_html_e( 'Méthode', 'always-analytics' ); ?>
				<svg class="aa-score-info-chevron is-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
			</button>
		</div>

		<!-- Explainer — toujours visible sur desktop, repliable sur mobile -->
		<div class="aa-score-explainer" id="eng-score-explainer">
			<div class="aa-score-explainer__inner">
				<div class="aa-score-explainer__signals">
					<div class="aa-score-explainer__signal">
						<div class="aa-score-explainer__signal-header">
							<span class="aa-score-explainer__signal-icon aa-signal-icon--duration">
								<!-- Lucide: timer -->
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" x2="14" y1="2" y2="2"/><line x1="12" x2="15" y1="14" y2="11"/><circle cx="12" cy="14" r="8"/></svg>
							</span>
							<span class="aa-score-explainer__signal-name"><?php esc_html_e( 'Durée', 'always-analytics' ); ?></span>
						</div>
						<span class="aa-score-explainer__signal-weight">~24 %</span>
						<p><?php esc_html_e( 'Temps moyen passé sur la page. Plus un visiteur reste, plus il est engagé.', 'always-analytics' ); ?></p>
					</div>
					<div class="aa-score-explainer__signal">
						<div class="aa-score-explainer__signal-header">
							<span class="aa-score-explainer__signal-icon aa-signal-icon--scroll">
								<!-- Lucide: arrow-down-to-line -->
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17V3"/><path d="m6 11 6 6 6-6"/><path d="M19 21H5"/></svg>
							</span>
							<span class="aa-score-explainer__signal-name"><?php esc_html_e( 'Scroll', 'always-analytics' ); ?></span>
						</div>
						<span class="aa-score-explainer__signal-weight">~22 %</span>
						<p><?php esc_html_e( 'Profondeur de défilement moyenne. Une page lue jusqu\'au bout signe un contenu pertinent.', 'always-analytics' ); ?></p>
					</div>
					<div class="aa-score-explainer__signal">
						<div class="aa-score-explainer__signal-header">
							<span class="aa-score-explainer__signal-icon aa-signal-icon--engagement">
								<!-- Lucide: mouse-pointer-click -->
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 9 5 12 1.8-5.2L21 14Z"/><path d="M7.2 2.2 8 5.1"/><path d="m5.1 8-2.9-.8"/><path d="M14 4.1 12 6"/><path d="m6 12-1.9 2"/></svg>
							</span>
							<span class="aa-score-explainer__signal-name"><?php esc_html_e( 'Engagement', 'always-analytics' ); ?></span>
						</div>
						<span class="aa-score-explainer__signal-weight">~22 %</span>
						<p><?php esc_html_e( 'Part de sessions actives (durée > 0 et non rebond). Filtre les visites superficielles.', 'always-analytics' ); ?></p>
					</div>
					<div class="aa-score-explainer__signal">
						<div class="aa-score-explainer__signal-header">
							<span class="aa-score-explainer__signal-icon aa-signal-icon--return">
								<!-- Lucide: repeat -->
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m17 2 4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
							</span>
							<span class="aa-score-explainer__signal-name"><?php esc_html_e( 'Retour', 'always-analytics' ); ?></span>
						</div>
						<span class="aa-score-explainer__signal-weight">~20 %</span>
						<p><?php esc_html_e( 'Part de visiteurs qui reviennent sur cette page. Indicateur fort de valeur perçue.', 'always-analytics' ); ?></p>
					</div>
					<div class="aa-score-explainer__signal">
						<div class="aa-score-explainer__signal-header">
							<span class="aa-score-explainer__signal-icon aa-signal-icon--depth">
								<!-- Lucide: layers -->
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>
							</span>
							<span class="aa-score-explainer__signal-name"><?php esc_html_e( 'Profondeur', 'always-analytics' ); ?></span>
						</div>
						<span class="aa-score-explainer__signal-weight">~13 %</span>
						<p><?php esc_html_e( 'Nombre moyen de pages vues par session après cette page. Mesure l\'effet d\'entraînement.', 'always-analytics' ); ?></p>
					</div>
				</div>

				<div class="aa-score-explainer__wilson">
					<div class="aa-score-explainer__wilson-head">
						<span class="aa-score-explainer__signal-icon aa-signal-icon--wilson">
							<!-- Lucide: shield-check -->
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
						</span>
						<strong><?php esc_html_e( 'Correction de Wilson', 'always-analytics' ); ?></strong>
						<span class="aa-score-explainer__wilson-badge"><?php esc_html_e( 'Modificateur global', 'always-analytics' ); ?></span>
					</div>
					<p><?php esc_html_e( 'L\'intervalle de Wilson applique un facteur de confiance statistique : moins il y a de données, plus le score est réduit vers la moyenne. Ce modificateur est affiché séparément car son algorithme peut évoluer indépendamment des signaux.', 'always-analytics' ); ?></p>
				</div>
			</div>
		</div>

		<!-- Table -->
		<div class="aa-card-body aa-card-body--flush">
			<table class="aa-full-table aa-eng-table" id="eng-pages-table">
				<thead>
					<tr>
						<th class="aa-eng-th-page"><?php esc_html_e( 'Page', 'always-analytics' ); ?></th>
						<th class="aa-eng-th-duration" title="<?php esc_attr_e( 'Durée moyenne passée sur la page', 'always-analytics' ); ?>"><?php esc_html_e( 'Durée moy.', 'always-analytics' ); ?></th>
						<th class="aa-eng-th-scroll" title="<?php esc_attr_e( 'Profondeur de scroll moyenne sur la page', 'always-analytics' ); ?>"><?php esc_html_e( 'Scroll moy.', 'always-analytics' ); ?></th>
						<th class="aa-eng-th-profile" title="<?php esc_attr_e( 'Profil de lecteur dominant sur cette page', 'always-analytics' ); ?>"><?php esc_html_e( 'Profil lecteur', 'always-analytics' ); ?></th>
						<th class="aa-eng-th-wilson" title="<?php esc_attr_e( 'Correction Wilson — fiabilité statistique. Plus il y a de sessions, plus ce score est élevé et plus le score global est fiable.', 'always-analytics' ); ?>">
							<?php esc_html_e( 'Fiabilité', 'always-analytics' ); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr><td colspan="5" class="aa-no-data"><?php esc_html_e( 'Chargement…', 'always-analytics' ); ?></td></tr>
				</tbody>
			</table>
		</div>
		<!-- Show more -->
		<div class="aa-show-more-wrap" id="eng-show-more-wrap" style="display:none;">
			<button class="aa-show-more-btn" id="eng-show-more-btn">
				<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
				<?php esc_html_e( 'Afficher 10 de plus', 'always-analytics' ); ?>
				<span class="aa-show-more-remaining" id="eng-show-more-remaining"></span>
			</button>
		</div>
	</div>

</div><!-- .wrap -->
