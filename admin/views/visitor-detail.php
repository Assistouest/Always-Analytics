<?php
/**
 * Visitor detail view — Always Analytics.
 *
 * @package Always_Analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You are not allowed to view visitor details.', 'always-analytics' ) );
}

global $wpdb;

// Read-only lookup identifiers for this report; validated against a strict format below
// before any DB use, and no state is changed, so no nonce is required.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only lookup; access is capability-gated and the identifier is sanitized and format-validated below.
$always_analytics_visitor_hash = isset( $_GET['visitor_hash'] ) ? sanitize_text_field( wp_unslash( $_GET['visitor_hash'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only lookup; access is capability-gated and the identifier is sanitized and format-validated below.
$always_analytics_session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';

if ( $always_analytics_visitor_hash && ! preg_match( '/^[a-f0-9]{64}$/i', $always_analytics_visitor_hash ) ) {
	$always_analytics_visitor_hash = '';
}
if ( $always_analytics_session_id && ! preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $always_analytics_session_id ) ) {
	$always_analytics_session_id = '';
}


if ( empty( $always_analytics_visitor_hash ) && ! empty( $always_analytics_session_id ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Capability-gated lookup in a plugin-owned table; the UUID value is prepared.
	$always_analytics_visitor_hash = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT visitor_hash FROM {$wpdb->prefix}always_analytics_sessions WHERE session_id = %s",
			$always_analytics_session_id
		)
	);
}

if ( empty( $always_analytics_visitor_hash ) ) {
	wp_die(
		esc_html__( 'The visitor identifier is missing.', 'always-analytics' ),
		esc_html__( 'Visitor details', 'always-analytics' ),
		array( 'response' => 400 )
	);
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Capability-gated report over a plugin-owned table; the visitor hash is prepared.
$always_analytics_sessions = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}always_analytics_sessions WHERE visitor_hash = %s ORDER BY ended_at DESC",
		$always_analytics_visitor_hash
	)
);

if ( empty( $always_analytics_sessions ) ) {
	wp_die(
		esc_html__( 'The visitor could not be found.', 'always-analytics' ),
		esc_html__( 'Visitor details', 'always-analytics' ),
		array( 'response' => 404 )
	);
}

$always_analytics_tz_offset_seconds = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

if ( ! function_exists( 'always_analytics_time_ago' ) ) {
	/**
	 * Return a localized relative time.
	 *
	 * @param string $datetime UTC datetime string.
	 * @return string
	 */
	function always_analytics_time_ago( $datetime ) {
		$timestamp = strtotime( $datetime . ' UTC' );
		if ( false === $timestamp ) {
			return '';
		}

		return sprintf(
			/* translators: %s: human-readable time difference. */
			esc_html__( '%s ago', 'always-analytics' ),
			human_time_diff( $timestamp, time() )
		);
	}
}

$always_analytics_total_pages   = array_sum( array_column( $always_analytics_sessions, 'page_count' ) );
$always_analytics_total_dur_raw = array_sum(
	array_map(
		function ( $s ) {
			if ( ! empty( $s->engagement_time ) && $s->engagement_time > 0 ) {
					return (int) $s->engagement_time;
			}

			if ( (int) $s->page_count <= 1 ) {
				return 0;
			}
			return (int) $s->duration;
		},
		$always_analytics_sessions
	)
);
$always_analytics_last_session  = reset( $always_analytics_sessions );
$always_analytics_first_session = end( $always_analytics_sessions );


$always_analytics_svg_allowed = array(
	'svg'    => array(
		'xmlns'           => array(),
		'width'           => array(),
		'height'          => array(),
		'viewBox'         => array(),
		'fill'            => array(),
		'stroke'          => array(),
		'stroke-width'    => array(),
		'stroke-linecap'  => array(),
		'stroke-linejoin' => array(),
		'class'           => array(),
	),
	'rect'   => array(
		'x'      => array(),
		'y'      => array(),
		'width'  => array(),
		'height' => array(),
		'rx'     => array(),
	),
	'circle' => array(
		'cx' => array(),
		'cy' => array(),
		'r'  => array(),
	),
	'path'   => array( 'd' => array() ),
);


$always_analytics_device      = $always_analytics_last_session->device_type ?? '';
$always_analytics_device_icon = '';
if ( 'mobile' === $always_analytics_device ) {
	$always_analytics_device_icon = '<svg class="always-analytics-device-svg" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><circle cx="12" cy="17" r="1"/></svg>';
} elseif ( 'tablet' === $always_analytics_device ) {
	$always_analytics_device_icon = '<svg class="always-analytics-device-svg" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="2" width="18" height="20" rx="2"/><circle cx="12" cy="17" r="1"/></svg>';
} else {
	$always_analytics_device_icon = '<svg class="always-analytics-device-svg" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 20h8M12 18v2"/></svg>';
}


$always_analytics_dur_m = floor( $always_analytics_total_dur_raw / 60 );
$always_analytics_dur_s = $always_analytics_total_dur_raw % 60;
?>
<div class="wrap always-analytics-wrap">

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=always-analytics' ) ); ?>" class="button always-analytics-back-btn--top">
		&larr; <?php esc_html_e( 'Back to dashboard', 'always-analytics' ); ?>
	</a>

	<div class="always-analytics-card">
		<div class="always-analytics-card-header">
			<h2>
				<?php esc_html_e( 'Visitor', 'always-analytics' ); ?>
				<?php echo esc_html( substr( $always_analytics_visitor_hash, 0, 8 ) ); ?>
			</h2>
			<div class="always-analytics-visitor-badges">
				<span class="always-analytics-badge" title="<?php echo esc_attr( ! empty( $always_analytics_device ) ? $always_analytics_device : 'desktop' ); ?>">
					<?php echo wp_kses( $always_analytics_device_icon, $always_analytics_svg_allowed ); ?>
				</span>
				<span class="always-analytics-badge">
					<?php
					/* translators: %s: number of visits. */
					echo esc_html( sprintf( _n( '%s visit', '%s visits', count( $always_analytics_sessions ), 'always-analytics' ), number_format_i18n( count( $always_analytics_sessions ) ) ) );
					?>
				</span>
			</div>

		</div>

		<div class="always-analytics-card-body">

			<!-- Aggregated totals -->
			<div class="always-analytics-visitor-stats">
				<div>
					<strong class="always-analytics-visitor-stat__label"><?php esc_html_e( 'Total page views', 'always-analytics' ); ?></strong>
					<div class="always-analytics-visitor-stat__value"><?php echo (int) $always_analytics_total_pages; ?></div>
				</div>
				<div>
					<strong class="always-analytics-visitor-stat__label"><?php esc_html_e( 'Total duration', 'always-analytics' ); ?></strong>
					<div class="always-analytics-visitor-stat__value">
						<?php echo esc_html( ( $always_analytics_dur_m > 0 ? $always_analytics_dur_m . 'm ' : '' ) . $always_analytics_dur_s . 's' ); ?>
					</div>
				</div>
				<div>
					<strong class="always-analytics-visitor-stat__label"><?php esc_html_e( 'First visit', 'always-analytics' ); ?></strong>
					<div class="always-analytics-visitor-stat__value always-analytics-visitor-stat__value--medium">
						<?php echo esc_html( always_analytics_time_ago( $always_analytics_first_session->started_at ) ); ?>
					</div>
				</div>
				<div>
					<strong class="always-analytics-visitor-stat__label"><?php esc_html_e( 'Last activity', 'always-analytics' ); ?></strong>
					<div class="always-analytics-visitor-stat__value always-analytics-visitor-stat__value--medium">
						<?php echo esc_html( always_analytics_time_ago( $always_analytics_last_session->ended_at ) ); ?>
					</div>
				</div>
			</div>

			<!-- Sessions list -->
			<?php
			foreach ( $always_analytics_sessions as $always_analytics_sess_index => $always_analytics_session ) :
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Capability-gated session timeline from a plugin-owned table; the session ID is prepared.
				$always_analytics_hits = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}always_analytics_hits WHERE session_id = %s AND is_superseded = 0 ORDER BY hit_at ASC",
						$always_analytics_session->session_id
					)
				);
				$always_analytics_sess_dur_raw = ( ! empty( $always_analytics_session->engagement_time ) && $always_analytics_session->engagement_time > 0 )
					? (int) $always_analytics_session->engagement_time
					: ( (int) $always_analytics_session->page_count > 1 ? (int) $always_analytics_session->duration : 0 );
				$always_analytics_sess_m       = floor( $always_analytics_sess_dur_raw / 60 );
				$always_analytics_sess_s       = $always_analytics_sess_dur_raw % 60;
				?>
			<div class="always-analytics-session-block">
				<h3 class="always-analytics-session-title">
					<?php if ( 0 === $always_analytics_sess_index ) : ?>
						<span class="always-analytics-session-live-dot" title="<?php echo esc_attr__( 'Most recent session', 'always-analytics' ); ?>"></span>
					<?php endif; ?>
					<?php esc_html_e( 'Visit', 'always-analytics' ); ?> <?php echo esc_html( count( $always_analytics_sessions ) - $always_analytics_sess_index ); ?>
					<small class="always-analytics-session-meta">
						— <?php echo esc_html( always_analytics_time_ago( $always_analytics_session->started_at ) ); ?>
						&nbsp;·&nbsp; <?php echo esc_html( ( $always_analytics_sess_m > 0 ? $always_analytics_sess_m . 'm ' : '' ) . $always_analytics_sess_s . 's' ); ?>
						&nbsp;·&nbsp;
						<?php
						/* translators: %s: number of pages in the session. */
						echo esc_html( sprintf( _n( '%s page', '%s pages', (int) $always_analytics_session->page_count, 'always-analytics' ), number_format_i18n( (int) $always_analytics_session->page_count ) ) );
						?>
					</small>
				</h3>

				<div class="always-analytics-timeline">
					<?php if ( empty( $always_analytics_hits ) ) : ?>
						<p class="always-analytics-no-data"><?php esc_html_e( 'No page was recorded for this session.', 'always-analytics' ); ?></p>
					<?php endif; ?>
					<?php foreach ( $always_analytics_hits as $always_analytics_index => $always_analytics_hit ) : ?>
					<div class="always-analytics-timeline-entry">
						<div class="always-analytics-timeline-dot"></div>
						<div class="always-analytics-timeline-time">
							<?php echo esc_html( gmdate( 'H:i:s', strtotime( $always_analytics_hit->hit_at ) + $always_analytics_tz_offset_seconds ) ); ?>
						</div>
						<div class="always-analytics-timeline-card">
							<div class="always-analytics-timeline-card__title">
								<?php echo esc_html( ! empty( $always_analytics_hit->page_title ) ? $always_analytics_hit->page_title : $always_analytics_hit->page_url ); ?>
							</div>
							<div class="always-analytics-timeline-card__url">
								<a href="<?php echo esc_url( $always_analytics_hit->page_url ); ?>" target="_blank">
									<?php echo esc_html( $always_analytics_hit->page_url ); ?>
								</a>
							</div>

							<?php
							if ( 0 === $always_analytics_index && ! empty( $always_analytics_hit->referrer ) ) :
								$always_analytics_ref_domain = '';
								$always_analytics_parsed_ref = wp_parse_url( $always_analytics_hit->referrer );
								if ( ! empty( $always_analytics_parsed_ref['host'] ) ) {
									$always_analytics_ref_domain = $always_analytics_parsed_ref['host'];
								}
								?>
								<div class="always-analytics-timeline-referrer">
									<span class="dashicons dashicons-external" aria-hidden="true"></span>
									<?php esc_html_e( 'Source:', 'always-analytics' ); ?> <?php echo esc_html( $always_analytics_hit->referrer ); ?>
								</div>
							<?php endif; ?>

							<?php if ( ! empty( $always_analytics_hit->utm_source ) ) : ?>
								<div class="always-analytics-utm-tags">
									<span class="always-analytics-utm-tag">UTM Source: <?php echo esc_html( $always_analytics_hit->utm_source ); ?></span>
									<?php if ( $always_analytics_hit->utm_medium ) : ?>
									<span class="always-analytics-utm-tag">UTM Medium: <?php echo esc_html( $always_analytics_hit->utm_medium ); ?></span>
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
