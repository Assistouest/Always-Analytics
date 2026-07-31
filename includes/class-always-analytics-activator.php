<?php
namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin activator — creates database tables and default options.
 */
class Always_Analytics_Activator {


	/**
	 * Run activation tasks.
	 */
	public static function activate() {
		self::check_requirements();
		self::migrate_legacy_table_names();
		self::create_tables();
		self::migrate_from_advstats();
		self::set_default_options();
		self::clean_internal_referrers();
		self::schedule_crons();
		add_option( 'always_analytics_activated_at', time() );
		flush_rewrite_rules();
	}




	public static function maybe_update() {

		wp_cache_delete( 'always_analytics_version', 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		$current_version = get_option( 'always_analytics_version', '0' );

		if ( version_compare( $current_version, ALWAYS_ANALYTICS_VERSION, '<' ) ) {
			self::migrate_legacy_table_names();
			self::create_tables();
			self::migrate_from_advstats();
			self::set_default_options();
			self::clean_internal_referrers();
			self::schedule_crons();

			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time upgrade verification against a plugin-owned table; caching schema existence would be incorrect.
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'always_analytics_hits' ) ) ) {
				update_option( 'always_analytics_version', ALWAYS_ANALYTICS_VERSION );
			}
		}
	}

	/**
	 * Check minimum requirements.
	 */
	private static function check_requirements() {
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( ALWAYS_ANALYTICS_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'Always Analytics requires PHP 7.4 or later.', 'always-analytics' ),
				esc_html__( 'Plugin activation error', 'always-analytics' ),
				array( 'back_link' => true )
			);
		}
		if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
			deactivate_plugins( ALWAYS_ANALYTICS_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'Always Analytics requires WordPress 5.8 or later.', 'always-analytics' ),
				esc_html__( 'Plugin activation error', 'always-analytics' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Rename tables created by versions that used the short aa_ prefix.
	 *
	 * The rename runs before schema creation so existing data remains available after
	 * the plugin adopts the full WordPress.org-safe prefix.
	 *
	 * @return void
	 */
	private static function migrate_legacy_table_names() {
		global $wpdb;

		$suffixes = array(
			'hits',
			'sessions',
			'daily',
			'scroll',
			'campaigns',
			'link_clicks',
		);

		foreach ( $suffixes as $suffix ) {
			$legacy_table  = $wpdb->prefix . 'aa_' . $suffix;
			$current_table = $wpdb->prefix . 'always_analytics_' . $suffix;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time existence check for a legacy plugin-owned table assembled from a fixed suffix list.
			$legacy_exists = (bool) $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_table )
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time existence check for the destination plugin-owned table assembled from a fixed suffix list.
			$current_exists = (bool) $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $current_table )
			);

			if ( ! $legacy_exists || $current_exists ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Both identifiers are constructed from $wpdb->prefix and the fixed suffix allowlist above; WordPress 5.8 has no %i identifier placeholder.
			$wpdb->query( "RENAME TABLE `{$legacy_table}` TO `{$current_table}`" );
		}

		$legacy_hooks = array(
			'aa_daily_aggregate',
			'aa_daily_purge',
			'aa_expire_sessions',
		);

		foreach ( $legacy_hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}


	/**
	 * Create a table without relying on dbDelta's index parser.
	 *
	 * dbDelta can emit undefined-array-key warnings for valid index definitions
	 * on some WordPress, database, and PHP combinations. Frameworks that convert
	 * warnings to exceptions can then make plugin activation fail. Table creation
	 * is therefore performed directly, while explicit migrations below handle
	 * schema changes for existing installations.
	 *
	 * @param string $create_sql Complete CREATE TABLE statement.
	 * @return void
	 */
	private static function create_table_if_missing( $create_sql ) {
		global $wpdb;

		$query = preg_replace(
			'/^CREATE\s+TABLE\s+/i',
			'CREATE TABLE IF NOT EXISTS ',
			ltrim( $create_sql ),
			1
		);

		if ( ! is_string( $query ) || '' === $query ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Query is derived only from the plugin's fixed CREATE TABLE definitions; there are no runtime values to prepare.
		$result = $wpdb->query( $query );

		if ( false === $result && ! empty( $wpdb->last_error ) ) {
			wp_die(
				esc_html(
					sprintf(
						/* translators: %s: database error message. */
						__( 'Always Analytics could not create its database tables: %s', 'always-analytics' ),
						$wpdb->last_error
					)
				),
				esc_html__( 'Plugin activation error', 'always-analytics' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Create custom database tables.
	 */
	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql_hits = "CREATE TABLE {$wpdb->prefix}always_analytics_hits (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            visitor_hash    VARCHAR(64)     NOT NULL,
            session_id      VARCHAR(64)     NOT NULL,
            page_url        VARCHAR(2048)   NOT NULL,
            page_title      VARCHAR(512)    DEFAULT '',
            post_id         BIGINT UNSIGNED DEFAULT 0,
            post_type       VARCHAR(20)     DEFAULT '',
            referrer        VARCHAR(2048)   DEFAULT '',
            referrer_domain  VARCHAR(255)    DEFAULT '',
            utm_source      VARCHAR(255)    DEFAULT '',
            utm_medium      VARCHAR(255)    DEFAULT '',
            utm_campaign    VARCHAR(255)    DEFAULT '',
            device_type     VARCHAR(20)     DEFAULT 'unknown',
            browser         VARCHAR(100)    DEFAULT '',
            browser_version  VARCHAR(20)     DEFAULT '',
            os              VARCHAR(100)    DEFAULT '',
            os_version      VARCHAR(20)     DEFAULT '',
            screen_width    SMALLINT UNSIGNED DEFAULT 0,
            screen_height   SMALLINT UNSIGNED DEFAULT 0,
            is_new_visitor  TINYINT(1)      DEFAULT 1,
            is_logged_in    TINYINT(1)      DEFAULT 0,
            user_id         BIGINT UNSIGNED DEFAULT 0,
            scroll_depth    TINYINT UNSIGNED DEFAULT 0,
            hit_source      VARCHAR(20)     DEFAULT 'js',
            is_superseded   TINYINT(1)      DEFAULT 0,
            hit_at          DATETIME        NOT NULL,

            PRIMARY KEY  (id),
            KEY idx_hit_at_ns    (hit_at, is_superseded),
            KEY idx_vh_hit_at    (visitor_hash, hit_at),
            KEY idx_session_id   (session_id),
            KEY idx_post_id      (post_id)
        ) {$charset_collate};";

		$sql_sessions = "CREATE TABLE {$wpdb->prefix}always_analytics_sessions (
            session_id      VARCHAR(64)     NOT NULL,
            visitor_hash    VARCHAR(64)     NOT NULL,
            started_at      DATETIME        NOT NULL,
            ended_at        DATETIME        DEFAULT NULL,
            duration        INT UNSIGNED    DEFAULT 0,
            page_count      SMALLINT UNSIGNED DEFAULT 1,
            entry_page      VARCHAR(2048)   DEFAULT '',
            exit_page       VARCHAR(2048)   DEFAULT '',
            referrer        VARCHAR(2048)   DEFAULT '',
            device_type     VARCHAR(20)     DEFAULT 'unknown',
            is_bounce       TINYINT(1)      DEFAULT 1,
            max_scroll_depth  TINYINT UNSIGNED DEFAULT 0,
            engagement_time  INT UNSIGNED    DEFAULT 0,
            PRIMARY KEY  (session_id),
            KEY idx_started  (started_at),
            KEY idx_visitor  (visitor_hash)
        ) {$charset_collate};";

		$sql_daily = "CREATE TABLE {$wpdb->prefix}always_analytics_daily (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            stat_date       DATE            NOT NULL,
            page_url        VARCHAR(2048)   NOT NULL,
            post_id         BIGINT UNSIGNED DEFAULT 0,
            unique_visitors  INT UNSIGNED    DEFAULT 0,
            page_views      INT UNSIGNED    DEFAULT 0,
            sessions        INT UNSIGNED    DEFAULT 0,
            avg_duration    FLOAT           DEFAULT 0,
            bounce_rate     FLOAT           DEFAULT 0,
            new_visitors    INT UNSIGNED    DEFAULT 0,
            returning_vis   INT UNSIGNED    DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_date_page (stat_date, page_url(191)),
            KEY idx_post_id (post_id)
        ) {$charset_collate};";

		$sql_scroll = "CREATE TABLE {$wpdb->prefix}always_analytics_scroll (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id      VARCHAR(64)     NOT NULL,
            visitor_hash    VARCHAR(64)     NOT NULL,
            page_url        VARCHAR(2048)   NOT NULL,
            post_id         BIGINT UNSIGNED DEFAULT 0,
            scroll_depth    TINYINT UNSIGNED NOT NULL,
            recorded_at     DATETIME        NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_session  (session_id),
            KEY idx_page     (page_url(191)),
            KEY idx_recorded (recorded_at)
        ) {$charset_collate};";

		$sql_campaigns = "CREATE TABLE {$wpdb->prefix}always_analytics_campaigns (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_date      DATE            NOT NULL,
            label           VARCHAR(255)    NOT NULL,
            description     TEXT            NOT NULL,
            color           VARCHAR(7)      DEFAULT '#6c63ff',
            created_at      DATETIME        NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_event_date (event_date)
        ) {$charset_collate};";

		$sql_link_clicks = "CREATE TABLE {$wpdb->prefix}always_analytics_link_clicks (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id      VARCHAR(64)     NOT NULL,
            visitor_hash    VARCHAR(64)     NOT NULL,
            page_url        VARCHAR(2048)   NOT NULL,
            link_url        VARCHAR(2048)   NOT NULL,
            link_domain     VARCHAR(255)    DEFAULT '',
            link_type       VARCHAR(10)     NOT NULL DEFAULT 'outbound',
            clicked_at      DATETIME        NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_clicked_at   (clicked_at),
            KEY idx_link_type    (link_type, clicked_at),
            KEY idx_session      (session_id),
            KEY idx_link_domain  (link_domain)
        ) {$charset_collate};";

		$table_queries = array(
			$sql_hits,
			$sql_sessions,
			$sql_daily,
			$sql_scroll,
			$sql_campaigns,
			$sql_link_clicks,
		);

		foreach ( $table_queries as $table_query ) {
			self::create_table_if_missing( $table_query );
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection of a fixed plugin-owned table; current metadata is required during activation.
		$cols = $wpdb->get_col( "DESCRIBE {$wpdb->prefix}always_analytics_hits", 0 );
		if ( ! in_array( 'scroll_depth', $cols, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent schema migration on a fixed plugin-owned table.
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}always_analytics_hits ADD COLUMN scroll_depth TINYINT UNSIGNED DEFAULT 0 AFTER user_id" );
		}
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection of a fixed plugin-owned table; current metadata is required during activation.
		$cols_s = $wpdb->get_col( "DESCRIBE {$wpdb->prefix}always_analytics_sessions", 0 );
		if ( ! in_array( 'max_scroll_depth', $cols_s, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent schema migration on a fixed plugin-owned table.
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}always_analytics_sessions ADD COLUMN max_scroll_depth TINYINT UNSIGNED DEFAULT 0" );
		}
		if ( ! in_array( 'engagement_time', $cols_s, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent schema migration on a fixed plugin-owned table.
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}always_analytics_sessions ADD COLUMN engagement_time INT UNSIGNED DEFAULT 0 AFTER max_scroll_depth" );
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection of a fixed plugin-owned table; current metadata is required during activation.
		$cols = $wpdb->get_col( "DESCRIBE {$wpdb->prefix}always_analytics_hits", 0 );
		if ( ! in_array( 'hit_source', $cols, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent schema migration on a fixed plugin-owned table.
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}always_analytics_hits ADD COLUMN hit_source VARCHAR(20) DEFAULT 'js' AFTER scroll_depth" );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection of a fixed plugin-owned table; current metadata is required during activation.
			$cols = $wpdb->get_col( "DESCRIBE {$wpdb->prefix}always_analytics_hits", 0 );
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection of a fixed plugin-owned table; current metadata is required during activation.
		$indexes   = $wpdb->get_results( "SHOW INDEX FROM {$wpdb->prefix}always_analytics_hits", ARRAY_A );
		$idx_names = array_column( $indexes, 'Key_name' );
		if ( ! in_array( 'idx_hit_source', $idx_names, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent schema migration on a fixed plugin-owned table.
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}always_analytics_hits ADD INDEX idx_hit_source (hit_source)" );
		}

		if ( ! in_array( 'is_superseded', $cols, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent schema migration on a fixed plugin-owned table.
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}always_analytics_hits ADD COLUMN is_superseded TINYINT(1) DEFAULT 0 AFTER hit_source" );
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection of a fixed plugin-owned table; current metadata is required during activation.
		$indexes   = (array) $wpdb->get_results( "SHOW INDEX FROM {$wpdb->prefix}always_analytics_hits", ARRAY_A );
		$idx_names = ! empty( $indexes ) ? array_column( $indexes, 'Key_name' ) : array();
		if ( ! in_array( 'idx_hit_at_ns', $idx_names, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent index creation on a fixed plugin-owned table.
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}always_analytics_hits ADD INDEX idx_hit_at_ns (hit_at, is_superseded)" );
		}
		if ( ! in_array( 'idx_vh_hit_at', $idx_names, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent index creation on a fixed plugin-owned table.
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}always_analytics_hits ADD INDEX idx_vh_hit_at (visitor_hash, hit_at)" );
			$idx_names[] = 'idx_vh_hit_at';
		}

		// idx_hit_at and idx_visitor_hash are single-column indexes made redundant by the
		// composite idx_hit_at_ns (hit_at, is_superseded) and idx_vh_hit_at (visitor_hash, hit_at)
		// indexes above: MySQL/MariaDB can already serve any hit_at-only or visitor_hash-only
		// lookup from the leftmost prefix of those composite indexes. Dropping the single-column
		// duplicates removes write overhead and storage with no query-plan loss, but only once
		// their composite replacement actually exists.
		if ( in_array( 'idx_hit_at', $idx_names, true ) && in_array( 'idx_hit_at_ns', $idx_names, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent schema migration on a fixed plugin-owned table.
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}always_analytics_hits DROP INDEX idx_hit_at" );
		}
		if ( in_array( 'idx_visitor_hash', $idx_names, true ) && in_array( 'idx_vh_hit_at', $idx_names, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent schema migration on a fixed plugin-owned table.
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}always_analytics_hits DROP INDEX idx_visitor_hash" );
		}

		self::drop_geolocation_columns();
	}

	/**
	 * Remove the country_code/region/city columns and their index, added by the
	 * discontinued DB-IP-based geolocation feature. Runs on every activation;
	 * each check is a no-op once the columns are gone.
	 *
	 * @return void
	 */
	private static function drop_geolocation_columns() {
		global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection of a fixed plugin-owned table; current metadata is required during activation.
		$hit_indexes = $wpdb->get_results( "SHOW INDEX FROM {$wpdb->prefix}always_analytics_hits", ARRAY_A );
		$hit_idx     = ! empty( $hit_indexes ) ? array_column( $hit_indexes, 'Key_name' ) : array();
		if ( in_array( 'idx_country', $hit_idx, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent schema migration on a fixed plugin-owned table.
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}always_analytics_hits DROP INDEX idx_country" );
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection of a fixed plugin-owned table; current metadata is required during activation.
		$hit_cols = $wpdb->get_col( "DESCRIBE {$wpdb->prefix}always_analytics_hits", 0 );
		foreach ( array( 'country_code', 'region', 'city' ) as $column ) {
			if ( in_array( $column, $hit_cols, true ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column is selected from the three-value hardcoded allowlist above; WordPress 5.8 has no identifier placeholder.
				$wpdb->query( "ALTER TABLE {$wpdb->prefix}always_analytics_hits DROP COLUMN {$column}" );
			}
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection of a fixed plugin-owned table; current metadata is required during activation.
		$session_cols = $wpdb->get_col( "DESCRIBE {$wpdb->prefix}always_analytics_sessions", 0 );
		if ( in_array( 'country_code', $session_cols, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent schema migration on a fixed plugin-owned table.
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}always_analytics_sessions DROP COLUMN country_code" );
		}
	}


	/**
	 * Migrate data from old advstats_* tables and option to always_analytics_* equivalents.
	 * Runs only when the old tables/option exist — safe to call on fresh installs.
	 */
	private static function migrate_from_advstats() {
		global $wpdb;

		$old_option = get_option( 'advstats_options', null );
		if ( null !== $old_option && false === get_option( 'always_analytics_options' ) ) {
			add_option( 'always_analytics_options', $old_option );
			delete_option( 'advstats_options' );
		}

		$table_map = array(
			'advstats_hits'     => 'always_analytics_hits',
			'advstats_sessions' => 'always_analytics_sessions',
			'advstats_scroll'   => 'always_analytics_scroll',
			'advstats_daily'    => 'always_analytics_daily',
		);

		foreach ( $table_map as $old_suffix => $new_suffix ) {
			$old_table = $wpdb->prefix . $old_suffix;
			$new_table = $wpdb->prefix . $new_suffix;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time source-table existence check; table name is assembled from the fixed migration map below.
			$old_exists = (bool) $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table )
			);
			if ( ! $old_exists ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time destination-table existence check; table name is assembled from the fixed migration map below.
			$new_exists = (bool) $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table )
			);
			if ( ! $new_exists ) {
				continue;
			}

			$new_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$new_table}` LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Destination identifier is assembled from $wpdb->prefix and the fixed migration map; WordPress 5.8 has no %i placeholder.
			if ( $new_count > 0 ) {
				continue;
			}

			$old_cols = $wpdb->get_col( "DESCRIBE `{$old_table}`", 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Source identifier is assembled from $wpdb->prefix and the fixed migration map; WordPress 5.8 has no %i placeholder.
			$new_cols = $wpdb->get_col( "DESCRIBE `{$new_table}`", 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Destination identifier is assembled from $wpdb->prefix and the fixed migration map; WordPress 5.8 has no %i placeholder.

			$common = array_values(
				array_filter(
					array_intersect( $old_cols, $new_cols ),
					static function ( $column ) {
						return is_string( $column ) && 1 === preg_match( '/^[A-Za-z0-9_]+$/D', $column );
					}
				)
			);
			if ( empty( $common ) ) {
				continue;
			}

			$cols_sql = implode(
				', ',
				array_map(
					function ( $c ) {
						return '`' . $c . '`';
					},
					$common
				)
			);

			$has_id = in_array( 'id', $common, true );

			if ( $has_id ) {
				$min_id = (int) $wpdb->get_var( "SELECT MIN(id) FROM `{$old_table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Source identifier is assembled from $wpdb->prefix and the fixed migration map; WordPress 5.8 has no %i placeholder.
				$max_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM `{$old_table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Source identifier is assembled from $wpdb->prefix and the fixed migration map; WordPress 5.8 has no %i placeholder.

				$chunk = 5000;
				for ( $offset = $min_id; $offset <= $max_id; $offset += $chunk ) {
					$end = $offset + $chunk - 1;

					// Table identifiers come from the fixed migration map; column identifiers are intersected
					// DESCRIBE results restricted to [A-Za-z0-9_], and row bounds are prepared. phpcs:disable
					// is used because the flagged token is on the line after $wpdb->prepare(, past the reach
					// of a same-line/previous-line phpcs:ignore.
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query(
						$wpdb->prepare(
							"INSERT IGNORE INTO `{$new_table}` ({$cols_sql}) SELECT {$cols_sql} FROM `{$old_table}` WHERE id BETWEEN %d AND %d",
							$offset,
							$end
						)
					);
					// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}
			} else {

				// Table identifiers come from the fixed migration map and column identifiers are intersected
				// DESCRIBE results restricted to [A-Za-z0-9_]; the statement contains no runtime values.
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query(
					"INSERT IGNORE INTO `{$new_table}` ({$cols_sql}) SELECT {$cols_sql} FROM `{$old_table}`"
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Source identifier is assembled from $wpdb->prefix and the fixed migration map; the legacy table is dropped only after migration.
			$wpdb->query( "DROP TABLE IF EXISTS `{$old_table}`" );
		}

		delete_option( 'advstats_db_version' );
		delete_option( 'advstats_db_schema_version' );

		$old_crons = array(
			'advstats_daily_aggregate',
			'advstats_daily_purge',
			'advstats_expire_sessions',
		);
		foreach ( $old_crons as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options() {
		$defaults = array(
			'disable_tracking'    => false,
			'tracking_mode'       => 'cookieless',
			'excluded_roles'      => array( 'administrator' ),
			'excluded_ips'        => '',
			'anonymize_ip'        => true,
			'retention_days'      => 90,
			'delete_on_uninstall' => false,
			'cache_ttl'           => 300,
			'export_format'       => 'csv',
			'external_favicons'   => false,
			'consent_enabled'     => false,
			'consent_message'     => __( 'This site uses audience measurement cookies. Do you agree?', 'always-analytics' ),
			'consent_accept'      => __( 'Accept', 'always-analytics' ),
			'consent_decline'     => __( 'Decline', 'always-analytics' ),
			'info_message'        => __( 'This site uses privacy-focused, cookieless audience measurement. The statistics help improve the site content.', 'always-analytics' ),
			'info_ok'             => __( 'Got it', 'always-analytics' ),
			'consent_bg_color'    => '#1a1a2e',
			'consent_text_color'  => '#ffffff',
			'consent_btn_color'   => '#6c63ff',
		);

		if ( false === get_option( 'always_analytics_options' ) ) {
			add_option( 'always_analytics_options', $defaults );
		}
	}

	/**
	 * Remove historical self-referrals from acquisition data.
	 * Internal navigation is already measured by the dedicated link reports.
	 *
	 * @return void
	 */
	private static function clean_internal_referrers() {
		global $wpdb;

		if ( ! class_exists( 'Always_Analytics\\Always_Analytics_Tracker' ) ) {
			require_once ALWAYS_ANALYTICS_PLUGIN_DIR . 'includes/class-always-analytics-tracker.php';
		}

		$table = $wpdb->prefix . 'always_analytics_hits';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time activation check against a plugin-owned table; caching schema existence would be incorrect.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		$internal_hosts = Always_Analytics_Tracker::get_internal_hosts();
		if ( empty( $internal_hosts ) ) {
			return;
		}

		$host_variants = array();
		foreach ( $internal_hosts as $internal_host ) {
			$host_variants[] = $internal_host;
			$host_variants[] = 'www.' . $internal_host;
		}
		$host_variants = array_values( array_unique( $host_variants ) );
		$placeholders  = implode( ', ', array_fill( 0, count( $host_variants ), '%s' ) );

		// Placeholders are generated one-for-one from normalized internal host variants; values are passed
		// separately to prepare() via the spread operator. phpcs:disable is used because the flagged token
		// is 3 lines past this comment, and the spread call also trips the placeholder-count sniff, which
		// cannot see through a variadic spread.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}always_analytics_hits
                 SET referrer = '', referrer_domain = ''
                 WHERE LOWER(TRIM(TRAILING '.' FROM referrer_domain)) IN ({$placeholders})",
				...$host_variants
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	}

	/**
	 * Schedule WP-Cron events.
	 */
	private static function schedule_crons() {
		if ( ! wp_next_scheduled( 'always_analytics_daily_aggregate' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 02:00:00' ), 'daily', 'always_analytics_daily_aggregate' );
		}
		if ( ! wp_next_scheduled( 'always_analytics_daily_purge' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 03:00:00' ), 'daily', 'always_analytics_daily_purge' );
		}
		if ( ! wp_next_scheduled( 'always_analytics_expire_sessions' ) ) {
			wp_schedule_event( time(), 'hourly', 'always_analytics_expire_sessions' );
		}
	}
}
