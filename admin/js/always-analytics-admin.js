/**
 * Advanced Stats — Admin JS
 * Zéro cache : cache:'no-store' + _t=timestamp sur CHAQUE requête fetch.
 */
(function () {
    'use strict';

    if (typeof alwaysAnalyticsAdmin === 'undefined') return;

    var API_BASE = alwaysAnalyticsAdmin.restBase;
    var NONCE    = alwaysAnalyticsAdmin.nonce;

    var state = {
        from: dateOffset(0),
        to:   dateOffset(0),
        device:   '',
        postType: '',
        country:  '',
    };

    // ── Init ──────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        bindEvents();
        loadAllData();

        // Auto-refresh toutes les 60 s si on regarde aujourd'hui
        setInterval(function () {
            if (state.to === dateOffset(0)) loadAllData();
        }, 60000);
    });

    // Exposé globalement pour le bouton Actualiser
    window.loadAllData = loadAllData;

    // ── Events ────────────────────────────────────────────────────────────────

    function bindEvents() {
        var periodSel = document.getElementById('aa-period');
        if (periodSel) {
            periodSel.addEventListener('change', function () {
                var v = this.value;
                var customDiv = document.getElementById('aa-custom-dates');
                if (v === 'custom') { if (customDiv) customDiv.style.display = 'flex'; return; }
                if (customDiv) customDiv.style.display = 'none';
                var today = dateOffset(0);
                var map = {
                    today:  { from: today,                 to: today },
                    '7days':  { from: dateOffset(-7),        to: today },
                    '30days': { from: dateOffset(-30),       to: today },
                    '90days': { from: dateOffset(-90),       to: today },
                    year:   { from: new Date().getFullYear() + '-01-01', to: today },
                };
                if (map[v]) { state.from = map[v].from; state.to = map[v].to; }
                loadAllData();
            });
        }

        var applyBtn = document.getElementById('aa-apply-dates');
        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                var f = document.getElementById('aa-from').value;
                var t = document.getElementById('aa-to').value;
                if (f && t) { state.from = f; state.to = t; loadAllData(); }
            });
        }

        var devSel = document.getElementById('aa-device-filter');
        if (devSel) {
            devSel.addEventListener('change', function () {
                state.device = this.value; loadAllData();
            });
        }

        document.querySelectorAll('.aa-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.aa-toggle').forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');
                if (window.AlwaysAnalyticsCharts) AlwaysAnalyticsCharts.toggleDataset(this.getAttribute('data-dataset'));
            });
        });
    }

    // ── Fetch central — ZÉRO CACHE ────────────────────────────────────────────

    /**
     * Chaque appel :
     *  - cache:'no-store'  → le navigateur ne lit JAMAIS son cache
     *  - _t=timestamp      → URL unique → LiteSpeed/Varnish/CDN ne peuvent pas servir de réponse cachée
     *  - headers explicites → force tous les proxies intermédiaires
     */
    function apiFetch(endpoint, params, callback) {
        var qs = 'from='  + enc(state.from)
               + '&to='   + enc(state.to)
               + '&_t='   + Date.now();           // timestamp unique anti-cache

        if (state.device)   qs += '&device='    + enc(state.device);
        if (state.postType) qs += '&post_type='  + enc(state.postType);
        if (state.country)  qs += '&country='    + enc(state.country);
        if (params)         qs += '&' + params;

        fetch(API_BASE + endpoint + '?' + qs, {
            method:  'GET',
            cache:   'no-store',          // instruction navigateur : jamais de cache
            headers: {
                'X-WP-Nonce':     NONCE,
                'Cache-Control':  'no-cache, no-store, must-revalidate',
                'Pragma':         'no-cache',
            },
        })
        .then(function (r) {
            if (!r.ok) throw new Error(r.status);
            return r.json();
        })
        .then(callback)
        .catch(function (e) { console.error('[Always Analytics] ' + endpoint, e); });
    }

    // ── Loaders ───────────────────────────────────────────────────────────────

    function loadAllData() {
        loadOverview();
        loadChart();
        loadRecentVisitors();
        loadTopPages();
        loadReferrers();
        loadCountries();
        loadDevices();
    }

    function loadOverview() {
        apiFetch('overview', null, function (d) {
            setText('kpi-visitors',  fmt(d.unique_visitors));
            setText('kpi-pageviews', fmt(d.page_views));
            setText('kpi-sessions',  fmt(d.sessions));
            setText('kpi-duration',  fmtDuration(d.avg_duration));
            setText('kpi-bounce',    d.engagement_rate + '%');
            setChange('kpi-visitors-change',  d.change_visitors);
            setChange('kpi-pageviews-change', d.change_views);
        });
    }

    function loadChart() {
        apiFetch('chart/visits', null, function (d) {
            if (window.AlwaysAnalyticsCharts) AlwaysAnalyticsCharts.renderVisitsChart(d);
        });
    }

    function loadRecentVisitors() {
        apiFetch('recent-visitors', 'limit=5', function (data) {
            var tbody = document.querySelector('#aa-recent-visitors tbody');
            if (!tbody) return;
            if (!data || !data.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="aa-no-data">' + alwaysAnalyticsAdmin.i18n.noData + '</td></tr>';
                return;
            }
            var now = new Date();
            tbody.innerHTML = data.map(function (s) {
                var flag   = flag2(s.country_code);
                var device = s.device_type === 'mobile' ? '📱' : (s.device_type === 'tablet' ? '💊' : '💻');
                var vid    = s.visitor_hash.substring(0, 8);
                var ended  = new Date(s.ended_at + 'Z');
                var sec    = Math.floor((now - ended) / 1000);
                var time   = sec < 60 ? 'En ce moment' : 'Il y a ' + Math.floor(sec / 60) + ' min';
                var pages  = parseInt(s.total_pages, 10) || parseInt(s.last_page_count, 10) || 0;
                var dur    = parseInt(s.total_duration, 10) || 0;
                var sessions_label = parseInt(s.session_count, 10) > 1 ? ' <small style="color:#64748b">(' + s.session_count + ' visites)</small>' : '';
                return '<tr>'
                    + '<td><strong>Visiteur ' + vid + '</strong>' + sessions_label + '<br><small>' + flag + ' ' + device + '</small></td>'
                    + '<td><span class="aa-time-badge">' + time + '</span><br><small>Durée : ' + fmtDuration(dur) + '</small></td>'
                    + '<td style="text-align:center"><span class="aa-badge">' + pages + '</span></td>'
                    + '<td style="text-align:right"><a href="?page=always-analytics-visitor&visitor_hash=' + enc(s.visitor_hash) + '" class="button button-small">Voir parcours</a></td>'
                    + '</tr>';
            }).join('');
        });
    }

    function loadTopPages() {
        var link = document.getElementById('aa-all-pages-link');
        if (link) {
            var base = link.href.split('?')[0];
            link.href = base + '?page=always-analytics-top-pages&from=' + enc(state.from) + '&to=' + enc(state.to);
        }
        apiFetch('top-pages', 'limit=8', function (data) {
            var tbody = document.querySelector('#aa-top-pages tbody');
            if (!tbody) return;
            if (!data || !data.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="aa-no-data">' + alwaysAnalyticsAdmin.i18n.noData + '</td></tr>';
                return;
            }
            var max = Math.max.apply(null, data.map(function (d) { return +d.views; }));
            tbody.innerHTML = data.map(function (p) {
                var pct   = Math.round((+p.views / max) * 100);
                var title = p.page_title || p.page_url;
                return '<tr>'
                    + '<td title="' + esc(p.page_url) + '">'
                    +   '<div class="aa-bar"><span>' + esc(title) + '</span></div>'
                    +   '<div class="aa-bar-track"><div class="aa-bar-fill" style="width:' + pct + '%"></div></div>'
                    + '</td>'
                    + '<td style="text-align:right">' + fmt(+p.views) + '</td>'
                    + '<td style="text-align:right">' + fmt(+p.unique_visitors) + '</td>'
                    + '</tr>';
            }).join('');
        });
    }

    function loadReferrers() {
        apiFetch('top-referrers', 'limit=8', function (data) {
            var tbody = document.querySelector('#aa-referrers tbody');
            if (!tbody) return;
            if (!data || !data.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="aa-no-data">' + alwaysAnalyticsAdmin.i18n.noData + '</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(function (r) {
                return '<tr>'
                    + '<td>' + esc(r.referrer_domain) + '</td>'
                    + '<td style="text-align:right">' + fmt(+r.hits) + '</td>'
                    + '<td style="text-align:right">' + fmt(+r.unique_visitors) + '</td>'
                    + '</tr>';
            }).join('');
        });
    }

    function loadCountries() {
        var link = document.getElementById('aa-all-countries-link');
        if (link) {
            var base = link.href.split('?')[0];
            link.href = base + '?page=always-analytics-countries&from=' + enc(state.from) + '&to=' + enc(state.to);
        }
        apiFetch('countries', 'limit=8', function (data) {
            var tbody = document.querySelector('#aa-countries tbody');
            if (!tbody) return;
            if (!data || !data.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="aa-no-data">' + alwaysAnalyticsAdmin.i18n.noData + '</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(function (c) {
                return '<tr>'
                    + '<td>' + flag2(c.country_code) + ' ' + esc(c.country_code) + '</td>'
                    + '<td style="text-align:right">' + fmt(+c.hits) + '</td>'
                    + '<td style="text-align:right">' + parseFloat(c.percentage).toFixed(1) + '%</td>'
                    + '</tr>';
            }).join('');
        });
    }

    function loadDevices() {
        apiFetch('devices', null, function (data) {
            if (data.devices && window.AlwaysAnalyticsCharts) AlwaysAnalyticsCharts.renderDevicesChart(data.devices);

            var bl = document.getElementById('aa-browsers-list');
            if (bl && data.browsers) {
                bl.innerHTML = data.browsers.map(function (b) {
                    return '<li><span>' + esc(b.browser) + '</span><span>' + fmt(+b.count) + '</span></li>';
                }).join('') || '<li>—</li>';
            }

            var ol = document.getElementById('aa-os-list');
            if (ol && data.os) {
                ol.innerHTML = data.os.map(function (o) {
                    return '<li><span>' + esc(o.os) + '</span><span>' + fmt(+o.count) + '</span></li>';
                }).join('') || '<li>—</li>';
            }
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function setText(id, v) {
        var el = document.getElementById(id);
        if (el) el.textContent = v;
    }

    function setChange(id, v) {
        var el = document.getElementById(id);
        if (!el) return;
        if (!v) { el.textContent = ''; el.className = 'aa-kpi-change'; return; }
        el.textContent = (v > 0 ? '+' : '') + v + '%';
        el.className   = 'aa-kpi-change ' + (v >= 0 ? 'positive' : 'negative');
    }

    function fmt(n) {
        return (typeof n === 'number' ? n : parseInt(n, 10) || 0).toLocaleString('fr-FR');
    }

    function fmtDuration(s) {
        if (!s || s <= 0) return '0s';
        s = Math.round(s);
        var m = Math.floor(s / 60);
        return m > 0 ? m + 'm ' + (s % 60) + 's' : s + 's';
    }

    function dateOffset(days) {
        var d = new Date();
        d.setDate(d.getDate() + days);
        return d.getFullYear() + '-'
            + String(d.getMonth() + 1).padStart(2, '0') + '-'
            + String(d.getDate()).padStart(2, '0');
    }

    function enc(s) { return encodeURIComponent(s || ''); }

    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }

    function flag2(code) {
        if (!code || code.length !== 2) return '🌐';
        return String.fromCodePoint(0x1F1E6 + code.charCodeAt(0) - 65)
             + String.fromCodePoint(0x1F1E6 + code.charCodeAt(1) - 65);
    }

    // ── Manual anonymization button ──────────────────────────────────────────
    var purgeBtn = document.getElementById('aa-purge-btn');
    if (purgeBtn) {
        purgeBtn.addEventListener('click', function () {
            var i18n = (alwaysAnalyticsAdmin && alwaysAnalyticsAdmin.i18n) || {};
            var confirmMsg = i18n.purgeConfirm || 'Lancer l\'anonymisation maintenant ?';
            if (!window.confirm(confirmMsg)) {
                return;
            }

            purgeBtn.disabled = true;
            purgeBtn.textContent = '⏳ …';

            var result = document.getElementById('aa-purge-result');

            var data = new URLSearchParams();
            data.append('action', 'always_analytics_manual_purge');
            data.append('nonce',  alwaysAnalyticsAdmin.purgeNonce);

            fetch(alwaysAnalyticsAdmin.ajaxUrl, {
                method:      'POST',
                credentials: 'same-origin',
                headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:        data.toString(),
            })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (result) {
                    result.style.display = 'inline';
                    if (json.success) {
                        result.style.color = 'var(--aa-success, green)';
                        result.textContent = (i18n.purgeSuccess || json.data.message);
                    } else {
                        result.style.color = 'var(--aa-danger, red)';
                        result.textContent = (i18n.purgeError || 'Erreur.');
                    }
                }
            })
            .catch(function () {
                if (result) {
                    result.style.display = 'inline';
                    result.style.color   = 'var(--aa-danger, red)';
                    result.textContent   = i18n.purgeError || 'Erreur réseau.';
                }
            })
            .finally(function () {
                purgeBtn.disabled    = false;
                purgeBtn.textContent = '🔄 Lancer l\'anonymisation';
            });
        });
    }

})();
