<?php
/**
 * Public shortcodes.
 *
 * @package AlwaysAnalytics
 */

namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders public shortcodes.
 */
final class Always_Analytics_Shortcodes {

	/**
	 * Registers plugin shortcodes.
	 *
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'always_analytics_visitors', array( __CLASS__, 'render_visitors_banner' ) );
		add_shortcode( 'always_analytics_popular_posts', array( __CLASS__, 'render_popular_posts' ) );
	}

	/**
	 * Renders a compact audience summary.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_visitors_banner( $atts ) {
		global $wpdb;

		shortcode_atts( array(), $atts, 'always_analytics_visitors' );
		self::enqueue_styles();

		$cache_key = 'always_analytics_visitors_banner_' . wp_date( 'Y-m-d' );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (string) $cached;
		}

		$today  = wp_date( 'Y-m-d' );
		$from   = wp_date( 'Y-m-d', strtotime( '-7 days' ) );
		$offset = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

		$from_utc = gmdate( 'Y-m-d H:i:s', strtotime( $from . ' 00:00:00' ) - $offset );
		$to_utc   = gmdate( 'Y-m-d H:i:s', strtotime( $today . ' 23:59:59' ) - $offset );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned reporting table with transient caching below.
		$page_views = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}always_analytics_hits WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0",
				$from_utc,
				$to_utc
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned reporting table with transient caching below.
		$top_domains = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT referrer_domain, COUNT(DISTINCT session_id) AS sessions
				FROM {$wpdb->prefix}always_analytics_hits
				WHERE hit_at >= %s
					AND hit_at <= %s
					AND is_superseded = 0
					AND referrer_domain IS NOT NULL
					AND referrer_domain != ''
				GROUP BY referrer_domain
				ORDER BY sessions DESC
				LIMIT 10",
				$from_utc,
				$to_utc
			)
		);

		$ai_domains  = self::get_ai_domains();
		$best_source = null;
		$best_ai     = null;

		foreach ( $top_domains as $domain ) {
			$domain_name = strtolower( (string) $domain->referrer_domain );
			$is_ai       = self::is_ai_domain( $domain_name, $ai_domains );

			if ( $is_ai && null === $best_ai ) {
				$best_ai = $domain;
			} elseif ( ! $is_ai && null === $best_source ) {
				$best_source = $domain;
			}

			if ( null !== $best_source && null !== $best_ai ) {
				break;
			}
		}

		ob_start();
		?>
		<div class="always-analytics-sidebar-widget">
			<div class="always-analytics-live-badge">
				<span class="always-analytics-live-dot" aria-hidden="true"></span>
				<?php echo esc_html__( 'Live audience', 'always-analytics' ); ?>
			</div>

			<div class="always-analytics-stats-header">
				<span class="always-analytics-stats-number"><?php echo esc_html( number_format_i18n( $page_views ) ); ?></span>
				<span class="always-analytics-stats-label"><?php echo esc_html__( 'page views in the last 7 days', 'always-analytics' ); ?></span>
			</div>

			<?php if ( null !== $best_source || null !== $best_ai ) : ?>
				<div class="always-analytics-sources-list">
					<?php if ( null !== $best_source ) : ?>
						<?php self::render_source( $best_source->referrer_domain, __( 'Top source', 'always-analytics' ), false ); ?>
					<?php endif; ?>
					<?php if ( null !== $best_ai ) : ?>
						<?php self::render_source( $best_ai->referrer_domain, __( 'Top AI source', 'always-analytics' ), true ); ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		$html = (string) ob_get_clean();
		set_transient( $cache_key, $html, 15 * MINUTE_IN_SECONDS );

		return $html;
	}

	/**
	 * Renders the most engaging posts.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_popular_posts( $atts ) {
		global $wpdb;

		$atts = shortcode_atts(
			array(
				'limit'             => 5,
				'days'              => 30,
				'show_reading_time' => 'yes',
			),
			$atts,
			'always_analytics_popular_posts'
		);

		$limit             = min( 20, max( 1, absint( $atts['limit'] ) ) );
		$days              = min( 365, max( 1, absint( $atts['days'] ) ) );
		$show_reading_time = 'yes' === strtolower( sanitize_key( $atts['show_reading_time'] ) );
		$language          = function_exists( 'pll_current_language' ) ? (string) pll_current_language() : '';

		self::enqueue_styles();

		$cache_key = 'always_analytics_popular_posts_' . md5( $language . '|' . $days . '|' . $limit );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (string) $cached;
		}

		$offset   = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		$from_utc = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) - $offset );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned reporting tables with transient caching below.
		$popular_posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					h.post_id,
					COUNT(*) AS page_views,
					AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time WHEN s.duration > 0 THEN s.duration ELSE 0 END) AS avg_engagement,
					COUNT(DISTINCT CASE WHEN s.session_id IS NOT NULL THEN h.session_id END) AS total_sessions,
					COUNT(DISTINCT CASE WHEN s.is_bounce = 0 THEN h.session_id END) AS engaged_sessions,
					AVG(CASE WHEN s.max_scroll_depth > 0 THEN s.max_scroll_depth ELSE 0 END) AS avg_scroll,
					COUNT(DISTINCT CASE WHEN rv.visit_count > 1 THEN h.visitor_hash END) AS returning_visitors,
					COUNT(DISTINCT h.visitor_hash) AS unique_visitors,
					COALESCE(AVG(CASE WHEN s.page_count > 0 THEN s.page_count ELSE 0 END), 0) AS avg_depth
				FROM {$wpdb->prefix}always_analytics_hits h
				LEFT JOIN {$wpdb->prefix}always_analytics_sessions s ON s.session_id = h.session_id
				LEFT JOIN (
					SELECT visitor_hash, post_id, COUNT(DISTINCT session_id) AS visit_count
					FROM {$wpdb->prefix}always_analytics_hits
					WHERE hit_at >= %s AND is_superseded = 0 AND post_id > 0
					GROUP BY visitor_hash, post_id
				) rv ON rv.visitor_hash = h.visitor_hash AND rv.post_id = h.post_id
				WHERE h.hit_at >= %s AND h.is_superseded = 0 AND h.post_id > 0
				GROUP BY h.post_id
				HAVING total_sessions >= 5
				LIMIT %d",
				$from_utc,
				$from_utc,
				$limit * 5
			)
		);

		if ( empty( $popular_posts ) ) {
			return '<p class="always-analytics-no-data">' . esc_html__( 'No data is available yet.', 'always-analytics' ) . '</p>';
		}

		$ranked_posts = self::calculate_wilson_scores( $popular_posts );
		$posts_data   = array();

		foreach ( $ranked_posts as $row ) {
			$post = get_post( $row['post_id'] );
			if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
				continue;
			}

			if ( function_exists( 'pll_get_post_language' ) && function_exists( 'pll_current_language' ) ) {
				if ( pll_get_post_language( $post->ID ) !== pll_current_language() ) {
					continue;
				}
			}

			$posts_data[] = array(
				'post'           => $post,
				'avg_engagement' => $row['avg_engagement'],
			);

			if ( count( $posts_data ) >= $limit ) {
				break;
			}
		}

		if ( empty( $posts_data ) ) {
			return '<p class="always-analytics-no-data">' . esc_html__( 'No published posts are available yet.', 'always-analytics' ) . '</p>';
		}

		ob_start();
		?>
		<div class="always-analytics-posts-list">
			<?php foreach ( $posts_data as $item ) : ?>
				<?php
				$post       = $item['post'];
				$thumbnail  = get_the_post_thumbnail_url( $post->ID, 'medium_large' );
				$title      = get_the_title( $post->ID );
				$permalink  = get_permalink( $post->ID );
				$excerpt    = wp_trim_words( get_the_excerpt( $post ), 15, '&hellip;' );
				$engagement = self::format_duration( (int) round( $item['avg_engagement'] ) );
				?>
				<article class="always-analytics-post-card">
					<?php if ( $thumbnail ) : ?>
						<a class="always-analytics-post-thumb" href="<?php echo esc_url( $permalink ); ?>">
							<img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
						</a>
					<?php endif; ?>
					<div class="always-analytics-post-content">
						<h4 class="always-analytics-post-title"><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a></h4>
						<?php if ( $excerpt ) : ?>
							<p class="always-analytics-post-excerpt"><?php echo esc_html( $excerpt ); ?></p>
						<?php endif; ?>
						<?php if ( $show_reading_time ) : ?>
							<span class="always-analytics-post-meta">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: average engaged reading time. */
										__( '%s average engaged reading time', 'always-analytics' ),
										$engagement
									)
								);
								?>
							</span>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
		$html = (string) ob_get_clean();
		set_transient( $cache_key, $html, 6 * HOUR_IN_SECONDS );

		return $html;
	}

	/**
	 * Loads the public stylesheet only when a shortcode is rendered.
	 *
	 * @return void
	 */
	private static function enqueue_styles() {
		wp_enqueue_style(
			'always-analytics-public',
			ALWAYS_ANALYTICS_PLUGIN_URL . 'public/css/always-analytics-public.css',
			array(),
			ALWAYS_ANALYTICS_VERSION
		);
	}

	/**
	 * Returns known AI referrer domains.
	 *
	 * @return string[]
	 */
	private static function get_ai_domains() {
		return array(
			'andisearch.com',
			'anthropic.com',
			'bard.google.com',
			'bing.com/chat',
			'brave.com/leo',
			'chat.com',
			'chat.openai.com',
			'chatgpt.com',
			'claude.ai',
			'copilot.microsoft.com',
			'gemini.google.com',
			'kagi.com',
			'leo.ai',
			'openai.com',
			'perplexity.ai',
			'phind.com',
			'pi.ai',
			'poe.com',
			'searchgpt.com',
			'you.com',
		);
	}

	/**
	 * Checks whether a referrer matches a known AI source.
	 *
	 * @param string   $domain     Referrer domain.
	 * @param string[] $ai_domains Known AI domains.
	 * @return bool
	 */
	private static function is_ai_domain( $domain, $ai_domains ) {
		foreach ( $ai_domains as $ai_domain ) {
			if ( false !== strpos( $domain, $ai_domain ) ) {
				return true;
			}
		}

		return false;
	}




	private static function render_source( $domain, $label, $is_ai ) {
		$domain = preg_replace( '/^www\./i', '', sanitize_text_field( (string) $domain ) );
		$class  = $is_ai ? ' always-analytics-source-ai' : '';
		$icon   = $is_ai ? '✦' : '↗';
		?>
		<div class="always-analytics-source-item<?php echo esc_attr( $class ); ?>" title="<?php echo esc_attr( $label ); ?>">
			<span class="always-analytics-source-icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
			<span class="always-analytics-domain"><?php echo esc_html( $domain ); ?></span>
		</div>
		<?php
	}

	/**
	 * Calculates a conservative engagement score for each post.
	 *
	 * @param array<int, object> $rows Database result rows.
	 * @return array<int, array<string, float|int>>
	 */
	private static function calculate_wilson_scores( $rows ) {
		$weights = array(
			'duration'   => 22 / 92,
			'scroll'     => 20 / 92,
			'engagement' => 20 / 92,
			'returning'  => 18 / 92,
			'depth'      => 12 / 92,
		);
		$z       = 1.96;
		$z2      = $z * $z;
		$values  = array_filter(
			array_map(
				static function ( $row ) {
					return (float) $row->avg_engagement;
				},
				$rows
			),
			static function ( $value ) {
				return $value > 0;
			}
		);

		sort( $values );
		$count  = count( $values );
		$median = 120.0;
		if ( $count > 0 ) {
			$middle = (int) floor( $count / 2 );
			$median = 0 === $count % 2 ? ( $values[ $middle - 1 ] + $values[ $middle ] ) / 2 : $values[ $middle ];
		}
		$median = max( 10.0, $median );

		$ranked = array();
		foreach ( $rows as $row ) {
			$total_sessions = max( 1, (int) $row->total_sessions );
			$unique         = max( 1, (int) $row->unique_visitors );
			$duration       = max( 0.0, (float) $row->avg_engagement );
			$raw_score      = min( 1.0, $duration / ( 2.0 * $median ) ) * $weights['duration'];
			$raw_score     += ( max( 0.0, min( 100.0, (float) $row->avg_scroll ) ) / 100 ) * $weights['scroll'];
			$raw_score     += min( 1.0, max( 0, (int) $row->engaged_sessions ) / $total_sessions ) * $weights['engagement'];
			$raw_score     += min( 1.0, ( max( 0, (int) $row->returning_visitors ) / $unique ) / 0.5 ) * $weights['returning'];
			$raw_score     += min( 1.0, max( 0.0, ( (float) $row->avg_depth - 1.0 ) / 4.0 ) ) * $weights['depth'];

			$wilson = (
				$raw_score + $z2 / ( 2 * $total_sessions )
				- $z * sqrt( $raw_score * ( 1 - $raw_score ) / $total_sessions + $z2 / ( 4 * $total_sessions * $total_sessions ) )
			) / ( 1 + $z2 / $total_sessions );

			$ranked[] = array(
				'post_id'        => absint( $row->post_id ),
				'avg_engagement' => $duration,
				'wilson_score'   => $wilson,
			);
		}

		usort(
			$ranked,
			static function ( $left, $right ) {
				return $right['wilson_score'] <=> $left['wilson_score'];
			}
		);

		return $ranked;
	}

	/**
	 * Formats a duration in seconds.
	 *
	 * @param int $seconds Duration in seconds.
	 * @return string
	 */
	private static function format_duration( $seconds ) {
		$seconds = max( 0, absint( $seconds ) );
		if ( $seconds < MINUTE_IN_SECONDS ) {
			return sprintf(
				/* translators: %d: number of seconds. */
				_n( '%d second', '%d seconds', $seconds, 'always-analytics' ),
				$seconds
			);
		}

		$minutes = (int) floor( $seconds / MINUTE_IN_SECONDS );
		return sprintf(
			/* translators: %d: number of minutes. */
			_n( '%d minute', '%d minutes', $minutes, 'always-analytics' ),
			$minutes
		);
	}
}
