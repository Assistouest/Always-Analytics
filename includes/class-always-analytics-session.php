<?php
namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


final class Always_Analytics_Session {

	/**
	 * Checks whether a collector session exists.
	 *
	 * @param string $session_id Client session identifier.
	 * @return bool
	 */
	public static function exists( $session_id ) {
		global $wpdb;

		if ( ! is_string( $session_id ) || ! preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $session_id ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Existence check in the plugin-owned session table; the UUID is strictly validated and prepared.
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}always_analytics_sessions WHERE session_id = %s LIMIT 1",
				$session_id
			)
		);
	}




	public static function update_session( $session_id, $hit_data ) {
		global $wpdb;
		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic session upsert in a plugin-owned table; all collector values use placeholders.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}always_analytics_sessions
                (session_id, visitor_hash, started_at, ended_at, duration,
                 page_count, entry_page, exit_page, referrer,
                 device_type, is_bounce, max_scroll_depth, engagement_time)
             VALUES (%s, %s, %s, %s, 0, 1, %s, %s, %s, %s, 1, 0, 0)
             ON DUPLICATE KEY UPDATE
                ended_at   = VALUES(ended_at),
                duration   = LEAST(3600, GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, VALUES(ended_at)))),
                page_count = page_count + 1,
                exit_page  = VALUES(exit_page),
                is_bounce  = CASE
                    WHEN page_count + 1 > 1
                      OR GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, VALUES(ended_at))) >= 10
                    THEN 0
                    ELSE is_bounce
                END",
				$session_id,
				$hit_data['visitor_hash'],
				$now,
				$now,
				$hit_data['page_url'],
				$hit_data['page_url'],
				$hit_data['referrer'],
				$hit_data['device_type']
			)
		);
	}




	public static function ping_session( $session_id, $scroll_depth = null, $client_duration = null, $engagement_time = null ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live session read from a plugin-owned table; the session ID is prepared.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT started_at, duration, is_bounce, max_scroll_depth, engagement_time FROM {$wpdb->prefix}always_analytics_sessions WHERE session_id = %s",
				$session_id
			)
		);

		if ( ! $existing ) {
			return;
		}

		$now = current_time( 'mysql', true );

		if ( null !== $client_duration ) {

			$cd       = min( 3600, absint( $client_duration ) );
			$duration = ( $cd > 0 ) ? $cd : (int) $existing->duration;
		} else {

			$server_duration = max( 0, min( 3600, (int) ( strtotime( $now ) - strtotime( $existing->started_at ) ) ) );
			$duration        = max( (int) $existing->duration, $server_duration );
		}

		$duration = max( (int) $existing->duration, $duration );

		$update = array(
			'ended_at' => $now,
			'duration' => $duration,
		);
		$format = array( '%s', '%d' );

		if ( $duration >= 10 && 1 === (int) $existing->is_bounce ) {
			$update['is_bounce'] = 0;
			$format[]            = '%d';
		}

		if ( null !== $engagement_time ) {
			$et = min( 3600, absint( $engagement_time ) );
			if ( $et > 0 && $et > (int) $existing->engagement_time ) {
				$update['engagement_time'] = $et;
				$format[]                  = '%d';
			}
		}

		if ( null !== $scroll_depth ) {
			$depth = absint( $scroll_depth );
			if ( $depth > (int) $existing->max_scroll_depth ) {
				$update['max_scroll_depth'] = $depth;
				$format[]                   = '%d';
			}
		}

		$table = $wpdb->prefix . 'always_analytics_sessions';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name comes from $wpdb->prefix, not user input; $wpdb->update() already parameterizes values.
		$wpdb->update(
			$table,
			$update,
			array( 'session_id' => $session_id ),
			$format,
			array( '%s' )
		);
	}




	public static function expire_stale_sessions() {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-30 minutes' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled normalization of stale rows in the plugin-owned session table; the cutoff is prepared.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}always_analytics_sessions
             SET duration = CASE
                    WHEN page_count > 1 THEN LEAST(3600, GREATEST(5, TIMESTAMPDIFF(SECOND, started_at, ended_at)))
                    WHEN max_scroll_depth > 0 THEN 5
                    ELSE 0
                 END,
                 is_bounce = CASE
                    WHEN page_count > 1 OR max_scroll_depth >= 25 THEN 0
                    ELSE is_bounce
                 END
             WHERE ended_at < %s
               AND duration = 0
               AND page_count >= 1",
				$cutoff
			)
		);
	}
}
