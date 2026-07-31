<?php
/**
 * Detect IP addresses assigned to known cloud and datacenter networks.
 *
 * @package Always_Analytics
 */

namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detect IP addresses assigned to known cloud and datacenter networks.
 *
 * The bundled range lists are derived from the CC0-licensed
 * cloud-provider-ip-addresses project and are used only as one bot-scoring
 * signal. No remote lookup is performed.
 */
class Always_Analytics_Datacenter {

	/**
	 * Loaded hexadecimal ranges grouped by address family.
	 *
	 * @var array|null
	 */
	private static $data = null;

	/**
	 * Determine whether an IP address belongs to a bundled datacenter range.
	 *
	 * @param string $ip IPv4 or IPv6 address.
	 * @return bool
	 */
	public static function is_datacenter_ip( $ip ) {
		if ( empty( $ip ) || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		self::load();

		if ( null === self::$data ) {
			return false;
		}

		$binary = inet_pton( $ip );
		if ( false === $binary ) {
			return false;
		}

		$family = 4 === strlen( $binary ) ? 'v4' : 'v6';

		return self::search( bin2hex( $binary ), self::$data[ $family ] );
	}

	/**
	 * Search a sorted list of inclusive hexadecimal ranges.
	 *
	 * Fixed-width hexadecimal addresses preserve numeric ordering, so a
	 * binary search can compare strings without integer-size limitations.
	 *
	 * @param string $ip_hex Hexadecimal IP address.
	 * @param array  $ranges List of start and end pairs.
	 * @return bool
	 */
	private static function search( $ip_hex, $ranges ) {
		$low  = 0;
		$high = count( $ranges ) - 1;

		while ( $low <= $high ) {
			$middle = (int) floor( ( $low + $high ) / 2 );
			$range  = $ranges[ $middle ];

			if ( strcmp( $ip_hex, $range[0] ) < 0 ) {
				$high = $middle - 1;
			} elseif ( strcmp( $ip_hex, $range[1] ) > 0 ) {
				$low = $middle + 1;
			} else {
				return true;
			}
		}

		return false;
	}

	/**
	 * Load both bundled range files.
	 *
	 * @return void
	 */
	private static function load() {
		if ( null !== self::$data ) {
			return;
		}

		$v4 = self::load_range_file( ALWAYS_ANALYTICS_PLUGIN_DIR . 'data/datacenter-v4-ranges.txt', 8 );
		$v6 = self::load_range_file( ALWAYS_ANALYTICS_PLUGIN_DIR . 'data/datacenter-v6-ranges.txt', 32 );

		self::$data = $v4 && $v6
			? array(
				'v4' => $v4,
				'v6' => $v6,
			)
			: null;
	}

	/**
	 * Parse a local tab-separated range file.
	 *
	 * @param string $file_path       Absolute local path.
	 * @param int    $expected_length Expected hexadecimal address length.
	 * @return array
	 */
	private static function load_range_file( $file_path, $expected_length ) {
		if ( ! is_readable( $file_path ) ) {
			return array();
		}

		$lines  = file( $file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		$ranges = array();

		if ( false === $lines ) {
			return $ranges;
		}

		foreach ( $lines as $line ) {
			if ( '#' === substr( $line, 0, 1 ) ) {
				continue;
			}

			$parts = explode( "\t", strtolower( trim( $line ) ), 2 );
			if ( 2 !== count( $parts ) ) {
				continue;
			}

			if (
				strlen( $parts[0] ) !== $expected_length ||
				strlen( $parts[1] ) !== $expected_length ||
				! ctype_xdigit( $parts[0] ) ||
				! ctype_xdigit( $parts[1] )
			) {
				continue;
			}

			$ranges[] = array( $parts[0], $parts[1] );
		}

		return $ranges;
	}
}
