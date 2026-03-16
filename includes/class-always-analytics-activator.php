<?php
namespace Always_Analytics;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin activator — creates database tables and default options.
 */
class Always_Analytics_Activator
{

    /**
     * Run activation tasks.
     */
    public static function activate()
    {
        self::check_requirements();
        // Sur activation (nouveau install ou réactivation), on force toujours
        // la création des tables, indépendamment de la version stockée.
        // Cela couvre le cas où la table n'a jamais été créée malgré une
        // version déjà enregistrée (ex: dbDelta silencieusement échoué).
        self::create_tables();
        self::migrate_from_statify();
        self::migrate_from_advstats();
        self::set_default_options();
        self::schedule_crons();
        flush_rewrite_rules();
    }

    /**
     * Check if an update is needed and run migrations.
     * Called on admin_init pour les mises à jour sans réactivation.
     */
    public static function maybe_update()
    {
        // Clear object cache to avoid stale version values
        wp_cache_delete('always_analytics_version', 'options');
        wp_cache_delete('alloptions', 'options');

        $current_version = get_option('always_analytics_version', '0');

        if (version_compare($current_version, AA_VERSION, '<')) {
            self::create_tables();
            self::migrate_from_statify();   // upgrade depuis Statify (nom précédent)
            self::migrate_from_advstats();  // upgrade depuis advstats (nom d'origine)
            // Ne marquer la version comme à jour QUE si la table principale existe.
            // Évite le verrou définitif si dbDelta échoue silencieusement.
            global $wpdb;
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'aa_hits' ) ) ) {
                update_option('always_analytics_version', AA_VERSION);
            }
        }
    }

    /**
     * Check minimum requirements.
     */
    private static function check_requirements()
    {
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(AA_PLUGIN_BASENAME);
            wp_die(
                esc_html__('Advanced Stats requires PHP 7.4 or higher.', 'always-analytics'),
                'Plugin Activation Error',
                array('back_link' => true)
            );
        }
        if (version_compare(get_bloginfo('version'), '5.8', '<')) {
            deactivate_plugins(AA_PLUGIN_BASENAME);
            wp_die(
                esc_html__('Advanced Stats requires WordPress 5.8 or higher.', 'always-analytics'),
                'Plugin Activation Error',
                array('back_link' => true)
            );
        }
    }

    /**
     * Create custom database tables.
     */
    private static function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_hits = $wpdb->prefix . 'aa_hits';
        $table_daily = $wpdb->prefix . 'aa_daily';
        $table_sessions = $wpdb->prefix . 'aa_sessions';

        $sql_hits = "CREATE TABLE {$table_hits} (
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
            country_code    CHAR(2)         DEFAULT '',
            region          VARCHAR(100)    DEFAULT '',
            city            VARCHAR(100)    DEFAULT '',
            is_new_visitor  TINYINT(1)      DEFAULT 1,
            is_logged_in    TINYINT(1)      DEFAULT 0,
            user_id         BIGINT UNSIGNED DEFAULT 0,
            scroll_depth    TINYINT UNSIGNED DEFAULT 0,
            hit_source      VARCHAR(20)     DEFAULT 'js',
            is_superseded   TINYINT(1)      DEFAULT 0,
            hit_at          DATETIME        NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_hit_at       (hit_at),
            KEY idx_hit_at_ns    (hit_at, is_superseded),
            KEY idx_vh_hit_at    (visitor_hash, hit_at),
            KEY idx_visitor_hash (visitor_hash),
            KEY idx_session_id   (session_id),
            KEY idx_post_id      (post_id),
            KEY idx_country      (country_code)
        ) {$charset_collate};";

        $sql_sessions = "CREATE TABLE {$table_sessions} (
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
            country_code    CHAR(2)         DEFAULT '',
            is_bounce       TINYINT(1)      DEFAULT 1,
            max_scroll_depth  TINYINT UNSIGNED DEFAULT 0,
            engagement_time  INT UNSIGNED    DEFAULT 0,
            PRIMARY KEY  (session_id),
            KEY idx_started  (started_at),
            KEY idx_visitor  (visitor_hash)
        ) {$charset_collate};";

        $sql_daily = "CREATE TABLE {$table_daily} (
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

        $table_scroll = $wpdb->prefix . 'aa_scroll';

        $sql_scroll = "CREATE TABLE {$table_scroll} (
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

        $table_campaigns = $wpdb->prefix . 'aa_campaigns';
        $sql_campaigns = "CREATE TABLE {$table_campaigns} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_date      DATE            NOT NULL,
            label           VARCHAR(255)    NOT NULL,
            description     TEXT            DEFAULT '',
            color           VARCHAR(7)      DEFAULT '#6c63ff',
            created_at      DATETIME        NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_event_date (event_date)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_hits);
        dbDelta($sql_sessions);
        dbDelta($sql_daily);
        dbDelta($sql_scroll);
        dbDelta($sql_campaigns);

        // Migration si table existante : ajouter les colonnes manquantes
        $cols = $wpdb->get_col($wpdb->prepare("DESCRIBE {$table_hits}"), 0);
        if (!in_array('scroll_depth', $cols, true)) {
            $wpdb->query($wpdb->prepare("ALTER TABLE {$table_hits} ADD COLUMN scroll_depth TINYINT UNSIGNED DEFAULT 0 AFTER user_id"));
        }
        $cols_s = $wpdb->get_col($wpdb->prepare("DESCRIBE {$table_sessions}"), 0);
        if (!in_array('max_scroll_depth', $cols_s, true)) {
            $wpdb->query($wpdb->prepare("ALTER TABLE {$table_sessions} ADD COLUMN max_scroll_depth TINYINT UNSIGNED DEFAULT 0"));
        }
        if (!in_array('engagement_time', $cols_s, true)) {
            $wpdb->query($wpdb->prepare("ALTER TABLE {$table_sessions} ADD COLUMN engagement_time INT UNSIGNED DEFAULT 0 AFTER max_scroll_depth"));
        }

        // ── v1.2 migrations ────────────────────────────────────────────────────
        // Colonne hit_source : distingue hits JS, noscript, pre_consent
        // Rafraîchissement de $cols ici car scroll_depth a pu être ajouté juste avant.
        $cols = $wpdb->get_col($wpdb->prepare("DESCRIBE {$table_hits}"), 0);
        if (!in_array('hit_source', $cols, true)) {
            $wpdb->query($wpdb->prepare("ALTER TABLE {$table_hits} ADD COLUMN hit_source VARCHAR(20) DEFAULT 'js' AFTER scroll_depth"));
            // Rafraîchir $cols après l'ALTER pour que les vérifications suivantes soient exactes.
            $cols = $wpdb->get_col($wpdb->prepare("DESCRIBE {$table_hits}"), 0);
        }

        // Index sur hit_source pour les filtrages dashboard
        $indexes  = $wpdb->get_results($wpdb->prepare("SHOW INDEX FROM {$table_hits}"), ARRAY_A);
        $idx_names = array_column($indexes, 'Key_name');
        if (!in_array('idx_hit_source', $idx_names, true)) {
            $wpdb->query($wpdb->prepare("ALTER TABLE {$table_hits} ADD INDEX idx_hit_source (hit_source)"));
        }

        // Colonne is_superseded sur hits (pre_consent fusionné → marquer pour exclusion).
        // IMPORTANT : doit être ajoutée AVANT les index idx_hit_at_ns qui la référencent.
        if (!in_array('is_superseded', $cols, true)) {
            $wpdb->query($wpdb->prepare("ALTER TABLE {$table_hits} ADD COLUMN is_superseded TINYINT(1) DEFAULT 0 AFTER hit_source"));
        }

        // ── P-11 / P-12 / P-13 — Index composites ────────────────────────────────
        // idx_hit_at_ns : couvre le filtre hit_at + is_superseded = 0 présent sur toutes les requêtes.
        // idx_vh_hit_at : accélère is_new_visitor() mode cookieless (visitor_hash + hit_at range).
        // Rechargement de SHOW INDEX après les ALTERs ci-dessus pour avoir l'état réel.
        // Ajout conditionnel (idempotent) : safe sur installations fraîches comme sur mises à jour.
        $indexes   = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM {$table_hits}" ), ARRAY_A );
        $idx_names = array_column( $indexes, 'Key_name' );
        if ( ! in_array( 'idx_hit_at_ns', $idx_names, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "ALTER TABLE {$table_hits} ADD INDEX idx_hit_at_ns (hit_at, is_superseded)" );
        }
        if ( ! in_array( 'idx_vh_hit_at', $idx_names, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "ALTER TABLE {$table_hits} ADD INDEX idx_vh_hit_at (visitor_hash, hit_at)" );
        }
    }


    /**
     * Migrate data from the previous "Statify" branding (statify_* → aa_*).
     * Runs only when statify_* tables or options exist — safe on fresh installs.
     */
    private static function migrate_from_statify()
    {
        global $wpdb;

        // ── Option ────────────────────────────────────────────────────────────
        $old_option = get_option('statify_options', null);
        if (null !== $old_option && false === get_option('always_analytics_options')) {
            add_option('always_analytics_options', $old_option);
        }
        delete_option('statify_options');
        delete_option('statify_db_version');
        delete_option('statify_db_schema_version');

        // ── Tables ────────────────────────────────────────────────────────────
        $table_map = array(
            'statify_hits'     => 'aa_hits',
            'statify_sessions' => 'aa_sessions',
            'statify_scroll'   => 'aa_scroll',
            'statify_daily'    => 'aa_daily',
        );

        foreach ($table_map as $old_suffix => $new_suffix) {
            $old_table = $wpdb->prefix . $old_suffix;
            $new_table = $wpdb->prefix . $new_suffix;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $old_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $old_table));
            if (! $old_exists) {
                continue;
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $new_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $new_table));
            if (! $new_exists) {
                continue;
            }
            // Skip if destination already has data
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $new_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$new_table}` LIMIT 1")); // phpcs:ignore
            if ($new_count > 0) {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $old_cols = $wpdb->get_col($wpdb->prepare("DESCRIBE `{$old_table}`"), 0); // phpcs:ignore
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $new_cols = $wpdb->get_col($wpdb->prepare("DESCRIBE `{$new_table}`"), 0); // phpcs:ignore
            $common   = array_intersect($old_cols, $new_cols);
            if (empty($common)) {
                continue;
            }

            $cols_sql = implode(', ', array_map(function ($c) { return '`' . $c . '`'; }, $common));
            $has_id   = in_array('id', $common, true);

            if ($has_id) {
                $min_id = (int) $wpdb->get_var($wpdb->prepare("SELECT MIN(id) FROM `{$old_table}`")); // phpcs:ignore
                $max_id = (int) $wpdb->get_var($wpdb->prepare("SELECT MAX(id) FROM `{$old_table}`")); // phpcs:ignore
                $chunk  = 5000;
                for ($offset = $min_id; $offset <= $max_id; $offset += $chunk) {
                    $end = $offset + $chunk - 1;
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $wpdb->query($wpdb->prepare(
                        "INSERT IGNORE INTO `{$new_table}` ({$cols_sql}) SELECT {$cols_sql} FROM `{$old_table}` WHERE id BETWEEN %d AND %d",
                        $offset, $end
                    ));
                }
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO `{$new_table}` ({$cols_sql}) SELECT {$cols_sql} FROM `{$old_table}`"
                ));
            }

            // Drop old table
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS `{$old_table}`")); // phpcs:ignore
        }

        // ── Cron hooks ────────────────────────────────────────────────────────
        $old_crons = array(
            'statify_daily_aggregate',
            'statify_daily_purge',
            'statify_expire_sessions',
        );
        foreach ($old_crons as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }

    /**
     * Migrate data from old advstats_* tables and option to aa_* equivalents.
     * Runs only when the old tables/option exist — safe to call on fresh installs.
     */
    private static function migrate_from_advstats()
    {
        global $wpdb;

        // ── Option ────────────────────────────────────────────────────────────
        $old_option = get_option('advstats_options', null);
        if (null !== $old_option && false === get_option('always_analytics_options')) {
            add_option('always_analytics_options', $old_option);
            delete_option('advstats_options');
        }

        // ── Tables ────────────────────────────────────────────────────────────
        $table_map = array(
            'advstats_hits' => 'aa_hits',
            'advstats_sessions' => 'aa_sessions',
            'advstats_scroll' => 'aa_scroll',
            'advstats_daily' => 'aa_daily',
        );

        foreach ($table_map as $old_suffix => $new_suffix) {
            $old_table = $wpdb->prefix . $old_suffix;
            $new_table = $wpdb->prefix . $new_suffix;

            // Old table must exist
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $old_exists = (bool)$wpdb->get_var(
                $wpdb->prepare('SHOW TABLES LIKE %s', $old_table)
            );
            if (!$old_exists) {
                continue;
            }

            // New table must exist (created just before by create_tables())
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $new_exists = (bool)$wpdb->get_var(
                $wpdb->prepare('SHOW TABLES LIKE %s', $new_table)
            );
            if (!$new_exists) {
                continue;
            }

            // Skip if new table already has data (migration already ran)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $new_count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$new_table}` LIMIT 1")); // phpcs:ignore
            if ($new_count > 0) {
                continue;
            }

            // Determine common columns between old and new table to avoid
            // INSERT errors if schemas differ slightly.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $old_cols = $wpdb->get_col($wpdb->prepare("DESCRIBE `{$old_table}`"), 0); // phpcs:ignore
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $new_cols = $wpdb->get_col($wpdb->prepare("DESCRIBE `{$new_table}`"), 0); // phpcs:ignore

            $common = array_intersect($old_cols, $new_cols);
            if (empty($common)) {
                continue;
            }

            $cols_sql = implode(', ', array_map(function ($c) {
                return '`' . $c . '`';
            }, $common));

            // Batch copy in chunks of 5 000 rows to avoid memory issues on
            // large sites. Uses AUTO_INCREMENT id when available, otherwise
            // copies all at once.
            $has_id = in_array('id', $common, true);

            if ($has_id) {
                $min_id = (int)$wpdb->get_var($wpdb->prepare("SELECT MIN(id) FROM `{$old_table}`")); // phpcs:ignore
                $max_id = (int)$wpdb->get_var($wpdb->prepare("SELECT MAX(id) FROM `{$old_table}`")); // phpcs:ignore

                $chunk = 5000;
                for ($offset = $min_id; $offset <= $max_id; $offset += $chunk) {
                    $end = $offset + $chunk - 1;
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $wpdb->query($wpdb->prepare(
                        "INSERT IGNORE INTO `{$new_table}` ({$cols_sql})
                         SELECT {$cols_sql} FROM `{$old_table}`
                         WHERE id BETWEEN %d AND %d",
                        $offset, $end
                    ));
                }
            }
            else {
                // No id column (sessions use session_id as PK)
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO `{$new_table}` ({$cols_sql})
                     SELECT {$cols_sql} FROM `{$old_table}`"
                ));
            }

            // Drop old table once data is safely in the new one
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS `{$old_table}`"));
        }

        // Also clean up old db_version option
        delete_option('advstats_db_version');
        delete_option('advstats_db_schema_version');

        // Clean up old cron hooks
        $old_crons = array(
            'advstats_daily_aggregate',
            'advstats_daily_purge',
            'advstats_expire_sessions',
        );
        foreach ($old_crons as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options()
    {
        $defaults = array(
            'disable_tracking' => false,
            'tracking_mode' => 'cookieless', // 'cookieless' or 'cookie'
            'excluded_roles' => array('administrator'),
            'excluded_ips' => '',
            'anonymize_ip' => true, // FORCÉ true — requis RGPD mode cookieless
            'retention_days' => 90,
            'delete_on_uninstall' => true,
            'geo_enabled' => true,
            'geo_provider'    => 'native',
            'maxmind_db_path' => '',
            'cache_ttl' => 300, // 5 minutes
            'bot_filter_mode' => 'normal', // 'normal' or 'off'
            'export_format' => 'csv',
            'consent_enabled' => false,
            'consent_message' => __('Ce site utilise des cookies pour analyser le trafic. Acceptez-vous ?', 'always-analytics'),
            'consent_accept' => __('Accepter', 'always-analytics'),
            'consent_decline' => __('Refuser', 'always-analytics'),
            'consent_bg_color' => '#1a1a2e',
            'consent_text_color' => '#ffffff',
            'consent_btn_color' => '#6c63ff',
        );

        // Only set defaults if option doesn't exist yet (fresh install or post-migration)
        if (false === get_option('always_analytics_options')) {
            add_option('always_analytics_options', $defaults);
        }
    }

    /**
     * Schedule WP-Cron events.
     */
    private static function schedule_crons()
    {
        if (!wp_next_scheduled('aa_daily_aggregate')) {
            wp_schedule_event(strtotime('tomorrow 02:00:00'), 'daily', 'aa_daily_aggregate');
        }
        if (!wp_next_scheduled('aa_daily_purge')) {
            wp_schedule_event(strtotime('tomorrow 03:00:00'), 'daily', 'aa_daily_purge');
        }
        if (!wp_next_scheduled('always_analytics_expire_sessions')) {
            wp_schedule_event(time(), 'hourly', 'always_analytics_expire_sessions');
        }
    }
}
