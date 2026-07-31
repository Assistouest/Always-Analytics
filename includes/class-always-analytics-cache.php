<?php
/**
 * Caching facade for expensive lookups (currently a pass-through no-op).
 *
 * @package Always_Analytics
 */

namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin caching facade. All methods are currently no-ops (cache disabled);
 * callers must not assume values actually persist between requests.
 */
class Always_Analytics_Cache {

	/**
	 * Retrieves a cached value.
	 *
	 * @param string $key Cache key.
	 * @return mixed False (cache disabled).
	 */
	public static function get( $key ) {
		return false;
	}

	/**
	 * Stores a value in the cache.
	 *
	 * @param string $key        Cache key.
	 * @param mixed  $value      Value to store.
	 * @param int    $expiration TTL in seconds.
	 * @return void
	 */
	public static function set( $key, $value, $expiration = 300 ) {
	}

	/**
	 * Deletes a cached value.
	 *
	 * @param string $key Cache key.
	 * @return void
	 */
	public static function delete( $key ) {
	}

	/**
	 * Invalidates all cached values in a group.
	 *
	 * @param string $group Cache group.
	 * @return void
	 */
	public static function invalidate_group( $group ) {
	}

	/**
	 * Computes a cache TTL suitable for the given reporting period.
	 *
	 * @param string $to_date End date (Y-m-d) of the reporting period.
	 * @return int TTL in seconds.
	 */
	public static function ttl_for_period( $to_date ) {
		return 0;
	}

	/**
	 * Returns a cached value, computing and storing it via $callback on miss.
	 *
	 * @param string   $key        Cache key.
	 * @param callable $callback   Value producer on cache miss.
	 * @param int      $expiration TTL in seconds.
	 * @return mixed
	 */
	public static function remember( $key, $callback, $expiration = 300 ) {
		return call_user_func( $callback );
	}
}
