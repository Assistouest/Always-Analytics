<?php
/**
 * Uninstall handler — removes all plugin data.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$options = get_option('always_analytics_options', array());

if (!empty($options['delete_on_uninstall'])) {
    global $wpdb;

    // Drop custom tables (new names).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
    // Note: prepare() is intentionally omitted — DDL with hardcoded table names
    // has no user-supplied values and no placeholders to bind.
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aa_hits" );      // phpcs:ignore
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aa_sessions" );  // phpcs:ignore
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aa_daily" );     // phpcs:ignore
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aa_scroll" );    // phpcs:ignore

    // Also drop legacy advstats_* tables in case migration never ran.
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}advstats_hits" );     // phpcs:ignore
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}advstats_sessions" ); // phpcs:ignore
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}advstats_daily" );    // phpcs:ignore
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}advstats_scroll" );   // phpcs:ignore

    // Delete options (new names)
    delete_option('always_analytics_options');
    delete_option('always_analytics_version');
    delete_option('always_analytics_schema_version');

    // Delete legacy options
    delete_option('advstats_options');
    delete_option('advstats_db_version');
    delete_option('advstats_db_schema_version');

    // Delete transients (both prefixes)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query(
        $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
        '_transient_aa_%',
        '_transient_timeout_aa_%',
        '_transient_advstats_%',
        '_transient_timeout_advstats_%'
    )
    );
}
