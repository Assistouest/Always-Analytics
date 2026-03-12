<?php
/**
 * Countries full view — Advanced Stats.
 *
 * @package Always_Analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : wp_date( 'Y-m-d' );
$to   = isset( $_GET['to'] )   ? sanitize_text_field( wp_unslash( $_GET['to'] ) )   : wp_date( 'Y-m-d' );

if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) $from = wp_date( 'Y-m-d' );
if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) )   $to   = wp_date( 'Y-m-d' );

global $wpdb;
$table = $wpdb->prefix . 'aa_hits';

// Conversion fuseau horaire du site — identique à la REST API
$tz_offset_seconds = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
$from_utc = gmdate( 'Y-m-d H:i:s', strtotime( $from . ' 00:00:00' ) - $tz_offset_seconds );
$to_utc   = gmdate( 'Y-m-d H:i:s', strtotime( $to   . ' 23:59:59' ) - $tz_offset_seconds );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$total_hits = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$table} WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0",
    $from_utc,
    $to_utc
) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$rows = $wpdb->get_results( $wpdb->prepare(
    "SELECT
        country_code,
        COUNT(*) as hits,
        COUNT(DISTINCT visitor_hash) as unique_visitors,
        COUNT(DISTINCT session_id) as sessions
     FROM {$table}
     WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0 AND country_code != ''
     GROUP BY country_code
     ORDER BY hits DESC",
    $from_utc,
    $to_utc
) );

// Country name map (ISO 3166-1 alpha-2 — common subset)
$country_names = array(
    'FR'=>'France','US'=>'États-Unis','GB'=>'Royaume-Uni','DE'=>'Allemagne',
    'ES'=>'Espagne','IT'=>'Italie','BE'=>'Belgique','CH'=>'Suisse','CA'=>'Canada',
    'NL'=>'Pays-Bas','PT'=>'Portugal','MA'=>'Maroc','TN'=>'Tunisie','DZ'=>'Algérie',
    'SN'=>'Sénégal','CI'=>'Côte d\'Ivoire','JP'=>'Japon','CN'=>'Chine','IN'=>'Inde',
    'BR'=>'Brésil','AU'=>'Australie','RU'=>'Russie','PL'=>'Pologne','SE'=>'Suède',
    'NO'=>'Norvège','DK'=>'Danemark','FI'=>'Finlande','AT'=>'Autriche','MX'=>'Mexique',
    'AR'=>'Argentine','CL'=>'Chili','CO'=>'Colombie','ZA'=>'Afrique du Sud',
    'NG'=>'Nigéria','KE'=>'Kenya','EG'=>'Égypte','SA'=>'Arabie Saoudite','TR'=>'Turquie',
    'KR'=>'Corée du Sud','SG'=>'Singapour','ID'=>'Indonésie','TH'=>'Thaïlande',
    'LU'=>'Luxembourg','IE'=>'Irlande','CZ'=>'Rép. Tchèque','HU'=>'Hongrie',
    'RO'=>'Roumanie','UA'=>'Ukraine','GR'=>'Grèce','HR'=>'Croatie',
);

/**
 * Retourne une balise <img> avec le drapeau du pays.
 * Les SVG sont servis localement depuis /assets/flags/ (lipis/flag-icons, licence MIT).
 * Aucune requête externe — compatible avec la politique de confidentialité du plugin.
 *
 * @param string $code  Code ISO 3166-1 alpha-2 (ex: "US", "FR").
 * @param int    $size  Taille en pixels (hauteur). Défaut : 16px.
 * @return string       Balise HTML <img> ou fallback texte.
 */
function aa_country_flag_php( $code, $size = 16 ) {
    if ( ! $code || strlen( $code ) !== 2 ) {
        return '<span style="display:inline-block;width:' . ( $size * 1.5 ) . 'px;height:' . $size . 'px;'
             . 'line-height:' . $size . 'px;text-align:center;background:#e2e8f0;border-radius:2px;'
             . 'font-size:11px;color:#64748b;font-weight:600;">?</span>';
    }

    $code_lower = strtolower( $code );
    $file_path  = AA_PLUGIN_DIR . 'assets/flags/' . $code_lower . '.webp';

    // Fallback : si le fichier SVG n'existe pas encore, affiche le code pays en badge
    if ( ! file_exists( $file_path ) ) {
        return '<span style="display:inline-block;padding:0 4px;height:' . $size . 'px;line-height:' . $size . 'px;'
             . 'background:#e2e8f0;border-radius:2px;font-size:10px;color:#475569;font-weight:700;vertical-align:middle;">'
             . esc_html( strtoupper( $code ) ) . '</span>';
    }

    $url = AA_PLUGIN_URL . 'assets/flags/' . $code_lower . '.webp';
    $alt = esc_attr( strtoupper( $code ) );

    return '<img src="' . esc_url( $url ) . '" alt="' . $alt . '" title="' . $alt . '" '
         . 'width="' . round( $size * 1.5 ) . '" height="' . $size . '" '
         . 'style="vertical-align:middle;border-radius:2px;object-fit:cover;" '
         . 'loading="lazy" />';
}

$max_hits     = ! empty( $rows ) ? (int) $rows[0]->hits : 1;
$label_from   = wp_date( 'd/m/Y', strtotime( $from ) );
$label_to     = wp_date( 'd/m/Y', strtotime( $to ) );
$period_label = ( $from === $to ) ? $label_from : $label_from . ' → ' . $label_to;
?>
<div class="wrap aa-wrap">

    <!-- Header -->
    <div class="aa-header" style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=always-analytics&from=' . urlencode( $from ) . '&to=' . urlencode( $to ) ) ); ?>" class="aa-back-btn">
                ← <?php esc_html_e( 'Tableau de bord', 'always-analytics' ); ?>
            </a>
            <div>
                <h1 style="margin:0;font-size:22px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:8px;">
                    🌍 <?php esc_html_e( 'Pays', 'always-analytics' ); ?>
                </h1>
                <p style="margin:4px 0 0;color:#64748b;font-size:13px;">
                    <?php echo esc_html( $period_label ); ?> &middot;
                    <strong><?php echo count( $rows ); ?></strong> <?php esc_html_e( 'pays', 'always-analytics' ); ?>
                </p>
            </div>
        </div>

        <div class="aa-detail-date-filter">
            <input type="date" id="co-from" value="<?php echo esc_attr( $from ); ?>" />
            <span style="color:#94a3b8;">→</span>
            <input type="date" id="co-to" value="<?php echo esc_attr( $to ); ?>" />
            <button id="co-apply" class="button button-primary"><?php esc_html_e( 'Appliquer', 'always-analytics' ); ?></button>
        </div>
    </div>

    <!-- KPIs -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
        <div class="aa-kpi-card" style="text-align:center;">
            <div class="aa-kpi-icon">🌍</div>
            <div class="aa-kpi-value"><?php echo count( $rows ); ?></div>
            <div class="aa-kpi-label"><?php esc_html_e( 'Pays distincts', 'always-analytics' ); ?></div>
        </div>
        <div class="aa-kpi-card" style="text-align:center;">
            <div class="aa-kpi-icon">📊</div>
            <div class="aa-kpi-value"><?php echo number_format_i18n( $total_hits ); ?></div>
            <div class="aa-kpi-label"><?php esc_html_e( 'Visites totales', 'always-analytics' ); ?></div>
        </div>
        <div class="aa-kpi-card" style="text-align:center;">
            <div class="aa-kpi-icon">🏆</div>
            <div class="aa-kpi-value" style="font-size:22px;">
                <?php
                if ( ! empty( $rows ) ) {
                    $top        = $rows[0];
                    $top_flag   = aa_country_flag_php( $top->country_code, 20 );
                    $top_name   = esc_html( $country_names[ $top->country_code ] ?? $top->country_code );
                    $allowed    = array( 'img' => array( 'src' => array(), 'alt' => array(), 'title' => array(), 'width' => array(), 'height' => array(), 'style' => array(), 'loading' => array(), 'onerror' => array() ), 'span' => array( 'style' => array() ) );
                    echo wp_kses( $top_flag, $allowed ) . ' ' . $top_name;
                } else {
                    echo '—';
                }
                ?>
            </div>
            <div class="aa-kpi-label"><?php esc_html_e( 'Pays principal', 'always-analytics' ); ?></div>
        </div>
    </div>

    <!-- Layout: table + top 5 visual -->
    <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">

        <!-- Table -->
        <div class="aa-card">
            <div class="aa-card-header" style="flex-wrap:wrap;gap:12px;">
                <h2 style="margin:0;"><?php esc_html_e( 'Tous les pays', 'always-analytics' ); ?></h2>
                <input type="search" id="co-search" placeholder="<?php esc_attr_e( 'Rechercher…', 'always-analytics' ); ?>"
                       style="padding:6px 12px;border:1px solid #e2e4e7;border-radius:8px;font-size:13px;width:220px;margin-left:auto;" />
            </div>
            <div class="aa-card-body" style="padding:0;">
                <?php if ( empty( $rows ) ) : ?>
                    <p class="aa-no-data" style="padding:40px;text-align:center;">
                        <?php esc_html_e( 'Aucune donnée pour cette période.', 'always-analytics' ); ?>
                    </p>
                <?php else : ?>
                <table class="aa-table aa-full-table" id="co-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th><?php esc_html_e( 'Pays', 'always-analytics' ); ?></th>
                            <th style="text-align:right;width:100px;"><?php esc_html_e( 'Visites', 'always-analytics' ); ?></th>
                            <th style="text-align:right;width:130px;"><?php esc_html_e( 'Visiteurs uniques', 'always-analytics' ); ?></th>
                            <th style="text-align:right;width:70px;">%</th>
                            <th style="width:140px;"><?php esc_html_e( 'Part', 'always-analytics' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $i => $row ) :
                            $pct  = $total_hits > 0 ? round( ( $row->hits / $total_hits ) * 100, 1 ) : 0;
                            $bar  = $max_hits > 0  ? round( ( $row->hits / $max_hits )  * 100 )      : 0;
                            $flag = aa_country_flag_php( $row->country_code );
                            $name = $country_names[ $row->country_code ] ?? $row->country_code;
                            $rank = $i + 1;
                            $medal = $rank === 1 ? '🥇' : ( $rank === 2 ? '🥈' : ( $rank === 3 ? '🥉' : '' ) );
                        ?>
                        <tr class="co-row">
                            <td style="color:#94a3b8;font-weight:600;font-size:13px;">
                                <?php echo $medal ? esc_html( $medal ) : esc_html( $rank ); ?>
                            </td>
                            <td>
                                <span style="margin-right:8px;"><?php echo wp_kses( $flag, array( 'img' => array( 'src' => array(), 'alt' => array(), 'title' => array(), 'width' => array(), 'height' => array(), 'style' => array(), 'loading' => array(), 'onerror' => array() ), 'span' => array( 'style' => array() ) ) ); ?></span>
                                <span style="font-weight:600;color:#0f172a;"><?php echo esc_html( $name ); ?></span>
                                <span style="color:#94a3b8;font-size:12px;margin-left:4px;"><?php echo esc_html( $row->country_code ); ?></span>
                            </td>
                            <td style="text-align:right;font-weight:700;color:#0f172a;">
                                <?php echo number_format_i18n( (int) $row->hits ); ?>
                            </td>
                            <td style="text-align:right;color:#475569;">
                                <?php echo number_format_i18n( (int) $row->unique_visitors ); ?>
                            </td>
                            <td style="text-align:right;color:#64748b;font-size:13px;">
                                <?php echo esc_html( $pct ); ?>%
                            </td>
                            <td>
                                <div style="background:#f1f5f9;border-radius:4px;height:8px;overflow:hidden;">
                                    <div style="width:<?php echo esc_attr( $bar ); ?>%;height:100%;background:linear-gradient(90deg,#6c63ff,#8b83ff);border-radius:4px;"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Side: Top 5 visual podium -->
        <?php if ( ! empty( $rows ) ) : ?>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div class="aa-card">
                <div class="aa-card-header">
                    <h2 style="margin:0;font-size:15px;">🏆 <?php esc_html_e( 'Top 5', 'always-analytics' ); ?></h2>
                </div>
                <div class="aa-card-body" style="padding:12px 16px;">
                    <?php foreach ( array_slice( $rows, 0, 5 ) as $i => $row ) :
                        $pct  = $total_hits > 0 ? round( ( $row->hits / $total_hits ) * 100, 1 ) : 0;
                        $flag = aa_country_flag_php( $row->country_code );
                        $name = $country_names[ $row->country_code ] ?? $row->country_code;
                        $colors = array('#6c63ff','#3b82f6','#10b981','#f59e0b','#ef4444');
                        $color  = $colors[ $i ] ?? '#6c63ff';
                    ?>
                    <div style="margin-bottom:14px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                            <span style="font-size:14px;display:flex;align-items:center;gap:6px;"><?php
                                echo wp_kses( $flag, array( 'img' => array( 'src' => array(), 'alt' => array(), 'title' => array(), 'width' => array(), 'height' => array(), 'style' => array(), 'loading' => array(), 'onerror' => array() ), 'span' => array( 'style' => array() ) ) );
                                echo esc_html( $name );
                            ?></span>
                            <span style="font-weight:700;font-size:13px;color:#0f172a;"><?php echo esc_html( $pct ); ?>%</span>
                        </div>
                        <div style="background:#f1f5f9;border-radius:6px;height:10px;overflow:hidden;">
                            <div style="width:<?php echo esc_attr( $pct ); ?>%;height:100%;background:<?php echo esc_attr( $color ); ?>;border-radius:6px;transition:width .4s;"></div>
                        </div>
                        <div style="font-size:11px;color:#94a3b8;margin-top:2px;"><?php echo number_format_i18n( (int) $row->hits ); ?> visites</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Continent breakdown (simple) -->
            <?php
            $continents = array(
                'Europe'   => array('FR','DE','GB','ES','IT','BE','CH','NL','PT','SE','NO','DK','FI','AT','LU','IE','CZ','HU','RO','UA','GR','HR','PL','RU'),
                'Amériques'=> array('US','CA','MX','BR','AR','CL','CO'),
                'Afrique'  => array('MA','TN','DZ','SN','CI','ZA','NG','KE','EG'),
                'Asie'     => array('JP','CN','IN','KR','SG','ID','TH','SA','TR'),
                'Océanie'  => array('AU'),
            );
            $cont_totals = array();
            foreach ( $rows as $row ) {
                foreach ( $continents as $cont => $codes ) {
                    if ( in_array( $row->country_code, $codes, true ) ) {
                        $cont_totals[ $cont ] = ( $cont_totals[ $cont ] ?? 0 ) + $row->hits;
                        break;
                    }
                }
            }
            arsort( $cont_totals );
            ?>
            <?php if ( ! empty( $cont_totals ) ) : ?>
            <div class="aa-card">
                <div class="aa-card-header">
                    <h2 style="margin:0;font-size:15px;">🗺️ <?php esc_html_e( 'Par continent', 'always-analytics' ); ?></h2>
                </div>
                <div class="aa-card-body" style="padding:12px 16px;">
                    <?php $cont_max = reset( $cont_totals ); ?>
                    <?php foreach ( $cont_totals as $cont => $hits ) :
                        $cpct = $cont_max > 0 ? round( ( $hits / $cont_max ) * 100 ) : 0;
                        $gpct = $total_hits > 0 ? round( ( $hits / $total_hits ) * 100, 1 ) : 0;
                    ?>
                    <div style="margin-bottom:12px;">
                        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:3px;">
                            <span style="font-weight:500;color:#0f172a;"><?php echo esc_html( $cont ); ?></span>
                            <span style="color:#64748b;"><?php echo esc_html( $gpct ); ?>%</span>
                        </div>
                        <div style="background:#f1f5f9;border-radius:4px;height:6px;overflow:hidden;">
                            <div style="width:<?php echo esc_attr( $cpct ); ?>%;height:100%;background:linear-gradient(90deg,#6c63ff,#8b83ff);border-radius:4px;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="aa-footer" style="margin-top:24px;">
        <p>Always Analytics v<?php echo esc_html( AA_VERSION ); ?></p>
    </div>
</div>

<script>
(function () {
    var applyBtn = document.getElementById('co-apply');
    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            var from = document.getElementById('co-from').value;
            var to   = document.getElementById('co-to').value;
            if (from && to) {
                window.location.href = '<?php echo esc_js( admin_url( 'admin.php?page=always-analytics-countries' ) ); ?>&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to);
            }
        });
    }
    var searchInput = document.getElementById('co-search');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var q = this.value.toLowerCase();
            var rows = document.querySelectorAll('#co-table .co-row');
            rows.forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }
})();
</script>
