<?php
/**
 * Remove plugin data when the administrator explicitly enabled deletion.
 *
 * @package Always_Analytics
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$always_analytics_uninstall_options = get_option( 'always_analytics_options', array() );

if ( empty( $always_analytics_uninstall_options['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;

$always_analytics_table_suffixes = array(
	'always_analytics_hits',
	'always_analytics_sessions',
	'always_analytics_daily',
	'always_analytics_scroll',
	'always_analytics_campaigns',
	'always_analytics_link_clicks',
	'aa_hits',
	'aa_sessions',
	'aa_daily',
	'aa_scroll',
	'aa_campaigns',
	'aa_link_clicks',
	'advstats_hits',
	'advstats_sessions',
	'advstats_daily',
	'advstats_scroll',
);

foreach ( $always_analytics_table_suffixes as $always_analytics_suffix ) {
	$always_analytics_table_name = $wpdb->prefix . $always_analytics_suffix;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching -- Destructive cleanup runs only after WordPress invokes uninstall and the administrator opted in; identifiers come from the fixed suffix allowlist above.
	$wpdb->query( "DROP TABLE IF EXISTS `{$always_analytics_table_name}`" );
}

$always_analytics_options_to_delete = array(
	'always_analytics_options',
	'always_analytics_version',
	'always_analytics_schema_version',
	'advstats_options',
	'advstats_db_version',
	'advstats_db_schema_version',
);

foreach ( $always_analytics_options_to_delete as $always_analytics_option_name ) {
	delete_option( $always_analytics_option_name );
}

$always_analytics_transient_prefixes = array(
	'always_analytics_',
	'aa_',
	'advstats_',
);

foreach ( $always_analytics_transient_prefixes as $always_analytics_prefix ) {
	$always_analytics_transient_pattern = $wpdb->esc_like( '_transient_' . $always_analytics_prefix ) . '%';
	$always_analytics_timeout_pattern   = $wpdb->esc_like( '_transient_timeout_' . $always_analytics_prefix ) . '%';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk deletion is required because transient names are prefix-matched; both LIKE values are escaped and prepared.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$always_analytics_transient_pattern,
			$always_analytics_timeout_pattern
		)
	);
}

$always_analytics_cron_hooks = array(
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

foreach ( $always_analytics_cron_hooks as $always_analytics_hook ) {
	wp_clear_scheduled_hook( $always_analytics_hook );
}
