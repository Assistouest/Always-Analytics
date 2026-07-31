<?php
/**
 * Admin page controller.
 *
 * @package Always_Analytics
 */

namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page controller.
 */
class Always_Analytics_Admin {

	/**
	 * Register plugin admin pages.
	 *
	 * @return void
	 */
	public function add_menu_pages() {
		add_menu_page(
			esc_html__( 'Always Analytics', 'always-analytics' ),
			esc_html__( 'Always Analytics', 'always-analytics' ),
			'manage_options',
			'always-analytics',
			array( $this, 'render_dashboard_page' ),
			ALWAYS_ANALYTICS_PLUGIN_URL . 'always-analytics.svg',
			30
		);

		add_submenu_page(
			'always-analytics',
			esc_html__( 'Dashboard', 'always-analytics' ),
			esc_html__( 'Dashboard', 'always-analytics' ),
			'manage_options',
			'always-analytics',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'always-analytics',
			esc_html__( 'Engagement', 'always-analytics' ),
			esc_html__( 'Engagement', 'always-analytics' ),
			'manage_options',
			'always-analytics-engagement',
			array( $this, 'render_engagement_page' )
		);

		add_submenu_page(
			'always-analytics',
			esc_html__( 'Settings', 'always-analytics' ),
			esc_html__( 'Settings', 'always-analytics' ),
			'manage_options',
			'always-analytics-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			null,
			esc_html__( 'Always Analytics — Visitor details', 'always-analytics' ),
			esc_html__( 'Visitor details', 'always-analytics' ),
			'manage_options',
			'always-analytics-visitor',
			array( $this, 'render_visitor_page' )
		);

		add_submenu_page(
			null,
			esc_html__( 'Top Pages', 'always-analytics' ),
			esc_html__( 'Top Pages', 'always-analytics' ),
			'manage_options',
			'always-analytics-top-pages',
			array( $this, 'render_top_pages_page' )
		);
	}

	/**
	 * Return a cache-busting asset version.
	 *
	 * @param string $relative_path Path relative to the plugin root.
	 * @return int|string
	 */
	private function asset_version( $relative_path ) {
		$file  = ALWAYS_ANALYTICS_PLUGIN_DIR . ltrim( $relative_path, '/' );
		$stamp = is_readable( $file ) ? (string) filemtime( $file ) : '0';

		return ALWAYS_ANALYTICS_VERSION . '-' . $stamp;
	}

	/**
	 * Enqueue assets only on plugin screens and the WordPress dashboard.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		$is_plugin_screen   = false !== strpos( $hook, 'always-analytics' );
		$is_settings_screen = 'always-analytics_page_always-analytics-settings' === $hook;

		if ( ! $is_plugin_screen && 'index.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'always-analytics-admin',
			ALWAYS_ANALYTICS_PLUGIN_URL . 'admin/css/always-analytics-admin.css',
			array(),
			$this->asset_version( 'admin/css/always-analytics-admin.css' )
		);

		wp_add_inline_style(
			'always-analytics-admin',
			'#toplevel_page_always-analytics .wp-menu-image img{width:20px!important;height:20px!important;padding:7px 0!important;object-fit:contain}'
		);

		if ( $is_settings_screen ) {
			wp_enqueue_style(
				'always-analytics-settings',
				ALWAYS_ANALYTICS_PLUGIN_URL . 'admin/css/always-analytics-settings.css',
				array( 'always-analytics-admin' ),
				$this->asset_version( 'admin/css/always-analytics-settings.css' )
			);

			wp_enqueue_script(
				'always-analytics-settings',
				ALWAYS_ANALYTICS_PLUGIN_URL . 'admin/js/always-analytics-settings.js',
				array(),
				$this->asset_version( 'admin/js/always-analytics-settings.js' ),
				true
			);
		}

		if ( 'index.php' === $hook ) {
			return;
		}

		if ( 'toplevel_page_always-analytics' === $hook ) {
			$this->enqueue_dashboard_assets();
			return;
		}

		if ( 'always-analytics_page_always-analytics-engagement' === $hook ) {
			$this->enqueue_engagement_assets();
			return;
		}

		if (
			'admin_page_always-analytics-top-pages' === $hook ||
			$is_settings_screen
		) {
			$this->enqueue_utility_assets();
		}
	}

	/**
	 * Enqueue the main analytics dashboard assets.
	 *
	 * @return void
	 */
	private function enqueue_dashboard_assets() {
		wp_enqueue_script(
			'always-analytics-chartjs',
			ALWAYS_ANALYTICS_PLUGIN_URL . 'admin/js/chart.js',
			array(),
			'4.4.7',
			true
		);

		wp_enqueue_script(
			'always-analytics-charts',
			ALWAYS_ANALYTICS_PLUGIN_URL . 'admin/js/always-analytics-charts.js',
			array( 'always-analytics-chartjs' ),
			$this->asset_version( 'admin/js/always-analytics-charts.js' ),
			true
		);

		wp_enqueue_script(
			'always-analytics-admin',
			ALWAYS_ANALYTICS_PLUGIN_URL . 'admin/js/always-analytics-admin.js',
			array( 'always-analytics-charts' ),
			$this->asset_version( 'admin/js/always-analytics-admin.js' ),
			true
		);

		$referrer_sources_file = ALWAYS_ANALYTICS_PLUGIN_DIR . 'data/referrer-sources.php';
		$referrer_sources      = is_readable( $referrer_sources_file ) ? include $referrer_sources_file : array();
		$options               = get_option( 'always_analytics_options', array() );

		wp_localize_script(
			'always-analytics-admin',
			'alwaysAnalyticsAdmin',
			array(
				'restBase'         => esc_url_raw( rest_url( 'always-analytics/v1/' ) ),
				'externalFavicons' => ! empty( $options['external_favicons'] ),
				'faviconService'   => 'https://www.google.com/s2/favicons',
				'faviconParameter' => 'domain',
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'exportNonce'      => wp_create_nonce( 'always_analytics_export' ),
				'purgeNonce'       => wp_create_nonce( 'always_analytics_manual_purge' ),
				'ajaxUrl'          => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'locale'           => str_replace( '_', '-', get_user_locale() ),
				'referrerSources'  => $referrer_sources,
				'i18n'             => array(
					'visitors'               => esc_html__( 'Visitors', 'always-analytics' ),
					'pageViews'              => esc_html__( 'Page views', 'always-analytics' ),
					'sessions'               => esc_html__( 'Sessions', 'always-analytics' ),
					'noData'                 => esc_html__( 'No data is available for this period.', 'always-analytics' ),
					'loading'                => esc_html__( 'Loading…', 'always-analytics' ),
					'export'                 => esc_html__( 'Export', 'always-analytics' ),
					'purgeConfirm'           => esc_html__( 'This will anonymize records older than the retention period. Continue?', 'always-analytics' ),
					'purgeSuccess'           => esc_html__( 'Anonymization completed successfully.', 'always-analytics' ),
					'purgeError'             => esc_html__( 'Anonymization failed.', 'always-analytics' ),
					'now'                    => esc_html__( 'Now', 'always-analytics' ),
					'direct'                 => esc_html__( 'Direct', 'always-analytics' ),
					'noSource'               => esc_html__( 'No source is available in this category.', 'always-analytics' ),
					'noDataForFilter'        => esc_html__( 'No data is available for this filter.', 'always-analytics' ),
					'runAnonymization'       => esc_html__( 'Run anonymization…', 'always-analytics' ),
					'noEvents'               => esc_html__( 'No events.', 'always-analytics' ),
					'edit'                   => esc_html__( 'Edit', 'always-analytics' ),
					'delete'                 => esc_html__( 'Delete', 'always-analytics' ),
					'dateLabelRequired'      => esc_html__( 'The date and label are required.', 'always-analytics' ),
					'saving'                 => esc_html__( 'Saving…', 'always-analytics' ),
					'eventExists'            => esc_html__( 'An event already exists for this date.', 'always-analytics' ),
					'serverError'            => esc_html__( 'Server error.', 'always-analytics' ),
					'error'                  => esc_html__( 'Error.', 'always-analytics' ),
					'save'                   => esc_html__( 'Save', 'always-analytics' ),
					'deleteEventConfirm'     => esc_html__( 'Delete this event?', 'always-analytics' ),
					'eventDeleteFailed'      => esc_html__( 'The event could not be deleted.', 'always-analytics' ),
					'deletionFailed'         => esc_html__( 'Deletion failed.', 'always-analytics' ),
					'creationFailed'         => esc_html__( 'Creation failed.', 'always-analytics' ),
					/* translators: %s: total number of hits. */
					'totalHits'              => esc_html__( '%s hits total', 'always-analytics' ),
					'preConsentExcludedNote' => esc_html__( 'Pre-consent hits marked as superseded after acceptance are excluded from the primary count.', 'always-analytics' ),
					/* translators: %s: shortened visitor identifier. */
					'visitorLabel'           => esc_html__( 'Visitor %s', 'always-analytics' ),
					/* translators: %s: number of visits. */
					'visitCount'             => esc_html__( '%s visit', 'always-analytics' ),
					/* translators: %s: number of visits. */
					'visitsCount'            => esc_html__( '%s visits', 'always-analytics' ),
					/* translators: %s: number of pages. */
					'pageCount'              => esc_html__( '%s page', 'always-analytics' ),
					/* translators: %s: number of pages. */
					'pagesCount'             => esc_html__( '%s pages', 'always-analytics' ),
					/* translators: %s: number of minutes. */
					'minutesAgo'             => esc_html__( '%s min ago', 'always-analytics' ),
					/* translators: %s: number of hours. */
					'hoursAgo'               => esc_html__( '%s h ago', 'always-analytics' ),
					/* translators: %s: number of hits. */
					'hitCount'               => esc_html__( '%s hits', 'always-analytics' ),
					'networkError'           => esc_html__( 'Network error.', 'always-analytics' ),
					'preConsentInfo'         => esc_html__( 'The consent notice is active. Merged pre-consent hits are excluded from the primary count.', 'always-analytics' ),
					'legacyNoScriptInfo'     => esc_html__( 'Historical data includes records created by the legacy no-JavaScript collection method.', 'always-analytics' ),
					'cookieFallbackInfo'     => esc_html__( 'Some visitors block cookies, so the tracker automatically falls back to cookieless hits.', 'always-analytics' ),
					'cookielessSourceInfo'   => esc_html__( 'Standard cookieless mode is active, and the JavaScript tracker is the only hit source.', 'always-analytics' ),
					'browsers'               => esc_html__( 'Browsers', 'always-analytics' ),
					'operatingSystems'       => esc_html__( 'Operating systems', 'always-analytics' ),
					/* translators: %s: number of sessions. */
					'sessionCount'           => esc_html__( '%s session', 'always-analytics' ),
					/* translators: %s: number of sessions. */
					'sessionsCount'          => esc_html__( '%s sessions', 'always-analytics' ),
					/* translators: %s: number of unique clicks. */
					'uniqueCount'            => esc_html__( '%s unique', 'always-analytics' ),
				),
			)
		);
	}

	/**
	 * Enqueue engagement report assets.
	 *
	 * @return void
	 */
	private function enqueue_engagement_assets() {
		wp_enqueue_script(
			'always-analytics-chartjs',
			ALWAYS_ANALYTICS_PLUGIN_URL . 'admin/js/chart.js',
			array(),
			'4.4.7',
			true
		);

		wp_enqueue_script(
			'always-analytics-engagement',
			ALWAYS_ANALYTICS_PLUGIN_URL . 'admin/js/always-analytics-engagement.js',
			array( 'always-analytics-chartjs' ),
			$this->asset_version( 'admin/js/always-analytics-engagement.js' ),
			true
		);

		wp_localize_script(
			'always-analytics-engagement',
			'alwaysAnalyticsEngagement',
			array(
				'restBase' => esc_url_raw( rest_url( 'always-analytics/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'adminUrl' => esc_url_raw( admin_url() ),
				'locale'   => str_replace( '_', '-', get_user_locale() ),
				'i18n'     => array(
					'engagedSessions'     => esc_html__( 'Engaged sessions', 'always-analytics' ),
					'averageDuration'     => esc_html__( 'Average duration (s)', 'always-analytics' ),
					'averageScroll'       => esc_html__( 'Average scroll (%)', 'always-analytics' ),
					'noData'              => esc_html__( 'No data is available for this period.', 'always-analytics' ),
					'notMeasured'         => esc_html__( 'Not measured', 'always-analytics' ),
					/* translators: %s: number of page views. */
					'pageViewsCount'      => esc_html__( '%s page views', 'always-analytics' ),
					'highestScrollNote'   => wp_kses_post( __( 'Each page view is counted <strong>only once</strong> in the highest scroll range reached.', 'always-analytics' ) ),
					/* translators: %s: average scroll percentage. */
					'averageScrollTitle'  => esc_html__( 'Average scroll: %s%%', 'always-analytics' ),
					'editInWordPress'     => esc_html__( 'Edit in WordPress', 'always-analytics' ),
					/* translators: %s: number of sessions remaining. */
					'remaining'           => esc_html__( '(%s remaining)', 'always-analytics' ),
					/* translators: 1: number of views, 2: number of sessions. */
					'viewsSessions'       => esc_html__( '%1$s views · %2$s sessions', 'always-analytics' ),
					/* translators: 1: number of sessions, 2: reliability percentage. */
					'sessionsReliability' => esc_html__( '%1$s sessions · reliability: %2$s%%', 'always-analytics' ),
					'noSessions'          => esc_html__( 'No sessions', 'always-analytics' ),
					/* translators: %s: number of sessions. */
					'sessionCount'        => esc_html__( '%s session', 'always-analytics' ),
					/* translators: %s: number of sessions. */
					'sessionsCount'       => esc_html__( '%s sessions', 'always-analytics' ),
					'bouncer'             => esc_html__( 'Bouncer', 'always-analytics' ),
					'explorer'            => esc_html__( 'Explorer', 'always-analytics' ),
					'deepReader'          => esc_html__( 'Deep reader', 'always-analytics' ),
					'highBounceRate'      => esc_html__( 'High bounce rate', 'always-analytics' ),
					'bouncerAction'       => esc_html__( 'The content may not engage this segment, or the traffic targeting may be mismatched.', 'always-analytics' ),
					'partialReading'      => esc_html__( 'Partial content reading', 'always-analytics' ),
					'explorerAction'      => esc_html__( 'The visitor browses quickly without deep engagement.', 'always-analytics' ),
					'specificAnswer'      => esc_html__( 'Search for a specific answer', 'always-analytics' ),
					'scannerAction'       => esc_html__( 'Visitors may be comparing options or looking for a specific answer efficiently.', 'always-analytics' ),
					'completeReading'     => esc_html__( 'Complete reading and strong engagement', 'always-analytics' ),
					'deepReaderAction'    => esc_html__( 'This segment engages deeply with the content, which may indicate a strong match with expectations.', 'always-analytics' ),
				),
			)
		);
	}

	/**
	 * Enqueue scripts shared by settings and detail reports.
	 *
	 * @return void
	 */
	private function enqueue_utility_assets() {
		wp_enqueue_script(
			'always-analytics-pages',
			ALWAYS_ANALYTICS_PLUGIN_URL . 'admin/js/always-analytics-pages.js',
			array(),
			$this->asset_version( 'admin/js/always-analytics-pages.js' ),
			true
		);

		wp_localize_script(
			'always-analytics-pages',
			'alwaysAnalyticsPages',
			array(
				'topPagesUrl' => esc_url_raw( admin_url( 'admin.php?page=always-analytics-top-pages' ) ),
			)
		);
	}

	/**
	 * Render the main dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page() {
		$this->assert_manage_options();
		include ALWAYS_ANALYTICS_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$this->assert_manage_options();
		include ALWAYS_ANALYTICS_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Render the visitor details page.
	 *
	 * @return void
	 */
	public function render_visitor_page() {
		$this->assert_manage_options();
		include ALWAYS_ANALYTICS_PLUGIN_DIR . 'admin/views/visitor-detail.php';
	}

	/**
	 * Render the top pages page.
	 *
	 * @return void
	 */
	public function render_top_pages_page() {
		$this->assert_manage_options();
		include ALWAYS_ANALYTICS_PLUGIN_DIR . 'admin/views/top-pages.php';
	}

	/**
	 * Render the engagement page.
	 *
	 * @return void
	 */
	public function render_engagement_page() {
		$this->assert_manage_options();
		include ALWAYS_ANALYTICS_PLUGIN_DIR . 'admin/views/engagement.php';
	}

	/**
	 * Stop rendering when the current user lacks the required capability.
	 *
	 * @return void
	 */
	private function assert_manage_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'always-analytics' ) );
		}
	}

	/**
	 * Run the manual anonymization action.
	 *
	 * @return void
	 */
	public function ajax_manual_purge() {
		check_ajax_referer( 'always_analytics_manual_purge', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Permission denied.', 'always-analytics' ) ),
				403
			);
		}

		$privacy = new Always_Analytics_Privacy();
		$privacy->purge_old_data();

		wp_send_json_success(
			array( 'message' => esc_html__( 'Anonymization completed successfully.', 'always-analytics' ) )
		);
	}

	/**
	 * Replace the default admin footer text with a support note, on this plugin's own screens only.
	 *
	 * @param string $text Default footer text.
	 * @return string
	 */
	public function filter_admin_footer_text( $text ) {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'always-analytics' ) ) {
			return $text;
		}

		return sprintf(
			/* translators: %s: "Buy me a coffee" link. */
			esc_html__( 'Thank you for using Always Analytics! If it saves you time, %s to support development.', 'always-analytics' ),
			'<a href="https://buymeacoffee.com/assistouest" target="_blank" rel="noopener noreferrer">' . esc_html__( 'buy me a coffee', 'always-analytics' ) . ' &#9749;</a>'
		);
	}

	/**
	 * Renders a dismissible support notice on this plugin's own screens, once the site has
	 * had the plugin active long enough to see real value from it (not on a fresh install).
	 *
	 * @return void
	 */
	public function maybe_render_support_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'always-analytics' ) ) {
			return;
		}

		$dismissed_until = (int) get_user_meta( get_current_user_id(), 'always_analytics_support_notice_dismissed_until', true );
		if ( $dismissed_until > time() ) {
			return;
		}

		$activated_at = (int) get_option( 'always_analytics_activated_at' );
		if ( ! $activated_at ) {
			// Backfill for sites where the plugin was already active before this option existed
			// (activate() only runs on a fresh activation, not on every page load).
			$activated_at = time();
			update_option( 'always_analytics_activated_at', $activated_at );
		}
		if ( ( time() - $activated_at ) < 3 * DAY_IN_SECONDS ) {
			return;
		}

		global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cheap aggregate read of the plugin's own small daily-rollup table to gate a UI notice; not a hot path.
		$page_views = (int) $wpdb->get_var( "SELECT SUM(page_views) FROM {$wpdb->prefix}always_analytics_daily" );
		if ( $page_views < 20 ) {
			return;
		}

		$dismiss_nonce = wp_create_nonce( 'always_analytics_dismiss_support_notice' );
		?>
		<div class="notice notice-info is-dismissible always-analytics-support-notice" data-nonce="<?php echo esc_attr( $dismiss_nonce ); ?>">
			<p>
				<?php
				printf(
					/* translators: 1: number of tracked page views, 2: "buy me a coffee" link. */
					esc_html__( '%1$s tracked so far, with zero third-party trackers and everything staying on your own server. If Always Analytics is useful to you, %2$s helps keep it maintained.', 'always-analytics' ),
					'<strong>' . esc_html(
						sprintf(
							/* translators: %s: number of page views. */
							_n( '%s page view', '%s page views', $page_views, 'always-analytics' ),
							number_format_i18n( $page_views )
						)
					) . '</strong>',
					'<a href="https://buymeacoffee.com/assistouest" target="_blank" rel="noopener noreferrer">' . esc_html__( 'buying me a coffee', 'always-analytics' ) . ' &#9749;</a>'
				);
				?>
			</p>
		</div>
		<script>
		( function () {
			var notice = document.currentScript.previousElementSibling;
			if ( ! notice ) {
				return;
			}
			notice.addEventListener( 'click', function ( event ) {
				if ( ! event.target.closest( '.notice-dismiss' ) ) {
					return;
				}
				var body = new FormData();
				body.append( 'action', 'always_analytics_dismiss_support_notice' );
				body.append( 'nonce', notice.getAttribute( 'data-nonce' ) );
				fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } );
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * Persists a 60-day dismissal of the support notice for the current user.
	 *
	 * @return void
	 */
	public function ajax_dismiss_support_notice() {
		check_ajax_referer( 'always_analytics_dismiss_support_notice', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'always-analytics' ) ), 403 );
		}

		update_user_meta( get_current_user_id(), 'always_analytics_support_notice_dismissed_until', time() + 60 * DAY_IN_SECONDS );

		wp_send_json_success();
	}
}
