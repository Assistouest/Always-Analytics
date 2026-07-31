<?php
/**
 * Data lifecycle service for IP truncation and retention processing.
 *
 * @package Always_Analytics
 */

namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data lifecycle service for IP truncation and retention processing.
 *
 * Older raw records are de-linked after aggregation so historical reports
 * remain useful without retaining direct account associations indefinitely.
 */
class Always_Analytics_Privacy {



	/**
	 * Anonymize an IP address (remove last octet for IPv4, last 80 bits for IPv6).
	 *
	 * @param string $ip Raw IP address.
	 * @return string Anonymized IP address.
	 */
	public static function anonymize_ip( $ip ) {
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return preg_replace( '/\.\d+$/', '.0', $ip );
		}
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$packed = inet_pton( $ip );
			if ( false === $packed ) {
				return '::';
			}
			for ( $i = 6; $i < 16; $i++ ) {
				$packed[ $i ] = "\x00";
			}
			return inet_ntop( $packed );
		}
		return '0.0.0.0';
	}



	/**
	 * De-links raw records older than the configured retention period.
	 *
	 * Daily aggregates are created first so historical totals remain useful.
	 * Direct account identifiers and stable visitor relationships are then removed.
	 *
	 * @return void
	 */
	public function purge_old_data() {
		global $wpdb;

		$options        = get_option( 'always_analytics_options', array() );
		$retention_days = isset( $options['retention_days'] )
			? min( 395, max( 30, absint( $options['retention_days'] ) ) )
			: 90;

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		do_action( 'always_analytics_before_purge', $cutoff_date );

		$table_hits     = $wpdb->prefix . 'always_analytics_hits';
		$table_scroll   = $wpdb->prefix . 'always_analytics_scroll';
		$table_daily    = $wpdb->prefix . 'always_analytics_daily';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Retention processing reads and deletes plugin-owned analytics data; all variable values are prepared and live results are required.
		$days_to_agg = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT DATE(hit_at) as d
             FROM {$wpdb->prefix}always_analytics_hits
             WHERE hit_at < %s
               AND DATE(hit_at) NOT IN (SELECT DISTINCT stat_date FROM {$wpdb->prefix}always_analytics_daily)
             ORDER BY d ASC
             LIMIT 365",
				$cutoff_date
			)
		);

		foreach ( $days_to_agg as $day ) {
			$this->aggregate_day( $day );
		}

		$this->enrich_daily_aggregates( $cutoff_date );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}always_analytics_hits
             SET visitor_hash = SHA2(CONCAT(id, RAND(), UUID()), 256),
                 user_id      = 0,
                 is_logged_in = 0,
                 referrer     = CASE WHEN referrer_domain != '' THEN referrer_domain ELSE '' END
             WHERE hit_at < %s
               AND visitor_hash NOT LIKE %s",
				$cutoff_date,
				'anon_%'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}always_analytics_hits
             SET visitor_hash = CONCAT('anon_', LEFT(visitor_hash, 58))
             WHERE hit_at < %s
               AND visitor_hash NOT LIKE %s",
				$cutoff_date,
				'anon_%'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}always_analytics_sessions
             SET visitor_hash = CONCAT('anon_', SHA2(CONCAT(session_id, RAND()), 256))
             WHERE started_at < %s
               AND visitor_hash NOT LIKE %s",
				$cutoff_date,
				'anon_%'
			)
		);

		// Rows marked is_superseded=1 (e.g. a pre-consent hit replaced once the visitor accepts
		// cookie tracking) are already excluded from every report query and have no further
		// business value. A 1-day buffer avoids racing the in-flight consent-upgrade request
		// that sets the flag. This is dead-weight cleanup, not a retention/anonymization step.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}always_analytics_hits WHERE is_superseded = 1 AND hit_at < %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) )
			)
		);

		$has_scroll = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_scroll ) );
		if ( $has_scroll ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}always_analytics_scroll
                 SET visitor_hash = CONCAT('anon_', SHA2(CONCAT(id, RAND()), 256))
                 WHERE recorded_at < %s
                   AND visitor_hash NOT LIKE %s",
					$cutoff_date,
					'anon_%'
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		do_action( 'always_analytics_after_purge', $cutoff_date );
	}




	/**
	 * Aggregates a single day of hit/session data into the daily rollup table.
	 *
	 * @param string $day Date (Y-m-d) to aggregate.
	 * @return void
	 */
	private function aggregate_day( $day ) {
		global $wpdb;
		$table_hits  = $wpdb->prefix . 'always_analytics_hits';
		$table_daily = $wpdb->prefix . 'always_analytics_daily';
		$t_sess      = $wpdb->prefix . 'always_analytics_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy aggregation writes anonymized totals to a plugin-owned table; the date is prepared.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}always_analytics_daily
                (stat_date, page_url, post_id, unique_visitors, page_views, sessions,
                 new_visitors, returning_vis, avg_duration, bounce_rate)
             SELECT
                DATE(h.hit_at),
                h.page_url,
                h.post_id,
                COUNT(DISTINCT h.visitor_hash),
                COUNT(*),
                COUNT(DISTINCT h.session_id),
                SUM(h.is_new_visitor),
                SUM(CASE WHEN h.is_new_visitor = 0 THEN 1 ELSE 0 END),
                COALESCE(AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time
                                  WHEN s.duration > 0 THEN s.duration ELSE NULL END), 0),
                CASE WHEN COUNT(DISTINCT h.session_id) > 0
                     THEN SUM(CASE WHEN s.is_bounce = 1 THEN 1 ELSE 0 END) / COUNT(DISTINCT h.session_id) * 100
                     ELSE 0 END
             FROM {$wpdb->prefix}always_analytics_hits h
             LEFT JOIN {$wpdb->prefix}always_analytics_sessions s ON s.session_id = h.session_id
             WHERE DATE(h.hit_at) = %s
             GROUP BY DATE(h.hit_at), h.page_url, h.post_id
             ON DUPLICATE KEY UPDATE
                unique_visitors = VALUES(unique_visitors),
                page_views      = VALUES(page_views),
                sessions        = VALUES(sessions),
                new_visitors    = VALUES(new_visitors),
                returning_vis   = VALUES(returning_vis),
                avg_duration    = VALUES(avg_duration),
                bounce_rate     = VALUES(bounce_rate)",
				$day
			)
		);
	}




	/**
	 * Backfills average duration / bounce rate on already-created daily aggregates.
	 *
	 * @param string $cutoff_date Retention cutoff (Y-m-d H:i:s).
	 * @return void
	 */
	private function enrich_daily_aggregates( $cutoff_date ) {
		global $wpdb;
		$table_hits  = $wpdb->prefix . 'always_analytics_hits';
		$table_daily = $wpdb->prefix . 'always_analytics_daily';
		$t_sess      = $wpdb->prefix . 'always_analytics_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time backfill of anonymized aggregates in plugin-owned tables; cutoff values are prepared.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}always_analytics_daily d
             INNER JOIN (
                SELECT DATE(h.hit_at) as stat_date, h.page_url,
                    COALESCE(AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time
                                      WHEN s.duration > 0 THEN s.duration ELSE NULL END), 0) as avg_dur,
                    CASE WHEN COUNT(DISTINCT h.session_id) > 0
                         THEN SUM(CASE WHEN s.is_bounce = 1 THEN 1 ELSE 0 END) / COUNT(DISTINCT h.session_id) * 100
                         ELSE 0 END as br
                FROM {$wpdb->prefix}always_analytics_hits h
                LEFT JOIN {$wpdb->prefix}always_analytics_sessions s ON s.session_id = h.session_id
                WHERE h.hit_at < %s AND h.visitor_hash NOT LIKE %s
                GROUP BY DATE(h.hit_at), h.page_url
             ) src ON d.stat_date = src.stat_date AND d.page_url = src.page_url
             SET d.avg_duration = src.avg_dur,
                 d.bounce_rate  = src.br
             WHERE d.avg_duration = 0",
				$cutoff_date,
				'anon_%'
			)
		);
	}
}
