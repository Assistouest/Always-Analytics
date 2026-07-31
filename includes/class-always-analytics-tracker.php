<?php
namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Always_Analytics_Tracker {


	/**
	 * Truncates a string without requiring the optional mbstring extension.
	 *
	 * @param mixed $value  Value to truncate.
	 * @param int   $length Maximum length.
	 * @return string
	 */
	private static function truncate_string( $value, $length ) {
		$value = (string) $value;
		return function_exists( 'mb_substr' )
			? mb_substr( $value, 0, $length )
			: substr( $value, 0, $length );
	}

	/**
	 * Validates the browser-generated session identifier.
	 *
	 * @param mixed $value Identifier value.
	 * @return bool
	 */
	private static function is_valid_session_id( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value );
	}

	public static function track( $data ) {
		global $wpdb;

		$options = get_option( 'always_analytics_options', array() );

		$ip = self::get_client_ip();
		if ( self::is_excluded_ip( $ip, $options ) || self::has_exclusion_cookie() ) {
			return false;
		}

		$ua_string = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		$referrer        = isset( $data['referrer'] ) ? esc_url_raw( $data['referrer'] ) : '';
		$referrer_domain = '';
		if ( $referrer ) {
			$parsed          = wp_parse_url( $referrer );
			$referrer_domain = isset( $parsed['host'] ) ? self::normalize_host( $parsed['host'] ) : '';

			if ( $referrer_domain && self::is_internal_host( $referrer_domain ) ) {
				$referrer        = '';
				$referrer_domain = '';
			}
		}

		$candidate_post_id = isset( $data['postId'] ) ? absint( $data['postId'] ) : 0;
		$candidate_url     = isset( $data['url'] ) ? (string) $data['url'] : '';
		if ( ! self::is_valid_wp_target( $candidate_post_id, $candidate_url ) ) {
			return false;
		}

		$hit_source = isset( $data['hitSource'] ) ? sanitize_key( $data['hitSource'] ) : 'js';
		if ( ! in_array( $hit_source, array( 'js', 'pre_consent' ), true ) ) {
			return false;
		}

		$bot_context = array(
			'ua_string'       => $ua_string,
			'ip'              => $ip,
			'accept_lang'     => isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] )
									? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) )
									: null,
			'accept'          => isset( $_SERVER['HTTP_ACCEPT'] )
									? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) )
									: null,
			'page_url'        => isset( $data['url'] ) ? $data['url'] : '',
			'referrer_domain' => $referrer_domain,
			'screen_width'    => isset( $data['screenWidth'] ) ? absint( $data['screenWidth'] ) : 0,
			'screen_height'   => isset( $data['screenHeight'] ) ? absint( $data['screenHeight'] ) : 0,
			'challenge_token' => isset( $data['challengeToken'] ) ? sanitize_text_field( $data['challengeToken'] ) : '',
			'nav_lang'        => isset( $data['navigatorLanguage'] ) ? sanitize_text_field( $data['navigatorLanguage'] ) : '',
			'plugins_count'   => isset( $data['pluginsCount'] ) ? absint( $data['pluginsCount'] ) : -1,
			'webdriver'       => ! empty( $data['webdriver'] ),
			'nav_languages'   => isset( $data['navigatorLanguages'] ) ? sanitize_text_field( $data['navigatorLanguages'] ) : '',
			'hit_source'      => $hit_source,
			'sec_fetch_site'  => isset( $_SERVER['HTTP_SEC_FETCH_SITE'] ) ? sanitize_key( wp_unslash( $_SERVER['HTTP_SEC_FETCH_SITE'] ) ) : '',
			'sec_fetch_mode'  => isset( $_SERVER['HTTP_SEC_FETCH_MODE'] ) ? sanitize_key( wp_unslash( $_SERVER['HTTP_SEC_FETCH_MODE'] ) ) : '',
			'sec_fetch_dest'  => isset( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ? sanitize_key( wp_unslash( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ) : '',
			'origin_present'  => ! empty( $_SERVER['HTTP_ORIGIN'] ) || ! empty( $_SERVER['HTTP_REFERER'] ),
			'timezone'        => isset( $data['timezone'] ) ? sanitize_text_field( $data['timezone'] ) : '',
			'color_depth'     => isset( $data['colorDepth'] ) ? absint( $data['colorDepth'] ) : 0,
			'hardware'        => isset( $data['hardwareConcurrency'] ) ? absint( $data['hardwareConcurrency'] ) : 0,
			'touch_points'    => isset( $data['touchPoints'] ) ? absint( $data['touchPoints'] ) : 0,
		);

		if ( Always_Analytics_Bot_Filter::should_discard( $bot_context ) ) {
			return false;
		}

		$tracking_mode  = isset( $options['tracking_mode'] ) ? sanitize_key( $options['tracking_mode'] ) : 'cookieless';
		$has_visitor_id = 'cookie' === $tracking_mode
			&& 'pre_consent' !== $hit_source
			&& ! empty( $data['visitorId'] );
		$effective_mode = $has_visitor_id ? 'cookie' : 'cookieless';
		$must_anonymize = 'cookieless' === $effective_mode || ! empty( $options['anonymize_ip'] );

		if ( $must_anonymize ) {
			$ip = Always_Analytics_Privacy::anonymize_ip( $ip );
		}
		$device_info = self::parse_user_agent( $ua_string );

		$visitor_hash = self::generate_visitor_hash( $ip, $ua_string, $effective_mode, $data );

		if ( empty( $visitor_hash ) ) {
			return false;
		}

		if ( self::is_behavioral_bot( $visitor_hash ) ) {
			return false;
		}

		$is_new = self::is_new_visitor( $visitor_hash, $effective_mode, $options );

		$post_id   = isset( $data['postId'] ) ? absint( $data['postId'] ) : 0;
		$post_type = '';
		if ( $post_id > 0 ) {
			$post_type = get_post_type( $post_id );
			if ( false === $post_type ) {
				$post_type = '';
			}
		}

		$session_id = isset( $data['sessionId'] ) ? sanitize_text_field( $data['sessionId'] ) : '';
		if ( ! self::is_valid_session_id( $session_id ) ) {
			$session_id = wp_generate_uuid4();
		}

		$hit_data = array(
			'visitor_hash'    => substr( $visitor_hash, 0, 64 ),
			'session_id'      => substr( $session_id, 0, 64 ),
			'page_url'        => self::truncate_string( isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : '', 2048 ),
			'page_title'      => self::truncate_string( isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '', 512 ),
			'post_id'         => $post_id,
			'post_type'       => self::truncate_string( sanitize_key( $post_type ), 20 ),
			'referrer'        => self::truncate_string( $referrer, 2048 ),
			'referrer_domain' => self::truncate_string( $referrer_domain, 255 ),
			'utm_source'      => self::truncate_string( isset( $data['utmSource'] ) ? sanitize_text_field( $data['utmSource'] ) : '', 255 ),
			'utm_medium'      => self::truncate_string( isset( $data['utmMedium'] ) ? sanitize_text_field( $data['utmMedium'] ) : '', 255 ),
			'utm_campaign'    => self::truncate_string( isset( $data['utmCampaign'] ) ? sanitize_text_field( $data['utmCampaign'] ) : '', 255 ),
			'device_type'     => $device_info['device_type'],
			'browser'         => self::truncate_string( $device_info['browser'], 100 ),
			'browser_version' => self::truncate_string( $device_info['browser_version'], 20 ),
			'os'              => self::truncate_string( $device_info['os'], 100 ),
			'os_version'      => self::truncate_string( $device_info['os_version'], 20 ),
			'screen_width'    => isset( $data['screenWidth'] ) ? absint( $data['screenWidth'] ) : 0,
			'screen_height'   => isset( $data['screenHeight'] ) ? absint( $data['screenHeight'] ) : 0,
			'is_new_visitor'  => $is_new ? 1 : 0,
			'is_logged_in'    => ( 'cookie' === $effective_mode && is_user_logged_in() ) ? 1 : 0,
			'user_id'         => 'cookie' === $effective_mode ? get_current_user_id() : 0,
			'scroll_depth'    => 0,

			'hit_source'      => self::truncate_string(
				isset( $data['hitSource'] )
				? sanitize_key( $data['hitSource'] )
				: ( 'cookie' === $tracking_mode && 'cookieless' === $effective_mode ? 'js_cookieless' : 'js' ),
				20
			),
			'is_superseded'   => 0,
			'hit_at'          => current_time( 'mysql', true ),

		);

		$hit_data = apply_filters( 'always_analytics_before_track', $hit_data );
		if ( empty( $hit_data ) ) {
			return false;
		}

		$table = $wpdb->prefix . 'always_analytics_hits';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name comes from $wpdb->prefix, not user input; $wpdb->insert() parameterizes values.
		$inserted = $wpdb->insert( $table, $hit_data );

		if ( false === $inserted ) {
			return false;
		}

		$hit_id = $wpdb->insert_id;

		Always_Analytics_Session::update_session( $session_id, $hit_data );

		do_action( 'always_analytics_after_track', $hit_id, $hit_data );

		return $hit_id;
	}






	public static function upgrade_pre_consent( $pre_session_id, $new_visitor_hash ) {
		global $wpdb;
		$table_hits = $wpdb->prefix . 'always_analytics_hits';
		$table_sess = $wpdb->prefix . 'always_analytics_sessions';

		if ( empty( $pre_session_id ) || empty( $new_visitor_hash ) ) {
			return false;
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name comes from $wpdb->prefix, not user input; $wpdb->update() parameterizes values.
		$updated = $wpdb->update(
			$table_hits,
			array( 'is_superseded' => 1 ),
			array(
				'session_id' => $pre_session_id,
				'hit_source' => 'pre_consent',
			),
			array( '%d' ),
			array( '%s', '%s' )
		);

		if ( $updated ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name comes from $wpdb->prefix, not user input; $wpdb->update() parameterizes values.
			$wpdb->update(
				$table_sess,
				array( 'visitor_hash' => $new_visitor_hash ),
				array( 'session_id' => $pre_session_id ),
				array( '%s' ),
				array( '%s' )
			);
		}

		return (bool) $updated;
	}






	public static function handle_scroll( $data ) {
		global $wpdb;

		$session_id   = isset( $data['sessionId'] ) ? sanitize_text_field( $data['sessionId'] ) : '';
		$depth        = isset( $data['scrollDepth'] ) ? absint( $data['scrollDepth'] ) : 0;
		$url          = isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : '';
		$visitor_hash = '';

		if ( empty( $session_id ) || $depth < 1 || $depth > 100 ) {
			return false;
		}

		if ( ! in_array( $depth, array( 25, 50, 75, 100 ), true ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Collector lookup in a plugin-owned table; the session ID is prepared.
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT visitor_hash FROM {$wpdb->prefix}always_analytics_sessions WHERE session_id = %s",
				$session_id
			)
		);

		if ( ! $session ) {
			return false;
		}

		$visitor_hash = $session->visitor_hash;

		$page_host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $page_host || ! self::is_internal_host( $page_host ) ) {
			return false;
		}

		$table_scroll = $wpdb->prefix . 'always_analytics_scroll';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Duplicate-milestone lookup in a plugin-owned table; all values are prepared.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}always_analytics_scroll WHERE session_id = %s AND scroll_depth = %d LIMIT 1",
				$session_id,
				$depth
			)
		);

		if ( ! $existing ) {

			$post_id = url_to_postid( $url );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Collector insert into a plugin-owned table; wpdb::insert() parameterizes all values.
			$wpdb->insert(
				$table_scroll,
				array(
					'session_id'   => $session_id,
					'visitor_hash' => $visitor_hash,
					'page_url'     => $url,
					'post_id'      => $post_id ? absint( $post_id ) : 0,
					'scroll_depth' => $depth,
					'recorded_at'  => current_time( 'mysql', true ),
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic maximum-depth update in a plugin-owned table; all values are prepared.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}always_analytics_sessions SET max_scroll_depth = GREATEST(max_scroll_depth, %d) WHERE session_id = %s",
				$depth,
				$session_id
			)
		);

		return true;
	}






	private static function is_behavioral_bot( $visitor_hash ) {
		$vkey    = 'always_analytics_bb_' . md5( $visitor_hash );
		$verdict = get_transient( $vkey );
		if ( '1' === $verdict ) {
			return true;
		}

		$ckey  = 'always_analytics_bc_' . md5( $visitor_hash );
		$count = (int) get_transient( $ckey ) + 1;
		set_transient( $ckey, $count, HOUR_IN_SECONDS );

		$threshold = (int) apply_filters( 'always_analytics_behavior_threshold', 30 );

		if ( $count < $threshold ) {
			return false;
		}

		if ( '0' === $verdict ) {
			return false;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Behavioral-bot aggregate over a plugin-owned table; all values are prepared.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) c,
                    SUM(CASE WHEN page_count = 1 AND engagement_time = 0 AND max_scroll_depth = 0 THEN 1 ELSE 0 END) z
             FROM {$wpdb->prefix}always_analytics_sessions
             WHERE visitor_hash = %s AND started_at >= %s",
				$visitor_hash,
				gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
			)
		);

		$sessions = $row ? (int) $row->c : 0;
		$zero_eng = $row ? (int) $row->z : 0;

		if ( $sessions >= $threshold && ( $zero_eng / max( 1, $sessions ) ) >= 0.9 ) {
			set_transient( $vkey, '1', HOUR_IN_SECONDS );

			self::purge_bot_sessions( $visitor_hash );
			return true;
		}

		set_transient( $vkey, '0', 10 * MINUTE_IN_SECONDS );
		return false;
	}




	private static function purge_bot_sessions( $visitor_hash ) {
		global $wpdb;
		$t_sess   = $wpdb->prefix . 'always_analytics_sessions';
		$t_hits   = $wpdb->prefix . 'always_analytics_hits';
		$t_scroll = $wpdb->prefix . 'always_analytics_scroll';
		$since    = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Behavioral-bot lookup in a plugin-owned table; all values are prepared.
		$session_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT session_id FROM {$wpdb->prefix}always_analytics_sessions
             WHERE visitor_hash = %s AND started_at >= %s
               AND page_count = 1 AND engagement_time = 0 AND max_scroll_depth = 0",
				$visitor_hash,
				$since
			)
		);

		if ( empty( $session_ids ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%s' ) );
		foreach ( array( $t_hits, $t_scroll, $t_sess ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The table is selected from a fixed plugin-owned list and placeholders are generated one-for-one for the validated session IDs.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE session_id IN ({$placeholders})",
					...$session_ids
				)
			);
		}
	}






	public static function get_client_ip() {
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( ! filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
			return '0.0.0.0';
		}

		$options = get_option( 'always_analytics_options', array() );
		$mode    = isset( $options['trusted_proxy_mode'] ) ? $options['trusted_proxy_mode'] : 'none';

		if ( 'none' === $mode ) {
			return $remote_addr;
		}

		if ( 'custom' === $mode && ! empty( $options['trusted_proxies'] ) ) {
			$trusted = array_filter( array_map( 'trim', explode( "\n", $options['trusted_proxies'] ) ) );
			if ( self::is_ip_in_range( $remote_addr, $trusted ) ) {
				if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
					$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
					if ( strpos( $ip, ',' ) !== false ) {
						$ip = trim( explode( ',', $ip )[0] );
					}
					if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
						return $ip;
					}
				}
			}
		}

		return $remote_addr;
	}




	public static function is_ip_in_range( $ip, $ranges ) {
		$ip_binary = inet_pton( $ip );
		if ( false === $ip_binary ) {
			return false;
		}

		$ip_bits = 4 === strlen( $ip_binary ) ? 32 : 128;

		foreach ( (array) $ranges as $range ) {
			$parts  = explode( '/', trim( (string) $range ), 2 );
			$subnet = $parts[0];

			$subnet_binary = inet_pton( $subnet );
			if ( false === $subnet_binary || strlen( $subnet_binary ) !== strlen( $ip_binary ) ) {
				continue;
			}

			if ( 1 === count( $parts ) ) {
				if ( hash_equals( $subnet_binary, $ip_binary ) ) {
					return true;
				}
				continue;
			}

			if ( '' === $parts[1] || ! ctype_digit( $parts[1] ) ) {
				continue;
			}

			$prefix = (int) $parts[1];
			if ( $prefix < 0 || $prefix > $ip_bits ) {
				continue;
			}

			$whole_bytes = intdiv( $prefix, 8 );
			$remaining   = $prefix % 8;

			if ( $whole_bytes > 0 && substr( $ip_binary, 0, $whole_bytes ) !== substr( $subnet_binary, 0, $whole_bytes ) ) {
				continue;
			}

			if ( 0 === $remaining ) {
				return true;
			}

			$mask = ( 0xff << ( 8 - $remaining ) ) & 0xff;
			if ( ( ord( $ip_binary[ $whole_bytes ] ) & $mask ) === ( ord( $subnet_binary[ $whole_bytes ] ) & $mask ) ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Normalize a host before acquisition comparisons.
	 *
	 * @param mixed $host Host or URL host value.
	 * @return string
	 */
	public static function normalize_host( $host ) {
		$host = strtolower( trim( (string) $host ) );
		$host = rtrim( $host, '.' );

		// Remove an IPv6 bracket pair or a port from regular host names.
		if ( str_starts_with( $host, '[' ) && false !== strpos( $host, ']' ) ) {
			$host = trim( substr( $host, 0, strpos( $host, ']' ) + 1 ), '[]' );
		} elseif ( 1 === substr_count( $host, ':' ) ) {
			$host = explode( ':', $host, 2 )[0];
		}

		$host = preg_replace( '/^www\./i', '', $host );
		return is_string( $host ) ? $host : '';
	}

	/**
	 * Return the host names configured for this WordPress installation.
	 *
	 * Both home_url() and site_url() are used because WordPress may be served
	 * from a different public URL than the directory containing WordPress.
	 * Only configured hosts are internal; unrelated subdomains remain valid
	 * external referrers.
	 *
	 * @return string[]
	 */
	public static function get_internal_hosts() {
		$hosts = array();
		$urls  = array( home_url( '/' ), site_url( '/' ) );

		foreach ( $urls as $url ) {
			$host = self::normalize_host( wp_parse_url( $url, PHP_URL_HOST ) );
			if ( '' !== $host ) {
				$hosts[] = $host;
			}
		}

		/**
		 * Filters the configured hosts treated as internal navigation.
		 *
		 * @param string[] $hosts Normalized hosts from home_url() and site_url().
		 */
		$hosts = (array) apply_filters( 'always_analytics_internal_hosts', array_values( array_unique( $hosts ) ) );
		$hosts = array_map( array( self::class, 'normalize_host' ), $hosts );

		return array_values( array_unique( array_filter( $hosts ) ) );
	}

	public static function is_internal_host( $host ) {
		$ref_host = self::normalize_host( $host );
		if ( '' === $ref_host ) {
			return false;
		}

		return in_array( $ref_host, self::get_internal_hosts(), true );
	}

	/**
	 * Strict validation that a hit's target is a real WordPress resource.
	 *
	 * Rejects probes on inexistent URLs (xmlrpc, wp-admin, /?xxx=…, 404s).
	 * A hit is valid only if at least one of the following is true:
	 *   - post_id > 0 and corresponds to an existing post
	 *   - URL resolves via url_to_postid() to an existing post
	 *   - URL matches the site home, an archive, or a known taxonomy term
	 */
	private static function is_valid_wp_target( $post_id, $page_url ) {

		if ( $post_id > 0 ) {
			return (bool) get_post_status( $post_id );
		}

		if ( empty( $page_url ) ) {
			return false;
		}

		$parsed = wp_parse_url( $page_url );
		if ( empty( $parsed['host'] ) || ! self::is_internal_host( $parsed['host'] ) ) {
			return false;
		}

		$path         = isset( $parsed['path'] ) ? strtolower( $parsed['path'] ) : '/';
		$reject_paths = array(
			'xmlrpc.php',
			'wp-login.php',
			'wp-admin',
			'wp-config',
			'wp-cron.php',
			'wp-trackback.php',
			'.env',
			'.git',
		);
		foreach ( $reject_paths as $needle ) {
			if ( strpos( $path, $needle ) !== false ) {
				return false;
			}
		}

		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( $path === $home_path || $path === ( $home_path . 'index.php' ) ) {
			return true;
		}

		if ( url_to_postid( $page_url ) > 0 ) {
			return true;
		}

		if ( preg_match( '#/(category|tag|author)/([^/]+)/?$#i', $path, $m ) ) {
			$tax = ( 'tag' === strtolower( $m[1] ) ) ? 'post_tag' : ( 'category' === strtolower( $m[1] ) ? 'category' : '' );
			if ( $tax ) {
				return (bool) get_term_by( 'slug', $m[2], $tax );
			}

			return (bool) get_user_by( 'slug', $m[2] );
		}

		if ( preg_match( '#^/?\d{4}(/\d{2}(/\d{2})?)?/?$#', $path ) ) {
			return true;
		}

		$trimmed = trim( $path, '/' );
		if ( $trimmed ) {
			$first_segment = explode( '/', $trimmed )[0];
			foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
				$slug = is_string( $pt->rewrite ) ? $pt->rewrite : ( is_array( $pt->rewrite ) && isset( $pt->rewrite['slug'] ) ? $pt->rewrite['slug'] : $pt->name );
				if ( $slug && $first_segment === $slug ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function is_excluded_ip( $ip, $options ) {
		if ( empty( $options['excluded_ips'] ) ) {
			return false;
		}
		$excluded = array_filter( array_map( 'trim', explode( "\n", $options['excluded_ips'] ) ) );

		return self::is_ip_in_range( $ip, $excluded );
	}




	public static function has_exclusion_cookie() {
		$excluded  = isset( $_COOKIE['always_analytics_exclude'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['always_analytics_exclude'] ) )
			: '';
		$opted_out = isset( $_COOKIE['always_analytics_opt_out'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['always_analytics_opt_out'] ) )
			: '';

		return '1' === $excluded || '1' === $opted_out;
	}




	public static function is_excluded_request() {
		if ( self::has_exclusion_cookie() ) {
			return true;
		}
		$options = get_option( 'always_analytics_options', array() );
		return self::is_excluded_ip( self::get_client_ip(), $options );
	}



	private static function generate_visitor_hash( $ip, $ua_string, $effective_mode, $data ) {
		if ( 'cookie' === $effective_mode && ! empty( $data['visitorId'] ) ) {
			return hash( 'sha256', sanitize_text_field( $data['visitorId'] ) );
		}

		$options = get_option( 'always_analytics_options', array() );
		$window  = isset( $options['cookieless_window'] ) ? $options['cookieless_window'] : 'daily';

		$accept_lang = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) )
			: '';

		if ( 'session' === $window ) {

			$session_id = isset( $data['sessionId'] ) && ! empty( $data['sessionId'] )
				? sanitize_text_field( $data['sessionId'] )
				: gmdate( 'Y-m-d' );
			$salt       = $session_id;
		} else {

			$salt = gmdate( 'Y-m-d' );
		}

		return hash( 'sha256', $ip . $ua_string . $accept_lang . $salt );
	}









	private static function is_new_visitor( $visitor_hash, $tracking_mode = 'cookieless', $options = null ) {
		global $wpdb;
		if ( null === $options ) {
			$options = get_option( 'always_analytics_options', array() );
		}
		$window = isset( $options['cookieless_window'] ) ? $options['cookieless_window'] : 'daily';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- New-visitor detection must read current plugin-owned hit data; all variable values are prepared.
		if ( 'cookie' === $tracking_mode ) {

			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT 1 FROM {$wpdb->prefix}always_analytics_hits WHERE visitor_hash = %s LIMIT 1",
					$visitor_hash
				)
			);
		} elseif ( 'session' === $window ) {

			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT 1 FROM {$wpdb->prefix}always_analytics_hits WHERE visitor_hash = %s LIMIT 1",
					$visitor_hash
				)
			);
		} else {

			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT 1 FROM {$wpdb->prefix}always_analytics_hits WHERE visitor_hash = %s AND hit_at >= %s AND hit_at < %s LIMIT 1",
					$visitor_hash,
					gmdate( 'Y-m-d' ) . ' 00:00:00',
					gmdate( 'Y-m-d', strtotime( '+1 day' ) ) . ' 00:00:00'
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return ( null === $found );
	}



	public static function parse_user_agent( $ua ) {
		$result = array(
			'device_type'     => 'desktop',
			'browser'         => 'Unknown',
			'browser_version' => '',
			'os'              => 'Unknown',
			'os_version'      => '',
		);

		if ( empty( $ua ) ) {
			$result['device_type'] = 'unknown';
			return $result;
		}

		$tablet_kw = array( 'iPad', 'Tablet', 'Kindle', 'Silk', 'PlayBook' );
		$mobile_kw = array( 'Mobile', 'Android', 'iPhone', 'iPod', 'webOS', 'BlackBerry', 'Opera Mini', 'Opera Mobi', 'Windows Phone' );

		foreach ( $tablet_kw as $kw ) {
			if ( stripos( $ua, $kw ) !== false ) {
				$result['device_type'] = 'tablet';
				break;
			}
		}
		if ( 'desktop' === $result['device_type'] ) {
			foreach ( $mobile_kw as $kw ) {
				if ( stripos( $ua, $kw ) !== false ) {
					$result['device_type'] = 'mobile';
					break;
				}
			}
		}

		$browsers = array(

			'Edge'            => '/Edg[e\/]?\s?([\d.]+)/i',
			'Opera GX'        => '/OPR\/([\d.]+).*OPG|OPG.*OPR\/([\d.]+)/i',
			'Opera Mini'      => '/Opera Mini\/([\d.]+)/i',
			'Opera'           => '/(?:Opera|OPR)\/([\d.]+)/i',
			'Samsung Browser' => '/SamsungBrowser\/([\d.]+)/i',
			'Yandex Browser'  => '/YaBrowser\/([\d.]+)/i',
			'Brave'           => '/Brave\/([\d.]+)/i',
			'Vivaldi'         => '/Vivaldi\/([\d.]+)/i',
			'DuckDuckGo'      => '/DuckDuckGo\/([\d.]+)/i',
			'Puffin'          => '/Puffin\/([\d.]+)/i',
			'UCBrowser'       => '/UCBrowser\/([\d.]+)/i',
			'QQ Browser'      => '/MQQBrowser\/([\d.]+)/i',
			'Baidu Browser'   => '/baidubrowser\/([\d.]+)/i',
			'Silk'            => '/Silk\/([\d.]+)/i',

			'Chrome'          => '/Chrome\/([\d.]+)/i',

			'Firefox Focus'   => '/Focus\/([\d.]+)/i',
			'Firefox'         => '/Firefox\/([\d.]+)/i',

			'Safari'          => '/Version\/([\d.]+).*Safari/i',

			'IE'              => '/(?:MSIE |Trident\/.*rv:)([\d.]+)/i',
		);
		foreach ( $browsers as $name => $pattern ) {
			if ( preg_match( $pattern, $ua, $m ) ) {
				$result['browser']         = $name;
				$result['browser_version'] = $m[1];
				break;
			}
		}

		$os_list = array(

			'iOS'           => '/OS ([\d_]+) like Mac OS X/i',
			'iPadOS'        => '/iPad.*OS ([\d_]+)/i',
			'macOS'         => '/Mac OS X ([\d_]+)/i',

			'Windows 11'    => '/Windows NT 10\.0.*Win64/i',
			'Windows 10'    => '/Windows NT 10/i',
			'Windows 8.1'   => '/Windows NT 6\.3/i',
			'Windows 8'     => '/Windows NT 6\.2/i',
			'Windows 7'     => '/Windows NT 6\.1/i',
			'Windows Vista' => '/Windows NT 6\.0/i',
			'Windows XP'    => '/Windows NT 5\.1/i',
			'Windows Phone' => '/Windows Phone/i',

			'Android'       => '/Android ([\d.]+)/i',

			'Ubuntu'        => '/Ubuntu/i',
			'Fedora'        => '/Fedora/i',
			'Debian'        => '/Debian/i',
			'Linux Mint'    => '/Linux Mint/i',
			'Chrome OS'     => '/CrOS/i',
			'Linux'         => '/Linux/i',

			'BlackBerry'    => '/BlackBerry/i',
			'Symbian'       => '/Symbian/i',
			'KaiOS'         => '/KAIOS/i',
			'Tizen'         => '/Tizen/i',
			'HarmonyOS'     => '/HarmonyOS/i',
		);
		foreach ( $os_list as $name => $pattern ) {
			if ( preg_match( $pattern, $ua, $m ) ) {
				$result['os'] = $name;
				if ( isset( $m[1] ) ) {
					$result['os_version'] = str_replace( '_', '.', $m[1] );
				}
				break;
			}
		}

		return $result;
	}
}
