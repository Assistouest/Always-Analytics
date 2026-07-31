<?php
/**
 * Deactivation tasks.
 *
 * @package Always_Analytics
 */

namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivation tasks.
 */
class Always_Analytics_Deactivator {

	/**
	 * Remove scheduled tasks without deleting analytics data.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$hooks = array(
			'always_analytics_daily_aggregate',
			'always_analytics_daily_purge',
			'always_analytics_expire_sessions',
			'aa_daily_aggregate',
			'aa_daily_purge',
			'aa_expire_sessions',
			'advstats_daily_aggregate',
			'advstats_daily_purge',
			'advstats_expire_sessions',
		);

		foreach ( $hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}
}
