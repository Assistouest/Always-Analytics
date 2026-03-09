<?php
namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Geolocation handler — resolves IP addresses to geographic locations.
 * Supports native (bundled ip-country lookup table) and MaxMind GeoLite2 providers.
 *
 * External HTTP lookups have been intentionally removed. The plugin relies
 * exclusively on local data sources (bundled PHP table or a local MaxMind .mmdb
 * file) to avoid outbound HTTP requests, data leakage, and MitM risks.
 */
class Always_Analytics_Geolocation {

    private $provider;
    private $options;

    /**
     * @param string $provider 'native' or 'maxmind'.
     * @param array  $options  Plugin options.
     */
    public function __construct( $provider = 'native', $options = array() ) {
        $this->provider = $provider;
        $this->options  = $options;
    }

    /**
     * Lookup geographic data for an IP address.
     *
     * @param string $ip The IP address.
     * @return array { country_code, region, city }
     */
    public function lookup( $ip ) {
        $default = array(
            'country_code' => '',
            'region'       => '',
            'city'         => '',
        );

        if ( empty( $ip ) || '0.0.0.0' === $ip || '127.0.0.1' === $ip || '::1' === $ip ) {
            return $default;
        }

        // Check cache first
        $cache_key = 'aa_geo_' . md5( $ip );
        $cached    = Always_Analytics_Cache::get( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $result = $default;

        switch ( $this->provider ) {
            case 'maxmind':
                $result = $this->lookup_maxmind( $ip );
                break;
            case 'native':
            default:
                $result = $this->lookup_native( $ip );
                break;
        }

        // Cache for 24 hours (IPs don't change geo often)
        Always_Analytics_Cache::set( $cache_key, $result, DAY_IN_SECONDS );

        return $result;
    }

    /**
     * Native geolocation using the bundled IP-to-country PHP lookup table.
     *
     * Returns country code only (no region/city). If the IP is not found in
     * the table, returns empty strings — no external request is made.
     *
     * @param string $ip The IP address.
     * @return array
     */
    private function lookup_native( $ip ) {
        $default = array( 'country_code' => '', 'region' => '', 'city' => '' );

        $country_file = AA_PLUGIN_DIR . 'data/ip-country.php';
        if ( ! file_exists( $country_file ) ) {
            return $default;
        }

        $lookup_table = include $country_file;
        if ( ! is_array( $lookup_table ) ) {
            return $default;
        }

        $country = $this->binary_search_country( $ip, $lookup_table );
        if ( $country ) {
            return array(
                'country_code' => $country,
                'region'       => '',
                'city'         => '',
            );
        }

        return $default;
    }

    /**
     * Binary search in the IP-to-country lookup table.

     *
     * @param string $ip    The IP address.
     * @param array  $table Array of [ 'start' => long, 'end' => long, 'cc' => 'XX' ].
     * @return string|false Country code or false.
     */
    private function binary_search_country( $ip, $table ) {
        $ip_long = ip2long( $ip );
        if ( false === $ip_long ) {
            return false;
        }

        $low  = 0;
        $high = count( $table ) - 1;

        while ( $low <= $high ) {
            $mid = intdiv( $low + $high, 2 );
            if ( $ip_long < $table[ $mid ]['s'] ) {
                $high = $mid - 1;
            } elseif ( $ip_long > $table[ $mid ]['e'] ) {
                $low = $mid + 1;
            } else {
                return $table[ $mid ]['cc'];
            }
        }

        return false;
    }


    /**
     * MaxMind GeoLite2 lookup.
     *
     * @param string $ip The IP address.
     * @return array
     */
    private function lookup_maxmind( $ip ) {
        $default = array( 'country_code' => '', 'region' => '', 'city' => '' );

        $db_path = ! empty( $this->options['maxmind_db_path'] )
            ? $this->options['maxmind_db_path']
            : AA_PLUGIN_DIR . 'data/GeoLite2-City.mmdb';

        if ( ! file_exists( $db_path ) ) {
            // Fallback to native if MaxMind DB not found
            return $this->lookup_native( $ip );
        }

        try {
            // Use the maxminddb PHP extension if available
            if ( extension_loaded( 'maxminddb' ) ) {
                $reader = new \MaxMind\Db\Reader( $db_path );
                $record = $reader->get( $ip );
                $reader->close();
            } else {
                // Use the PHP pure reader (must be installed via composer)
                if ( ! class_exists( '\\MaxMind\\Db\\Reader' ) ) {
                    $autoload = AA_PLUGIN_DIR . 'vendor/autoload.php';
                    if ( file_exists( $autoload ) ) {
                        require_once $autoload;
                    } else {
                        return $this->lookup_native( $ip );
                    }
                }
                $reader = new \MaxMind\Db\Reader( $db_path );
                $record = $reader->get( $ip );
                $reader->close();
            }

            if ( empty( $record ) ) {
                return $default;
            }

            return array(
                'country_code' => $record['country']['iso_code'] ?? '',
                'region'       => $record['subdivisions'][0]['names']['en'] ?? '',
                'city'         => $record['city']['names']['en'] ?? '',
            );
        } catch ( \Exception $e ) {
            return $this->lookup_native( $ip );
        }
    }
}
