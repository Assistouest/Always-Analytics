<?php
namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Bot filter — keeps analytics data clean by discarding non-human hits.
 *
 * This filter runs at recording time only: it decides whether a hit should
 * be stored in the database. Nothing is blocked at the HTTP level.
 *
 * Two modes (bot_filter_mode option):
 *   'normal'  → full filtering (UA, URL params, spam referrers). Default.
 *   'off'     → no filtering at all; every hit is recorded.
 *
 * The former 'strict' mode has been removed. Any stored value of 'strict'
 * is silently treated as 'normal' so existing installs are not broken.
 */
class Always_Analytics_Bot_Filter {

    /**
     * User-Agent substrings that identify bots, crawlers, and automation tools.
     * Matched case-insensitively via strpos against the lowercased UA string.
     *
     * @var string[]
     */
    private static $bot_keywords = array(
        // ── Generic bot / crawler tokens ─────────────────────────────────
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
        'scanner', 'scraper', 'archive',

        // ── System / network tools ────────────────────────────────────────
        'nagios', 'wget', 'curl', 'fetch',

        // ── Python HTTP clients ───────────────────────────────────────────
        'python-requests', 'python-urllib',

        // ── Go / Java HTTP clients ────────────────────────────────────────
        'go-http-client', 'java/', 'httpclient', 'httpurlconnection',

        // ── Node.js HTTP clients ──────────────────────────────────────────
        'axios', 'node-fetch', 'got/',

        // ── Ruby HTTP clients ─────────────────────────────────────────────
        'faraday', 'typhoeus',

        // ── PHP HTTP clients (non-browser) ────────────────────────────────
        'phpcrawl', 'guzzlehttp',

        // ── Python scraping frameworks ────────────────────────────────────
        'scrapy',

        // ── Android / mobile HTTP clients used by scraper apps ────────────
        'okhttp',

        // ── Perl HTTP clients ─────────────────────────────────────────────
        'libwww-perl', 'lwp-trivial', 'lwp-useragent',

        // ── SEO / commercial crawlers ─────────────────────────────────────
        'nutch', 'mj12bot', 'ahrefsbot', 'semrushbot', 'dotbot',
        'rogerbot', 'yandexbot', 'baiduspider', 'duckduckbot',
        'applebot', 'petalbot', 'bytespider',

        // ── AI / LLM crawlers ─────────────────────────────────────────────
        'gptbot', 'claudebot', 'anthropic', 'google-extended',
        'ccbot', 'omgilibot', 'diffbot', 'perplexitybot',

        // ── Social network preview bots (not real visits) ─────────────────
        'facebookexternalhit', 'facebot', 'twitterbot',
        'linkedinbot', 'pinterestbot', 'whatsapp',
        'telegrambot', 'discordbot', 'slackbot',

        // ── Uptime / monitoring tools ─────────────────────────────────────
        // Automated pings, not human visits.
        'monitor', 'uptimerobot', 'pingdom', 'statuscake',
        'newrelic', 'datadog', 'checker',

        // ── Performance / audit tools ─────────────────────────────────────
        // Lighthouse, PageSpeed Insights, GTmetrix, WebPageTest, etc.
        // are developer tools, not real user visits. They inflate page-view
        // counts and skew device/browser breakdowns.
        'lighthouse', 'pagespeed', 'gtmetrix', 'webpagetest',
        'chrome-lighthouse', 'google page speed',

        // ── Headless / automation browsers ────────────────────────────────
        'headlesschrome', 'phantomjs', 'selenium',
        'puppeteer', 'playwright', 'webdriver',
    );

    /**
     * Referrer domains known to inject ghost / spam referrals.
     * These send fabricated hits to appear in analytics source reports.
     *
     * @var string[]
     */
    private static $spam_referrers = array(
        'semalt.com',
        'darodar.com',
        'buttons-for-website.com',
        'social-buttons.com',
        'kambasoft.com',
        'savetubevideo.com',
        'makemoneyonline.com',
        'trafficmonetize.org',
        'buy-cheap-online.info',
        'anticrawler.org',
        'videos-for-your-business.com',
        'floating-share-buttons.com',
        'website-traffic-research.com',
    );

    /**
     * Query-string keys that are always considered legitimate.
     * These are never treated as suspicious regardless of their value.
     *
     * Covers: UTM / ad-click IDs, core WordPress navigation params,
     * standard WooCommerce store & checkout params, language switchers,
     * and common pagination / search keys.
     *
     * @var string[]
     */
    private static $safe_query_keys = array(
        // UTM / marketing tracking
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        // Ad-platform click IDs
        'fbclid', 'gclid', 'gclsrc', 'dclid', 'msclkid', 'twclid', 'ttclid',
        // WordPress core navigation
        'p', 'page_id', 'cat', 'tag', 's', 'paged', 'preview', 'preview_id',
        'post_type', 'attachment_id', 'author',
        // WooCommerce — store browsing & filtering
        'product_cat', 'product_tag', 'orderby', 'min_price', 'max_price',
        'filter_color', 'filter_size', 'filter_pa_color', 'filter_pa_size',
        'rating_filter', 'onsale_filter', 'instock_filter',
        // WooCommerce — cart & product actions
        'add-to-cart', 'quantity', 'variation_id', 'removed_item',
        'undo_item', 'notice_id',
        // WooCommerce — checkout & order confirmation (legitimate customer flow)
        'key', 'order', 'order-received', 'order-pay',
        // WooCommerce — account & password reset (real user actions)
        'lost-password', 'reset-password', 'show-reset-form', 'login',
        // WooCommerce — coupons
        'coupon_code',
        // Language switchers (Polylang, WPML, TranslatePress)
        'lang', 'language', 'locale',
        // Pagination / search / sorting
        'pg', 'page', 'per_page', 'offset', 'limit', 'sort', 'order_by',
        // Generic common safe keys
        'q', 'id', 'ref', 'v', 'n',
    );

    /**
     * Raw query-string fragments that identify automated probes targeting
     * WooCommerce or WordPress internals.
     *
     * These strings never appear in real customer sessions. Checked against
     * the raw (undecoded) query string before per-key heuristics.
     *
     * @var string[]
     */
    private static $woo_probe_patterns = array(
        // Payment endpoint name typos / probes
        // Scanners try common French/English payment keyword variants
        'paiement', 'payement', 'paiements', 'paiment',

        // WooCommerce AJAX actions probed directly as GET params on front-end URLs.
        // Legitimate wc-ajax calls go through wp-admin/admin-ajax.php, never bare.
        'wc-ajax=checkout',
        'wc-ajax=apply_coupon',
        'wc-ajax=remove_coupon',
        'wc-ajax=get_refreshed_fragments',
        'wc-ajax=wc_stripe',
        'wc-ajax=wc_paypal',

        // WooCommerce REST API credentials probed as GET params
        'consumer_key=',
        'consumer_secret=',
        'wc-api=',

        // WooCommerce OAuth flow probed on front-end
        'wc-auth/v1',

        // Generic checkout / cart path probes appended to arbitrary URLs
        'checkout-payment',
        'checkout-order',

        // WordPress admin & auth probes on front-end URLs
        'wp-admin',
        'wp-login',
        'xmlrpc',
    );

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Determine whether a User-Agent string belongs to a bot or automation tool.
     *
     * @param  string $ua_string Raw User-Agent header value.
     * @return bool   True → discard the hit.
     */
    public static function is_bot( $ua_string ) {
        // Empty or whitespace-only UA — no real browser omits this header.
        if ( '' === trim( (string) $ua_string ) ) {
            return true;
        }

        $ua_lower = strtolower( $ua_string );

        /**
         * Filters the list of bot User-Agent keywords.
         *
         * Use this hook to add project-specific UA patterns without
         * modifying the plugin core.
         *
         * @param string[] $keywords
         */
        $keywords = apply_filters( 'always_analytics_bot_user_agents', self::$bot_keywords );

        foreach ( $keywords as $keyword ) {
            if ( strpos( $ua_lower, strtolower( $keyword ) ) !== false ) {
                return true;
            }
        }

        // Very short UA strings are never produced by real browsers.
        if ( strlen( $ua_string ) < 20 ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the referrer domain is a known ghost / spam referrer.
     *
     * @param  string $referrer_domain Bare hostname (no scheme, no path).
     * @return bool   True → discard the hit.
     */
    public static function is_spam_referrer( $referrer_domain ) {
        if ( empty( $referrer_domain ) ) {
            return false;
        }

        // Normalise: lowercase, strip leading www.
        $domain = strtolower( preg_replace( '/^www\./i', '', $referrer_domain ) );

        /**
         * Filters the list of spam referrer domains.
         *
         * @param string[] $domains
         */
        $list = apply_filters( 'always_analytics_spam_referrers', self::$spam_referrers );

        foreach ( $list as $spam ) {
            if ( $domain === strtolower( $spam ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether a page URL contains query parameters that indicate
     * automated scanning rather than a real user navigation.
     *
     * Safe keys (UTM, WooCommerce checkout flow, WordPress core, etc.) are
     * explicitly whitelisted and never flagged, even if their value looks odd.
     *
     * @param  string $url Full page URL as sent by the JS tracker.
     * @return bool   True → discard the hit.
     */
    public static function has_suspicious_query_params( $url ) {
        if ( empty( $url ) ) {
            return false;
        }

        $parsed = wp_parse_url( $url );

        // No query string at all → nothing to check.
        if ( empty( $parsed['query'] ) ) {
            return false;
        }

        $query       = $parsed['query'];
        $query_lower = strtolower( $query );

        // ── 1. WooCommerce / WordPress endpoint probes ─────────────────────
        // Check the raw query string for known probe fragments first,
        // before we parse individual keys, because some patterns span
        // the key=value boundary (e.g. "wc-ajax=checkout").
        /**
         * Filters the list of WooCommerce / CMS probe patterns.
         *
         * @param string[] $patterns
         */
        $probe_patterns = apply_filters( 'always_analytics_woo_probe_patterns', self::$woo_probe_patterns );

        foreach ( $probe_patterns as $pattern ) {
            if ( strpos( $query_lower, strtolower( $pattern ) ) !== false ) {
                return true;
            }
        }

        // ── 2. Per-key heuristics ──────────────────────────────────────────
        parse_str( $query, $params );
        if ( empty( $params ) ) {
            return false;
        }

        /**
         * Filters the list of query-string keys considered always safe.
         *
         * @param string[] $keys
         */
        $safe_keys = apply_filters( 'always_analytics_safe_query_keys', self::$safe_query_keys );
        $safe_set  = array_flip( array_map( 'strtolower', $safe_keys ) );

        foreach ( $params as $raw_key => $value ) {
            $key = strtolower( $raw_key );

            // Whitelisted key → skip all checks for this param.
            if ( isset( $safe_set[ $key ] ) ) {
                continue;
            }

            // ── Heuristic A: key is ALL uppercase letters + digits, no vowel,
            //    4 or more characters. Classic random probe pattern.
            //    e.g. ?P455135N  ?XRTFQ  ?B4CK3ND
            if (
                preg_match( '/^[A-Z0-9]{4,}$/', $raw_key ) &&
                ! preg_match( '/[AEIOU]/', $raw_key )
            ) {
                return true;
            }

            // ── Heuristic B: key looks like a bare hexadecimal hash (8–64 hex chars).
            //    Real query-string keys are never raw MD5/SHA digests.
            //    e.g. ?3f2504e04f8941d39a0c0305e82c3301
            if ( preg_match( '/^[0-9a-f]{8,64}$/', $raw_key ) ) {
                return true;
            }

            // ── Heuristic C: key contains path traversal or shell metacharacters.
            //    e.g. ?../config  ?;ls  ?|whoami
            if ( preg_match( '/[\/\\\\;|`<>]/', $raw_key ) ) {
                return true;
            }

            // ── Heuristic D: value of an unknown key contains injection payloads.
            if ( is_string( $value ) ) {
                $val_lower      = strtolower( $value );
                $injection_tokens = array(
                    'select ', 'union ', 'insert ', 'drop ', 'delete from',
                    '<script', 'javascript:', 'onerror=', 'onload=',
                    '../', 'etc/passwd', 'cmd.exe', '/bin/sh',
                );
                foreach ( $injection_tokens as $token ) {
                    if ( strpos( $val_lower, $token ) !== false ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Convenience wrapper — run all three checks for a single hit.
     *
     * @param  string $ua_string       User-Agent header value.
     * @param  string $page_url        Full page URL from the JS tracker.
     * @param  string $referrer_domain Bare hostname of the referrer.
     * @return bool   True → the hit should be discarded.
     */
    public static function should_discard( $ua_string, $page_url = '', $referrer_domain = '' ) {
        return self::is_bot( $ua_string )
            || self::has_suspicious_query_params( $page_url )
            || self::is_spam_referrer( $referrer_domain );
    }

    /**
     * Return the raw bot keyword list (for admin UI or unit tests).
     *
     * @return string[]
     */
    public static function get_bot_keywords() {
        return self::$bot_keywords;
    }
}
