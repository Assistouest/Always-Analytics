<?php
/**
 * Plugin settings registration and validation.
 *
 * @package AlwaysAnalytics
 */

namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and sanitizes plugin settings.
 */
final class Always_Analytics_Settings {

	/**
	 * Registers the option and settings fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'always_analytics_settings',
			'always_analytics_options',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => array(),
			)
		);

		$this->register_collection_section();
		$this->register_data_section();
		$this->register_visitor_controls_section();
		$this->register_performance_section();
	}

	/**
	 * Sanitizes all settings before persistence.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_options( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$output = get_option( 'always_analytics_options', array() );

		foreach ( array( 'disable_tracking', 'delete_on_uninstall', 'external_favicons' ) as $key ) {
			$output[ $key ] = ! empty( $input[ $key ] );
		}

		$output['tracking_mode'] = isset( $input['tracking_mode'] ) && in_array( $input['tracking_mode'], array( 'cookieless', 'cookie' ), true )
			? $input['tracking_mode']
			: 'cookieless';

		$output['cookieless_window'] = isset( $input['cookieless_window'] ) && in_array( $input['cookieless_window'], array( 'daily', 'session' ), true )
			? $input['cookieless_window']
			: 'daily';

		// Mode-specific safeguards are enforced server-side, even if a crafted request bypasses the interface.
		$output['consent_enabled'] = 'cookie' === $output['tracking_mode'];
		$output['anonymize_ip']    = 'cookieless' === $output['tracking_mode'] || ! empty( $input['anonymize_ip'] );
		$output['retention_days']  = isset( $input['retention_days'] )
			? min( 395, max( 30, absint( $input['retention_days'] ) ) )
			: 90;
		$output['cache_ttl']       = isset( $input['cache_ttl'] )
			? min( 3600, max( 60, absint( $input['cache_ttl'] ) ) )
			: 300;

		$output['export_format'] = isset( $input['export_format'] ) && in_array( $input['export_format'], array( 'csv', 'json' ), true )
			? $input['export_format']
			: 'csv';

		$output['trusted_proxy_mode'] = isset( $input['trusted_proxy_mode'] ) && in_array( $input['trusted_proxy_mode'], array( 'none', 'custom' ), true )
			? $input['trusted_proxy_mode']
			: 'none';
		$output['excluded_ips']       = isset( $input['excluded_ips'] ) ? $this->sanitize_ip_list( $input['excluded_ips'] ) : '';
		$output['trusted_proxies']    = isset( $input['trusted_proxies'] ) ? $this->sanitize_ip_list( $input['trusted_proxies'] ) : '';
		$output['excluded_roles']     = isset( $input['excluded_roles'] ) && is_array( $input['excluded_roles'] )
			? array_values( array_unique( array_map( 'sanitize_key', $input['excluded_roles'] ) ) )
			: array();

		foreach ( array( 'consent_message', 'consent_accept', 'consent_decline', 'info_message', 'info_ok' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$output[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}

		foreach ( array( 'consent_bg_color', 'consent_text_color', 'consent_btn_color' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$output[ $key ] = sanitize_hex_color( $input[ $key ] );
			}
		}

		add_settings_error(
			'always_analytics_options',
			'always_analytics_saved',
			__( 'Settings saved.', 'always-analytics' ),
			'updated'
		);

		return $output;
	}

	/**
	 * Registers audience collection settings.
	 *
	 * @return void
	 */
	private function register_collection_section() {
		add_settings_section(
			'always_analytics_collection',
			__( 'Audience collection', 'always-analytics' ),
			array( $this, 'render_collection_description' ),
			'always-analytics-settings'
		);

		$this->add_field( 'disable_tracking', __( 'Pause collection', 'always-analytics' ), 'render_checkbox', 'always_analytics_collection', array( 'description' => __( 'Stop all analytics collection until this option is disabled.', 'always-analytics' ) ) );
		$this->add_field( 'tracking_mode', __( 'Tracking mode', 'always-analytics' ), 'render_tracking_mode', 'always_analytics_collection' );
		$this->add_field( 'cookieless_window', __( 'Cookieless uniqueness window', 'always-analytics' ), 'render_cookieless_window', 'always_analytics_collection' );
		$this->add_field( 'excluded_roles', __( 'Excluded roles', 'always-analytics' ), 'render_excluded_roles', 'always_analytics_collection' );
		$this->add_field( 'excluded_ips', __( 'Excluded IP addresses', 'always-analytics' ), 'render_textarea', 'always_analytics_collection', array( 'description' => __( 'Enter one IP address or CIDR range per line.', 'always-analytics' ) ) );
		$this->add_field( 'trusted_proxy_mode', __( 'Trusted proxy mode', 'always-analytics' ), 'render_trusted_proxy_mode', 'always_analytics_collection' );
		$this->add_field( 'trusted_proxies', __( 'Trusted proxy addresses', 'always-analytics' ), 'render_textarea', 'always_analytics_collection', array( 'description' => __( 'Used only in custom proxy mode. Enter one IP address or CIDR range per line.', 'always-analytics' ) ) );
	}

	/**
	 * Registers privacy settings.
	 *
	 * @return void
	 */
	private function register_data_section() {
		add_settings_section(
			'always_analytics_data',
			__( 'Data management', 'always-analytics' ),
			array( $this, 'render_data_description' ),
			'always-analytics-settings'
		);

		$this->add_field( 'anonymize_ip', __( 'Truncate IP addresses', 'always-analytics' ), 'render_anonymize_ip', 'always_analytics_data' );
		$this->add_field( 'retention_days', __( 'Raw data retention', 'always-analytics' ), 'render_retention', 'always_analytics_data' );
		$this->add_field( 'delete_on_uninstall', __( 'Delete data on uninstall', 'always-analytics' ), 'render_checkbox', 'always_analytics_data', array( 'description' => __( 'Permanently remove plugin tables and options when the plugin is deleted.', 'always-analytics' ) ) );
	}

	/**
	 * Registers notice and consent settings.
	 *
	 * @return void
	 */
	private function register_visitor_controls_section() {
		add_settings_section(
			'always_analytics_notice',
			__( 'Visitor controls', 'always-analytics' ),
			array( $this, 'render_visitor_controls_description' ),
			'always-analytics-settings'
		);

		$this->add_field( 'consent_enabled', __( 'Show visitor controls', 'always-analytics' ), 'render_checkbox', 'always_analytics_notice', array( 'description' => __( 'Show an opt-out control in cookieless mode or accept and decline controls in cookie mode.', 'always-analytics' ) ) );
		$this->add_field( 'info_message', __( 'Cookieless message', 'always-analytics' ), 'render_text', 'always_analytics_notice', array( 'class' => 'large-text' ) );
		$this->add_field( 'info_ok', __( 'Continue button', 'always-analytics' ), 'render_text', 'always_analytics_notice' );
		$this->add_field( 'consent_message', __( 'Cookie-mode message', 'always-analytics' ), 'render_text', 'always_analytics_notice', array( 'class' => 'large-text' ) );
		$this->add_field( 'consent_accept', __( 'Accept button', 'always-analytics' ), 'render_text', 'always_analytics_notice' );
		$this->add_field( 'consent_decline', __( 'Decline button', 'always-analytics' ), 'render_text', 'always_analytics_notice' );
		$this->add_field( 'consent_colors', __( 'Notice colors', 'always-analytics' ), 'render_consent_colors', 'always_analytics_notice' );
	}

	/**
	 * Registers performance and export settings.
	 *
	 * @return void
	 */
	private function register_performance_section() {
		add_settings_section(
			'always_analytics_performance',
			__( 'Performance and export', 'always-analytics' ),
			'__return_false',
			'always-analytics-settings'
		);

		$this->add_field(
			'cache_ttl',
			__( 'Dashboard cache duration', 'always-analytics' ),
			'render_number',
			'always_analytics_performance',
			array(
				'min'         => 60,
				'max'         => 3600,
				'description' => __( 'Duration in seconds, between 60 and 3600.', 'always-analytics' ),
			)
		);
		$this->add_field( 'bot_filter_info', __( 'Bot filtering', 'always-analytics' ), 'render_bot_filter', 'always_analytics_performance' );
		$this->add_field( 'external_favicons', __( 'External favicons', 'always-analytics' ), 'render_checkbox', 'always_analytics_performance', array( 'description' => __( "Display external site favicons (requires a connection to Google's servers. Your IP address may be collected by this third-party service).", 'always-analytics' ) ) );
		$this->add_field( 'export_format', __( 'Default export format', 'always-analytics' ), 'render_export_format', 'always_analytics_performance' );
	}

	/**
	 * Adds a settings field with a plugin-prefixed identifier.
	 *
	 * @param string               $id       Field identifier.
	 * @param string               $title    Field title.
	 * @param string               $callback Renderer method.
	 * @param string               $section  Section identifier.
	 * @param array<string, mixed> $args     Renderer arguments.
	 * @return void
	 */
	private function add_field( $id, $title, $callback, $section, $args = array() ) {
		$args['field'] = $id;
		add_settings_field(
			'always_analytics_' . $id,
			$title,
			array( $this, $callback ),
			'always-analytics-settings',
			$section,
			$args
		);
	}

	/**
	 * Explains the collection modes.
	 *
	 * @return void
	 */
	public function render_collection_description() {
		echo '<p>' . esc_html__( 'Cookieless collection is self-hosted and does not write a visitor identifier to persistent browser storage. Cookie mode creates a persistent first-party identifier only after acceptance.', 'always-analytics' ) . '</p>';
	}

	/**
	 * Explains privacy configuration limitations.
	 *
	 * @return void
	 */
	public function render_data_description() {
		echo '<p>' . esc_html__( 'Configure retention, IP truncation, and uninstall cleanup.', 'always-analytics' ) . '</p>';
	}

	/**
	 * Explains the front-end controls.
	 *
	 * @return void
	 */
	public function render_visitor_controls_description() {
		echo '<p>' . esc_html__( 'These optional controls are provided for sites that need a visitor-facing choice. They do not replace project-specific legal review.', 'always-analytics' ) . '</p>';
	}

	/**
	 * Renders a checkbox.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_checkbox( $args ) {
		$options = get_option( 'always_analytics_options', array() );
		$field   = sanitize_key( $args['field'] );
		?>
		<label>
			<input type="checkbox" name="always_analytics_options[<?php echo esc_attr( $field ); ?>]" value="1" <?php checked( ! empty( $options[ $field ] ) ); ?>>
			<?php if ( ! empty( $args['description'] ) ) : ?>
				<span class="description"><?php echo esc_html( $args['description'] ); ?></span>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Renders a text input.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_text( $args ) {
		$options = get_option( 'always_analytics_options', array() );
		$field   = sanitize_key( $args['field'] );
		$class   = ! empty( $args['class'] ) ? sanitize_html_class( $args['class'] ) : 'regular-text';
		$value   = isset( $options[ $field ] ) ? (string) $options[ $field ] : '';
		?>
		<input type="text" name="always_analytics_options[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="<?php echo esc_attr( $class ); ?>">
		<?php
	}

	/**
	 * Renders a multiline input.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_textarea( $args ) {
		$options = get_option( 'always_analytics_options', array() );
		$field   = sanitize_key( $args['field'] );
		$value   = isset( $options[ $field ] ) ? (string) $options[ $field ] : '';
		?>
		<textarea name="always_analytics_options[<?php echo esc_attr( $field ); ?>]" rows="4" class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders a numeric input.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_number( $args ) {
		$options = get_option( 'always_analytics_options', array() );
		$field   = sanitize_key( $args['field'] );
		$value   = isset( $options[ $field ] ) ? absint( $options[ $field ] ) : 300;
		?>
		<input type="number" name="always_analytics_options[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $args['min'] ); ?>" max="<?php echo esc_attr( $args['max'] ); ?>" class="small-text">
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders tracking mode choices.
	 *
	 * @return void
	 */
	public function render_tracking_mode() {
		$options = get_option( 'always_analytics_options', array() );
		$mode    = isset( $options['tracking_mode'] ) ? $options['tracking_mode'] : 'cookieless';
		?>
		<fieldset>
			<label><input type="radio" name="always_analytics_options[tracking_mode]" value="cookieless" <?php checked( $mode, 'cookieless' ); ?>> <?php echo esc_html__( 'Cookieless, self-hosted measurement (recommended)', 'always-analytics' ); ?></label><br>
			<label><input type="radio" name="always_analytics_options[tracking_mode]" value="cookie" <?php checked( $mode, 'cookie' ); ?>> <?php echo esc_html__( 'Persistent first-party cookie after acceptance (advanced)', 'always-analytics' ); ?></label>
		</fieldset>
		<p class="description"><?php echo esc_html__( 'Cookie mode changes how visitors are identified. Use it only when the site has the required visitor information and controls. Developers may enable it for testing.', 'always-analytics' ); ?></p>
		<?php
	}

	/**
	 * Renders cookieless uniqueness choices.
	 *
	 * @return void
	 */
	public function render_cookieless_window() {
		$options = get_option( 'always_analytics_options', array() );
		$window  = isset( $options['cookieless_window'] ) ? $options['cookieless_window'] : 'daily';
		?>
		<fieldset>
			<label><input type="radio" name="always_analytics_options[cookieless_window]" value="daily" <?php checked( $window, 'daily' ); ?>> <?php echo esc_html__( 'Daily rotating identifier', 'always-analytics' ); ?></label><br>
			<label><input type="radio" name="always_analytics_options[cookieless_window]" value="session" <?php checked( $window, 'session' ); ?>> <?php echo esc_html__( 'Browser session only', 'always-analytics' ); ?></label>
		</fieldset>
		<p class="description"><?php echo esc_html__( 'Session mode provides stronger unlinkability. Daily mode improves unique-visitor estimates within one UTC day.', 'always-analytics' ); ?></p>
		<?php
	}

	/**
	 * Renders the IP truncation option.
	 *
	 * @return void
	 */
	public function render_anonymize_ip() {
		$options = get_option( 'always_analytics_options', array() );
		$mode    = isset( $options['tracking_mode'] ) ? $options['tracking_mode'] : 'cookieless';
		?>
		<label>
			<input type="checkbox" name="always_analytics_options[anonymize_ip]" value="1" <?php checked( ! empty( $options['anonymize_ip'] ) || 'cookieless' === $mode ); ?> <?php disabled( 'cookieless' === $mode ); ?>>
			<?php echo esc_html__( 'Remove the last IPv4 octet or the last 80 IPv6 bits before analytics storage.', 'always-analytics' ); ?>
		</label>
		<?php if ( 'cookieless' === $mode ) : ?>
			<input type="hidden" name="always_analytics_options[anonymize_ip]" value="1">
			<p class="description"><?php echo esc_html__( 'This safeguard is enforced in cookieless mode.', 'always-analytics' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders the raw data retention selector.
	 *
	 * @return void
	 */
	public function render_retention() {
		$options = get_option( 'always_analytics_options', array() );
		$days    = isset( $options['retention_days'] ) ? absint( $options['retention_days'] ) : 90;
		?>
		<select name="always_analytics_options[retention_days]">
			<?php foreach ( array( 30, 90, 180, 365, 395 ) as $value ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $days, $value ); ?>>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: retention period in days. */
							_n( '%d day', '%d days', $value, 'always-analytics' ),
							$value
						)
					);
					?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Renders trusted proxy choices.
	 *
	 * @return void
	 */
	public function render_trusted_proxy_mode() {
		$options = get_option( 'always_analytics_options', array() );
		$mode    = isset( $options['trusted_proxy_mode'] ) ? $options['trusted_proxy_mode'] : 'none';
		?>
		<fieldset>
			<label><input type="radio" name="always_analytics_options[trusted_proxy_mode]" value="none" <?php checked( $mode, 'none' ); ?>> <?php echo esc_html__( 'Do not trust proxy headers', 'always-analytics' ); ?></label><br>
			<label><input type="radio" name="always_analytics_options[trusted_proxy_mode]" value="custom" <?php checked( $mode, 'custom' ); ?>> <?php echo esc_html__( 'Trust headers only from listed proxy addresses', 'always-analytics' ); ?></label>
		</fieldset>
		<?php
	}

	/**
	 * Renders role exclusion choices.
	 *
	 * @return void
	 */
	public function render_excluded_roles() {
		$options        = get_option( 'always_analytics_options', array() );
		$excluded_roles = isset( $options['excluded_roles'] ) ? (array) $options['excluded_roles'] : array();
		?>
		<fieldset>
			<?php foreach ( wp_roles()->get_names() as $role_key => $role_name ) : ?>
				<label>
					<input type="checkbox" name="always_analytics_options[excluded_roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $excluded_roles, true ) ); ?>>
					<?php echo esc_html( translate_user_role( $role_name ) ); ?>
				</label><br>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * Renders information about the built-in bot filter.
	 *
	 * @return void
	 */
	public function render_bot_filter() {
		echo '<p>' . esc_html__( 'The local filter validates the target URL, browser challenge, request headers, automation signals, and recent behavior. No external blocklist is contacted.', 'always-analytics' ) . '</p>';
	}

	/**
	 * Renders export format choices.
	 *
	 * @return void
	 */
	public function render_export_format() {
		$options = get_option( 'always_analytics_options', array() );
		$format  = isset( $options['export_format'] ) ? $options['export_format'] : 'csv';
		?>
		<select name="always_analytics_options[export_format]">
			<option value="csv" <?php selected( $format, 'csv' ); ?>>CSV</option>
			<option value="json" <?php selected( $format, 'json' ); ?>>JSON</option>
		</select>
		<?php
	}

	/**
	 * Renders notice color controls.
	 *
	 * @return void
	 */
	public function render_consent_colors() {
		$options    = get_option( 'always_analytics_options', array() );
		$bg_color   = sanitize_hex_color( $options['consent_bg_color'] ?? '' );
		$text_color = sanitize_hex_color( $options['consent_text_color'] ?? '' );
		$btn_color  = sanitize_hex_color( $options['consent_btn_color'] ?? '' );
		$colors     = array(
			'consent_bg_color'   => $bg_color ? $bg_color : '#0f172a',
			'consent_text_color' => $text_color ? $text_color : '#f8fafc',
			'consent_btn_color'  => $btn_color ? $btn_color : '#6366f1',
		);
		$labels     = array(
			'consent_bg_color'   => __( 'Background', 'always-analytics' ),
			'consent_text_color' => __( 'Text', 'always-analytics' ),
			'consent_btn_color'  => __( 'Primary button', 'always-analytics' ),
		);
		?>
		<div class="always-analytics-color-fields">
			<?php foreach ( $colors as $key => $value ) : ?>
				<label>
					<?php echo esc_html( $labels[ $key ] ); ?><br>
					<input type="color" name="always_analytics_options[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>">
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Sanitizes a line-delimited IP/CIDR list.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	private function sanitize_ip_list( $value ) {
		$lines = preg_split( '/\R/', sanitize_textarea_field( (string) $value ) );
		$valid = array();

		foreach ( (array) $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			$parts   = explode( '/', $line, 2 );
			$address = $parts[0];
			if ( ! filter_var( $address, FILTER_VALIDATE_IP ) ) {
				continue;
			}

			if ( 2 === count( $parts ) ) {
				if ( '' === $parts[1] || ! ctype_digit( $parts[1] ) ) {
					continue;
				}

				$prefix     = (int) $parts[1];
				$max_prefix = filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ? 32 : 128;
				if ( $prefix < 0 || $prefix > $max_prefix ) {
					continue;
				}

				$line = $address . '/' . $prefix;
			} else {
				$line = $address;
			}

			$valid[] = $line;
		}

		return implode( "\n", array_unique( $valid ) );
	}
}
