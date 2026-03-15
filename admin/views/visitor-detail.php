<?php
/**
 * Visitor detail view — Always Analytics.
 *
 * @package Always_Analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$t_sessions = $wpdb->prefix . 'aa_sessions';
$t_hits     = $wpdb->prefix . 'aa_hits';

$visitor_hash = isset( $_GET['visitor_hash'] ) ? sanitize_text_field( wp_unslash( $_GET['visitor_hash'] ) ) : '';
$session_id   = isset( $_GET['session_id'] )   ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) )   : '';

// Backward compat: resolve visitor_hash from session_id.
if ( empty( $visitor_hash ) && ! empty( $session_id ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$visitor_hash = $wpdb->get_var( $wpdb->prepare(
		"SELECT visitor_hash FROM {$t_sessions} WHERE session_id = %s",
		$session_id
	) );
}

if ( empty( $visitor_hash ) ) {
	echo '<div class="wrap"><h2>Erreur</h2><p>Identifiant visiteur manquant.</p></div>';
	return;
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$sessions = $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM {$t_sessions} WHERE visitor_hash = %s ORDER BY ended_at DESC",
	$visitor_hash
) );

if ( empty( $sessions ) ) {
	echo '<div class="wrap"><h2>Erreur</h2><p>Visiteur introuvable.</p></div>';
	return;
}

$tz_offset_seconds = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

if ( ! function_exists( 'aa_time_ago' ) ) {
	/**
	 * Returns a human-readable "time ago" string.
	 *
	 * @param string $datetime UTC datetime string.
	 * @return string Human-readable elapsed time.
	 */
	function aa_time_ago( $datetime ) {
		$time = strtotime( $datetime . ' UTC' );
		$diff = time() - $time;
		if ( $diff < 60 )    { return 'Il y a ' . $diff . ' s'; }
		if ( $diff < 3600 )  { return 'Il y a ' . floor( $diff / 60 ) . ' min'; }
		if ( $diff < 86400 ) { return 'Il y a ' . floor( $diff / 3600 ) . ' h'; }
		return 'Il y a ' . floor( $diff / 86400 ) . ' j';
	}
}

$total_pages   = array_sum( array_column( $sessions, 'page_count' ) );
$total_dur_raw = array_sum( array_map( function( $s ) {
	return ( ! empty( $s->engagement_time ) && $s->engagement_time > 0 ) ? (int) $s->engagement_time : (int) $s->duration;
}, $sessions ) );
$first_session = end( $sessions );
$last_session  = reset( $sessions );

// SVG allowed tags for device icons.
$svg_allowed = array(
	'svg'    => array( 'xmlns' => array(), 'width' => array(), 'height' => array(), 'viewBox' => array(), 'fill' => array(), 'stroke' => array(), 'stroke-width' => array(), 'stroke-linecap' => array(), 'stroke-linejoin' => array(), 'class' => array() ),
	'rect'   => array( 'x' => array(), 'y' => array(), 'width' => array(), 'height' => array(), 'rx' => array() ),
	'circle' => array( 'cx' => array(), 'cy' => array(), 'r' => array() ),
	'path'   => array( 'd' => array() ),
);

// Device icon SVG.
$device      = $last_session->device_type ?? '';
$device_icon = '';
if ( 'mobile' === $device ) {
	$device_icon = '<svg class="aa-device-svg" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><circle cx="12" cy="17" r="1"/></svg>';
} elseif ( 'tablet' === $device ) {
	$device_icon = '<svg class="aa-device-svg" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="2" width="18" height="20" rx="2"/><circle cx="12" cy="17" r="1"/></svg>';
} else {
	$device_icon = '<svg class="aa-device-svg" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 20h8M12 18v2"/></svg>';
}

// Country flag.
$flag_html  = '';
$cc         = $last_session->country_code ?: '';
$flag_allowed = array(
	'img'  => array( 'src' => array(), 'alt' => array(), 'title' => array(), 'width' => array(), 'height' => array(), 'class' => array(), 'loading' => array() ),
	'span' => array( 'class' => array() ),
);
if ( $cc && strlen( $cc ) === 2 ) {
	if ( function_exists( 'aa_country_flag_php' ) ) {
		$flag_html = aa_country_flag_php( $cc, 18 );
	} else {
		$lc        = strtolower( $cc );
		$uc        = strtoupper( $cc );
		$file_path = AA_PLUGIN_DIR . 'assets/flags/' . $lc . '.webp';
		if ( file_exists( $file_path ) ) {
			$flag_html = '<img src="' . esc_url( AA_PLUGIN_URL . 'assets/flags/' . $lc . '.webp' ) . '" alt="' . esc_attr( $uc ) . '" title="' . esc_attr( $uc ) . '" width="24" height="18" class="aa-flag-img" loading="lazy">';
		} else {
			$flag_html = '<span class="aa-flag-badge">' . esc_html( $uc ) . '</span>';
		}
	}
}

$dur_m = floor( $total_dur_raw / 60 );
$dur_s = $total_dur_raw % 60;
?>
<div class="wrap aa-wrap">

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=always-analytics' ) ); ?>" class="button aa-back-btn--top">
		&larr; <?php esc_html_e( 'Retour au tableau de bord', 'always-analytics' ); ?>
	</a>

	<div class="aa-card">
		<div class="aa-card-header">
			<h2>
				<?php esc_html_e( 'Visiteur', 'always-analytics' ); ?>
				<?php echo esc_html( substr( $visitor_hash, 0, 8 ) ); ?>
			</h2>
			<div class="aa-visitor-badges">
				<?php if ( $flag_html ) : ?>
					<span class="aa-badge"><?php echo wp_kses( $flag_html, $flag_allowed ); ?></span>
				<?php else : ?>
					<span class="aa-badge">—</span>
				<?php endif; ?>
				<span class="aa-badge" title="<?php echo esc_attr( $device ?: 'desktop' ); ?>">
					<?php echo wp_kses( $device_icon, $svg_allowed ); ?>
				</span>
				<span class="aa-badge"><?php echo count( $sessions ); ?> visite(s)</span>
			</div>
		</div>

		<div class="aa-card-body">

			<!-- Aggregated totals -->
			<div class="aa-visitor-stats">
				<div>
					<strong class="aa-visitor-stat__label"><?php esc_html_e( 'Pages vues (total)', 'always-analytics' ); ?></strong>
					<div class="aa-visitor-stat__value"><?php echo (int) $total_pages; ?></div>
				</div>
				<div>
					<strong class="aa-visitor-stat__label"><?php esc_html_e( 'Durée totale', 'always-analytics' ); ?></strong>
					<div class="aa-visitor-stat__value">
						<?php echo esc_html( ( $dur_m > 0 ? $dur_m . 'm ' : '' ) . $dur_s . 's' ); ?>
					</div>
				</div>
				<div>
					<strong class="aa-visitor-stat__label"><?php esc_html_e( 'Première visite', 'always-analytics' ); ?></strong>
					<div class="aa-visitor-stat__value aa-visitor-stat__value--medium">
						<?php echo esc_html( aa_time_ago( $first_session->started_at ) ); ?>
					</div>
				</div>
				<div>
					<strong class="aa-visitor-stat__label"><?php esc_html_e( 'Dernière activité', 'always-analytics' ); ?></strong>
					<div class="aa-visitor-stat__value aa-visitor-stat__value--medium">
						<?php echo esc_html( aa_time_ago( $last_session->ended_at ) ); ?>
					</div>
				</div>
			</div>

			<!-- Sessions list -->
			<?php foreach ( $sessions as $sess_index => $session ) :
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$hits         = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM {$t_hits} WHERE session_id = %s AND is_superseded = 0 ORDER BY hit_at ASC",
					$session->session_id
				) );
				$sess_dur_raw = ( ! empty( $session->engagement_time ) && $session->engagement_time > 0 )
					? (int) $session->engagement_time
					: (int) $session->duration;
				$sess_m = floor( $sess_dur_raw / 60 );
				$sess_s = $sess_dur_raw % 60;
			?>
			<div class="aa-session-block">
				<h3 class="aa-session-title">
					<?php if ( $sess_index === 0 ) : ?>
						<span class="aa-session-live-dot" title="Session la plus récente"></span>
					<?php endif; ?>
					<?php esc_html_e( 'Visite', 'always-analytics' ); ?> <?php echo esc_html( count( $sessions ) - $sess_index ); ?>
					<small class="aa-session-meta">
						— <?php echo esc_html( aa_time_ago( $session->started_at ) ); ?>
						&nbsp;·&nbsp; <?php echo esc_html( ( $sess_m > 0 ? $sess_m . 'm ' : '' ) . $sess_s . 's' ); ?>
						&nbsp;·&nbsp; <?php echo (int) $session->page_count; ?> page(s)
					</small>
				</h3>

				<div class="aa-timeline">
					<?php if ( empty( $hits ) ) : ?>
						<p class="aa-no-data"><?php esc_html_e( 'Aucune page enregistrée pour cette session.', 'always-analytics' ); ?></p>
					<?php endif; ?>
					<?php foreach ( $hits as $index => $hit ) : ?>
					<div class="aa-timeline-entry">
						<div class="aa-timeline-dot"></div>
						<div class="aa-timeline-time">
							<?php echo esc_html( gmdate( 'H:i:s', strtotime( $hit->hit_at ) + $tz_offset_seconds ) ); ?>
						</div>
						<div class="aa-timeline-card">
							<div class="aa-timeline-card__title">
								<?php echo esc_html( $hit->page_title ?: $hit->page_url ); ?>
							</div>
							<div class="aa-timeline-card__url">
								<a href="<?php echo esc_url( $hit->page_url ); ?>" target="_blank">
									<?php echo esc_html( $hit->page_url ); ?>
								</a>
							</div>

							<?php if ( $index === 0 && ! empty( $hit->referrer ) ) :
								$ref_domain = '';
								$parsed_ref = wp_parse_url( $hit->referrer );
								if ( ! empty( $parsed_ref['host'] ) ) {
									$ref_domain = $parsed_ref['host'];
								}
							?>
								<div class="aa-timeline-referrer">
									<?php if ( $ref_domain ) : ?>
										<img src="https://www.google.com/s2/favicons?domain=<?php echo esc_attr( urlencode( $ref_domain ) ); ?>&sz=32"
											 width="14" height="14" alt="" loading="lazy"
											 class="aa-referrer-favicon"
											 onerror="this.style.display='none'">
									<?php else : ?>
										<span class="dashicons dashicons-external"></span>
									<?php endif; ?>
									<?php esc_html_e( 'Source :', 'always-analytics' ); ?> <?php echo esc_html( $hit->referrer ); ?>
								</div>
							<?php endif; ?>

							<?php if ( ! empty( $hit->utm_source ) ) : ?>
								<div class="aa-utm-tags">
									<span class="aa-utm-tag">UTM Source: <?php echo esc_html( $hit->utm_source ); ?></span>
									<?php if ( $hit->utm_medium ) : ?>
									<span class="aa-utm-tag">UTM Medium: <?php echo esc_html( $hit->utm_medium ); ?></span>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endforeach; ?>

		</div>
	</div>
</div>
