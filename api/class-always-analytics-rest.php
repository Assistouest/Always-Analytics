<?php
/**
 * REST API endpoints for hit collection and administrator analytics reports.
 *
 * @package Always_Analytics
 */

namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API for public collection and administrator reports.
 */
final class Always_Analytics_Rest {

	const NAMESPACE = 'always-analytics/v1';

	/**
	 * Resolves the client IP used for rate limiting.
	 *
	 * Proxy headers are trusted only when the direct connection address is in
	 * the administrator-provided trusted proxy list.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return string
	 */
	private static function get_rate_limit_ip( $request ) {
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '0.0.0.0';

		if ( ! filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
			return '0.0.0.0';
		}

		$options = get_option( 'always_analytics_options', array() );
		$mode    = isset( $options['trusted_proxy_mode'] ) ? sanitize_key( $options['trusted_proxy_mode'] ) : 'none';

		if ( 'custom' === $mode && ! empty( $options['trusted_proxies'] ) ) {
			$trusted = array_filter( array_map( 'trim', explode( "\n", $options['trusted_proxies'] ) ) );
			if ( Always_Analytics_Tracker::is_ip_in_range( $remote_addr, $trusted ) ) {
				$forwarded = $request->get_header( 'x-forwarded-for' );
				if ( $forwarded ) {
					$first = trim( explode( ',', $forwarded )[0] );
					if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
						return $first;
					}
				}
			}
		}

		return $remote_addr;
	}

	/**
	 * Sends no-cache headers for plugin REST responses.
	 *
	 * @return void
	 */
	private static function no_cache_headers() {
		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}

	/**
	 * Validates a browser-generated UUID identifier.
	 *
	 * @param mixed $value Identifier value.
	 * @return bool
	 */
	public static function is_valid_client_identifier( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value );
	}

	/**
	 * Applies a short rolling IP rate limit to all collector requests.
	 *
	 * @param string $ip Client IP address.
	 * @param int    $limit Maximum requests per minute.
	 * @return bool True when the request is allowed.
	 */
	private static function allow_collector_request( $ip, $limit = 240 ) {
		$key   = 'always_analytics_rl_all_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Adds no-cache headers before serving plugin REST responses.
	 *
	 * @param bool             $served  Whether the request was already served.
	 * @param WP_HTTP_Response $result  Response object.
	 * @param \WP_REST_Request $request REST request.
	 * @return bool
	 */
	public static function serve_no_cache_headers( $served, $result, $request ) {
		unset( $result );

		if ( 0 === strpos( $request->get_route(), '/always-analytics/' ) ) {
			self::no_cache_headers();
		}

		return $served;
	}

	/**
	 * Checks administrator access for reporting endpoints.
	 *
	 * @return bool
	 */
	public static function can_manage_analytics() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Declares that the public collector is intentionally public.
	 *
	 * Request validation, target validation, bot filtering, and rate limiting
	 * are performed by the endpoint callback.
	 *
	 * @return bool
	 */
	public static function can_submit_hit() {
		return true;
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve_no_cache_headers' ), 1, 3 );

		register_rest_route(
			self::NAMESPACE,
			'/hit',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_hit' ),
				'permission_callback' => array( __CLASS__, 'can_submit_hit' ),
				'args'                => self::get_hit_route_args(),
			)
		);

		$admin_routes = array(
			array( 'overview', 'get_overview' ),
			array( 'chart/visits', 'get_chart_visits' ),
			array( 'top-pages', 'get_top_pages' ),
			array( 'top-referrers', 'get_top_referrers' ),
			array( 'devices', 'get_devices' ),
			array( 'visitors', 'get_visitors' ),
			array( 'recent-visitors', 'get_recent_visitors' ),
			array( 'engagement', 'get_engagement' ),
			array( 'engagement/pages', 'get_engagement_pages' ),
			array( 'reader-profiles', 'get_reader_profiles' ),
			array( 'links/internal', 'get_internal_links' ),
			array( 'links/outbound', 'get_outbound_links' ),
			array( 'export', 'handle_export' ),
			array( 'hit-sources', 'get_hit_sources' ),
		);

		foreach ( $admin_routes as $route ) {
			register_rest_route(
				self::NAMESPACE,
				'/' . $route[0],
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, $route[1] ),
					'permission_callback' => array( __CLASS__, 'can_manage_analytics' ),
					'args'                => self::get_report_route_args(),
				)
			);
		}

		register_rest_route(
			self::NAMESPACE,
			'/campaigns',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_campaigns' ),
					'permission_callback' => array( __CLASS__, 'can_manage_analytics' ),
					'args'                => self::get_report_route_args(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_campaign' ),
					'permission_callback' => array( __CLASS__, 'can_manage_analytics' ),
					'args'                => self::get_campaign_route_args(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/campaigns/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_campaign' ),
				'permission_callback' => array( __CLASS__, 'can_manage_analytics' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => static function ( $value ) {
							return absint( $value ) > 0;
						},
					),
				),
			)
		);
	}

	/**
	 * Returns public collector argument definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_hit_route_args() {
		return array(
			'action'    => array(
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => static function ( $value ) {
					return in_array( $value, array( '', 'pre_consent', 'ping', 'scroll', 'link_click' ), true );
				},
			),
			'sessionId' => array(
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( __CLASS__, 'is_valid_client_identifier' ),
			),
			'url'       => array(
				'sanitize_callback' => 'esc_url_raw',
			),
		);
	}

	/**
	 * Returns common report argument definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_report_route_args() {
		return array(
			'from'      => array(
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( __CLASS__, 'is_valid_date' ),
			),
			'to'        => array(
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( __CLASS__, 'is_valid_date' ),
			),
			'post_type' => array( 'sanitize_callback' => 'sanitize_key' ),
			'device'    => array( 'sanitize_callback' => 'sanitize_key' ),
			'limit'     => array(
				'sanitize_callback' => static function ( $value ) {
					return min( 500, max( 1, absint( $value ) ) );
				},
			),
			'format'    => array(
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => static function ( $value ) {
					return in_array( $value, array( 'csv', 'json' ), true );
				},
			),
		);
	}

	/**
	 * Returns campaign argument definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_campaign_route_args() {
		return array(
			'event_date'  => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( __CLASS__, 'is_valid_date' ),
			),
			'label'       => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => static function ( $value ) {
					return '' !== trim( (string) $value ) && strlen( $value ) <= 255;
				},
			),
			'description' => array(
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'color'       => array(
				'sanitize_callback' => 'sanitize_hex_color',
				'validate_callback' => static function ( $value ) {
					return null !== sanitize_hex_color( $value );
				},
			),
		);
	}

	/**
	 * Validates an ISO calendar date.
	 *
	 * @param mixed $value Date value.
	 * @return bool
	 */
	public static function is_valid_date( $value ) {
		if ( null === $value || '' === $value ) {
			return true;
		}

		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $value );
		return false !== $date && $date->format( 'Y-m-d' ) === $value;
	}



	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting endpoints query plugin-owned analytics tables directly. Values are prepared, routes require manage_options, and live filtered data is intentionally not cached at the SQL-result level.

	/**
	 * Resolves normalized date-range and filter parameters shared by report endpoints.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return array<string, mixed>
	 */
	private static function get_date_params( $request ) {
		$today     = wp_date( 'Y-m-d' );
		$yesterday = wp_date( 'Y-m-d', strtotime( '-1 day' ) );
		$from      = (string) ( $request->get_param( 'from' ) ? $request->get_param( 'from' ) : wp_date( 'Y-m-d', strtotime( '-30 days' ) ) );
		$to        = (string) ( $request->get_param( 'to' ) ? $request->get_param( 'to' ) : $today );

		if ( ! self::is_valid_date( $from ) ) {
			$from = wp_date( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( ! self::is_valid_date( $to ) ) {
			$to = $today;
		}
		if ( $from > $to ) {
			$swap = $from;
			$from = $to;
			$to   = $swap;
		}

		$tz_offset_seconds = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

		return array(
			'from'         => $from,
			'to'           => $to,
			'from_utc'     => gmdate( 'Y-m-d H:i:s', strtotime( $from . ' 00:00:00' ) - $tz_offset_seconds ),
			'to_utc'       => gmdate( 'Y-m-d H:i:s', strtotime( $to . ' 23:59:59' ) - $tz_offset_seconds ),
			'is_today'     => $from === $today && $to === $today,
			'is_yesterday' => $from === $yesterday && $to === $yesterday,
			'post_type'    => sanitize_key( $request->get_param( 'post_type' ) ? $request->get_param( 'post_type' ) : '' ),
			'device'       => sanitize_key( $request->get_param( 'device' ) ? $request->get_param( 'device' ) : '' ),
		);
	}

	/**
	 * Builds a parameterized WHERE clause from resolved date/filter params.
	 *
	 * @param array<string, mixed> $params Resolved params from get_date_params().
	 * @param array<int, mixed>    $args   Reference; placeholder values are appended here.
	 * @return string
	 */
	private static function build_where( $params, &$args ) {
		$where  = array( 'hit_at >= %s', 'hit_at <= %s' );
		$args[] = $params['from_utc'];
		$args[] = $params['to_utc'];
		if ( ! empty( $params['post_type'] ) ) {
			$where[] = 'post_type = %s';
			$args[]  = $params['post_type']; }
		if ( ! empty( $params['device'] ) ) {
			$where[] = 'device_type = %s';
			$args[]  = $params['device']; }
		return implode( ' AND ', $where );
	}



	/**
	 * Handles the public hit-collection endpoint (page views, pings, scroll, link clicks).
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function handle_hit( $request ) {
		$raw_body = (string) $request->get_body();
		if ( strlen( $raw_body ) > 32768 ) {
			return new \WP_REST_Response( null, 413 );
		}

		$origin  = $request->get_header( 'origin' );
		$referer = $request->get_header( 'referer' );

		if ( $origin && ! Always_Analytics_Tracker::is_internal_host( wp_parse_url( $origin, PHP_URL_HOST ) ) ) {
			return new \WP_REST_Response( null, 403 );
		}
		if ( ! $origin && $referer && ! Always_Analytics_Tracker::is_internal_host( wp_parse_url( $referer, PHP_URL_HOST ) ) ) {
			return new \WP_REST_Response( null, 403 );
		}

		$data = $request->get_json_params();
		if ( empty( $data ) ) {
			$data = $request->get_body_params();
		}
		if ( empty( $data ) ) {
			return new \WP_REST_Response( null, 204 );
		}

		$session_id = isset( $data['sessionId'] ) ? sanitize_text_field( $data['sessionId'] ) : '';
		if ( ! self::is_valid_client_identifier( $session_id ) ) {
			return new \WP_REST_Response( null, 204 );
		}

		$action    = isset( $data['action'] ) ? sanitize_key( $data['action'] ) : '';
		$client_ip = self::get_rate_limit_ip( $request );
		if ( ! self::allow_collector_request( $client_ip ) ) {
			return new \WP_REST_Response( null, 429 );
		}

		$ua_for_limit = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$burst_key    = 'always_analytics_rl_fp_' . md5( $client_ip . '|' . $ua_for_limit );
		$burst_count  = (int) get_transient( $burst_key );
		if ( $burst_count >= 90 ) {
			return new \WP_REST_Response( null, 429 );
		}
		set_transient( $burst_key, $burst_count + 1, 10 * MINUTE_IN_SECONDS );

		if ( ! in_array( $action, array( 'ping', 'scroll', 'link_click' ), true ) ) {

			if ( $session_id ) {
				$rate_key = 'always_analytics_rl_' . md5( $session_id );
				if ( false !== get_transient( $rate_key ) ) {
					return new \WP_REST_Response( null, 429 );
				}
				set_transient( $rate_key, 1, 2 );
			}

			$ip_key  = 'always_analytics_rl_ip_' . md5( $client_ip );
			$ip_hits = (int) get_transient( $ip_key );
			if ( $ip_hits >= 60 ) {
				return new \WP_REST_Response( null, 429 );
			}

			if ( 0 === $ip_hits ) {
				set_transient( $ip_key, 1, 60 );
			} else {
				set_transient( $ip_key, $ip_hits + 1, 60 );
			}
		}

		if ( in_array( $action, array( 'ping', 'scroll', 'link_click' ), true ) ) {
			if ( ! Always_Analytics_Session::exists( $session_id ) ) {
				return new \WP_REST_Response( null, 204 );
			}

			if ( Always_Analytics_Tracker::is_excluded_request() ) {
				return new \WP_REST_Response( null, 204 );
			}
			$event_ua = isset( $_SERVER['HTTP_USER_AGENT'] )
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
				: '';

			$discard = Always_Analytics_Bot_Filter::should_discard(
				array(
					'ua_string'      => $event_ua,
					'ip'             => Always_Analytics_Tracker::get_client_ip(),
					'hit_source'     => 'js',
					'sec_fetch_site' => $request->get_header( 'sec-fetch-site' ),
					'sec_fetch_mode' => $request->get_header( 'sec-fetch-mode' ),
					'origin_present' => (bool) ( $request->get_header( 'origin' ) || $request->get_header( 'referer' ) ),
				)
			);
			if ( $discard ) {
				return new \WP_REST_Response( null, 204 );
			}
		}

		if ( 'ping' === $action ) {
			if ( $session_id ) {
				$scroll_depth    = isset( $data['scrollDepth'] ) ? absint( $data['scrollDepth'] ) : null;
				$client_duration = isset( $data['clientDuration'] ) ? absint( $data['clientDuration'] ) : null;
				$engagement_time = isset( $data['engagementTime'] ) ? absint( $data['engagementTime'] ) : null;
				Always_Analytics_Session::ping_session( $session_id, $scroll_depth, $client_duration, $engagement_time );
			}
			return new \WP_REST_Response( null, 204 );
		}

		if ( 'scroll' === $action ) {
			if ( $session_id ) {
				Always_Analytics_Tracker::handle_scroll( $data );
			}
			return new \WP_REST_Response( null, 204 );
		}

		if ( 'link_click' === $action ) {
			if ( $session_id ) {
				self::record_link_click( $data, $session_id );
			}
			return new \WP_REST_Response( null, 204 );
		}

		if ( 'pre_consent' === $action ) {
			$data['hitSource'] = 'pre_consent';
			Always_Analytics_Tracker::track( $data );
			return new \WP_REST_Response( null, 204 );
		}

		$pre_consent_sid = isset( $data['preConsentSessionId'] )
			? sanitize_text_field( $data['preConsentSessionId'] )
			: '';
		if ( ! self::is_valid_client_identifier( $pre_consent_sid ) ) {
			$pre_consent_sid = '';
		}

		$data['hitSource'] = 'js';

		$hit_id = Always_Analytics_Tracker::track( $data );

		if ( $hit_id && $pre_consent_sid && ! empty( $data['visitorId'] ) ) {
			$new_visitor_hash = hash( 'sha256', sanitize_text_field( $data['visitorId'] ) );
			Always_Analytics_Tracker::upgrade_pre_consent( $pre_consent_sid, $new_visitor_hash );
		}

		return new \WP_REST_Response( null, 204 );
	}



	/**
	 * GET /overview - Top-level KPI summary for the selected date range.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_overview( $request ) {
		global $wpdb;
		self::no_cache_headers();

		$params = self::get_date_params( $request );
		$table  = $wpdb->prefix . 'always_analytics_hits';
		$t_sess = $wpdb->prefix . 'always_analytics_sessions';

		$args  = array();
		$where = self::build_where( $params, $args );

		// Interpolated fragments ({$where}) are built exclusively from fixed internal clauses (see
		// build_where()); every runtime value is bound through a %s/%d placeholder. phpcs:disable is used
		// instead of phpcs:ignore because the flagged tokens sit on later lines of these multi-line query
		// strings, past the reach of a same-line ignore comment; the spread-args (...$args) calls also trip
		// the placeholder-count sniff, which cannot see through a variadic spread.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$main = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
                COUNT(DISTINCT visitor_hash) as unique_visitors,
                COUNT(*) as page_views,
                COUNT(DISTINCT session_id) as sessions,
                SUM(is_new_visitor) as new_visitors
             FROM {$wpdb->prefix}always_analytics_hits
             WHERE {$where} AND is_superseded = 0",
				...$args
			)
		);

		$sess_args = array( $params['from_utc'], $params['to_utc'] );

		$avg_duration = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time WHEN s.duration > 0 THEN s.duration ELSE NULL END)
             FROM {$wpdb->prefix}always_analytics_sessions s
             WHERE s.session_id IN (
                 SELECT DISTINCT session_id FROM {$wpdb->prefix}always_analytics_hits
                 WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
             ) AND (s.duration > 0 OR s.engagement_time > 0)",
				...$sess_args
			)
		);

		$engagement = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as total, SUM(CASE WHEN s.is_bounce = 0 THEN 1 ELSE 0 END) as engaged
             FROM {$wpdb->prefix}always_analytics_sessions s
             WHERE s.session_id IN (
                 SELECT DISTINCT session_id FROM {$wpdb->prefix}always_analytics_hits
                 WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
             ) AND (s.duration > 0 OR s.engagement_time > 0)",
				...$sess_args
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$engagement_rate = ( $engagement && $engagement->total > 0 )
			? round( ( $engagement->engaged / $engagement->total ) * 100, 1 )
			: 0;

		$days_diff = max( 1, ( strtotime( $params['to'] ) - strtotime( $params['from'] ) ) / DAY_IN_SECONDS );
		$prev_from = gmdate( 'Y-m-d', strtotime( $params['from'] . " -{$days_diff} days" ) );
		$prev_to   = gmdate( 'Y-m-d', strtotime( $params['from'] . ' -1 day' ) );
		$tz_off    = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

		$prev = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT visitor_hash) as unique_visitors, COUNT(*) as page_views
             FROM {$wpdb->prefix}always_analytics_hits WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0",
				gmdate( 'Y-m-d H:i:s', strtotime( $prev_from . ' 00:00:00' ) - $tz_off ),
				gmdate( 'Y-m-d H:i:s', strtotime( $prev_to . ' 23:59:59' ) - $tz_off )
			)
		);

		$change_visitors = 0;
		$change_views    = 0;
		if ( $prev && $prev->unique_visitors > 0 ) {
			$change_visitors = round( ( ( $main->unique_visitors - $prev->unique_visitors ) / $prev->unique_visitors ) * 100, 1 );
		}
		if ( $prev && $prev->page_views > 0 ) {
			$change_views = round( ( ( $main->page_views - $prev->page_views ) / $prev->page_views ) * 100, 1 );
		}

		return rest_ensure_response(
			array(
				'unique_visitors' => (int) $main->unique_visitors,
				'page_views'      => (int) $main->page_views,
				'sessions'        => (int) $main->sessions,
				'new_visitors'    => (int) $main->new_visitors,
				'avg_duration'    => round( (float) $avg_duration ),
				'engagement_rate' => $engagement_rate,
				'change_visitors' => $change_visitors,
				'change_views'    => $change_views,
			)
		);
	}



	/**
	 * GET /chart/visits - Visitors/page views/sessions time series (hourly or daily).
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_chart_visits( $request ) {
		global $wpdb;
		self::no_cache_headers();

		$params       = self::get_date_params( $request );
		$table        = $wpdb->prefix . 'always_analytics_hits';
		$from         = $params['from_utc'];
		$to           = $params['to_utc'];
		$is_today     = $params['is_today'];
		$is_yesterday = $params['is_yesterday'];

		if ( $params['from'] === $params['to'] ) {
			$tz_offset_seconds = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
			$sql               = "SELECT HOUR(hit_at) as hour_utc,
                            COUNT(DISTINCT visitor_hash) as visitors,
                            COUNT(*) as page_views,
                            COUNT(DISTINCT session_id) as sessions
                     FROM {$wpdb->prefix}always_analytics_hits WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0";
			$args              = array( $from, $to );
			if ( ! empty( $params['device'] ) ) {
				$sql   .= ' AND device_type = %s';
				$args[] = $params['device']; }
			$sql .= ' GROUP BY HOUR(hit_at) ORDER BY hour_utc ASC';

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query text is assembled only from fixed internal SQL clauses; every runtime value is supplied through a matching placeholder.
			$results = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

			$tz_hours = (int) round( $tz_offset_seconds / HOUR_IN_SECONDS );
			$indexed  = array();
			foreach ( $results as $row ) {
				$local_hour             = ( (int) $row->hour_utc + $tz_hours + 24 ) % 24;
				$indexed[ $local_hour ] = $row;
			}

			$now_local_hour = (int) wp_date( 'G' );
			$filled         = array();
			for ( $h = 0; $h <= 23; $h++ ) {
				$filled[] = array(
					'hour'       => $h,
					'visitors'   => isset( $indexed[ $h ] ) ? (int) $indexed[ $h ]->visitors : 0,
					'page_views' => isset( $indexed[ $h ] ) ? (int) $indexed[ $h ]->page_views : 0,
					'sessions'   => isset( $indexed[ $h ] ) ? (int) $indexed[ $h ]->sessions : 0,
					'future'     => ( $is_today && $h > $now_local_hour ),
				);
			}
			return rest_ensure_response( $filled );
		}

		$tz_offset_seconds = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		$tz_str            = sprintf( '%+03d:00', (int) round( $tz_offset_seconds / HOUR_IN_SECONDS ) );
		$sql               = "SELECT DATE(CONVERT_TZ(hit_at, '+00:00', %s)) as date,
                        COUNT(DISTINCT visitor_hash) as visitors,
                        COUNT(*) as page_views,
                        COUNT(DISTINCT session_id) as sessions
                 FROM {$wpdb->prefix}always_analytics_hits WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0";
		$args              = array( $tz_str, $from, $to );
		if ( ! empty( $params['device'] ) ) {
			$sql   .= ' AND device_type = %s';
			$args[] = $params['device']; }
		$sql   .= " GROUP BY DATE(CONVERT_TZ(hit_at, '+00:00', %s)) ORDER BY date ASC";
		$args[] = $tz_str;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query text is assembled only from fixed internal SQL clauses; every runtime value is supplied through a matching placeholder.
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

		$indexed = array();
		foreach ( $results as $row ) {
			$indexed[ $row->date ] = $row;
		}

		$filled  = array();
		$current = strtotime( $params['from'] );
		$end     = strtotime( $params['to'] );
		while ( $current <= $end ) {
			$date_str = gmdate( 'Y-m-d', $current );
			$filled[] = array(
				'date'       => $date_str,
				'visitors'   => isset( $indexed[ $date_str ] ) ? (int) $indexed[ $date_str ]->visitors : 0,
				'page_views' => isset( $indexed[ $date_str ] ) ? (int) $indexed[ $date_str ]->page_views : 0,
				'sessions'   => isset( $indexed[ $date_str ] ) ? (int) $indexed[ $date_str ]->sessions : 0,
			);
			$current += DAY_IN_SECONDS;
		}

		return rest_ensure_response( $filled );
	}



	/**
	 * GET /top-pages - Most-viewed pages for the selected date range.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_top_pages( $request ) {
		global $wpdb;
		self::no_cache_headers();

		$params = self::get_date_params( $request );
		$table  = $wpdb->prefix . 'always_analytics_hits';
		$limit  = absint( $request->get_param( 'limit' ) ? $request->get_param( 'limit' ) : 20 );

		$sql  = "SELECT page_url, page_title, post_id,
                    COUNT(*) as views,
                    COUNT(DISTINCT visitor_hash) as unique_visitors
                FROM {$wpdb->prefix}always_analytics_hits WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0";
		$args = array( $params['from_utc'], $params['to_utc'] );
		if ( ! empty( $params['device'] ) ) {
			$sql   .= ' AND device_type = %s';
			$args[] = $params['device']; }
		$sql   .= ' GROUP BY page_url, page_title, post_id ORDER BY views DESC LIMIT %d';
		$args[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query text is assembled only from fixed internal SQL clauses; every runtime value is supplied through a matching placeholder.
		return rest_ensure_response( $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) );
	}



	/**
	 * GET /top-referrers - Traffic sources grouped/categorized by referrer domain.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_top_referrers( $request ) {
		global $wpdb;
		self::no_cache_headers();

		$params = self::get_date_params( $request );
		$table  = $wpdb->prefix . 'always_analytics_hits';
		$limit  = min( 100, max( 1, absint( $request->get_param( 'limit' ) ? $request->get_param( 'limit' ) : 20 ) ) );

		$sources_file = ALWAYS_ANALYTICS_PLUGIN_DIR . 'data/referrer-sources.php';
		$sources      = is_readable( $sources_file ) ? include $sources_file : array();
		$priorities   = array(
			'ai'     => 0,
			'social' => 1,
			'search' => 2,
		);

		usort(
			$sources,
			static function ( $left, $right ) use ( $priorities ) {
				$left_priority  = $priorities[ $left['cat'] ?? '' ] ?? 9;
				$right_priority = $priorities[ $right['cat'] ?? '' ] ?? 9;
				return $left_priority <=> $right_priority;
			}
		);

		$case_parts = array();
		$case_args  = array();
		$source_map = array();

		foreach ( $sources as $source ) {
			if ( empty( $source['pattern'] ) || empty( $source['cat'] ) || empty( $source['label'] ) ) {
				continue;
			}

			$source_key                = sanitize_key( $source['cat'] ) . ':' . sanitize_title( $source['label'] );
			$case_parts[]              = 'WHEN LOWER(referrer_domain) REGEXP %s THEN %s';
			$case_args[]               = (string) $source['pattern'];
			$case_args[]               = $source_key;
			$source_map[ $source_key ] = array(
				'category'    => sanitize_key( $source['cat'] ),
				'label'       => sanitize_text_field( $source['label'] ),
				'color'       => sanitize_hex_color( $source['color'] ?? '' ) ? sanitize_hex_color( $source['color'] ?? '' ) : '#64748B',
				'icon_domain' => sanitize_text_field( $source['icon_domain'] ?? '' ),
			);
		}

		$source_case = 'CASE ' . implode( ' ', $case_parts ) . " ELSE CONCAT('site:', LOWER(referrer_domain)) END";
		$sql         = "SELECT {$source_case} AS source_key,
                               COUNT(DISTINCT session_id) AS hits,
                               COUNT(DISTINCT visitor_hash) AS unique_visitors,
                               MIN(LOWER(referrer_domain)) AS sample_domain
                        FROM {$wpdb->prefix}always_analytics_hits
                        WHERE hit_at >= %s AND hit_at <= %s
                          AND is_superseded = 0
                          AND referrer_domain != ''";
		$args        = array_merge( $case_args, array( $params['from_utc'], $params['to_utc'] ) );

		$internal_hosts = Always_Analytics_Tracker::get_internal_hosts();
		if ( ! empty( $internal_hosts ) ) {
			$host_variants = array();
			foreach ( $internal_hosts as $internal_host ) {
				$host_variants[] = $internal_host;
				$host_variants[] = 'www.' . $internal_host;
			}
			$host_variants = array_values( array_unique( $host_variants ) );
			$placeholders  = implode( ', ', array_fill( 0, count( $host_variants ), '%s' ) );
			$sql          .= " AND LOWER(TRIM(TRAILING '.' FROM referrer_domain)) NOT IN ({$placeholders})";
			$args          = array_merge( $args, $host_variants );
		}

		if ( ! empty( $params['device'] ) ) {
			$sql   .= ' AND device_type = %s';
			$args[] = $params['device'];
		}

		$sql   .= ' GROUP BY source_key ORDER BY hits DESC LIMIT %d';
		$args[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query text is assembled only from fixed internal SQL clauses; every runtime value is supplied through a matching placeholder.
		$grouped_rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
		$rows         = array();

		foreach ( $grouped_rows as $row ) {
			$source_key = (string) $row->source_key;

			if ( isset( $source_map[ $source_key ] ) ) {
				$source_info    = $source_map[ $source_key ];
				$domain         = '';
				$favicon_domain = ! empty( $source_info['icon_domain'] ) ? $source_info['icon_domain'] : (string) $row->sample_domain;
			} else {
				$source_info    = array(
					'category' => 'site',
					'label'    => preg_replace( '/^site:/', '', $source_key ),
					'color'    => '#64748B',
				);
				$domain         = $source_info['label'];
				$favicon_domain = $domain;
			}

			$rows[] = (object) array(
				'source_key'      => $source_key,
				'source_category' => $source_info['category'],
				'source_label'    => $source_info['label'],
				'source_color'    => $source_info['color'],
				'referrer_domain' => $domain,
				'favicon_domain'  => $favicon_domain,
				'hits'            => (int) $row->hits,
				'unique_visitors' => (int) $row->unique_visitors,
			);
		}

		$sql_direct  = "SELECT COUNT(DISTINCT session_id) AS hits,
                               COUNT(DISTINCT visitor_hash) AS unique_visitors
                        FROM {$wpdb->prefix}always_analytics_hits
                        WHERE hit_at >= %s AND hit_at <= %s
                          AND is_superseded = 0
                          AND (referrer_domain = '' OR referrer_domain IS NULL)";
		$args_direct = array( $params['from_utc'], $params['to_utc'] );

		if ( ! empty( $params['device'] ) ) {
			$sql_direct   .= ' AND device_type = %s';
			$args_direct[] = $params['device'];
		}

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query text is assembled only from fixed internal SQL clauses; every runtime value is supplied through a matching placeholder.
		$direct = $wpdb->get_row( $wpdb->prepare( $sql_direct, $args_direct ) );

		if ( $direct && (int) $direct->hits > 0 ) {
			$rows[] = (object) array(
				'source_key'      => 'direct',
				'source_category' => 'direct',
				'source_label'    => __( 'Direct', 'always-analytics' ),
				'source_color'    => '#64748B',
				'referrer_domain' => '',
				'favicon_domain'  => '',
				'hits'            => (int) $direct->hits,
				'unique_visitors' => (int) $direct->unique_visitors,
			);
		}

		return rest_ensure_response( $rows );
	}

	/**
	 * GET /devices - Device, browser, and OS breakdowns for the selected date range.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_devices( $request ) {
		global $wpdb;
		self::no_cache_headers();

		$params    = self::get_date_params( $request );
		$table     = $wpdb->prefix . 'always_analytics_hits';
		$base_args = array( $params['from_utc'], $params['to_utc'] );

		// $wpdb->prepare() accepts a single placeholder-values array in place of variadic args since
		// WP 6.2; the sniff's static placeholder counter does not recognize that form and misreports
		// "1 replacement found" for these 2-placeholder queries.
        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$devices = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT device_type, COUNT(DISTINCT session_id) as count
             FROM {$wpdb->prefix}always_analytics_hits WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
             GROUP BY device_type ORDER BY count DESC",
				$base_args
			)
		);

		$browsers_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT device_type, browser, COUNT(DISTINCT session_id) as count
             FROM {$wpdb->prefix}always_analytics_hits
             WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0 AND browser != ''
             GROUP BY device_type, browser
             ORDER BY count DESC",
				$base_args
			)
		);

		$os_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT device_type, os, COUNT(DISTINCT session_id) as count
             FROM {$wpdb->prefix}always_analytics_hits
             WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0 AND os != ''
             GROUP BY device_type, os
             ORDER BY count DESC",
				$base_args
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$browsers_by_device = array();
		foreach ( $browsers_raw as $row ) {
			$browsers_by_device[ $row->device_type ][] = (object) array(
				'browser' => $row->browser,
				'count'   => $row->count,
			);
		}

		$os_by_device = array();
		foreach ( $os_raw as $row ) {
			$os_by_device[ $row->device_type ][] = (object) array(
				'os'    => $row->os,
				'count' => $row->count,
			);
		}

		$browsers_global = array();
		foreach ( $browsers_raw as $row ) {
			$k = $row->browser;
			if ( ! isset( $browsers_global[ $k ] ) ) {
				$browsers_global[ $k ] = 0;
			}
			$browsers_global[ $k ] += (int) $row->count;
		}
		arsort( $browsers_global );
		$browsers_global = array_slice( $browsers_global, 0, 10, true );
		$browsers        = array_map(
			function ( $browser, $count ) {
				return (object) array(
					'browser' => $browser,
					'count'   => (string) $count,
				);
			},
			array_keys( $browsers_global ),
			array_values( $browsers_global )
		);

		$os_global = array();
		foreach ( $os_raw as $row ) {
			$k = $row->os;
			if ( ! isset( $os_global[ $k ] ) ) {
				$os_global[ $k ] = 0;
			}
			$os_global[ $k ] += (int) $row->count;
		}
		arsort( $os_global );
		$os_global = array_slice( $os_global, 0, 10, true );
		$os_list   = array_map(
			function ( $os, $count ) {
				return (object) array(
					'os'    => $os,
					'count' => (string) $count,
				);
			},
			array_keys( $os_global ),
			array_values( $os_global )
		);

		$by_device = array();
		foreach ( array( 'desktop', 'mobile', 'tablet' ) as $dtype ) {
			$by_device[ $dtype ] = array(
				'browsers' => array_slice( $browsers_by_device[ $dtype ] ?? array(), 0, 10 ),
				'os'       => array_slice( $os_by_device[ $dtype ] ?? array(), 0, 10 ),
			);
		}

		return rest_ensure_response(
			array(
				'devices'   => $devices,
				'browsers'  => $browsers,
				'os'        => $os_list,
				'by_device' => $by_device,
			)
		);
	}



	/**
	 * GET /visitors - New vs. returning vs. logged-in visitor counts.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_visitors( $request ) {
		global $wpdb;
		self::no_cache_headers();

		$params = self::get_date_params( $request );
		$table  = $wpdb->prefix . 'always_analytics_hits';

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
                SUM(CASE WHEN is_new_visitor = 1 THEN 1 ELSE 0 END) as new_visitors,
                SUM(CASE WHEN is_new_visitor = 0 THEN 1 ELSE 0 END) as returning_visitors,
                SUM(CASE WHEN is_logged_in = 1 THEN 1 ELSE 0 END) as logged_in
             FROM {$wpdb->prefix}always_analytics_hits WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0",
				$params['from_utc'],
				$params['to_utc']
			)
		);

		return rest_ensure_response( $result );
	}



	/**
	 * GET /recent-visitors - Most recently active visitors with their recent pages.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_recent_visitors( $request ) {
		global $wpdb;
		self::no_cache_headers();

		$params = self::get_date_params( $request );
		$t_hits = $wpdb->prefix . 'always_analytics_hits';
		$t_sess = $wpdb->prefix . 'always_analytics_sessions';
		$limit  = absint( $request->get_param( 'limit' ) ? $request->get_param( 'limit' ) : 15 );

		$args  = array( $params['from_utc'], $params['to_utc'] );
		$extra = '';
		if ( ! empty( $params['device'] ) ) {
			$extra .= ' AND h.device_type = %s';
			$args[] = $params['device']; }
		$args[] = $limit;

		// {$extra} is built only from a fixed ' AND h.device_type = %s' fragment (see above); the actual
		// value is bound through the %s placeholder in $args. phpcs:disable, not phpcs:ignore, because the
		// flagged token is many lines into this multi-line query string.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
                s.visitor_hash,
                MAX(s.ended_at)                         AS ended_at,
                MAX(s.started_at)                       AS last_started_at,
                SUM(CASE WHEN s.engagement_time > 0 THEN s.engagement_time ELSE s.duration END) AS total_duration,
                SUM(s.page_count)                       AS total_pages,
                COUNT(s.session_id)                     AS session_count,
                MAX(s.device_type)                      AS device_type,
                -- Most recent session for the visitor-path link
                SUBSTRING_INDEX(GROUP_CONCAT(s.session_id ORDER BY s.ended_at DESC), ',', 1) AS last_session_id,
                -- Duration of the most recent session only
                MAX(CASE WHEN s.ended_at = (SELECT MAX(s2.ended_at) FROM {$wpdb->prefix}always_analytics_sessions s2 WHERE s2.visitor_hash = s.visitor_hash) THEN CASE WHEN s.engagement_time > 0 THEN s.engagement_time ELSE s.duration END END) AS last_duration,
                MAX(CASE WHEN s.ended_at = (SELECT MAX(s2.ended_at) FROM {$wpdb->prefix}always_analytics_sessions s2 WHERE s2.visitor_hash = s.visitor_hash) THEN s.page_count END) AS last_page_count,
                -- Referrer domain from the first hit of the most recent session
                (SELECT h2.referrer_domain FROM {$wpdb->prefix}always_analytics_hits h2
                 INNER JOIN {$wpdb->prefix}always_analytics_sessions s3 ON s3.session_id = h2.session_id
                 WHERE s3.visitor_hash = s.visitor_hash AND h2.is_superseded = 0 AND h2.referrer_domain != ''
                 ORDER BY s3.ended_at DESC, h2.hit_at ASC
                 LIMIT 1)                               AS last_referrer_domain
             FROM {$wpdb->prefix}always_analytics_sessions s
             INNER JOIN (
                 SELECT DISTINCT session_id
                 FROM {$wpdb->prefix}always_analytics_hits h
                 WHERE h.hit_at >= %s AND h.hit_at <= %s AND h.is_superseded = 0{$extra}
             ) h ON h.session_id = s.session_id
             GROUP BY s.visitor_hash
             HAVING (
                 SUM(CASE WHEN s.engagement_time > 0 THEN s.engagement_time ELSE s.duration END) > 0
                 OR SUM(s.page_count) > 1
                 OR MAX(s.ended_at) > UTC_TIMESTAMP() - INTERVAL 60 SECOND
             )
             ORDER BY MAX(s.ended_at) DESC
             LIMIT %d",
				...$args
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		if ( ! empty( $results ) ) {
			foreach ( $results as $visitor ) {
				$page_args  = array(
					(string) $visitor->visitor_hash,
					$params['from_utc'],
					$params['to_utc'],
				);
				$page_extra = '';

				if ( ! empty( $params['device'] ) ) {
					$page_extra .= ' AND h.device_type = %s';
					$page_args[] = $params['device'];
				}

				// {$page_extra} is the same fixed ' AND h.device_type = %s' fragment as above.
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
				$page_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT h.page_url, h.page_title, h.hit_at
                     FROM {$wpdb->prefix}always_analytics_hits h
                     WHERE h.visitor_hash = %s
                       AND h.hit_at >= %s
                       AND h.hit_at <= %s
                       AND h.is_superseded = 0{$page_extra}
                     ORDER BY h.hit_at DESC
                     LIMIT 12",
						...$page_args
					)
				);
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

				$seen_pages   = array();
				$recent_pages = array();

				foreach ( $page_rows as $page_row ) {
					$url = trim( (string) $page_row->page_url );
					if ( '' === $url || isset( $seen_pages[ $url ] ) ) {
						continue;
					}

					$seen_pages[ $url ] = true;
					$recent_pages[]     = array(
						'url'    => esc_url_raw( $url ),
						'title'  => sanitize_text_field( (string) $page_row->page_title ),
						'hit_at' => $page_row->hit_at,
					);

					if ( count( $recent_pages ) >= 3 ) {
						break;
					}
				}

				$visitor->recent_pages = $recent_pages;
			}
		}

		return rest_ensure_response( $results );
	}



	/**
	 * GET /export - Streams a CSV/JSON export of the report data and exits.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return void
	 */
	public static function handle_export( $request ) {
		self::no_cache_headers();
		$params = self::get_date_params( $request );
		$format = sanitize_key( $request->get_param( 'format' ) ? $request->get_param( 'format' ) : 'csv' );
		Always_Analytics_Export::download( $params, $format );
	}



	/**
	 * GET /engagement - Engagement KPIs, scroll distribution, and engagement chart.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_engagement( $request ) {
		global $wpdb;
		self::no_cache_headers();

		$params       = self::get_date_params( $request );
		$t_hits       = $wpdb->prefix . 'always_analytics_hits';
		$t_sess       = $wpdb->prefix . 'always_analytics_sessions';
		$t_scroll     = $wpdb->prefix . 'always_analytics_scroll';
		$from         = $params['from_utc'];
		$to           = $params['to_utc'];
		$is_today     = $params['is_today'];
		$is_yesterday = $params['is_yesterday'];

		$has_sess_table   = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t_sess ) );
		$has_scroll_table = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t_scroll ) );

		$has_scroll_col = false;
		if ( $has_sess_table ) {
			$has_scroll_col = (bool) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
					$t_sess,
					'max_scroll_depth'
				)
			);
		}

		if ( $has_sess_table && $has_scroll_col ) {
			$kpis = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
                    COUNT(DISTINCT CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS total_sessions,
                    COUNT(DISTINCT CASE WHEN s.is_bounce = 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS engaged_sessions,
                    ROUND(AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time WHEN s.duration > 0 THEN s.duration ELSE NULL END))   AS avg_duration,
                    ROUND(AVG(CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN s.page_count ELSE NULL END), 2) AS avg_pages,
                    ROUND(AVG(CASE WHEN s.max_scroll_depth > 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN s.max_scroll_depth ELSE NULL END), 1) AS avg_scroll_depth,
                    COUNT(DISTINCT CASE WHEN s.max_scroll_depth >= 75 AND (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS deep_readers
                 FROM {$wpdb->prefix}always_analytics_hits h
                 LEFT JOIN {$wpdb->prefix}always_analytics_sessions s ON s.session_id = h.session_id
                 WHERE h.hit_at >= %s AND h.hit_at <= %s AND h.is_superseded = 0",
					$from,
					$to
				)
			);
		} elseif ( $has_sess_table ) {
			$kpis = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
                    COUNT(DISTINCT CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS total_sessions,
                    COUNT(DISTINCT CASE WHEN s.is_bounce = 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS engaged_sessions,
                    ROUND(AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time WHEN s.duration > 0 THEN s.duration ELSE NULL END))   AS avg_duration,
                    ROUND(AVG(CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN s.page_count ELSE NULL END), 2) AS avg_pages,
                    0                                                                      AS avg_scroll_depth,
                    0                                                                      AS deep_readers
                 FROM {$wpdb->prefix}always_analytics_hits h
                 LEFT JOIN {$wpdb->prefix}always_analytics_sessions s ON s.session_id = h.session_id
                 WHERE h.hit_at >= %s AND h.hit_at <= %s AND h.is_superseded = 0",
					$from,
					$to
				)
			);
		} else {

			$kpis = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
                    COUNT(DISTINCT session_id)                                             AS total_sessions,
                    COUNT(DISTINCT CASE WHEN pv > 1 THEN session_id END)                  AS engaged_sessions,
                    0                                                                       AS avg_duration,
                    ROUND(AVG(pv), 2)                                                      AS avg_pages,
                    0                                                                       AS avg_scroll_depth,
                    0                                                                       AS deep_readers
                 FROM (
                     SELECT session_id, COUNT(*) AS pv
                     FROM {$wpdb->prefix}always_analytics_hits
                     WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     GROUP BY session_id
                 ) sub",
					$from,
					$to
				)
			);
		}

		if ( ! $kpis ) {
			$kpis = (object) array(
				'total_sessions'   => 0,
				'engaged_sessions' => 0,
				'avg_duration'     => 0,
				'avg_pages'        => 0,
				'avg_scroll_depth' => 0,
				'deep_readers'     => 0,
			);
		}

		$total           = max( 1, (int) $kpis->total_sessions );
		$engaged         = (int) $kpis->engaged_sessions;
		$deep            = (int) $kpis->deep_readers;
		$engagement_rate = round( $engaged / $total * 100, 1 );
		$deep_read_rate  = round( $deep / $total * 100, 1 );

		$scroll_map = array(
			10  => 0,
			25  => 0,
			50  => 0,
			75  => 0,
			100 => 0,
		);

		if ( $has_scroll_table ) {

			$scroll_dist = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT mx.max_depth AS scroll_depth, COUNT(*) AS page_views
                 FROM (
                     SELECT sr.session_id,
                            SUBSTRING_INDEX(sr.page_url, '?', 1) AS clean_url,
                            MAX(sr.scroll_depth) AS max_depth
                     FROM {$wpdb->prefix}always_analytics_scroll sr
                     INNER JOIN {$wpdb->prefix}always_analytics_sessions s ON s.session_id = sr.session_id
                     WHERE sr.session_id IN (
                         SELECT DISTINCT session_id FROM {$wpdb->prefix}always_analytics_hits
                         WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     ) AND (s.duration > 0 OR s.engagement_time > 0)
                     GROUP BY sr.session_id, SUBSTRING_INDEX(sr.page_url, '?', 1)
                 ) mx
                 WHERE mx.max_depth IN (10, 25, 50, 75, 100)
                 GROUP BY mx.max_depth",
					$from,
					$to
				)
			);
			foreach ( $scroll_dist as $row ) {
				$d = (int) $row->scroll_depth;
				if ( isset( $scroll_map[ $d ] ) ) {
					$scroll_map[ $d ] = (int) $row->page_views;
				}
			}
		}

		if ( $has_sess_table && $has_scroll_col && array_sum( $scroll_map ) === 0 ) {

			$r = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
                    SUM(CASE WHEN s.max_scroll_depth >= 10  AND s.max_scroll_depth < 25  AND (s.duration > 0 OR s.engagement_time > 0) THEN s.page_count ELSE 0 END) AS s10,
                    SUM(CASE WHEN s.max_scroll_depth >= 25  AND s.max_scroll_depth < 50  AND (s.duration > 0 OR s.engagement_time > 0) THEN s.page_count ELSE 0 END) AS s25,
                    SUM(CASE WHEN s.max_scroll_depth >= 50  AND s.max_scroll_depth < 75  AND (s.duration > 0 OR s.engagement_time > 0) THEN s.page_count ELSE 0 END) AS s50,
                    SUM(CASE WHEN s.max_scroll_depth >= 75  AND s.max_scroll_depth < 100 AND (s.duration > 0 OR s.engagement_time > 0) THEN s.page_count ELSE 0 END) AS s75,
                    SUM(CASE WHEN s.max_scroll_depth >= 100                              AND (s.duration > 0 OR s.engagement_time > 0) THEN s.page_count ELSE 0 END) AS s100
                 FROM {$wpdb->prefix}always_analytics_sessions s
                 WHERE s.session_id IN (
                     SELECT DISTINCT session_id FROM {$wpdb->prefix}always_analytics_hits
                     WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                 )",
					$from,
					$to
				)
			);
			if ( $r ) {
				$scroll_map[10]  = (int) $r->s10;
				$scroll_map[25]  = (int) $r->s25;
				$scroll_map[50]  = (int) $r->s50;
				$scroll_map[75]  = (int) $r->s75;
				$scroll_map[100] = (int) $r->s100;
			}
		}

		$tz_off_sec = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		$tz_str     = sprintf( '%+03d:00', (int) round( $tz_off_sec / HOUR_IN_SECONDS ) );
		$chart      = array();

		$scroll_col = $has_sess_table && $has_scroll_col
			? 'ROUND(AVG(CASE WHEN s.max_scroll_depth > 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN s.max_scroll_depth ELSE NULL END),1)'
			: '0';

		$dur_col = $has_sess_table
			? 'ROUND(AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time WHEN s.duration > 0 THEN s.duration ELSE NULL END))'
			: '0';

		$eng_col = $has_sess_table
			? 'COUNT(DISTINCT CASE WHEN s.is_bounce = 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END)'
			: 'COUNT(DISTINCT CASE WHEN pv.pv > 1 THEN h.session_id END)';

		if ( $is_today || $is_yesterday ) {
			if ( $has_sess_table ) {
				// {$eng_col}/{$dur_col}/{$scroll_col} are fixed hardcoded SQL fragments chosen by boolean
				// capability flags above (see $has_sess_table etc.), never by request input.
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$chart_raw = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT HOUR(h.hit_at) AS hour_utc,
                            COUNT(DISTINCT CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS sessions,
                            {$eng_col} AS engaged,
                            {$dur_col} AS avg_dur,
                            {$scroll_col} AS avg_scroll
                     FROM {$wpdb->prefix}always_analytics_hits h
                     LEFT JOIN {$wpdb->prefix}always_analytics_sessions s ON s.session_id = h.session_id
                     WHERE h.hit_at >= %s AND h.hit_at <= %s AND h.is_superseded = 0
                     GROUP BY HOUR(h.hit_at) ORDER BY hour_utc ASC",
						$from,
						$to
					)
				);
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			} else {
				$chart_raw = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT HOUR(hit_at) AS hour_utc,
                            COUNT(DISTINCT session_id) AS sessions,
                            0 AS engaged, 0 AS avg_dur, 0 AS avg_scroll
                     FROM {$wpdb->prefix}always_analytics_hits
                     WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     GROUP BY HOUR(hit_at) ORDER BY hour_utc ASC",
						$from,
						$to
					)
				);
			}

			$tz_h    = (int) round( $tz_off_sec / HOUR_IN_SECONDS );
			$indexed = array();
			foreach ( $chart_raw as $row ) {
				$lh             = ( (int) $row->hour_utc + $tz_h + 24 ) % 24;
				$indexed[ $lh ] = $row;
			}
			$now_h = (int) wp_date( 'G' );
			for ( $h = 0; $h <= 23; $h++ ) {
				$chart[] = array(
					'label'      => $h . 'h',
					'sessions'   => isset( $indexed[ $h ] ) ? (int) $indexed[ $h ]->sessions : 0,
					'engaged'    => isset( $indexed[ $h ] ) ? (int) $indexed[ $h ]->engaged : 0,
					'avg_dur'    => isset( $indexed[ $h ] ) ? (int) $indexed[ $h ]->avg_dur : 0,
					'avg_scroll' => isset( $indexed[ $h ] ) ? (float) $indexed[ $h ]->avg_scroll : 0,
					'future'     => ( $is_today && $h > $now_h ),
				);
			}
		} else {
			if ( $has_sess_table ) {
				// Same fixed $eng_col/$dur_col/$scroll_col fragments as above.
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$chart_raw = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT DATE(CONVERT_TZ(h.hit_at, '+00:00', %s)) AS date,
                            COUNT(DISTINCT CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS sessions,
                            {$eng_col} AS engaged,
                            {$dur_col} AS avg_dur,
                            {$scroll_col} AS avg_scroll
                     FROM {$wpdb->prefix}always_analytics_hits h
                     LEFT JOIN {$wpdb->prefix}always_analytics_sessions s ON s.session_id = h.session_id
                     WHERE h.hit_at >= %s AND h.hit_at <= %s AND h.is_superseded = 0
                     GROUP BY DATE(CONVERT_TZ(h.hit_at, '+00:00', %s))
                     ORDER BY date ASC",
						$tz_str,
						$from,
						$to,
						$tz_str
					)
				);
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			} else {
				$chart_raw = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT DATE(CONVERT_TZ(hit_at, '+00:00', %s)) AS date,
                            COUNT(DISTINCT session_id) AS sessions,
                            0 AS engaged, 0 AS avg_dur, 0 AS avg_scroll
                     FROM {$wpdb->prefix}always_analytics_hits
                     WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     GROUP BY DATE(CONVERT_TZ(hit_at, '+00:00', %s))
                     ORDER BY date ASC",
						$tz_str,
						$from,
						$to,
						$tz_str
					)
				);
			}

			$indexed = array();
			foreach ( $chart_raw as $row ) {
				$indexed[ $row->date ] = $row; }
			$cur = strtotime( $params['from'] );
			$end = strtotime( $params['to'] );
			while ( $cur <= $end ) {
				$ds      = gmdate( 'Y-m-d', $cur );
				$dt      = new \DateTime( $ds );
				$chart[] = array(
					'label'      => wp_date( 'd M', $dt->getTimestamp() ),
					'sessions'   => isset( $indexed[ $ds ] ) ? (int) $indexed[ $ds ]->sessions : 0,
					'engaged'    => isset( $indexed[ $ds ] ) ? (int) $indexed[ $ds ]->engaged : 0,
					'avg_dur'    => isset( $indexed[ $ds ] ) ? (int) $indexed[ $ds ]->avg_dur : 0,
					'avg_scroll' => isset( $indexed[ $ds ] ) ? (float) $indexed[ $ds ]->avg_scroll : 0,
				);
				$cur    += DAY_IN_SECONDS;
			}
		}

		return rest_ensure_response(
			array(
				'kpis'                => array(
					'total_sessions'   => (int) $kpis->total_sessions,
					'engaged_sessions' => $engaged,
					'engagement_rate'  => $engagement_rate,
					'avg_duration'     => (int) ( $kpis->avg_duration ?? 0 ),
					'avg_pages'        => (float) ( $kpis->avg_pages ?? 0 ),
					'avg_scroll_depth' => (float) ( $kpis->avg_scroll_depth ?? 0 ),
					'deep_read_rate'   => $deep_read_rate,
				),
				'scroll_distribution' => $scroll_map,
				'chart'               => $chart,
			)
		);
	}





















	/**
	 * GET /reader-profiles - Classifies sessions into reading-behavior profiles.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_reader_profiles( $request ) {
		global $wpdb;
		self::no_cache_headers();

		$params = self::get_date_params( $request );
		$from   = $params['from_utc'];
		$to     = $params['to_utc'];
		$t_hits = $wpdb->prefix . 'always_analytics_hits';
		$t_sess = $wpdb->prefix . 'always_analytics_sessions';

		$has_sess = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t_sess ) );
		if ( ! $has_sess ) {
			return rest_ensure_response(
				array(
					'profiles'        => array(),
					'total_sessions'  => 0,
					'median_velocity' => 0,
				)
			);
		}

		$avg_velocity_row   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT GREATEST(10, COALESCE(
                ROUND(AVG(
                    ROUND(s.max_scroll_depth / COALESCE(NULLIF(s.engagement_time, 0), NULLIF(s.duration, 0)) * 100)
                )),
            10))
             FROM {$wpdb->prefix}always_analytics_sessions s
             INNER JOIN (
                 SELECT DISTINCT session_id FROM {$wpdb->prefix}always_analytics_hits
                 WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
             ) h ON h.session_id = s.session_id
             WHERE s.max_scroll_depth >= 100
               AND (s.engagement_time > 0 OR s.duration > 0)",
				$from,
				$to
			)
		);
		$velocity_threshold = (int) ( $avg_velocity_row ?? 10 );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
                CASE
                    WHEN s.max_scroll_depth < 20 THEN 'bouncer'
                    WHEN s.max_scroll_depth < 75 THEN 'explorer'
                    WHEN s.max_scroll_depth >= 100
                         AND COALESCE(NULLIF(s.engagement_time, 0), NULLIF(s.duration, 0)) > 0
                         AND ROUND(s.max_scroll_depth / COALESCE(NULLIF(s.engagement_time, 0), NULLIF(s.duration, 0)) * 100) > %d
                         THEN 'rapid_scanner'
                    ELSE 'deep_reader'
                END AS profile_key,
                COUNT(*) AS cnt
             FROM {$wpdb->prefix}always_analytics_sessions s
             INNER JOIN (
                 SELECT DISTINCT session_id FROM {$wpdb->prefix}always_analytics_hits
                 WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
             ) h ON h.session_id = s.session_id
             WHERE s.max_scroll_depth > 0
               AND (s.duration > 0 OR s.engagement_time > 0)
             GROUP BY profile_key",
				$velocity_threshold,
				$from,
				$to
			)
		);

		$counts = array(
			'bouncer'       => 0,
			'explorer'      => 0,
			'deep_reader'   => 0,
			'rapid_scanner' => 0,
		);
		foreach ( $rows as $row ) {
			if ( isset( $counts[ $row->profile_key ] ) ) {
				$counts[ $row->profile_key ] = (int) $row->cnt;
			}
		}

		$total = array_sum( $counts );

		if ( 0 === $total ) {
			return rest_ensure_response(
				array(
					'profiles'        => array(),
					'total_sessions'  => 0,
					'median_velocity' => 0,
				)
			);
		}

		$profiles = array(
			array(
				'key'         => 'bouncer',
				'label'       => __( 'Bouncer', 'always-analytics' ),
				'range'       => '0–20%',
				'description' => __( 'Arrived and left quickly. The content promise or traffic targeting may not match.', 'always-analytics' ),
				'count'       => $counts['bouncer'],
				'pct'         => $total > 0 ? round( $counts['bouncer'] / $total * 100, 1 ) : 0,
				'color'       => '#ef4444',
			),
			array(
				'key'         => 'explorer',
				'label'       => __( 'Explorer', 'always-analytics' ),
				'range'       => '20–74%',
				'description' => __( 'Browsed the content without reaching the conclusion.', 'always-analytics' ),
				'count'       => $counts['explorer'],
				'pct'         => $total > 0 ? round( $counts['explorer'] / $total * 100, 1 ) : 0,
				'color'       => '#f59e0b',
			),
			array(
				'key'         => 'rapid_scanner',
				'label'       => __( 'Rapid scanner', 'always-analytics' ),
				'range'       => '100% · rapid',
				'description' => __( 'Scans quickly for a specific answer without reading the details.', 'always-analytics' ),
				'count'       => $counts['rapid_scanner'],
				'pct'         => $total > 0 ? round( $counts['rapid_scanner'] / $total * 100, 1 ) : 0,
				'color'       => '#6c63ff',
			),
			array(
				'key'         => 'deep_reader',
				'label'       => __( 'Deep reader', 'always-analytics' ),
				'range'       => '75–100%',
				'description' => __( 'Read through to the final line with sustained attention.', 'always-analytics' ),
				'count'       => $counts['deep_reader'],
				'pct'         => $total > 0 ? round( $counts['deep_reader'] / $total * 100, 1 ) : 0,
				'color'       => '#10b981',
			),
		);

		return rest_ensure_response(
			array(
				'profiles'        => $profiles,
				'total_sessions'  => $total,
				'median_velocity' => $velocity_threshold,
			)
		);
	}




	/**
	 * GET /engagement/pages - Per-page engagement scores and signals.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_engagement_pages( $request ) {
		global $wpdb;
		self::no_cache_headers();

		$params   = self::get_date_params( $request );
		$t_hits   = $wpdb->prefix . 'always_analytics_hits';
		$t_sess   = $wpdb->prefix . 'always_analytics_sessions';
		$t_scroll = $wpdb->prefix . 'always_analytics_scroll';
		$from     = $params['from_utc'];
		$to       = $params['to_utc'];
		$limit    = absint( $request->get_param( 'limit' ) ? $request->get_param( 'limit' ) : 50 );

		$has_sess   = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t_sess ) );
		$has_scroll = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t_scroll ) );

		$has_scroll_col = false;
		if ( $has_sess ) {
			$has_scroll_col = (bool) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
					$t_sess,
					'max_scroll_depth'
				)
			);
		}

		$group_key = "CASE WHEN h.post_id > 0 THEN CONCAT('pid:', h.post_id)
                           ELSE SUBSTRING_INDEX(h.page_url, '?', 1)
                      END";

		if ( $has_sess && $has_scroll ) {
			// $group_key is a fixed internal SQL fragment built above (not from request data); the flagged
			// tokens sit several lines into this multi-line query, past the reach of a same-line ignore.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
                    -- Canonical URL without parameters, or rebuilt from post_id
                    CASE WHEN h.post_id > 0 THEN MIN(SUBSTRING_INDEX(h.page_url, '?', 1))
                         ELSE SUBSTRING_INDEX(h.page_url, '?', 1)
                    END                             AS page_url,
                    MAX(h.page_title)               AS page_title,
                    MAX(h.post_id)                  AS post_id,
                    COUNT(DISTINCT CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS total_sessions,
                    COUNT(*)                        AS page_views,
                    COUNT(DISTINCT h.visitor_hash)  AS unique_visitors,
                    COALESCE(ROUND(AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time WHEN s.duration > 0 THEN s.duration ELSE NULL END)), 0) AS avg_duration,
                    COALESCE(ROUND(AVG(CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN sc.max_scroll ELSE NULL END),1),
                             ROUND(AVG(CASE WHEN s.max_scroll_depth > 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN s.max_scroll_depth ELSE NULL END),1),
                             0)                     AS avg_scroll,
                    COUNT(DISTINCT CASE WHEN s.is_bounce = 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS engaged_sessions,
                    COUNT(DISTINCT CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS measurable_sessions,
                    COUNT(DISTINCT CASE WHEN rv.visit_count > 1 THEN h.visitor_hash END) AS returning_visitors,
                    COALESCE(ROUND(AVG(CASE WHEN SUBSTRING_INDEX(h.page_url, '?', 1) = SUBSTRING_INDEX(s.entry_page, '?', 1) AND s.page_count > 0 THEN s.page_count ELSE NULL END), 2), 0) AS avg_depth_as_entry,
                    COUNT(DISTINCT CASE WHEN SUBSTRING_INDEX(h.page_url, '?', 1) = SUBSTRING_INDEX(s.entry_page, '?', 1) THEN h.session_id END) AS entry_sessions
                 FROM {$wpdb->prefix}always_analytics_hits h
                 LEFT JOIN {$wpdb->prefix}always_analytics_sessions s ON s.session_id = h.session_id
                 LEFT JOIN (
                     SELECT session_id, SUBSTRING_INDEX(page_url, '?', 1) AS clean_url, MAX(scroll_depth) AS max_scroll
                     FROM {$wpdb->prefix}always_analytics_scroll
                     WHERE session_id IN (
                         SELECT DISTINCT session_id FROM {$wpdb->prefix}always_analytics_hits
                         WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     )
                     GROUP BY session_id, clean_url
                 ) sc ON sc.session_id = h.session_id AND sc.clean_url = SUBSTRING_INDEX(h.page_url, '?', 1)
                 LEFT JOIN (
                     SELECT visitor_hash,
                            CASE WHEN post_id > 0 THEN CONCAT('pid:', post_id) ELSE SUBSTRING_INDEX(page_url, '?', 1) END AS grp_key,
                            COUNT(DISTINCT session_id) AS visit_count
                     FROM {$wpdb->prefix}always_analytics_hits
                     WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     GROUP BY visitor_hash, grp_key
                 ) rv ON rv.visitor_hash = h.visitor_hash
                     AND rv.grp_key = {$group_key}
                 WHERE h.hit_at >= %s AND h.hit_at <= %s AND h.is_superseded = 0
                 GROUP BY {$group_key}",
					$from,
					$to,
					$from,
					$to,
					$from,
					$to
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} elseif ( $has_sess && $has_scroll_col ) {
			// $group_key is a fixed internal SQL fragment built above (not from request data); the flagged
			// tokens sit several lines into this multi-line query, past the reach of a same-line ignore.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
                    CASE WHEN h.post_id > 0 THEN MIN(SUBSTRING_INDEX(h.page_url, '?', 1))
                         ELSE SUBSTRING_INDEX(h.page_url, '?', 1)
                    END                             AS page_url,
                    MAX(h.page_title)               AS page_title,
                    MAX(h.post_id)                  AS post_id,
                    COUNT(DISTINCT CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS total_sessions,
                    COUNT(*)                        AS page_views,
                    COUNT(DISTINCT h.visitor_hash)  AS unique_visitors,
                    COALESCE(ROUND(AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time WHEN s.duration > 0 THEN s.duration ELSE NULL END)), 0) AS avg_duration,
                    COALESCE(ROUND(AVG(CASE WHEN s.max_scroll_depth > 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN s.max_scroll_depth ELSE NULL END), 1), 0) AS avg_scroll,
                    COUNT(DISTINCT CASE WHEN s.is_bounce = 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS engaged_sessions,
                    COUNT(DISTINCT CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS measurable_sessions,
                    COUNT(DISTINCT CASE WHEN rv.visit_count > 1 THEN h.visitor_hash END) AS returning_visitors,
                    COALESCE(ROUND(AVG(CASE WHEN SUBSTRING_INDEX(h.page_url, '?', 1) = SUBSTRING_INDEX(s.entry_page, '?', 1) AND s.page_count > 0 THEN s.page_count ELSE NULL END), 2), 0) AS avg_depth_as_entry,
                    COUNT(DISTINCT CASE WHEN SUBSTRING_INDEX(h.page_url, '?', 1) = SUBSTRING_INDEX(s.entry_page, '?', 1) THEN h.session_id END) AS entry_sessions
                 FROM {$wpdb->prefix}always_analytics_hits h
                 LEFT JOIN {$wpdb->prefix}always_analytics_sessions s ON s.session_id = h.session_id
                 LEFT JOIN (
                     SELECT visitor_hash,
                            CASE WHEN post_id > 0 THEN CONCAT('pid:', post_id) ELSE SUBSTRING_INDEX(page_url, '?', 1) END AS grp_key,
                            COUNT(DISTINCT session_id) AS visit_count
                     FROM {$wpdb->prefix}always_analytics_hits
                     WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     GROUP BY visitor_hash, grp_key
                 ) rv ON rv.visitor_hash = h.visitor_hash
                     AND rv.grp_key = {$group_key}
                 WHERE h.hit_at >= %s AND h.hit_at <= %s AND h.is_superseded = 0
                 GROUP BY {$group_key}",
					$from,
					$to,
					$from,
					$to
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			// $group_key is a fixed internal SQL fragment built above (not from request data); the flagged
			// tokens sit several lines into this multi-line query, past the reach of a same-line ignore.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
                    CASE WHEN h.post_id > 0 THEN MIN(SUBSTRING_INDEX(h.page_url, '?', 1))
                         ELSE SUBSTRING_INDEX(h.page_url, '?', 1)
                    END                             AS page_url,
                    MAX(h.page_title)               AS page_title,
                    MAX(h.post_id)                  AS post_id,
                    COUNT(DISTINCT h.session_id)    AS total_sessions,
                    COUNT(*)                        AS page_views,
                    COUNT(DISTINCT h.visitor_hash)  AS unique_visitors,
                    0                               AS avg_duration,
                    0                               AS avg_scroll,
                    COUNT(DISTINCT CASE WHEN pv.pv > 1 THEN h.session_id END) AS engaged_sessions,
                    COUNT(DISTINCT h.session_id)    AS measurable_sessions,
                    COUNT(DISTINCT CASE WHEN rv.visit_count > 1 THEN h.visitor_hash END) AS returning_visitors,
                    COALESCE(ROUND(AVG(CASE WHEN pv.pv > 0 THEN pv.pv ELSE NULL END), 2), 0) AS avg_depth_as_entry,
                    COUNT(DISTINCT h.session_id)    AS entry_sessions
                 FROM {$wpdb->prefix}always_analytics_hits h
                 LEFT JOIN (
                     SELECT session_id, COUNT(*) AS pv
                     FROM {$wpdb->prefix}always_analytics_hits
                     WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     GROUP BY session_id
                 ) pv ON pv.session_id = h.session_id
                 LEFT JOIN (
                     SELECT visitor_hash,
                            CASE WHEN post_id > 0 THEN CONCAT('pid:', post_id) ELSE SUBSTRING_INDEX(page_url, '?', 1) END AS grp_key,
                            COUNT(DISTINCT session_id) AS visit_count
                     FROM {$wpdb->prefix}always_analytics_hits
                     WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     GROUP BY visitor_hash, grp_key
                 ) rv ON rv.visitor_hash = h.visitor_hash
                     AND rv.grp_key = {$group_key}
                 WHERE h.hit_at >= %s AND h.hit_at <= %s AND h.is_superseded = 0
                 GROUP BY {$group_key}",
					$from,
					$to,
					$from,
					$to,
					$from,
					$to
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( empty( $rows ) ) {
			return rest_ensure_response( array() );
		}

		$durations = array_filter(
			array_map(
				function ( $r ) {
					return (float) $r->avg_duration;
				},
				$rows
			),
			function ( $d ) {
				return $d > 0;
			}
		);
		sort( $durations );
		$count_dur  = count( $durations );
		$median_dur = $count_dur > 0
			? ( 0 === $count_dur % 2
				? ( $durations[ $count_dur / 2 - 1 ] + $durations[ $count_dur / 2 ] ) / 2
				: $durations[ (int) ( $count_dur / 2 ) ] )
			: 120;
		$median_dur = max( 10, $median_dur );

		$z  = 1.96;
		$z2 = $z * $z;

		$w_duration   = 22 / 92;
		$w_scroll     = 20 / 92;
		$w_engagement = 20 / 92;
		$w_return     = 18 / 92;
		$w_depth      = 12 / 92;

		$scored = array();
		foreach ( $rows as $row ) {
			$n_total      = max( 1, (int) $row->total_sessions );
			$n_measurable = max( 0, (int) $row->measurable_sessions );
			$n_engaged    = max( 0, (int) $row->engaged_sessions );
			$n_returning  = max( 0, (int) $row->returning_visitors );
			$n_unique     = max( 1, (int) $row->unique_visitors );
			$n_entry      = max( 0, (int) $row->entry_sessions );
			$avg_dur      = max( 0, (float) $row->avg_duration );
			$avg_scroll   = max( 0, min( 100, (float) $row->avg_scroll ) );
			$avg_depth    = max( 0, (float) $row->avg_depth_as_entry );

			$s_duration = $avg_dur > 0 ? min( 1.0, $avg_dur / ( 2.0 * $median_dur ) ) : 0.0;

			$s_scroll = $avg_scroll / 100.0;

			$s_engagement = $n_measurable > 0 ? min( 1.0, $n_engaged / $n_measurable ) : 0.0;

			$s_return = min( 1.0, ( $n_returning / $n_unique ) / 0.5 );

			$s_depth = ( $n_entry > 0 && $avg_depth > 0 )
				? min( 1.0, max( 0.0, ( $avg_depth - 1.0 ) / 4.0 ) )
				: 0.0;

			$p_brut = $s_duration * $w_duration
					+ $s_scroll * $w_scroll
					+ $s_engagement * $w_engagement
					+ $s_return * $w_return
					+ $s_depth * $w_depth;

			$n            = $n_total;
			$wilson_final = ( $p_brut + $z2 / ( 2 * $n )
				- $z * sqrt( $p_brut * ( 1 - $p_brut ) / $n + $z2 / ( 4 * $n * $n ) ) )
				/ ( 1 + $z2 / $n );

			$score = round( min( 100, max( 0, $wilson_final * 100 ) ), 1 );

			$confidence_factor = $p_brut > 0 ? round( $wilson_final / $p_brut * 100, 1 ) : 0;

			$eng_rate_pct = $n_measurable > 0 ? round( $n_engaged / $n_measurable * 100 ) : null;

			$scored[] = array(
				'page_url'           => $row->page_url,
				'page_title'         => $row->page_title,
				'post_id'            => (int) $row->post_id,
				'total_sessions'     => $n_total,
				'page_views'         => (int) $row->page_views,
				'unique_visitors'    => $n_unique,
				'avg_duration'       => $avg_dur,
				'avg_scroll'         => $avg_scroll,
				'engaged_sessions'   => $n_engaged,
				'returning_visitors' => $n_returning,
				'avg_depth_as_entry' => round( $avg_depth, 1 ),
				'entry_sessions'     => $n_entry,
				'engagement_score'   => $score,
				'score_signals'      => array(
					'duration'   => array(
						'score' => round( $s_duration * 100, 1 ),
						'raw'   => round( $avg_dur ),
					),
					'scroll'     => array(
						'score' => round( $s_scroll * 100, 1 ),
						'raw'   => round( $avg_scroll ),
					),
					'engagement' => array(
						'score' => round( $s_engagement * 100, 1 ),
						'raw'   => $eng_rate_pct,
					),
					'return'     => array(
						'score' => round( $s_return * 100, 1 ),
						'raw'   => round( $n_returning / $n_unique * 100 ),
					),
					'depth'      => array(
						'score' => round( $s_depth * 100, 1 ),
						'raw'   => round( $avg_depth, 1 ),
					),

					'confidence' => array(
						'score' => $confidence_factor,
						'raw'   => $n_total,
					),
				),
			);
		}

		usort(
			$scored,
			function ( $a, $b ) {
				if ( $b['engagement_score'] !== $a['engagement_score'] ) {
					return $b['engagement_score'] <=> $a['engagement_score'];
				}
				return $b['total_sessions'] <=> $a['total_sessions'];
			}
		);

		return rest_ensure_response( array_slice( $scored, 0, $limit ) );
	}






	/**
	 * GET /hit-sources - Breakdown of hits by collection source (js, pre-consent, etc).
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_hit_sources( $request ) {
		global $wpdb;
		self::no_cache_headers();

		$params = self::get_date_params( $request );
		$table  = $wpdb->prefix . 'always_analytics_hits';
		$from   = $params['from_utc'];
		$to     = $params['to_utc'];

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
                hit_source,
                COUNT(*)                        AS hits,
                COUNT(DISTINCT visitor_hash)    AS unique_visitors,
                COUNT(DISTINCT session_id)      AS sessions,
                SUM(is_superseded)              AS superseded_count
             FROM {$wpdb->prefix}always_analytics_hits
             WHERE hit_at >= %s AND hit_at <= %s
             GROUP BY hit_source
             ORDER BY hits DESC",
				$from,
				$to
			)
		);

		$totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
                COUNT(*)                     AS total_hits,
                COUNT(DISTINCT visitor_hash) AS total_uv,
                COUNT(DISTINCT session_id)   AS total_sessions
             FROM {$wpdb->prefix}always_analytics_hits
             WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0",
				$from,
				$to
			)
		);

		$tz_offset_seconds = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		$tz_str            = sprintf( '%+03d:00', (int) round( $tz_offset_seconds / HOUR_IN_SECONDS ) );
		$is_today          = $params['is_today'];
		$is_yesterday      = $params['is_yesterday'];

		if ( $is_today || $is_yesterday ) {
			$trend = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
                    hit_source,
                    HOUR(hit_at) AS period,
                    COUNT(*) AS hits
                 FROM {$wpdb->prefix}always_analytics_hits
                 WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                 GROUP BY hit_source, HOUR(hit_at)
                 ORDER BY hit_source, period ASC",
					$from,
					$to
				)
			);
		} else {
			$trend = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
                    hit_source,
                    DATE(CONVERT_TZ(hit_at, '+00:00', %s)) AS period,
                    COUNT(*) AS hits
                 FROM {$wpdb->prefix}always_analytics_hits
                 WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                 GROUP BY hit_source, DATE(CONVERT_TZ(hit_at, '+00:00', %s))
                 ORDER BY hit_source, period ASC",
					$tz_str,
					$from,
					$to,
					$tz_str
				)
			);
		}

		$new_vis = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
                hit_source,
                SUM(is_new_visitor)                                              AS new_visitors,
                COUNT(DISTINCT CASE WHEN is_new_visitor = 1 THEN visitor_hash END) AS new_uv
             FROM {$wpdb->prefix}always_analytics_hits
             WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
             GROUP BY hit_source",
				$from,
				$to
			)
		);

		$total_hits = $totals ? (int) $totals->total_hits : 1;

		$new_vis_idx = array();
		foreach ( $new_vis as $nv ) {
			$new_vis_idx[ $nv->hit_source ] = (int) $nv->new_visitors;
		}

		$trend_idx = array();
		foreach ( $trend as $t ) {
			$src = $t->hit_source;
			if ( ! isset( $trend_idx[ $src ] ) ) {
				$trend_idx[ $src ] = array();
			}
			$trend_idx[ $src ][] = array(
				'period' => $t->period,
				'hits'   => (int) $t->hits,
			);
		}

		$source_meta = array(
			'js'            => array(
				'label'       => __( 'JavaScript', 'always-analytics' ),
				'description' => __( 'Hits collected by the JavaScript tracker in cookieless or accepted-cookie mode', 'always-analytics' ),
				'color'       => '#6c63ff',
				'icon'        => 'js',
			),
			'js_cookieless' => array(
				'label'       => __( 'Cookieless JavaScript fallback', 'always-analytics' ),
				'description' => __( 'Cookie mode is configured, but the visitor cookie is blocked; automatic cookieless fallback is used', 'always-analytics' ),
				'color'       => '#a78bfa',
				'icon'        => 'fallback',
			),
			'pre_consent'   => array(
				'label'       => __( 'Pre-consent', 'always-analytics' ),
				'description' => __( 'Cookieless hits sent before the visitor responds to the notice. Hits merged after acceptance are marked as superseded.', 'always-analytics' ),
				'color'       => '#f59e0b',
				'icon'        => 'consent',
			),
			'noscript'      => array(
				'label'       => __( 'Legacy no-JavaScript source', 'always-analytics' ),
				'description' => __( 'Historical records created by the legacy no-JavaScript collection method', 'always-analytics' ),
				'color'       => '#10b981',
				'icon'        => 'noscript',
			),
			'cookie'        => array(
				'label'       => __( 'Cookie', 'always-analytics' ),
				'description' => __( 'Hits with an active first-party visitor cookie after acceptance', 'always-analytics' ),
				'color'       => '#3b82f6',
				'icon'        => 'cookie',
			),
		);

		$sources = array();
		foreach ( $rows as $row ) {
			$src  = $row->hit_source ? $row->hit_source : 'js';
			$hits = (int) $row->hits;
			$meta = isset( $source_meta[ $src ] ) ? $source_meta[ $src ] : array(
				'label'       => $src,
				'description' => '',
				'color'       => '#94a3b8',
				'icon'        => 'unknown',
			);

			$sources[] = array(
				'source'           => $src,
				'label'            => $meta['label'],
				'description'      => $meta['description'],
				'color'            => $meta['color'],
				'icon'             => $meta['icon'],
				'hits'             => $hits,
				'unique_visitors'  => (int) $row->unique_visitors,
				'sessions'         => (int) $row->sessions,
				'superseded_count' => (int) $row->superseded_count,
				'new_visitors'     => isset( $new_vis_idx[ $src ] ) ? $new_vis_idx[ $src ] : 0,
				'pct_of_total'     => $total_hits > 0 ? round( $hits / $total_hits * 100, 1 ) : 0,
				'trend'            => isset( $trend_idx[ $src ] ) ? $trend_idx[ $src ] : array(),
			);
		}

		return rest_ensure_response(
			array(
				'sources'        => $sources,
				'total_hits'     => (int) ( $totals ? $totals->total_hits : 0 ),
				'total_uv'       => (int) ( $totals ? $totals->total_uv : 0 ),
				'total_sessions' => (int) ( $totals ? $totals->total_sessions : 0 ),
				'is_today'       => $is_today,
			)
		);
	}






	/**
	 * Records an internal/outbound link click for the current session, if not a duplicate.
	 *
	 * @param array<string, mixed> $data       Decoded hit payload.
	 * @param string               $session_id Validated client session identifier.
	 * @return void
	 */
	private static function record_link_click( $data, $session_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'always_analytics_link_clicks';

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			return;
		}

		$link_url = isset( $data['linkUrl'] ) ? esc_url_raw( $data['linkUrl'], array( 'http', 'https' ) ) : '';
		$page_url = isset( $data['pageUrl'] ) ? esc_url_raw( $data['pageUrl'], array( 'http', 'https' ) ) : '';

		$link_host = wp_parse_url( $link_url, PHP_URL_HOST );
		$page_host = wp_parse_url( $page_url, PHP_URL_HOST );
		if ( ! $link_url || ! $page_url || ! $link_host || ! $page_host ) {
			return;
		}

		if ( ! Always_Analytics_Tracker::is_internal_host( $page_host ) ) {
			return;
		}

		$link_type   = Always_Analytics_Tracker::is_internal_host( $link_host ) ? 'internal' : 'outbound';
		$link_domain = strtolower( sanitize_text_field( $link_host ) );

		$dup = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}always_analytics_link_clicks WHERE session_id = %s AND page_url = %s AND link_url = %s LIMIT 1",
				$session_id,
				$page_url,
				$link_url
			)
		);
		if ( $dup ) {
			return;
		}

		$t_sess       = $wpdb->prefix . 'always_analytics_sessions';
		$visitor_hash = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT visitor_hash FROM {$wpdb->prefix}always_analytics_sessions WHERE session_id = %s",
				$session_id
			)
		);
		if ( ! $visitor_hash ) {
			return;
		}

		$link_url    = substr( $link_url, 0, 2048 );
		$page_url    = substr( $page_url, 0, 2048 );
		$link_domain = substr( sanitize_text_field( $link_domain ), 0, 255 );

		$wpdb->insert(
			$table,
			array(
				'session_id'   => $session_id,
				'visitor_hash' => $visitor_hash ? $visitor_hash : '',
				'page_url'     => $page_url,
				'link_url'     => $link_url,
				'link_domain'  => $link_domain,
				'link_type'    => $link_type,
				'clicked_at'   => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}






	/**
	 * GET /links/internal - Top internal link click destinations and source pages.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_internal_links( $request ) {
		global $wpdb;
		self::no_cache_headers();

		$params = self::get_date_params( $request );
		$table  = $wpdb->prefix . 'always_analytics_link_clicks';
		$limit  = absint( $request->get_param( 'limit' ) ? $request->get_param( 'limit' ) : 15 );

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			return rest_ensure_response(
				array(
					'total_clicks' => 0,
					'unique_links' => 0,
					'links'        => array(),
				)
			);
		}

		$tz_offset_seconds = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		$from_utc          = $params['from_utc'];
		$to_utc            = $params['to_utc'];

		$totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as total_clicks,
                    COUNT(DISTINCT link_url) as unique_links,
                    COUNT(DISTINCT session_id) as unique_sessions
             FROM {$wpdb->prefix}always_analytics_link_clicks
             WHERE link_type = 'internal' AND clicked_at >= %s AND clicked_at <= %s",
				$from_utc,
				$to_utc
			)
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT link_url,
                    COUNT(*) as clicks,
                    COUNT(DISTINCT session_id) as unique_clicks
             FROM {$wpdb->prefix}always_analytics_link_clicks
             WHERE link_type = 'internal' AND clicked_at >= %s AND clicked_at <= %s
             GROUP BY link_url
             ORDER BY clicks DESC
             LIMIT %d",
				$from_utc,
				$to_utc,
				$limit
			)
		);

		$sources = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page_url,
                    COUNT(*) as clicks
             FROM {$wpdb->prefix}always_analytics_link_clicks
             WHERE link_type = 'internal' AND clicked_at >= %s AND clicked_at <= %s
             GROUP BY page_url
             ORDER BY clicks DESC
             LIMIT %d",
				$from_utc,
				$to_utc,
				$limit
			)
		);

		return rest_ensure_response(
			array(
				'total_clicks'    => (int) ( $totals->total_clicks ?? 0 ),
				'unique_links'    => (int) ( $totals->unique_links ?? 0 ),
				'unique_sessions' => (int) ( $totals->unique_sessions ?? 0 ),
				'links'           => $rows ? $rows : array(),
				'sources'         => $sources ? $sources : array(),
			)
		);
	}






	/**
	 * GET /links/outbound - Top outbound link click destinations and domains.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_outbound_links( $request ) {
		global $wpdb;
		self::no_cache_headers();

		$params = self::get_date_params( $request );
		$table  = $wpdb->prefix . 'always_analytics_link_clicks';
		$limit  = absint( $request->get_param( 'limit' ) ? $request->get_param( 'limit' ) : 15 );

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			return rest_ensure_response(
				array(
					'total_clicks'   => 0,
					'unique_domains' => 0,
					'domains'        => array(),
					'links'          => array(),
				)
			);
		}

		$from_utc = $params['from_utc'];
		$to_utc   = $params['to_utc'];

		$totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as total_clicks,
                    COUNT(DISTINCT link_domain) as unique_domains,
                    COUNT(DISTINCT session_id) as unique_sessions
             FROM {$wpdb->prefix}always_analytics_link_clicks
             WHERE link_type = 'outbound' AND clicked_at >= %s AND clicked_at <= %s",
				$from_utc,
				$to_utc
			)
		);

		$domains = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT link_domain,
                    COUNT(*) as clicks,
                    COUNT(DISTINCT session_id) as unique_clicks
             FROM {$wpdb->prefix}always_analytics_link_clicks
             WHERE link_type = 'outbound' AND clicked_at >= %s AND clicked_at <= %s AND link_domain != ''
             GROUP BY link_domain
             ORDER BY clicks DESC
             LIMIT %d",
				$from_utc,
				$to_utc,
				$limit
			)
		);

		$links = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT link_url, link_domain,
                    COUNT(*) as clicks,
                    COUNT(DISTINCT session_id) as unique_clicks
             FROM {$wpdb->prefix}always_analytics_link_clicks
             WHERE link_type = 'outbound' AND clicked_at >= %s AND clicked_at <= %s
             GROUP BY link_url, link_domain
             ORDER BY clicks DESC
             LIMIT %d",
				$from_utc,
				$to_utc,
				$limit
			)
		);

		return rest_ensure_response(
			array(
				'total_clicks'    => (int) ( $totals->total_clicks ?? 0 ),
				'unique_domains'  => (int) ( $totals->unique_domains ?? 0 ),
				'unique_sessions' => (int) ( $totals->unique_sessions ?? 0 ),
				'domains'         => $domains ? $domains : array(),
				'links'           => $links ? $links : array(),
			)
		);
	}



	/**
	 * GET /campaigns?from=&to=
	 * Returns all campaigns. Optionally filtered by date range (any campaign
	 * whose event_date falls in [from, to] is returned, but we always return
	 * all so the chart can show them on any range).
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_campaigns( $request ) {
		global $wpdb;
		unset( $request );
		$table = $wpdb->prefix . 'always_analytics_campaigns';

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			return rest_ensure_response( array() );
		}

		$rows = $wpdb->get_results(
			"SELECT id, event_date, label, description, color, created_at FROM {$wpdb->prefix}always_analytics_campaigns ORDER BY event_date ASC",
			ARRAY_A
		);

		return rest_ensure_response( $rows ? $rows : array() );
	}

	/**
	 * POST /campaigns
	 * Body (JSON): { event_date, label, description, color }
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function create_campaign( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'always_analytics_campaigns';

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			$charset_collate = $wpdb->get_charset_collate();
			$sql             = "CREATE TABLE {$wpdb->prefix}always_analytics_campaigns (
                id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                event_date      DATE            NOT NULL,
                label           VARCHAR(255)    NOT NULL,
                description     TEXT            NOT NULL,
                color           VARCHAR(7)      DEFAULT '#6c63ff',
                created_at      DATETIME        NOT NULL,
                PRIMARY KEY  (id),
                KEY idx_event_date (event_date)
            ) {$charset_collate};";
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		$event_date  = (string) $request->get_param( 'event_date' );
		$label       = (string) $request->get_param( 'label' );
		$description = (string) $request->get_param( 'description' );
		$color       = sanitize_hex_color( $request->get_param( 'color' ) ) ? sanitize_hex_color( $request->get_param( 'color' ) ) : '#6c63ff';

		if ( ! self::is_valid_date( $event_date ) ) {
			return new \WP_Error( 'always_analytics_invalid_date', __( 'The campaign date is invalid.', 'always-analytics' ), array( 'status' => 400 ) );
		}
		if ( '' === trim( $label ) ) {
			return new \WP_Error( 'always_analytics_invalid_label', __( 'A campaign label is required.', 'always-analytics' ), array( 'status' => 400 ) );
		}

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}always_analytics_campaigns WHERE event_date = %s",
				$event_date
			)
		);
		if ( (int) $existing >= 1 ) {
			return new \WP_Error( 'always_analytics_date_taken', __( 'A campaign already exists for this date.', 'always-analytics' ), array( 'status' => 409 ) );
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'event_date'  => $event_date,
				'label'       => $label,
				'description' => $description,
				'color'       => $color ? $color : '#6c63ff',
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new \WP_Error( 'always_analytics_db_error', __( 'The campaign could not be created.', 'always-analytics' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'id'          => $wpdb->insert_id,
				'event_date'  => $event_date,
				'label'       => $label,
				'description' => $description,
				'color'       => $color ? $color : '#6c63ff',
			)
		);
	}

	/**
	 * DELETE /campaigns/{id}
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function delete_campaign( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'always_analytics_campaigns';
		$id    = (int) $request->get_param( 'id' );

		$deleted = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		if ( ! $deleted ) {
			return new \WP_Error( 'always_analytics_not_found', __( 'The campaign was not found.', 'always-analytics' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'deleted' => true ) );
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}
