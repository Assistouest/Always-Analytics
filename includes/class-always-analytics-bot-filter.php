<?php
namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bot filter v2 — Multi-signal scoring engine.
 *
 * Instead of the v1 binary "keyword found → discard", every hit is now
 * scored across multiple independent signals.  Only when the cumulative
 * score reaches the threshold is the hit discarded.
 *
 * This dramatically reduces false positives: a phone called "Cubot"
 * no longer triggers an instant block — it merely adds a small score
 * (25) that alone never reaches the threshold (50).  A real bot, on
 * the other hand, will trip 2-3 signals at once and easily cross it.
 *
 * ── Signal categories ──────────────────────────────────────────────────
 *
 *   1. UA keyword match (word-boundary-aware, tiered by confidence)
 *   2. UA structural analysis (too short, empty, malformed browser claim)
 *   3. HTTP header anomaly detection (missing Accept-Language, etc.)
 *   4. Screen resolution plausibility
 *   5. JS challenge token verification (passive proof-of-browser)
 *   6. Automation flags from client (navigator.webdriver)
 *   7. Spam referrer detection
 *   8. Suspicious query-string parameters / probes
 *
 * ── Threshold ──────────────────────────────────────────────────────────
 *
 *   Score >= 50  →  discard (in 'normal' mode)
 *   Score <  50  →  record as normal visit
 *   Mode 'off'   →  no filtering, everything recorded
 *
 * ── JS challenge ───────────────────────────────────────────────────────
 *
 *   A lightweight, invisible proof-of-browser.  The server injects a
 *   rotating nonce in the page; the JS tracker hashes it with a simple
 *   algorithm and sends the result back.  It is NOT a CAPTCHA — zero
 *   user interaction, zero latency.  It merely proves that *real* JS
 *   executed in a *real* DOM context.
 *
 *   Missing token = +15  (could be a privacy extension blocking inline JS)
 *   Invalid token = +40  (likely forged request, not a browser)
 *
 *   These are deliberately LOW — the challenge alone never blocks.
 *   It only tips the balance when combined with other weak signals.
 */
class Always_Analytics_Bot_Filter {





	/**
	 * Cumulative score at which a hit is discarded in 'normal' mode.
	 * Chosen so that one single weak signal (25) never blocks,
	 * but two weak signals (25+25=50) or one strong signal (≥50) do.
	 */
	const DISCARD_THRESHOLD = 50;




	const W_UA_DEFINITE   = 90;
	const W_UA_LIKELY     = 60;
	const W_UA_SUSPICIOUS = 25;


	const W_UA_EMPTY = 90;
	const W_UA_SHORT = 55;


	const W_UA_INCOHERENT = 30;


	const W_NO_ACCEPT_LANG = 20;
	const W_LIB_ACCEPT     = 35;


	const W_SCREEN_ZERO   = 25;
	const W_SCREEN_ABSURD = 20;


	const W_NO_CHALLENGE  = 15;
	const W_BAD_CHALLENGE = 40;


	const W_WEBDRIVER      = 50;
	const W_NO_BROWSER_ENV = 20;
	const W_HEADLESS_COMBO = 60;


	const W_SPAM_REFERRER = 90;
	const W_SUSPICIOUS_QS = 90;
	const W_PROBE_PATTERN = 85;







	const W_DATACENTER    = 50;
	const W_NO_FETCH_META = 20;
	const W_CROSS_SITE    = 90;
	const W_NO_ORIGIN     = 15;
	const W_EMPTY_BROWSER = 25;








	private static $definite_bots = array(

		'googlebot',
		'bingbot',
		'yandexbot',
		'baiduspider',
		'duckduckbot',
		'slurp',
		'applebot',
		'naverbot',
		'seznambot',


		'googleother',
		'google-inspectiontool',
		'storebot-google',
		'google-safety',
		'feedfetcher-google',
		'apis-google',

		'bingpreview',
		'adidxbot',

		'yeti',

		'qwantbot',
		'qwantify',
		'barkrowler',
		'coccocbot',
		'mojeekbot',
		'seekportbot',


		'ahrefsbot',
		'semrushbot',
		'mj12bot',
		'dotbot',
		'rogerbot',
		'petalbot',
		'bytespider',
		'sogou',
		'blexbot',
		'linkdexbot',
		'exabot',
		'yoozbot',
		'serpstatbot',
		'aspiegelbot',
		'megaindex',
		'awariobot',
		'zoominfobot',
		'criteobot',
		'siteauditbot',
		'screaming frog',


		'gptbot',
		'chatgpt-user',
		'oai-searchbot',
		'claudebot',
		'claude-web',
		'claude-searchbot',
		'anthropic-ai',
		'google-extended',
		'google-cloudvertexbot',
		'ccbot',
		'omgilibot',
		'omgili',
		'diffbot',
		'perplexitybot',
		'perplexity-user',
		'cohere-ai',
		'cohere-training-data-crawler',
		'amazonbot',
		'meta-externalagent',
		'meta-externalfetcher',
		'facebookbot',
		'bytespider',
		'youbot',
		'you.com',
		'applebot-extended',
		'duckassistbot',
		'mistralai-user',
		'timpibot',
		'dataforseobot',
		'imagesiftbot',
		'webzio-extended',
		'ai2bot',
		'ai2bot-dolma',
		'kagibot',
		'iaskbot',


		'facebookexternalhit',
		'facebot',
		'twitterbot',
		'linkedinbot',
		'pinterestbot',
		'whatsapp',
		'telegrambot',
		'discordbot',
		'slackbot',
		'redditbot',
		'snapchat',
		'vkshare',
		'w3c_validator',


		'uptimerobot',
		'pingdom',
		'statuscake',
		'newrelicpinger',
		'datadogsynthetics',
		'site24x7',
		'hetrixtools',
		'checkmarknetwork',


		'lighthouse',
		'pagespeed',
		'gtmetrix',
		'webpagetest',
		'chrome-lighthouse',
		'google page speed',


		'headlesschrome',
		'phantomjs',


		'archive.org_bot',
		'ia_archiver',


		'mediapartners',
	);



	private static $likely_bots = array(

		'python-requests',
		'python-urllib',
		'python-httpx',
		'aiohttp',
		'httpx/',
		'scrapy',


		'go-http-client',


		'java/',
		'apache-httpclient',
		'okhttp',


		'node-fetch',
		'undici',
		'got/',
		'axios/',


		'faraday',
		'typhoeus',
		'rest-client',


		'guzzlehttp',
		'phpcrawl',
		'symfony/',


		'libwww-perl',
		'lwp-trivial',


		'restsharp',


		'wget/',
		'curl/',


		'selenium',
		'puppeteer',
		'playwright',
		'webdriver',
		'cypress/',


		'nutch/',
		'heritrix/',
	);





	private static $suspicious_keywords = array(
		'crawl',
		'spider',
		'scraper',
		'scanner',
		'nagios',
		'monitor',
		'checker',
		'fetch',
	);





	private static $false_positive_uas = array(
		'cubot',
		'hubot',
		'aboutblank',
		'turbotax',
		'lobster',
		'spideroak',
		'robotics',
		'outbrain',
		'fetchapi',
		'embedded',
		'monitorstand',
	);



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
		'best-seo-offer.com',
		'best-seo-solution.com',
		'get-free-traffic-now.com',
		'success-seo.com',
		'theguardlan.com',
		'econom.co',
		'guardlink.org',
		'ilovevitaly.com',
		'priceg.com',
		'hulfingtonpost.com',
		'cenoval.ru',
	);



	private static $safe_query_keys = array(

		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',

		'fbclid',
		'gclid',
		'gclsrc',
		'dclid',
		'msclkid',
		'twclid',
		'ttclid',

		'p',
		'page_id',
		'cat',
		'tag',
		's',
		'paged',
		'preview',
		'preview_id',
		'post_type',
		'attachment_id',
		'author',

		'product_cat',
		'product_tag',
		'orderby',
		'min_price',
		'max_price',
		'filter_color',
		'filter_size',
		'filter_pa_color',
		'filter_pa_size',
		'rating_filter',
		'onsale_filter',
		'instock_filter',

		'add-to-cart',
		'quantity',
		'variation_id',
		'removed_item',
		'undo_item',
		'notice_id',

		'key',
		'order',
		'order-received',
		'order-pay',

		'lost-password',
		'reset-password',
		'show-reset-form',
		'login',

		'coupon_code',

		'lang',
		'language',
		'locale',

		'pg',
		'page',
		'per_page',
		'offset',
		'limit',
		'sort',
		'order_by',

		'q',
		'id',
		'ref',
		'v',
		'n',
	);



	private static $woo_probe_patterns = array(
		'paiement',
		'payement',
		'paiements',
		'paiment',
		'wc-ajax=checkout',
		'wc-ajax=apply_coupon',
		'wc-ajax=remove_coupon',
		'wc-ajax=get_refreshed_fragments',
		'wc-ajax=wc_stripe',
		'wc-ajax=wc_paypal',
		'consumer_key=',
		'consumer_secret=',
		'wc-api=',
		'wc-auth/v1',
		'checkout-payment',
		'checkout-order',
		'wp-admin',
		'wp-login',
		'xmlrpc',
	);





	/**
	 * Generate a nonce for the current page load.
	 *
	 * Rotates every 12 hours, site-specific.  NOT a WP nonce (which is
	 * user-specific and breaks page caching).
	 *
	 * @return string 16-char hex nonce.
	 */
	public static function generate_challenge_nonce() {
		$secret = self::get_challenge_secret();
		$period = (int) floor( time() / ( 12 * HOUR_IN_SECONDS ) );
		$raw    = hash_hmac( 'sha256', (string) $period, $secret );
		return substr( $raw, 0, 16 );
	}

	/**
	 * Verify a challenge token sent by the JS tracker.
	 *
	 * Accepts both current and previous 12-hour period to handle
	 * visitors who loaded the page just before a rotation.
	 *
	 * @param  string $token        Challenge token sent by the client.
	 * @param  int    $screen_width Screen width reported by client.
	 * @param  string $nav_lang     navigator.language reported by client.
	 * @return string 'valid', 'invalid', or 'missing'.
	 */
	public static function verify_challenge( $token, $screen_width, $nav_lang ) {
		if ( empty( $token ) ) {
			return 'missing';
		}

		$secret = self::get_challenge_secret();
		$sw     = (string) absint( $screen_width );
		$lang   = sanitize_text_field( (string) $nav_lang );

		for ( $offset = 0; $offset <= 2; $offset++ ) {
			$period = (int) floor( time() / ( 12 * HOUR_IN_SECONDS ) ) - $offset;
			$nonce  = substr( hash_hmac( 'sha256', (string) $period, $secret ), 0, 16 );

			$input    = $nonce . '|' . $sw . '|' . $lang;
			$expected = substr( hash( 'sha256', $input ), -8 );

			if ( hash_equals( $expected, $token ) ) {
				return 'valid';
			}
		}

		return 'invalid';
	}

	/**
	 * @return string 64-char hex secret (auto-generated, stored in wp_options).
	 */
	private static function get_challenge_secret() {
		$secret = get_option( 'always_analytics_challenge_secret', '' );
		if ( empty( $secret ) || strlen( $secret ) < 32 ) {
			$secret = bin2hex( random_bytes( 32 ) );
			update_option( 'always_analytics_challenge_secret', $secret, true );
		}
		return $secret;
	}








	public static function score_hit( $context ) {
		$signals = array();
		$total   = 0;

		$ua         = isset( $context['ua_string'] ) ? (string) $context['ua_string'] : '';
		$hit_source = isset( $context['hit_source'] ) ? $context['hit_source'] : 'js';

		$ua_score  = 0;
		$ua_detail = '';

		if ( '' === trim( $ua ) ) {
			$ua_score  = self::W_UA_EMPTY;
			$ua_detail = 'empty UA';
		} elseif ( strlen( $ua ) < 20 ) {
			$ua_score  = self::W_UA_SHORT;
			$ua_detail = 'UA too short (' . strlen( $ua ) . ' chars)';
		} else {

			$is_fp    = false;
			$ua_lower = strtolower( $ua );
			foreach ( self::$false_positive_uas as $fp ) {
				if ( strpos( $ua_lower, strtolower( $fp ) ) !== false ) {
					$is_fp = true;
					break;
				}
			}

			if ( ! $is_fp ) {
				$match = self::match_ua_keywords( $ua_lower );
				if ( $match ) {
					$ua_score  = $match['weight'];
					$ua_detail = $match['keyword'] . ' (' . $match['tier'] . ')';
				}
			}
		}

		if ( $ua_score > 0 ) {
			$signals[] = array(
				'name'   => 'ua_keyword',
				'score'  => $ua_score,
				'detail' => $ua_detail,
			);
			$total    += $ua_score;
		}

		if ( strlen( $ua ) >= 20 && $ua_score < self::W_UA_LIKELY ) {
			$coherence = self::check_ua_coherence( $ua );
			if ( $coherence ) {
				$signals[] = array(
					'name'   => 'ua_coherence',
					'score'  => self::W_UA_INCOHERENT,
					'detail' => $coherence,
				);
				$total    += self::W_UA_INCOHERENT;
			}
		}

		$accept_lang = isset( $context['accept_lang'] ) ? $context['accept_lang'] : null;
		$accept      = isset( $context['accept'] ) ? $context['accept'] : null;

		if ( null !== $accept_lang && '' === trim( (string) $accept_lang ) ) {
			$signals[] = array(
				'name'   => 'no_accept_lang',
				'score'  => self::W_NO_ACCEPT_LANG,
				'detail' => 'empty Accept-Language',
			);
			$total    += self::W_NO_ACCEPT_LANG;
		}

		if ( 'noscript' === $hit_source && null !== $accept && '' !== (string) $accept ) {
			$al = strtolower( (string) $accept );
			if (
				strpos( $al, 'text/html' ) === false &&
				strpos( $al, 'application/xhtml' ) === false &&
				strpos( $al, '*/*' ) !== false
			) {
				$signals[] = array(
					'name'   => 'lib_accept',
					'score'  => self::W_LIB_ACCEPT,
					'detail' => 'Accept header looks like HTTP library',
				);
				$total    += self::W_LIB_ACCEPT;
			}
		}

		if ( 'noscript' !== $hit_source ) {
			$sw = isset( $context['screen_width'] ) ? (int) $context['screen_width'] : -1;
			$sh = isset( $context['screen_height'] ) ? (int) $context['screen_height'] : -1;

			if ( $sw === 0 && $sh === 0 ) {
				$signals[] = array(
					'name'   => 'screen_zero',
					'score'  => self::W_SCREEN_ZERO,
					'detail' => '0x0 screen',
				);
				$total    += self::W_SCREEN_ZERO;
			} elseif ( $sw > 10000 || $sh > 10000 ) {
				$signals[] = array(
					'name'   => 'screen_absurd',
					'score'  => self::W_SCREEN_ABSURD,
					'detail' => $sw . 'x' . $sh,
				);
				$total    += self::W_SCREEN_ABSURD;
			}
		}

		if ( 'noscript' !== $hit_source ) {
			$ct      = isset( $context['challenge_token'] ) ? $context['challenge_token'] : '';
			$ct_sw   = isset( $context['screen_width'] ) ? $context['screen_width'] : 0;
			$ct_lang = isset( $context['nav_lang'] ) ? $context['nav_lang'] : '';

			$ct_result = self::verify_challenge( $ct, $ct_sw, $ct_lang );

			if ( 'missing' === $ct_result ) {
				$signals[] = array(
					'name'   => 'no_challenge',
					'score'  => self::W_NO_CHALLENGE,
					'detail' => 'JS challenge token missing',
				);
				$total    += self::W_NO_CHALLENGE;
			} elseif ( 'invalid' === $ct_result ) {
				$signals[] = array(
					'name'   => 'bad_challenge',
					'score'  => self::W_BAD_CHALLENGE,
					'detail' => 'JS challenge token invalid',
				);
				$total    += self::W_BAD_CHALLENGE;
			}
		}

		if ( 'noscript' !== $hit_source ) {

			$webdriver = isset( $context['webdriver'] ) ? (bool) $context['webdriver'] : false;
			if ( $webdriver ) {
				$signals[] = array(
					'name'   => 'webdriver',
					'score'  => self::W_WEBDRIVER,
					'detail' => 'navigator.webdriver = true',
				);
				$total    += self::W_WEBDRIVER;
			}

			$plugins = isset( $context['plugins_count'] ) ? (int) $context['plugins_count'] : -1;
			$langs   = isset( $context['nav_languages'] ) ? (string) $context['nav_languages'] : '';

			if ( 0 === $plugins && ( '' === $langs || '0' === $langs ) ) {
				$signals[] = array(
					'name'   => 'no_browser_env',
					'score'  => self::W_NO_BROWSER_ENV,
					'detail' => '0 plugins + no languages',
				);
				$total    += self::W_NO_BROWSER_ENV;
			}

			if ( $sw === 0 && $sh === 0 && 0 === $plugins && ( '' === $langs || '0' === $langs ) ) {
				$signals[] = array(
					'name'   => 'headless_combo',
					'score'  => self::W_HEADLESS_COMBO,
					'detail' => 'headless fingerprint (0x0 + no plugins + no langs)',
				);
				$total    += self::W_HEADLESS_COMBO;
			}
		}

		// Modern browsers normally provide Fetch Metadata for same-origin collector calls.
		// Missing metadata is only a weak signal because older and privacy-focused browsers may omit it.
		$fetch_site = isset( $context['sec_fetch_site'] ) ? (string) $context['sec_fetch_site'] : '';
		$fetch_mode = isset( $context['sec_fetch_mode'] ) ? (string) $context['sec_fetch_mode'] : '';
		if ( 'cross-site' === $fetch_site ) {
			$signals[] = array(
				'name'   => 'cross_site_collector',
				'score'  => self::W_CROSS_SITE,
				'detail' => 'Sec-Fetch-Site=cross-site',
			);
			$total    += self::W_CROSS_SITE;
		} elseif ( '' === $fetch_site && '' === $fetch_mode ) {
			$signals[] = array(
				'name'   => 'no_fetch_metadata',
				'score'  => self::W_NO_FETCH_META,
				'detail' => 'Fetch Metadata missing',
			);
			$total    += self::W_NO_FETCH_META;
		}

		if ( empty( $context['origin_present'] ) ) {
			$signals[] = array(
				'name'   => 'no_origin',
				'score'  => self::W_NO_ORIGIN,
				'detail' => 'Origin and Referer missing',
			);
			$total    += self::W_NO_ORIGIN;
		}

		$timezone = isset( $context['timezone'] ) ? trim( (string) $context['timezone'] ) : '';
		$depth    = isset( $context['color_depth'] ) ? (int) $context['color_depth'] : 0;
		$hardware = isset( $context['hardware'] ) ? (int) $context['hardware'] : 0;
		if ( '' === $timezone && 0 === $depth && 0 === $hardware ) {
			$signals[] = array(
				'name'   => 'empty_browser_fingerprint',
				'score'  => self::W_EMPTY_BROWSER,
				'detail' => 'timezone/color depth/hardware unavailable',
			);
			$total    += self::W_EMPTY_BROWSER;
		}

		$client_ip = isset( $context['ip'] ) ? (string) $context['ip'] : '';
		if ( '' !== $client_ip && Always_Analytics_Datacenter::is_datacenter_ip( $client_ip ) ) {
			$signals[] = array(
				'name'   => 'datacenter_ip',
				'score'  => self::W_DATACENTER,
				'detail' => 'IP in datacenter range',
			);
			$total    += self::W_DATACENTER;
		}

		$ref_domain = isset( $context['referrer_domain'] ) ? $context['referrer_domain'] : '';
		if ( self::is_spam_referrer( $ref_domain ) ) {
			$signals[] = array(
				'name'   => 'spam_referrer',
				'score'  => self::W_SPAM_REFERRER,
				'detail' => $ref_domain,
			);
			$total    += self::W_SPAM_REFERRER;
		}

		$page_url = isset( $context['page_url'] ) ? $context['page_url'] : '';
		$qs       = self::check_query_params( $page_url );
		if ( $qs ) {
			$qs_w      = $qs['is_probe'] ? self::W_PROBE_PATTERN : self::W_SUSPICIOUS_QS;
			$signals[] = array(
				'name'   => 'suspicious_qs',
				'score'  => $qs_w,
				'detail' => $qs['detail'],
			);
			$total    += $qs_w;
		}

		$total = min( 100, $total );

		$dominant = '';
		$max_s    = 0;
		foreach ( $signals as $sig ) {
			if ( $sig['score'] > $max_s ) {
				$max_s    = $sig['score'];
				$dominant = $sig['name'];
			}
		}

		return array(
			'score'        => $total,
			'dominated_by' => $dominant,
			'signals'      => $signals,
		);
	}




	public static function should_discard( $context ) {
		$result = self::score_hit( $context );
		return $result['score'] >= self::DISCARD_THRESHOLD;
	}





	/**
	 * Legacy v1 method — still functional for callers using the old API.
	 *
	 * @param  string $ua_string
	 * @return bool
	 */
	public static function is_bot( $ua_string ) {
		return self::should_discard( array( 'ua_string' => $ua_string ) );
	}

	/**
	 * @param  string $referrer_domain
	 * @return bool
	 */
	public static function is_spam_referrer( $referrer_domain ) {
		if ( empty( $referrer_domain ) ) {
			return false;
		}

		$domain = strtolower( preg_replace( '/^www\./i', '', $referrer_domain ) );

		/** @var string[] */
		$list = apply_filters( 'always_analytics_spam_referrers', self::$spam_referrers );

		foreach ( $list as $spam ) {
			if ( $domain === strtolower( $spam ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Legacy v1 method — now a thin wrapper.
	 *
	 * @param  string $url
	 * @return bool
	 */
	public static function has_suspicious_query_params( $url ) {
		return (bool) self::check_query_params( $url );
	}

	/**
	 * Combined keyword lists (for admin UI / unit tests).
	 *
	 * @return string[]
	 */
	public static function get_bot_keywords() {
		return array_merge( self::$definite_bots, self::$likely_bots, self::$suspicious_keywords );
	}





	/**
	 * Match a lowercased UA against the three keyword tiers.
	 *
	 * Uses word-boundary-aware matching: the character before and after
	 * the match must NOT be a letter.  This prevents "Cubot" from
	 * matching the "bot" keyword.
	 *
	 * Keywords containing '/' or '-' use plain strpos (these characters
	 * are natural delimiters).
	 *
	 * @param  string $ua_lower
	 * @return array|null [ 'keyword', 'tier', 'weight' ] or null.
	 */
	private static function match_ua_keywords( $ua_lower ) {
		$tiers = array(
			'definite'   => array(
				'list'   => 'definite_bots',
				'weight' => self::W_UA_DEFINITE,
			),
			'likely'     => array(
				'list'   => 'likely_bots',
				'weight' => self::W_UA_LIKELY,
			),
			'suspicious' => array(
				'list'   => 'suspicious_keywords',
				'weight' => self::W_UA_SUSPICIOUS,
			),
		);

		/**
		 * Filters extra bot UA patterns added by site owners.
		 *
		 * @param string[] $keywords
		 */
		$custom = apply_filters( 'always_analytics_bot_user_agents', array() );

		foreach ( $tiers as $tier_name => $info ) {
			$keywords = self::${$info['list']};

			foreach ( $keywords as $keyword ) {
				$kw = strtolower( $keyword );
				if ( self::ua_word_match( $ua_lower, $kw ) ) {
					return array(
						'keyword' => $keyword,
						'tier'    => $tier_name,
						'weight'  => $info['weight'],
					);
				}
			}
		}

		foreach ( $custom as $keyword ) {
			if ( self::ua_word_match( $ua_lower, strtolower( $keyword ) ) ) {
				return array(
					'keyword' => $keyword,
					'tier'    => 'custom',
					'weight'  => self::W_UA_LIKELY,
				);
			}
		}

		return null;
	}

	/**
	 * Word-boundary-aware substring match.
	 *
	 * @param  string $haystack Lowercased UA.
	 * @param  string $needle   Lowercased keyword.
	 * @return bool
	 */
	private static function ua_word_match( $haystack, $needle ) {

		if ( strpos( $needle, '/' ) !== false || strpos( $needle, '-' ) !== false ) {
			return strpos( $haystack, $needle ) !== false;
		}

		$pos        = 0;
		$needle_len = strlen( $needle );
		$hay_len    = strlen( $haystack );

		while ( ( $pos = strpos( $haystack, $needle, $pos ) ) !== false ) {
			$before_ok = ( 0 === $pos ) || ! ctype_alpha( $haystack[ $pos - 1 ] );
			$after_pos = $pos + $needle_len;
			$after_ok  = ( $after_pos >= $hay_len ) || ! ctype_alpha( $haystack[ $after_pos ] );

			if ( $before_ok && $after_ok ) {
				return true;
			}
			++$pos;
		}

		return false;
	}

	/**
	 * Check UA structural coherence.
	 *
	 * @param  string $ua
	 * @return string|null Description of incoherence, or null.
	 */
	private static function check_ua_coherence( $ua ) {
		$l = strtolower( $ua );

		if ( strpos( $l, 'chrome/' ) !== false && strpos( $l, 'applewebkit' ) === false ) {
			return 'claims Chrome, missing AppleWebKit';
		}

		if ( strpos( $l, 'firefox/' ) !== false && strpos( $l, 'gecko/' ) === false ) {
			return 'claims Firefox, missing Gecko';
		}

		if (
			strpos( $l, 'safari/' ) !== false &&
			strpos( $l, 'chrome/' ) === false &&
			strpos( $l, 'chromium/' ) === false &&
			strpos( $l, 'applewebkit' ) === false
		) {
			return 'claims Safari, missing AppleWebKit';
		}

		return null;
	}

	/**
	 * Analyse query-string parameters for probes / injections.
	 *
	 * @param  string $url
	 * @return array|null [ 'is_probe' => bool, 'detail' => string ] or null.
	 */
	private static function check_query_params( $url ) {
		if ( empty( $url ) ) {
			return null;
		}

		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['query'] ) ) {
			return null;
		}

		$query       = $parsed['query'];
		$query_lower = strtolower( $query );

		/** @var string[] */
		$probes = apply_filters( 'always_analytics_woo_probe_patterns', self::$woo_probe_patterns );

		foreach ( $probes as $pattern ) {
			if ( strpos( $query_lower, strtolower( $pattern ) ) !== false ) {
				return array(
					'is_probe' => true,
					'detail'   => 'probe: ' . $pattern,
				);
			}
		}

		parse_str( $query, $params );
		if ( empty( $params ) ) {
			return null;
		}

		/** @var string[] */
		$safe     = apply_filters( 'always_analytics_safe_query_keys', self::$safe_query_keys );
		$safe_set = array_flip( array_map( 'strtolower', $safe ) );

		foreach ( $params as $raw_key => $value ) {
			$key = strtolower( $raw_key );

			if ( isset( $safe_set[ $key ] ) ) {
				continue;
			}

			if (
				preg_match( '/^[A-Z0-9]{4,}$/', $raw_key ) &&
				! preg_match( '/[AEIOU]/', $raw_key )
			) {
				return array(
					'is_probe' => false,
					'detail'   => 'random key: ' . $raw_key,
				);
			}

			if ( preg_match( '/^[0-9a-f]{8,64}$/', $raw_key ) ) {
				return array(
					'is_probe' => false,
					'detail'   => 'hex key',
				);
			}

			if ( preg_match( '/[\/\\\\;|`<>]/', $raw_key ) ) {
				return array(
					'is_probe' => false,
					'detail'   => 'metachar in key',
				);
			}

			if ( is_string( $value ) ) {
				$vl         = strtolower( $value );
				$injections = array(
					'select ',
					'union ',
					'insert ',
					'drop ',
					'delete from',
					'<script',
					'javascript:',
					'onerror=',
					'onload=',
					'../',
					'etc/passwd',
					'cmd.exe',
					'/bin/sh',
				);
				foreach ( $injections as $token ) {
					if ( strpos( $vl, $token ) !== false ) {
						return array(
							'is_probe' => false,
							'detail'   => 'injection: ' . $token,
						);
					}
				}
			}
		}

		return null;
	}
}
