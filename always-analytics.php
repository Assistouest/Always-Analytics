<?php
/**
 * Plugin Name:       Always Analytics
 * Plugin URI:        https://assistouest.fr/always-analytics-wordpress/
 * Description:       Self-hosted, privacy-focused audience analytics for WordPress.
 * Version:           3.6.3
 * Author:            Adrien Piron
 * Author URI:        https://assistouest.fr/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       always-analytics
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 5.8
 *
 * Third-party libraries are documented in THIRD-PARTY-NOTICES.txt.
 * All runtime assets are bundled locally with the plugin.
 *
 * @package Always_Analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ALWAYS_ANALYTICS_VERSION', '3.6.3' );
define( 'ALWAYS_ANALYTICS_PLUGIN_FILE', __FILE__ );
define( 'ALWAYS_ANALYTICS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALWAYS_ANALYTICS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ALWAYS_ANALYTICS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load plugin classes from the plugin's internal directories.
 *
 * @param string $class_name Fully qualified class name.
 * @return void
 */
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'Always_Analytics\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( $prefix ) );
		$parts          = explode( '\\', $relative_class );
		$class_file     = 'class-' . strtolower( str_replace( '_', '-', array_pop( $parts ) ) ) . '.php';
		$directories    = array(
			ALWAYS_ANALYTICS_PLUGIN_DIR . 'includes/',
			ALWAYS_ANALYTICS_PLUGIN_DIR . 'admin/',
			ALWAYS_ANALYTICS_PLUGIN_DIR . 'api/',
			ALWAYS_ANALYTICS_PLUGIN_DIR . 'public/',
		);

		foreach ( $directories as $directory ) {
			$file = $directory . $class_file;

			if ( is_readable( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		require_once ALWAYS_ANALYTICS_PLUGIN_DIR . 'includes/class-always-analytics-activator.php';
		Always_Analytics\Always_Analytics_Activator::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		require_once ALWAYS_ANALYTICS_PLUGIN_DIR . 'includes/class-always-analytics-deactivator.php';
		Always_Analytics\Always_Analytics_Deactivator::deactivate();
	}
);

/**
 * Main plugin controller.
 */
final class Always_Analytics {

	/**
	 * Singleton instance.
	 *
	 * @var Always_Analytics|null
	 */
	private static $instance = null;

	/**
	 * Return the singleton instance.
	 *
	 * @return Always_Analytics
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->load_textdomain();
		$this->register_hooks();
	}

	/**
	 * Load required class files.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		require_once ALWAYS_ANALYTICS_PLUGIN_DIR . 'includes/class-always-analytics-loader.php';
		require_once ALWAYS_ANALYTICS_PLUGIN_DIR . 'includes/class-always-analytics-tracker.php';
		require_once ALWAYS_ANALYTICS_PLUGIN_DIR . 'includes/class-always-analytics-session.php';
		require_once ALWAYS_ANALYTICS_PLUGIN_DIR . 'includes/class-always-analytics-bot-filter.php';
		require_once ALWAYS_ANALYTICS_PLUGIN_DIR . 'includes/class-always-analytics-privacy.php';
		require_once ALWAYS_ANALYTICS_PLUGIN_DIR . 'includes/class-always-analytics-cache.php';
		require_once ALWAYS_ANALYTICS_PLUGIN_DIR . 'includes/class-always-analytics-export.php';
		require_once ALWAYS_ANALYTICS_PLUGIN_DIR . 'includes/class-always-analytics-consent.php';
		require_once ALWAYS_ANALYTICS_PLUGIN_DIR . 'api/class-always-analytics-rest.php';
		require_once ALWAYS_ANALYTICS_PLUGIN_DIR . 'public/class-always-analytics-shortcodes.php';

		if ( is_admin() ) {
			require_once ALWAYS_ANALYTICS_PLUGIN_DIR . 'admin/class-always-analytics-admin.php';
			require_once ALWAYS_ANALYTICS_PLUGIN_DIR . 'admin/class-always-analytics-dashboard.php';
			require_once ALWAYS_ANALYTICS_PLUGIN_DIR . 'admin/class-always-analytics-settings.php';
		}
	}

	/**
	 * Load bundled translations. WordPress.org language packs remain supported.
	 *
	 * @return void
	 */
	private function load_textdomain() {
		load_plugin_textdomain(
			'always-analytics',
			false,
			dirname( ALWAYS_ANALYTICS_PLUGIN_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracker' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_defer_attribute' ), 10, 3 );
		add_action( 'rest_api_init', array( 'Always_Analytics\\Always_Analytics_Rest', 'register_routes' ) );
		add_action( 'init', array( 'Always_Analytics\\Always_Analytics_Shortcodes', 'register' ) );

		$consent = new Always_Analytics\Always_Analytics_Consent();
		add_action( 'wp_enqueue_scripts', array( $consent, 'maybe_enqueue_assets' ), 20 );
		add_action( 'wp_footer', array( $consent, 'render_banner' ) );

		add_action( 'always_analytics_daily_aggregate', array( $this, 'run_daily_aggregate' ) );
		add_action( 'always_analytics_daily_purge', array( $this, 'run_daily_purge' ) );
		add_action( 'always_analytics_expire_sessions', array( $this, 'run_expire_sessions' ) );

		if ( is_admin() ) {
			$admin = new Always_Analytics\Always_Analytics_Admin();
			add_action( 'admin_menu', array( $admin, 'add_menu_pages' ) );
			add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_assets' ) );
			add_action( 'wp_ajax_always_analytics_manual_purge', array( $admin, 'ajax_manual_purge' ) );
			add_action( 'wp_ajax_always_analytics_dismiss_support_notice', array( $admin, 'ajax_dismiss_support_notice' ) );
			add_filter( 'admin_footer_text', array( $admin, 'filter_admin_footer_text' ) );
			add_action( 'admin_notices', array( $admin, 'maybe_render_support_notice' ) );

			$dashboard = new Always_Analytics\Always_Analytics_Dashboard();
			add_action( 'wp_dashboard_setup', array( $dashboard, 'register_widgets' ) );

			$settings = new Always_Analytics\Always_Analytics_Settings();
			add_action( 'admin_init', array( $settings, 'register_settings' ) );
			add_action( 'admin_init', array( 'Always_Analytics\\Always_Analytics_Activator', 'maybe_update' ) );
		}
	}

	/**
	 * Enqueue the public tracker when collection is enabled for the current user.
	 *
	 * Cookieless measurement remains active before consent. Persistent cookie
	 * tracking is enabled only after the visitor grants consent.
	 *
	 * @return void
	 */
	public function enqueue_tracker() {
		$options = get_option( 'always_analytics_options', array() );

		if ( ! empty( $options['disable_tracking'] ) ) {
			return;
		}

		$opt_out = isset( $_COOKIE['always_analytics_opt_out'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['always_analytics_opt_out'] ) )
			: '';
		if ( '1' === $opt_out ) {
			return;
		}

		if ( is_user_logged_in() ) {
			$excluded_roles = isset( $options['excluded_roles'] ) ? (array) $options['excluded_roles'] : array( 'administrator' );
			$current_user   = wp_get_current_user();

			if ( array_intersect( $excluded_roles, $current_user->roles ) ) {
				return;
			}
		}

		wp_enqueue_script(
			'always-analytics-tracker',
			ALWAYS_ANALYTICS_PLUGIN_URL . 'public/js/always-analytics-tracker.js',
			array(),
			ALWAYS_ANALYTICS_VERSION,
			true
		);

		$tracking_mode     = isset( $options['tracking_mode'] ) ? $options['tracking_mode'] : 'cookieless';
		$consent_enabled   = ! empty( $options['consent_enabled'] );
		$cookieless_window = isset( $options['cookieless_window'] ) ? $options['cookieless_window'] : 'daily';
		$config            = array(
			'endpoint'          => esc_url_raw( rest_url( 'always-analytics/v1/hit' ) ),
			'trackingMode'      => $tracking_mode,
			'cookielessWindow'  => $cookieless_window,
			'consentGiven'      => ( 'cookie' === $tracking_mode && $consent_enabled ) ? 'pending' : 'not_required',
			'postId'            => get_queried_object_id() ? absint( get_queried_object_id() ) : 0,
			'siteUrl'           => esc_url_raw( home_url( '/' ) ),
			'challengeNonce'    => Always_Analytics\Always_Analytics_Bot_Filter::generate_challenge_nonce(),
			'preConsentEnabled' => ( 'cookie' === $tracking_mode && $consent_enabled ),
			'optOutCookie'      => 'always_analytics_opt_out',
		);

		wp_localize_script( 'always-analytics-tracker', 'alwaysAnalyticsConfig', $config );
	}

	/**
	 * Add the defer attribute to the public tracker script.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 * @param string $src    Script source URL.
	 * @return string
	 */
	public function add_defer_attribute( $tag, $handle, $src ) {
		unset( $src );

		if ( 'always-analytics-tracker' === $handle && false === strpos( $tag, ' defer' ) ) {
			return str_replace( ' src', ' defer src', $tag );
		}

		return $tag;
	}




	/**
	 * Roll up yesterday's raw hits into the daily aggregate table.
	 *
	 * @return void
	 */
	public function run_daily_aggregate() {
		global $wpdb;

		$yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Daily roll-up writes to plugin-owned analytics tables; repeating it through a WordPress object API is not possible or useful to cache.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}always_analytics_daily
					(stat_date, page_url, post_id, unique_visitors, page_views, sessions,
					 new_visitors, returning_vis, avg_duration, bounce_rate)
				 SELECT
					DATE(h.hit_at) AS stat_date,
					h.page_url,
					h.post_id,
					COUNT(DISTINCT h.visitor_hash) AS unique_visitors,
					COUNT(*) AS page_views,
					COUNT(DISTINCT h.session_id) AS sessions,
					SUM(h.is_new_visitor) AS new_visitors,
					SUM(CASE WHEN h.is_new_visitor = 0 THEN 1 ELSE 0 END) AS returning_vis,
					COALESCE(AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time
						WHEN s.duration > 0 THEN s.duration ELSE NULL END), 0) AS avg_duration,
					CASE WHEN COUNT(DISTINCT h.session_id) > 0
						THEN SUM(CASE WHEN s.is_bounce = 1 THEN 1 ELSE 0 END) / COUNT(DISTINCT h.session_id) * 100
						ELSE 0 END AS bounce_rate
				 FROM {$wpdb->prefix}always_analytics_hits h
				 LEFT JOIN {$wpdb->prefix}always_analytics_sessions s ON s.session_id = h.session_id
				 WHERE DATE(h.hit_at) = %s AND h.is_superseded = 0
				 GROUP BY DATE(h.hit_at), h.page_url, h.post_id
				 ON DUPLICATE KEY UPDATE
					unique_visitors = VALUES(unique_visitors),
					page_views      = VALUES(page_views),
					sessions        = VALUES(sessions),
					new_visitors    = VALUES(new_visitors),
					returning_vis   = VALUES(returning_vis),
					avg_duration    = VALUES(avg_duration),
					bounce_rate     = VALUES(bounce_rate)",
				$yesterday
			)
		);
		do_action( 'always_analytics_after_daily_aggregate', $yesterday );
	}

	/**
	 * Run scheduled data retention processing.
	 *
	 * @return void
	 */
	public function run_daily_purge() {
		$privacy = new Always_Analytics\Always_Analytics_Privacy();
		$privacy->purge_old_data();
	}




	/**
	 * Expire sessions that have gone stale without a closing heartbeat.
	 *
	 * @return void
	 */
	public function run_expire_sessions() {
		Always_Analytics\Always_Analytics_Session::expire_stale_sessions();
	}
}

add_action(
	'plugins_loaded',
	static function () {
		Always_Analytics::instance();
	}
);
