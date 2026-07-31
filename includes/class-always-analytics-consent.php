<?php
/**
 * Front-end transparency, consent, and opt-out controls.
 *
 * @package AlwaysAnalytics
 */

namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the appropriate privacy notice for the selected tracking mode.
 */
final class Always_Analytics_Consent {

	/**
	 * Renders the front-end notice when enabled.
	 *
	 * @return void
	 */
	public function render_banner() {
		$options = get_option( 'always_analytics_options', array() );
		if ( empty( $options['consent_enabled'] ) || $this->is_excluded_user( $options ) ) {
			return;
		}

		$tracking_mode = isset( $options['tracking_mode'] ) ? sanitize_key( $options['tracking_mode'] ) : 'cookieless';
		if ( 'cookie' === $tracking_mode ) {
			$this->render_cookie_consent( $options );
			return;
		}

		$this->render_cookieless_notice( $options );
	}

	/**
	 * Enqueue local notice assets when privacy controls are enabled.
	 *
	 * @return void
	 */
	public function maybe_enqueue_assets() {
		$options = get_option( 'always_analytics_options', array() );
		if ( empty( $options['consent_enabled'] ) || $this->is_excluded_user( $options ) ) {
			return;
		}

		$this->enqueue_assets();
	}

	/**
	 * Load the local notice assets.
	 *
	 * @return void
	 */
	private function enqueue_assets() {
		wp_enqueue_style(
			'always-analytics-consent',
			ALWAYS_ANALYTICS_PLUGIN_URL . 'public/css/always-analytics-consent.css',
			array(),
			ALWAYS_ANALYTICS_VERSION
		);

		wp_enqueue_script(
			'always-analytics-consent',
			ALWAYS_ANALYTICS_PLUGIN_URL . 'public/js/always-analytics-consent.js',
			array(),
			ALWAYS_ANALYTICS_VERSION,
			true
		);

		wp_localize_script(
			'always-analytics-consent',
			'alwaysAnalyticsPrivacyConfig',
			array(
				'consentCookie' => 'always_analytics_consent',
				'optOutCookie'  => 'always_analytics_opt_out',
				'visitorCookie' => 'always_analytics_vid',
				'cookieDays'    => 395,
			)
		);
	}

	/**
	 * Renders a transparency notice and a persistent opt-out control.
	 *
	 * @param array<string, mixed> $options Plugin options.
	 * @return void
	 */
	private function render_cookieless_notice( $options ) {
		$site_name = get_bloginfo( 'name' );
		$message   = ! empty( $options['info_message'] )
			? (string) $options['info_message']
			: sprintf(
				/* translators: %s: site name. */
				__( '%s uses self-hosted, cookieless audience measurement. You can opt out at any time.', 'always-analytics' ),
				$site_name
			);
		$dismiss_text = ! empty( $options['info_ok'] ) ? (string) $options['info_ok'] : __( 'Continue', 'always-analytics' );
		$colors       = $this->get_notice_colors( $options );
		?>
		<div
			id="always-analytics-info-banner"
			class="always-analytics-privacy-banner"
			role="region"
			aria-label="<?php echo esc_attr__( 'Audience measurement information', 'always-analytics' ); ?>"
			style="--always-analytics-bg:<?php echo esc_attr( $colors['background'] ); ?>;--always-analytics-text:<?php echo esc_attr( $colors['text'] ); ?>;--always-analytics-accent:<?php echo esc_attr( $colors['button'] ); ?>;"
			hidden
		>
			<div class="always-analytics-privacy-banner__inner">
				<span class="always-analytics-privacy-banner__icon" aria-hidden="true"><?php echo wp_kses( $this->get_shield_icon(), $this->get_svg_kses_rules() ); ?></span>
				<p class="always-analytics-privacy-banner__text"><?php echo esc_html( $message ); ?></p>
				<div class="always-analytics-privacy-banner__actions">
					<button id="always-analytics-info-opt-out" class="always-analytics-privacy-button always-analytics-privacy-button--secondary" type="button">
						<?php echo esc_html__( 'Opt out', 'always-analytics' ); ?>
					</button>
					<button id="always-analytics-info-ok" class="always-analytics-privacy-button always-analytics-privacy-button--primary" type="button">
						<?php echo esc_html( $dismiss_text ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}




	/**
	 * Renders the cookie-based consent banner (accept/decline).
	 *
	 * @param array<string, mixed> $options Plugin options.
	 * @return void
	 */
	private function render_cookie_consent( $options ) {
		$message      = ! empty( $options['consent_message'] ) ? (string) $options['consent_message'] : __( 'This site would like to use an audience measurement cookie. Do you agree?', 'always-analytics' );
		$accept_text  = ! empty( $options['consent_accept'] ) ? (string) $options['consent_accept'] : __( 'Accept', 'always-analytics' );
		$decline_text = ! empty( $options['consent_decline'] ) ? (string) $options['consent_decline'] : __( 'Decline', 'always-analytics' );
		$colors       = $this->get_notice_colors( $options );
		?>
		<div
			id="always-analytics-consent-banner"
			class="always-analytics-privacy-banner"
			role="dialog"
			aria-modal="false"
			aria-label="<?php echo esc_attr__( 'Audience measurement consent', 'always-analytics' ); ?>"
			style="--always-analytics-bg:<?php echo esc_attr( $colors['background'] ); ?>;--always-analytics-text:<?php echo esc_attr( $colors['text'] ); ?>;--always-analytics-accent:<?php echo esc_attr( $colors['button'] ); ?>;"
			hidden
		>
			<div class="always-analytics-privacy-banner__inner">
				<span class="always-analytics-privacy-banner__icon" aria-hidden="true"><?php echo wp_kses( $this->get_cookie_icon(), $this->get_svg_kses_rules() ); ?></span>
				<p class="always-analytics-privacy-banner__text"><?php echo esc_html( $message ); ?></p>
				<div class="always-analytics-privacy-banner__actions">
					<button id="always-analytics-consent-decline" class="always-analytics-privacy-button always-analytics-privacy-button--secondary" type="button">
						<?php echo esc_html( $decline_text ); ?>
					</button>
					<button id="always-analytics-consent-accept" class="always-analytics-privacy-button always-analytics-privacy-button--primary" type="button">
						<?php echo esc_html( $accept_text ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Checks whether the current user belongs to an excluded role.
	 *
	 * @param array<string, mixed> $options Plugin options.
	 * @return bool
	 */
	private function is_excluded_user( $options ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$excluded_roles = isset( $options['excluded_roles'] ) ? array_map( 'sanitize_key', (array) $options['excluded_roles'] ) : array();
		$user           = wp_get_current_user();

		return ! empty( array_intersect( $excluded_roles, (array) $user->roles ) );
	}

	/**
	 * Returns validated notice colors.
	 *
	 * @param array<string, mixed> $options Plugin options.
	 * @return array<string, string>
	 */
	private function get_notice_colors( $options ) {
		$background = sanitize_hex_color( $options['consent_bg_color'] ?? '' );
		$text       = sanitize_hex_color( $options['consent_text_color'] ?? '' );
		$button     = sanitize_hex_color( $options['consent_btn_color'] ?? '' );

		return array(
			'background' => $background ? $background : '#0f172a',
			'text'       => $text ? $text : '#f8fafc',
			'button'     => $button ? $button : '#6366f1',
		);
	}

	/**
	 * Returns the shield icon markup.
	 *
	 * @return string
	 */
	private function get_shield_icon() {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>';
	}

	/**
	 * Returns the cookie icon markup.
	 *
	 * @return string
	 */
	private function get_cookie_icon() {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/></svg>';
	}

	/**
	 * Returns the allowlist used for inline SVG icons.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private function get_svg_kses_rules() {
		return array(
			'svg'      => array(
				'xmlns'           => true,
				'width'           => true,
				'height'          => true,
				'viewbox'         => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'focusable'       => true,
			),
			'path'     => array( 'd' => true ),
			'polyline' => array( 'points' => true ),
		);
	}
}
