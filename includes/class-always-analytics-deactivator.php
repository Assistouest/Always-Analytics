<?php
namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin deactivator — cleans up scheduled tasks.
 */
class Always_Analytics_Deactivator {

    public static function deactivate() {
        // Clear current cron hooks
        wp_clear_scheduled_hook( 'aa_daily_aggregate' );
        wp_clear_scheduled_hook( 'aa_daily_purge' );
        wp_clear_scheduled_hook( 'always_analytics_expire_sessions' );

        // Clear legacy cron hooks (advstats_ prefix) in case they still exist
        wp_clear_scheduled_hook( 'advstats_daily_aggregate' );
        wp_clear_scheduled_hook( 'advstats_daily_purge' );
        wp_clear_scheduled_hook( 'advstats_expire_sessions' );
    }
}
