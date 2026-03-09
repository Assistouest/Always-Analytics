<?php
namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cache layer — intentionnellement désactivé.
 * Les données sont toujours lues en direct depuis la base de données.
 * Cette classe est conservée pour compatibilité avec les appels existants.
 */
class Always_Analytics_Cache {

    public static function get( $key ) {
        return false;
    }

    public static function set( $key, $value, $expiration = 300 ) {
        // no-op
    }

    public static function delete( $key ) {
        // no-op
    }

    public static function invalidate_group( $group ) {
        // no-op
    }

    public static function ttl_for_period( $to_date ) {
        return 0;
    }

    public static function remember( $key, $callback, $expiration = 300 ) {
        return call_user_func( $callback );
    }
}
