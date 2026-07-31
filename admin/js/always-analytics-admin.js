


(function () {
    'use strict';

    if (typeof alwaysAnalyticsAdmin === 'undefined') return;

    var API_BASE = alwaysAnalyticsAdmin.restBase;
    var NONCE    = alwaysAnalyticsAdmin.nonce;
    var locale   = alwaysAnalyticsAdmin.locale || document.documentElement.lang || 'en-US';
    var I18N     = alwaysAnalyticsAdmin.i18n || {};

    var state = {
        from: dateOffset(0),
        to:   dateOffset(0),
        device:   '',
        postType: '',
    };

    function handleExternalFaviconEvent( event ) {
        var image = event.target;
        if ( ! image || ! image.matches || ! image.matches( 'img[data-always-analytics-favicon]' ) ) {
            return;
        }

        var fallback = image.nextElementSibling;
        if ( 'error' === event.type ) {
            image.hidden = true;
            if ( fallback ) {
                fallback.hidden = false;
            }
            return;
        }

        image.hidden = false;
        if ( fallback ) {
            fallback.hidden = true;
        }
    }

    // Capture image events because favicon rows are rendered after REST requests.
    // No inline event attributes are used, so this also works with strict CSP rules.
    document.addEventListener( 'load', handleExternalFaviconEvent, true );
    document.addEventListener( 'error', handleExternalFaviconEvent, true );


    document.addEventListener('DOMContentLoaded', function () {
        bindEvents();
        loadAllData();


        setInterval(function () {
            if (state.to === dateOffset(0)) loadAllData();
        }, 60000);
    });


    window.loadAllData = loadAllData;


    function bindEvents() {
        var periodSel = document.getElementById('always-analytics-period');
        if (periodSel) {
            periodSel.addEventListener('change', function () {
                var v     = this.value;
                var today = dateOffset(0);
                var map = {
                    today:     { from: today,                              to: today },
                    yesterday: { from: dateOffset(-1),                     to: dateOffset(-1) },
                    '7days':   { from: dateOffset(-7),                     to: today },
                    '30days':  { from: dateOffset(-30),                    to: today },
                    '90days':  { from: dateOffset(-90),                    to: today },
                    year:      { from: new Date().getFullYear() + '-01-01', to: today },
                };
                if (map[v]) { state.from = map[v].from; state.to = map[v].to; }
                loadAllData();
            });
        }

        document.querySelectorAll('.always-analytics-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.always-analytics-toggle').forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');
                if (window.AlwaysAnalyticsCharts) AlwaysAnalyticsCharts.toggleDataset(this.getAttribute('data-dataset'));
            });
        });


        document.addEventListener('click', function (e) {
            var tab = e.target.closest('.always-analytics-ref-tab');
            if (!tab) return;
            document.querySelectorAll('.always-analytics-ref-tab').forEach(function (t) {
                t.classList.remove('always-analytics-ref-tab--active');
            });
            tab.classList.add('always-analytics-ref-tab--active');
            _refCat = tab.getAttribute('data-cat');
            renderReferrers();
        });


        document.addEventListener('click', function (e) {
            var tab = e.target.closest('.always-analytics-dev-tab');
            if (!tab) return;
            document.querySelectorAll('.always-analytics-dev-tab').forEach(function (t) {
                t.classList.remove('always-analytics-dev-tab--active');
            });
            tab.classList.add('always-analytics-dev-tab--active');
            _devFilter = tab.getAttribute('data-device');
            renderDevices();
        });
    }


    


    function apiFetch(endpoint, params, callback) {
        var qs = 'from='  + enc(state.from)
               + '&to='   + enc(state.to)
               + '&_t='   + Date.now();           // Unique cache-busting timestamp.

        if (state.device)   qs += '&device='    + enc(state.device);
        if (state.postType) qs += '&post_type='  + enc(state.postType);
        if (params)         qs += '&' + params;

        var sep = API_BASE.indexOf('?') !== -1 ? '&' : '?';
        fetch(API_BASE + endpoint + sep + qs, {
            method:  'GET',
            cache:   'no-store',          
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


    function loadAllData() {
        loadOverview();
        loadChart();
        loadRecentVisitors();
        loadTopPages();
        loadReferrers();
        loadDevices();
        loadHitSources();
        loadInternalLinks();
        loadOutboundLinks();

        if (window.AlwaysAnalyticsCampaigns) {
            window.AlwaysAnalyticsCampaigns.loadCampaigns();
        }
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
            var tbody = document.querySelector('#always-analytics-recent-visitors tbody');
            if (!tbody) return;
            if (!data || !data.length) {
                tbody.innerHTML = '<tr><td colspan="2" class="always-analytics-no-data">' + alwaysAnalyticsAdmin.i18n.noData + '</td></tr>';
                return;
            }
            var now = new Date();
            tbody.innerHTML = data.map(function (s) {
                var deviceIcon = s.device_type === 'mobile'
                    ? '<svg class="always-analytics-visitor-device-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><circle cx="12" cy="17" r="1"/></svg>'
                    : s.device_type === 'tablet'
                    ? '<svg class="always-analytics-visitor-device-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="2" width="18" height="20" rx="2"/><circle cx="12" cy="17" r="1"/></svg>'
                    : '<svg class="always-analytics-visitor-device-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 20h8M12 18v2"/></svg>';
                var vid    = s.visitor_hash.substring(0, 8);
                var ended  = new Date(s.ended_at + 'Z');
                var sec    = Math.floor((now - ended) / 1000);
                var isLive = sec < 120;
                var time   = isLive
                    ? (I18N.now || 'Now')
                    : (sec < 3600
                        ? (I18N.minutesAgo || '%s min ago').replace('%s', Math.floor(sec / 60))
                        : (I18N.hoursAgo || '%s h ago').replace('%s', Math.floor(sec / 3600)));
                var dur    = parseInt(s.total_duration, 10) || 0;
                var pages  = parseInt(s.total_pages, 10) || parseInt(s.last_page_count, 10) || 0;
                var visits = parseInt(s.session_count, 10) || 1;
                var href   = '?page=always-analytics-visitor&visitor_hash=' + enc(s.visitor_hash);

                var refFavicon = '';
                if (s.last_referrer_domain) {
                    refFavicon = domainIcon( s.last_referrer_domain, 'always-analytics-visitor-ref-favicon' );
                }

                var recentPages = Array.isArray(s.recent_pages) ? s.recent_pages : [];
                var pagesHtml = recentPages.length ? '<div class="always-analytics-visitor-pages-list">' + recentPages.map(function (p) {
                    var url = (p && p.url) ? p.url : '';
                    if (!url) return '';
                    var label = url;
                    try {
                        var parsed = new URL(url);
                        label = (parsed.pathname || '/') + (parsed.search || '');
                        if (label === '/') label = parsed.hostname + '/';
                    } catch (e) {}
                    return '<a class="always-analytics-visitor-page-url" href="' + escAttr(url) + '" target="_blank" rel="noopener noreferrer" title="' + escAttr(url) + '">'
                        + '<span class="always-analytics-visitor-page-dot"></span>'
                        + '<span class="always-analytics-visitor-page-text">' + esc(label) + '</span>'
                        + '</a>';
                }).join('') + '</div>' : '';


                var liveDot = isLive
                    ? '<span class="always-analytics-visitor-live-dot" title="' + escAttr(I18N.now || 'Now') + '"></span>'
                    : '';

                return '<tr class="always-analytics-visitor-row">'

                    + '<td class="always-analytics-visitor-cell">'
                    +   '<div class="always-analytics-visitor-row__top">'
                    +     liveDot
                    +     '<a href="' + href + '" class="always-analytics-visitor-link">' + esc((I18N.visitorLabel || 'Visitor %s').replace('%s', vid)) + '</a>'
                    +     '<span class="always-analytics-visitor-visits">' + esc((visits === 1 ? (I18N.visitCount || '%s visit') : (I18N.visitsCount || '%s visits')).replace('%s', visits)) + '</span>'
                    +   '</div>'
                    +   '<div class="always-analytics-visitor-row__meta">'
                    +     deviceIcon
                    +     refFavicon
                    +     '<span class="always-analytics-visitor-pages">' + esc((pages === 1 ? (I18N.pageCount || '%s page') : (I18N.pagesCount || '%s pages')).replace('%s', pages)) + '</span>'
                    +     '<span class="always-analytics-visitor-dur">' + fmtDuration(dur) + '</span>'
                    +   '</div>'
                    +   pagesHtml
                    + '</td>'

                    + '<td class="always-analytics-visitor-cell always-analytics-visitor-cell--right">'
                    +   '<span class="always-analytics-time-badge' + (isLive ? ' always-analytics-time-badge--live' : '') + '">' + time + '</span>'
                    + '</td>'
                    + '</tr>';
            }).join('');
        });
    }

    function loadTopPages() {
        var link = document.getElementById('always-analytics-all-pages-link');
        if (link) {
            var base = link.href.split('?')[0];
            link.href = base + '?page=always-analytics-top-pages&from=' + enc(state.from) + '&to=' + enc(state.to);
        }
        apiFetch('top-pages', 'limit=8', function (data) {
            var tbody = document.querySelector('#always-analytics-top-pages tbody');
            if (!tbody) return;
            if (!data || !data.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="always-analytics-no-data">' + alwaysAnalyticsAdmin.i18n.noData + '</td></tr>';
                return;
            }
            var max = Math.max.apply(null, data.map(function (d) { return +d.views; }));
            tbody.innerHTML = data.map(function (p) {
                var pct   = Math.round((+p.views / max) * 100);
                var title = p.page_title || p.page_url;
                return '<tr>'
                    + '<td title="' + esc(p.page_url) + '">'
                    +   '<div class="always-analytics-bar"><span>' + esc(title) + '</span></div>'
                    +   '<div class="always-analytics-bar-track"><div class="always-analytics-bar-fill" style="width:' + pct + '%"></div></div>'
                    + '</td>'
                    + '<td style="text-align:right">' + fmt(+p.views) + '</td>'
                    + '<td style="text-align:right">' + fmt(+p.unique_visitors) + '</td>'
                    + '</tr>';
            }).join('');
        });
    }


    var REF_DB = (function () {


        var rawSources = (alwaysAnalyticsAdmin.referrerSources || []).slice();
        var priority = { ai: 0, social: 1, search: 2, site: 3 };

        rawSources.sort(function (a, b) {
            return (priority[a.cat] || 9) - (priority[b.cat] || 9);
        });

        var rules = rawSources.map(function (s) {
            return {
                re:    new RegExp(s.pattern, 'i'),
                cat:   s.cat,
                label: s.label,
                color: s.color,
            };
        });

        return {
            categorize: function (domain) {
                if (!domain) return { cat: 'direct', label: I18N.direct || 'Direct', color: '#64748B' };
                var d = domain.toLowerCase();
                for (var i = 0; i < rules.length; i++) {
                    if (rules[i].re.test(d)) {
                        return { cat: rules[i].cat, label: rules[i].label, color: rules[i].color };
                    }
                }
                return { cat: 'site', label: domain, color: '#64748B' };
            }
        };
    })();

    var _refData = [];
    var _refCat  = 'all';

    function renderReferrers() {
        var container = document.getElementById('always-analytics-referrers-list');
        if (!container) return;

        var rows = _refData.filter(function (r) {
            var info = r._info || REF_DB.categorize(r.referrer_domain);
            if (_refCat === 'all') return true;
            return info.cat === _refCat;
        });

        if (!rows.length) {
            container.innerHTML = '<div class="always-analytics-ref-empty">' + esc(I18N.noSource || 'No source is available in this category.') + '</div>';
            return;
        }

        var maxHits = Math.max.apply(null, rows.map(function (r) { return +r.hits || 0; })) || 1;

        var rendered = rows.map(function (r) {
            var info   = r._info || REF_DB.categorize(r.referrer_domain);
            var pct    = Math.round(((+r.hits || 0) / maxHits) * 100);
            var label  = esc(info.label);
            var domain = esc(r.referrer_domain || '—');
            var hits   = fmt(+r.hits || 0);
            var uniq   = fmt(+r.unique_visitors || 0);

            var rawDomain = (r.referrer_domain || '').toLowerCase();
            var rawLabel  = info.label.toLowerCase();
            var showDomain = info.cat !== 'site' && info.cat !== 'direct'
                && rawDomain !== ''
                && rawDomain !== rawLabel
                && rawLabel.indexOf(rawDomain.replace(/^www\./, '')) === -1;

            var faviconDomain = r.favicon_domain || r.referrer_domain || '';
            var iconHtml      = domainIcon( faviconDomain, 'always-analytics-ref-favicon' );

            var html = '<div class="always-analytics-ref-row">'
                + '<div class="always-analytics-ref-identity">'
                +   '<span class="always-analytics-ref-icon" style="background:' + info.color + '18;">'
                +     iconHtml
                +   '</span>'
                +   '<span class="always-analytics-ref-labels">'
                +     '<span class="always-analytics-ref-name">' + label + '</span>'
                +     (showDomain ? '<span class="always-analytics-ref-domain">' + domain + '</span>' : '')
                +   '</span>'
                + '</div>'
                + '<div class="always-analytics-ref-bar-wrap">'
                +   '<div class="always-analytics-ref-bar-track"><div class="always-analytics-ref-bar-fill" style="width:' + pct + '%;background:' + info.color + '"></div></div>'
                + '</div>'
                + '<div class="always-analytics-ref-stats">'
                +   '<span class="always-analytics-ref-hits">' + hits + ' sessions</span>'
                +   '<span class="always-analytics-ref-uniq">' + uniq + ' unique</span>'
                + '</div>'
                + '</div>';

            return html;
        });

        container.innerHTML = rendered.join('');
    }

    function loadReferrers() {
        apiFetch('top-referrers', 'limit=40', function (data) {
            _refData = (data || []).map(function (r) {
                var fallback = REF_DB.categorize(r.referrer_domain);
                r._info = {
                    cat:   r.source_category || fallback.cat,
                    label: r.source_label || fallback.label,
                    color: r.source_color || fallback.color,
                };
                return r;
            });

            _refData.sort(function (a, b) {
                return (+b.hits || 0) - (+a.hits || 0);
            });


            var counts = { all: 0, search: 0, social: 0, ai: 0, site: 0, direct: 0 };
            _refData.forEach(function (r) {
                counts.all++;
                var cat = r._info ? r._info.cat : REF_DB.categorize(r.referrer_domain).cat;
                if (cat in counts) counts[cat]++;
                else counts.site++;
            });


            document.querySelectorAll('.always-analytics-ref-tab').forEach(function (tab) {
                var cat = tab.getAttribute('data-cat');
                var badge = tab.querySelector('.always-analytics-ref-count');
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'always-analytics-ref-count';
                    tab.appendChild(badge);
                }
                var n = counts[cat] || 0;
                badge.textContent = n;
                badge.style.display = (n === 0 && cat !== 'all') ? 'none' : '';
            });

            renderReferrers();
        });
    }

    var BROWSER_DB = {
        'Chrome':          { domain: 'google.com',       color: '#4285F4' },
        'Firefox':         { domain: 'mozilla.org',      color: '#FF7139' },
        'Safari':          { domain: 'apple.com',        color: '#006CFF' },
        'Edge':            { domain: 'microsoft.com',    color: '#0078D4' },
        'Opera':           { domain: 'opera.com',        color: '#FF1B2D' },
        'Opera GX':        { domain: 'opera.com',        color: '#CC1B4A' },
        'Opera Mini':      { domain: 'opera.com',        color: '#FF1B2D' },
        'IE':              { domain: 'microsoft.com',    color: '#1EBBEE' },
        'Samsung Browser': { domain: 'samsung.com',      color: '#1428A0' },
        'Brave':           { domain: 'brave.com',        color: '#FB542B' },
        'Vivaldi':         { domain: 'vivaldi.com',      color: '#EF3939' },
        'DuckDuckGo':      { domain: 'duckduckgo.com',   color: '#DE5833' },
        'Yandex Browser':  { domain: 'browser.yandex.com', color: '#FF0000' },
        'UCBrowser':       { domain: 'ucweb.com',        color: '#FF6600' },
        'Puffin':          { domain: 'puffinbrowser.com',color: '#2196F3' },
        'QQ Browser':      { domain: 'qq.com',           color: '#12B7F5' },
        'Baidu Browser':   { domain: 'baidu.com',        color: '#2932E1' },
        'Silk':            { domain: 'amazon.com',       color: '#FF9900' },
        'Firefox Focus':   { domain: 'mozilla.org',      color: '#9747FF' },
    };


    var OS_DB = {
        'Windows 11':    { domain: 'microsoft.com', color: '#0078D4' },
        'Windows 10':    { domain: 'microsoft.com', color: '#0078D4' },
        'Windows 8.1':   { domain: 'microsoft.com', color: '#0078D4' },
        'Windows 8':     { domain: 'microsoft.com', color: '#0078D4' },
        'Windows 7':     { domain: 'microsoft.com', color: '#0078D4' },
        'Windows Vista': { domain: 'microsoft.com', color: '#0078D4' },
        'Windows XP':    { domain: 'microsoft.com', color: '#0078D4' },
        'Windows Phone': { domain: 'microsoft.com', color: '#0078D4' },
        'macOS':         { domain: 'apple.com',     color: '#555555' },
        'iOS':           { domain: 'apple.com',     color: '#555555' },
        'iPadOS':        { domain: 'apple.com',     color: '#555555' },
        'Android':       { domain: 'android.com',   color: '#3DDC84' },
        'Chrome OS':     { domain: 'google.com',    color: '#4285F4' },
        'Linux':         { domain: 'kernel.org',    color: '#F0AB00' },
        'Ubuntu':        { domain: 'ubuntu.com',    color: '#E95420' },
        'Fedora':        { domain: 'fedoraproject.org', color: '#294172' },
        'Debian':        { domain: 'debian.org',    color: '#A81D33' },
        'Linux Mint':    { domain: 'linuxmint.com', color: '#87CF3E' },
        'BlackBerry':    { domain: 'blackberry.com',color: '#000000' },
        'Symbian':       { domain: 'nokia.com',     color: '#005AFF' },
        'KaiOS':         { domain: 'kaiostech.com', color: '#6600CC' },
        'Tizen':         { domain: 'tizen.org',     color: '#2196F3' },
        'HarmonyOS':     { domain: 'harmonyos.com', color: '#CF0A2C' },
    };

    var _devData   = null; 
    var _devFilter = 'all';

    function renderDeviceRow(name, count, maxCount, dbEntry) {
        var color   = dbEntry ? dbEntry.color : '#64748B';
        var domain  = dbEntry ? dbEntry.domain : name.toLowerCase().replace(/\s+/g, '') + '.com';
        var pct     = Math.round((count / maxCount) * 100);
        var initial = (name || '?').charAt(0).toUpperCase();
        var fallback = '<span class="always-analytics-dev-fallback" style="background:' + color + ';color:#fff;">' + esc( initial ) + '</span>';

        var iconHtml = fallback;
        if ( domain && alwaysAnalyticsAdmin.externalFavicons && alwaysAnalyticsAdmin.faviconService ) {
            var parameter = alwaysAnalyticsAdmin.faviconParameter || 'domain';
            var separator = alwaysAnalyticsAdmin.faviconService.indexOf( '?' ) === -1 ? '?' : '&';
            var src       = alwaysAnalyticsAdmin.faviconService
                + separator + encodeURIComponent( parameter ) + '=' + encodeURIComponent( domain )
                + '&sz=64';

            iconHtml = '<img class="always-analytics-dev-favicon" data-always-analytics-favicon="1" src="' + escAttr( src ) + '" alt="" width="16" height="16" loading="lazy" referrerpolicy="no-referrer" />'
                + '<span class="always-analytics-dev-fallback" style="background:' + color + ';color:#fff;" hidden>' + esc( initial ) + '</span>';
        }

        return '<div class="always-analytics-dev-row">'
            + '<div class="always-analytics-dev-identity">'
            +   '<span class="always-analytics-dev-icon" style="background:' + color + '18;">'
            +     iconHtml
            +   '</span>'
            +   '<span class="always-analytics-dev-name">' + esc(name) + '</span>'
            + '</div>'
            + '<div class="always-analytics-dev-bar-wrap">'
            +   '<div class="always-analytics-dev-bar-track"><div class="always-analytics-dev-bar-fill" style="width:' + pct + '%;background:' + color + '"></div></div>'
            + '</div>'
            + '<span class="always-analytics-dev-count">' + esc((count === 1 ? (I18N.sessionCount || '%s session') : (I18N.sessionsCount || '%s sessions')).replace('%s', fmt(count))) + '</span>'
            + '</div>';
    }

    function renderDeviceSection(title, rows, dbMap) {
        if (!rows || !rows.length) return '';
        var maxCount = Math.max.apply(null, rows.map(function(r) { return +r.count || 0; })) || 1;
        return '<div class="always-analytics-dev-section">'
            + '<div class="always-analytics-dev-section-title">' + title + '</div>'
            + rows.map(function(r) {
                var name = r.browser || r.os || '?';

                var entry = dbMap[name] || null;
                if (!entry) {
                    for (var key in dbMap) {
                        if (name.toLowerCase().indexOf(key.toLowerCase()) !== -1) {
                            entry = dbMap[key]; break;
                        }
                    }
                }
                return renderDeviceRow(name, +r.count || 0, maxCount, entry);
            }).join('')
            + '</div>';
    }

    function renderDevices() {
        var container = document.getElementById('always-analytics-devices-list');
        if (!container || !_devData) return;

        var browsers, os;
        if (_devFilter === 'all') {
            browsers = _devData.browsers || [];
            os       = _devData.os       || [];
        } else {
            var bd   = (_devData.by_device && _devData.by_device[_devFilter]) || {};
            browsers = bd.browsers || [];
            os       = bd.os       || [];
        }

        if (!browsers.length && !os.length) {
            container.innerHTML = '<div class="always-analytics-dev-empty">' + esc(I18N.noDataForFilter || 'No data is available for this filter.') + '</div>';
            return;
        }

        container.innerHTML =
            renderDeviceSection(I18N.browsers || 'Browsers', browsers, BROWSER_DB) +
            renderDeviceSection(I18N.operatingSystems || 'Operating systems', os, OS_DB);
    }

    function loadDevices() {
        apiFetch('devices', null, function (data) {
            _devData = data;


            var counts = { all: 0, desktop: 0, mobile: 0, tablet: 0 };
            if (data.devices) {
                data.devices.forEach(function(d) {
                    counts.all += +d.count || 0;
                    if (d.device_type in counts) counts[d.device_type] = +d.count || 0;
                });
            }
            document.querySelectorAll('.always-analytics-dev-tab').forEach(function(tab) {
                var key = tab.getAttribute('data-device');
                var badge = tab.querySelector('.always-analytics-dev-count-badge');
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'always-analytics-dev-count-badge';
                    tab.appendChild(badge);
                }
                var n = counts[key] || 0;
                badge.textContent = n;
                badge.style.display = (n === 0 && key !== 'all') ? 'none' : '';
            });


            if (data.devices && window.AlwaysAnalyticsCharts) {
                AlwaysAnalyticsCharts.renderDevicesChart(data.devices);
            }

            renderDevices();
        });
    }


    function loadInternalLinks() {
        var tbody = document.querySelector('#always-analytics-internal-links-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="2" class="always-analytics-loading-cell"><span class="always-analytics-spinner"></span></td></tr>';

        apiFetch('links/internal', 'limit=10', function (d) {
            setText('always-analytics-int-total',    fmt(d.total_clicks));
            setText('always-analytics-int-unique',   fmt(d.unique_links));
            setText('always-analytics-int-sessions', fmt(d.unique_sessions));

            if (!d.links || !d.links.length) {
                tbody.innerHTML = '<tr><td colspan="2" class="always-analytics-no-data">' + alwaysAnalyticsAdmin.i18n.noData + '</td></tr>';
                return;
            }

            var maxClicks = d.links[0].clicks || 1;
            tbody.innerHTML = d.links.map(function (row) {
                var pct = Math.round((row.clicks / maxClicks) * 100);
                var path = row.link_url;
                try { path = new URL(row.link_url).pathname; } catch(e) {}
                if (path.length > 50) path = path.substring(0, 47) + '…';
                return '<tr>'
                    + '<td>'
                    +   '<div class="always-analytics-link-row">'
                    +     '<div class="always-analytics-link-bar" style="width:' + pct + '%"></div>'
                    +     '<span class="always-analytics-link-label" title="' + esc(row.link_url) + '">'
                    +       '<svg class="always-analytics-link-icon always-analytics-link-icon--internal" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>'
                    +       esc(path)
                    +     '</span>'
                    +   '</div>'
                    + '</td>'
                    + '<td class="always-analytics-col-right"><strong>' + fmt(row.clicks) + '</strong> <small class="always-analytics-text-muted">(' + fmt(row.unique_clicks) + ' unique)</small></td>'
                    + '</tr>';
            }).join('');
        });
    }

    function loadOutboundLinks() {
        var tbody = document.querySelector('#always-analytics-outbound-links-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="2" class="always-analytics-loading-cell"><span class="always-analytics-spinner"></span></td></tr>';

        apiFetch('links/outbound', 'limit=10', function (d) {
            setText('always-analytics-out-total',    fmt(d.total_clicks));
            setText('always-analytics-out-domains',  fmt(d.unique_domains));
            setText('always-analytics-out-sessions', fmt(d.unique_sessions));

            if (!d.domains || !d.domains.length) {
                tbody.innerHTML = '<tr><td colspan="2" class="always-analytics-no-data">' + alwaysAnalyticsAdmin.i18n.noData + '</td></tr>';
                return;
            }

            var maxClicks = d.domains[0].clicks || 1;
            tbody.innerHTML = d.domains.map(function (row) {
                var pct = Math.round((row.clicks / maxClicks) * 100);
                var domain = row.link_domain || '—';
                return '<tr>'
                    + '<td>'
                    +   '<div class="always-analytics-link-row">'
                    +     '<div class="always-analytics-link-bar always-analytics-link-bar--outbound" style="width:' + pct + '%"></div>'
                    +     '<span class="always-analytics-link-label">'
                    +       domainIcon( domain, 'always-analytics-link-favicon' )
                    +       esc(domain)
                    +     '</span>'
                    +   '</div>'
                    + '</td>'
                    + '<td class="always-analytics-col-right"><strong>' + fmt(row.clicks) + '</strong> <small class="always-analytics-text-muted">(' + fmt(row.unique_clicks) + ' unique)</small></td>'
                    + '</tr>';
            }).join('');
        });
    }


    function setText(id, v) {
        var el = document.getElementById(id);
        if (el) el.textContent = v;
    }

    function setChange(id, v) {
        var el = document.getElementById(id);
        if (!el) return;
        if (!v) { el.textContent = ''; el.className = 'always-analytics-kpi-change'; return; }
        el.textContent = (v > 0 ? '+' : '') + v + '%';
        el.className   = 'always-analytics-kpi-change ' + (v >= 0 ? 'positive' : 'negative');
    }

    function fmt(n) {
        return (typeof n === 'number' ? n : parseInt(n, 10) || 0).toLocaleString(locale);
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

    


    function domainIcon( domain, className ) {
        var normalized = String( domain || '' ).trim().toLowerCase();
        normalized = normalized.replace( /^[a-z][a-z0-9+.-]*:\/\//i, '' ).split( '/' )[0].replace( /^www\./i, '' );

        var initial       = normalized.charAt( 0 ).toUpperCase() || '?';
        var imageClass    = escAttr( className || '' );
        var fallbackClass = escAttr( ( className || '' ) + '-fallback' );
        var fallback      = '<span class="' + fallbackClass + '" aria-hidden="true">' + esc( initial ) + '</span>';

        if ( ! normalized || ! alwaysAnalyticsAdmin.externalFavicons || ! alwaysAnalyticsAdmin.faviconService ) {
            return fallback;
        }

        var parameter = alwaysAnalyticsAdmin.faviconParameter || 'domain';
        var separator = alwaysAnalyticsAdmin.faviconService.indexOf( '?' ) === -1 ? '?' : '&';
        var src       = alwaysAnalyticsAdmin.faviconService
            + separator + encodeURIComponent( parameter ) + '=' + encodeURIComponent( normalized )
            + '&sz=64';

        return '<img class="' + imageClass + '" data-always-analytics-favicon="1" src="' + escAttr( src ) + '" alt="" width="18" height="18" loading="lazy" referrerpolicy="no-referrer" />'
            + '<span class="' + fallbackClass + '" aria-hidden="true" hidden>' + esc( initial ) + '</span>';
    }


    var purgeBtn = document.getElementById('always-analytics-purge-btn');
    if (purgeBtn) {
        purgeBtn.addEventListener('click', function () {
            var i18n = (alwaysAnalyticsAdmin && alwaysAnalyticsAdmin.i18n) || {};
            var confirmMsg = i18n.purgeConfirm || 'Run anonymization now?';
            if (!window.confirm(confirmMsg)) {
                return;
            }

            purgeBtn.disabled = true;
            purgeBtn.textContent = '⏳ …';

            var result = document.getElementById('always-analytics-purge-result');

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
                        result.style.color = 'var(--always-analytics-success, green)';
                        result.textContent = (i18n.purgeSuccess || json.data.message);
                    } else {
                        result.style.color = 'var(--always-analytics-danger, red)';
                        result.textContent = (i18n.purgeError || 'Error.');
                    }
                }
            })
            .catch(function () {
                if (result) {
                    result.style.display = 'inline';
                    result.style.color   = 'var(--always-analytics-danger, red)';
                    result.textContent   = I18N.networkError || 'Network error.';
                }
            })
            .finally(function () {
                purgeBtn.disabled    = false;
                purgeBtn.textContent = I18N.runAnonymization || 'Run anonymization…';
            });
        });
    }


    window.AlwaysAnalyticsCampaigns = (function () {

        var _campaigns = [];

        function loadCampaigns() {
            fetch(API_BASE + 'campaigns?_t=' + Date.now(), {
                method:  'GET',
                cache:   'no-store',
                headers: { 'X-WP-Nonce': NONCE },
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                _campaigns = data || [];
                if (window.AlwaysAnalyticsCharts) {
                    AlwaysAnalyticsCharts.setCampaigns(_campaigns);
                }
                renderCampaignsList();
            })
            .catch(function (e) { console.error('[AA Campaigns] load', e); });
        }

        function renderCampaignsList() {
            var container = document.getElementById('always-analytics-campaigns-list');
            if (!container) return;

            var empty = container.querySelector('.always-analytics-no-data');

            if (!_campaigns.length) {
                container.innerHTML = '<p class="always-analytics-no-data">' + (I18N.noEvents || 'No events.') + '</p>';
                return;
            }


            var sorted = _campaigns.slice().sort(function (a, b) {
                return b.event_date.localeCompare(a.event_date);
            });

            container.innerHTML = sorted.map(function (c) {
                var parts = c.event_date.split('-');
                var d = new Date(parts[0], parts[1] - 1, parts[2]);
                var dLabel = d.toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' });
                var color = c.color || '#6c63ff';
                return '<div class="always-analytics-camp-row" data-id="' + c.id + '">'
                    + '<span class="always-analytics-camp-dot" style="background:' + color + ';"></span>'
                    + '<div class="always-analytics-camp-info">'
                    +   '<strong class="always-analytics-camp-name">' + esc(c.label) + '</strong>'
                    +   '<span class="always-analytics-camp-date">' + dLabel + '</span>'
                    +   (c.description ? '<span class="always-analytics-camp-desc-preview">' + esc(c.description) + '</span>' : '')
                    + '</div>'
                    + '<div class="always-analytics-camp-actions">'
                    +   '<button class="always-analytics-camp-edit" data-id="' + c.id + '" title="' + escAttr(I18N.edit || 'Edit') + '"><svg class="always-analytics-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg></button>'
                    +   '<button class="always-analytics-camp-del" data-id="' + c.id + '" title="' + escAttr(I18N.delete || 'Delete') + '"><svg class="always-analytics-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>'
                    + '</div>'
                    + '</div>';
            }).join('');
        }

        function openModal() {
            var modal = document.getElementById('always-analytics-campaign-modal');
            if (!modal) return;

            var today = new Date();
            var yyyy  = today.getFullYear();
            var mm    = String(today.getMonth() + 1).padStart(2, '0');
            var dd    = String(today.getDate()).padStart(2, '0');
            document.getElementById('always-analytics-camp-date').value  = yyyy + '-' + mm + '-' + dd;
            document.getElementById('always-analytics-camp-label').value = '';
            document.getElementById('always-analytics-camp-desc').value  = '';
            document.getElementById('always-analytics-camp-color').value = '#6c63ff';
            modal.querySelectorAll('.always-analytics-swatch').forEach(function (s) {
                s.classList.toggle('always-analytics-swatch--active', s.getAttribute('data-color') === '#6c63ff');
            });
            var err = document.getElementById('always-analytics-camp-error');
            if (err) { err.style.display = 'none'; err.textContent = ''; }
            modal.classList.add('always-analytics-modal--open');
            setTimeout(function () {
                var lbl = document.getElementById('always-analytics-camp-label');
                if (lbl) lbl.focus();
            }, 50);
        }

        function closeModal() {
            var modal = document.getElementById('always-analytics-campaign-modal');
            if (modal) modal.classList.remove('always-analytics-modal--open');
        }

        function saveCampaign() {
            var date  = document.getElementById('always-analytics-camp-date').value;
            var label = (document.getElementById('always-analytics-camp-label').value || '').trim();
            var desc  = (document.getElementById('always-analytics-camp-desc').value || '').trim();
            var color = document.getElementById('always-analytics-camp-color').value || '#6c63ff';
            var err   = document.getElementById('always-analytics-camp-error');

            if (!date || !label) {
                if (err) { err.textContent = (I18N.dateLabelRequired || 'The date and label are required.'); err.style.display = 'block'; }
                return;
            }

            var btn = document.getElementById('always-analytics-camp-save');
            if (btn) { btn.disabled = true; btn.textContent = I18N.saving || 'Saving…'; }

            fetch(API_BASE + 'campaigns', {
                method:  'POST',
                cache:   'no-store',
                headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' },
                body: JSON.stringify({ event_date: date, label: label, description: desc, color: color }),
            })
            .then(function (r) {
                if (r.status === 409) throw new Error(I18N.eventExists || 'An event already exists for this date.');
                if (!r.ok) throw new Error((I18N.serverError || 'Server error.') + ' (' + r.status + ')');
                return r.json();
            })
            .then(function (data) {
                _campaigns.push(data);
                if (window.AlwaysAnalyticsCharts) AlwaysAnalyticsCharts.setCampaigns(_campaigns);
                renderCampaignsList();
                closeModal();
            })
            .catch(function (e) {
                if (err) { err.textContent = e.message || I18N.error || 'Error.'; err.style.display = 'block'; }
            })
            .finally(function () {
                if (btn) { btn.disabled = false; btn.textContent = I18N.save || 'Save'; }
            });
        }

        function deleteCampaign(id) {
            if (!confirm(I18N.deleteEventConfirm || 'Delete this event?')) return;
            var sid = String(id);
            fetch(API_BASE + 'campaigns/' + sid, {
                method:  'DELETE',
                cache:   'no-store',
                headers: { 'X-WP-Nonce': NONCE },
            })
            .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
            .then(function () {
                _campaigns = _campaigns.filter(function (c) { return String(c.id) !== sid; });
                if (window.AlwaysAnalyticsCharts) AlwaysAnalyticsCharts.setCampaigns(_campaigns);
                renderCampaignsList();
            })
            .catch(function () { alert(I18N.eventDeleteFailed || 'The event could not be deleted.'); });
        }

        function openEditModal(id) {
            var sid  = String(id);
            var camp = _campaigns.find(function (c) { return String(c.id) === sid; });
            if (!camp) return;
            var modal = document.getElementById('always-analytics-campaign-edit-modal');
            if (!modal) return;

            document.getElementById('always-analytics-edit-camp-id').value    = camp.id;
            document.getElementById('always-analytics-edit-camp-date').value  = camp.event_date;
            document.getElementById('always-analytics-edit-camp-label').value = camp.label;
            document.getElementById('always-analytics-edit-camp-desc').value  = camp.description || '';
            document.getElementById('always-analytics-edit-camp-color').value = camp.color || '#6c63ff';

            var color = camp.color || '#6c63ff';
            modal.querySelectorAll('#always-analytics-edit-swatches .always-analytics-swatch').forEach(function (s) {
                s.classList.toggle('always-analytics-swatch--active', s.getAttribute('data-color') === color);
            });

            var err = document.getElementById('always-analytics-edit-camp-error');
            if (err) { err.style.display = 'none'; err.textContent = ''; }
            modal.classList.add('always-analytics-modal--open');
            setTimeout(function () { document.getElementById('always-analytics-edit-camp-label').focus(); }, 50);
        }

        function closeEditModal() {
            var modal = document.getElementById('always-analytics-campaign-edit-modal');
            if (modal) modal.classList.remove('always-analytics-modal--open');
        }

        function saveEditCampaign() {
            var id    = parseInt(document.getElementById('always-analytics-edit-camp-id').value, 10);
            var date  = document.getElementById('always-analytics-edit-camp-date').value;
            var label = (document.getElementById('always-analytics-edit-camp-label').value || '').trim();
            var desc  = (document.getElementById('always-analytics-edit-camp-desc').value || '').trim();
            var color = document.getElementById('always-analytics-edit-camp-color').value || '#6c63ff';
            var err   = document.getElementById('always-analytics-edit-camp-error');

            if (!date || !label) {
                if (err) { err.textContent = (I18N.dateLabelRequired || 'The date and label are required.'); err.style.display = 'block'; }
                return;
            }

            var btn = document.getElementById('always-analytics-edit-camp-save');
            if (btn) { btn.disabled = true; btn.textContent = I18N.saving || 'Saving…'; }


            var existingOnDate = _campaigns.find(function (c) { return c.event_date === date && String(c.id) !== String(id); });
            if (existingOnDate) {
                if (err) { err.textContent = I18N.eventExists || 'An event already exists for this date.'; err.style.display = 'block'; }
                if (btn) { btn.disabled = false; btn.textContent = I18N.save || 'Save'; }
                return;
            }


            fetch(API_BASE + 'campaigns/' + id, {
                method:  'DELETE',
                cache:   'no-store',
                headers: { 'X-WP-Nonce': NONCE },
            })
            .then(function (r) { if (!r.ok) throw new Error(I18N.deletionFailed || 'Deletion failed.'); return r.json(); })
            .then(function () {
                return fetch(API_BASE + 'campaigns', {
                    method:  'POST',
                    cache:   'no-store',
                    headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ event_date: date, label: label, description: desc, color: color }),
                });
            })
            .then(function (r) {
                if (!r.ok) throw new Error(I18N.creationFailed || 'Creation failed.');
                return r.json();
            })
            .then(function (newCamp) {
                var sid = String(id);
                _campaigns = _campaigns.filter(function (c) { return String(c.id) !== sid; });
                _campaigns.push(newCamp);
                if (window.AlwaysAnalyticsCharts) AlwaysAnalyticsCharts.setCampaigns(_campaigns);
                renderCampaignsList();
                closeEditModal();
            })
            .catch(function (e) {
                if (err) { err.textContent = e.message || I18N.error || 'Error.'; err.style.display = 'block'; }
            })
            .finally(function () {
                if (btn) { btn.disabled = false; btn.textContent = I18N.save || 'Save'; }
            });
        }

        function bindCampaignEvents() {
            var modal   = document.getElementById('always-analytics-campaign-modal');
            var addBtn  = document.getElementById('always-analytics-add-campaign-btn');
            var addBtn2 = document.getElementById('always-analytics-add-campaign-btn2');
            var saveBtn = document.getElementById('always-analytics-camp-save');


            function onAddClick(e) {
                e.preventDefault();
                e.stopPropagation();
                openModal();
            }
            if (addBtn)  addBtn.addEventListener('click', onAddClick);
            if (addBtn2) addBtn2.addEventListener('click', onAddClick);


            if (modal) {
                var closeBtn = modal.querySelector('.always-analytics-modal-close');
                if (closeBtn) closeBtn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); closeModal(); });
                var cancelBtn = modal.querySelector('.always-analytics-modal-cancel');
                if (cancelBtn) cancelBtn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); closeModal(); });
                var overlay = modal.querySelector('.always-analytics-modal-overlay');
                if (overlay) overlay.addEventListener('click', function (e) { e.stopPropagation(); closeModal(); });
            }


            var editModal = document.getElementById('always-analytics-campaign-edit-modal');
            if (editModal) {
                var editCloseBtn = editModal.querySelector('.always-analytics-modal-close');
                if (editCloseBtn) editCloseBtn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); closeEditModal(); });
                var editCancelBtn = editModal.querySelector('.always-analytics-modal-cancel');
                if (editCancelBtn) editCancelBtn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); closeEditModal(); });
                var editOverlay = editModal.querySelector('.always-analytics-modal-overlay');
                if (editOverlay) editOverlay.addEventListener('click', function (e) { e.stopPropagation(); closeEditModal(); });
            }


            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { closeModal(); closeEditModal(); }
            });


            if (saveBtn) saveBtn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); saveCampaign(); });


            var editSaveBtn = document.getElementById('always-analytics-edit-camp-save');
            if (editSaveBtn) editSaveBtn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); saveEditCampaign(); });


            if (modal) {
                modal.querySelectorAll('.always-analytics-swatch').forEach(function (swatch) {
                    swatch.addEventListener('click', function (e) {
                        e.stopPropagation();
                        modal.querySelectorAll('.always-analytics-swatch').forEach(function (s) { s.classList.remove('always-analytics-swatch--active'); });
                        swatch.classList.add('always-analytics-swatch--active');
                        var ci = document.getElementById('always-analytics-camp-color');
                        if (ci) ci.value = swatch.getAttribute('data-color');
                    });
                });
                var colorInput = document.getElementById('always-analytics-camp-color');
                if (colorInput) colorInput.addEventListener('input', function () {
                    modal.querySelectorAll('.always-analytics-swatch').forEach(function (s) { s.classList.remove('always-analytics-swatch--active'); });
                });
            }


            if (editModal) {
                editModal.querySelectorAll('#always-analytics-edit-swatches .always-analytics-swatch').forEach(function (swatch) {
                    swatch.addEventListener('click', function (e) {
                        e.stopPropagation();
                        editModal.querySelectorAll('#always-analytics-edit-swatches .always-analytics-swatch').forEach(function (s) { s.classList.remove('always-analytics-swatch--active'); });
                        swatch.classList.add('always-analytics-swatch--active');
                        var ci = document.getElementById('always-analytics-edit-camp-color');
                        if (ci) ci.value = swatch.getAttribute('data-color');
                    });
                });
                var editColorInput = document.getElementById('always-analytics-edit-camp-color');
                if (editColorInput) editColorInput.addEventListener('input', function () {
                    editModal.querySelectorAll('#always-analytics-edit-swatches .always-analytics-swatch').forEach(function (s) { s.classList.remove('always-analytics-swatch--active'); });
                });
            }


            var listContainer = document.getElementById('always-analytics-campaigns-list');
            if (listContainer) {
                listContainer.addEventListener('click', function (e) {
                    var editBtn = e.target.closest('.always-analytics-camp-edit');
                    var delBtn  = e.target.closest('.always-analytics-camp-del');
                    if (editBtn) {
                        e.stopPropagation();
                        openEditModal(parseInt(editBtn.getAttribute('data-id'), 10));
                    }
                    if (delBtn) {
                        e.stopPropagation();
                        deleteCampaign(parseInt(delBtn.getAttribute('data-id'), 10));
                    }
                });
            }


            var canvas = document.getElementById('always-analytics-visits-chart');
            if (canvas) {
                canvas.addEventListener('mouseleave', function () {
                    var tip = document.getElementById('always-analytics-camp-tooltip');
                    if (tip) tip.style.display = 'none';
                });
            }
        }


        document.addEventListener('DOMContentLoaded', function () {
            bindCampaignEvents();
            loadCampaigns();
        });

        return { loadCampaigns: loadCampaigns, deleteCampaign: deleteCampaign };
    })();


    


    function loadHitSources() {
        var tbody  = document.getElementById('always-analytics-sources-tbody');
        var bar    = document.getElementById('always-analytics-sources-bar');
        var legend = document.getElementById('always-analytics-sources-bar-legend');
        var badge  = document.getElementById('always-analytics-sources-total-badge');
        var info   = document.getElementById('always-analytics-sources-info-text');

        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="8" class="always-analytics-loading-cell"><span class="always-analytics-spinner"></span></td></tr>';
        if (bar)    bar.innerHTML    = '';
        if (legend) legend.innerHTML = '';
        if (badge)  badge.textContent = '';

        apiFetch('hit-sources', null, function (d) {
            if (!d || !d.sources || !d.sources.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="always-analytics-no-data">' + esc(I18N.noData || 'No data is available for this period.') + '</td></tr>';
                return;
            }

            var sources   = d.sources;
            var totalHits = d.total_hits || 1;


            if (bar) {
                bar.innerHTML = '';
                var barSorted = sources.slice().sort(function (a, b) { return b.hits - a.hits; });
                barSorted.forEach(function (s) {
                    var effective = s.hits - (s.source === 'pre_consent' ? s.superseded_count : 0);
                    var pct = totalHits > 0 ? Math.max(0.5, effective / totalHits * 100) : 0;
                    var seg = document.createElement('div');
                    seg.className = 'always-analytics-sources-bar-seg';
                    seg.style.width      = pct + '%';
                    seg.style.background = s.color;
                    seg.title = s.label + ' — ' + (I18N.hitCount || '%s hits').replace('%s', fmt(effective)) + ' (' + s.pct_of_total + '%)';
                    bar.appendChild(seg);
                });
            }


            if (legend) {
                legend.innerHTML = '';
                sources.forEach(function (s) {
                    var el = document.createElement('span');
                    el.className = 'always-analytics-sources-legend-item';
                    el.innerHTML =
                        '<span class="always-analytics-sources-dot" style="background:' + s.color + '"></span>' +
                        '<span>' + escHtml(s.label) + '</span>';
                    legend.appendChild(el);
                });
            }


            if (badge) badge.textContent = (I18N.totalHits || '%s hits total').replace('%s', fmt(totalHits));


            tbody.innerHTML = '';
            sources.forEach(function (s) {
                var tr = document.createElement('tr');
                tr.className = 'always-analytics-sources-row';

                var newVisPct = s.unique_visitors > 0
                    ? Math.round(s.new_visitors / s.unique_visitors * 100) + '%'
                    : '—';

                var supersededCell = s.source === 'pre_consent'
                    ? '<td class="always-analytics-num">' +
                          '<span class="always-analytics-sources-fused-badge" title="' + escAttr(I18N.preConsentExcludedNote || 'Pre-consent hits marked as superseded after acceptance are excluded from the primary count.') + '">' +
                              fmt(s.superseded_count) +
                          '</span>' +
                      '</td>'
                    : '<td class="always-analytics-num always-analytics-muted">—</td>';

                tr.innerHTML =
                    '<td class="always-analytics-sources-label-cell">' +
                        '<span class="always-analytics-sources-icon" style="color:' + s.color + '">' + sourcesIcon(s.icon) + '</span>' +
                        '<span class="always-analytics-sources-name">' + escHtml(s.label) + '</span>' +
                        '<span class="always-analytics-sources-desc-icon" title="' + escAttr(s.description) + '">' +
                            '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' +
                        '</span>' +
                    '</td>' +
                    '<td class="always-analytics-num"><strong>' + fmt(s.hits) + '</strong></td>' +
                    '<td class="always-analytics-num">' + fmt(s.unique_visitors) + '</td>' +
                    '<td class="always-analytics-num">' + fmt(s.sessions) + '</td>' +
                    '<td class="always-analytics-num">' +
                        fmt(s.new_visitors) +
                        '<span class="always-analytics-sources-newvis-pct"> (' + newVisPct + ')</span>' +
                    '</td>' +
                    '<td class="always-analytics-num">' +
                        '<span class="always-analytics-sources-pct-pill" style="--src-color:' + s.color + '">' + s.pct_of_total + '%</span>' +
                    '</td>' +
                    supersededCell +
                    '<td class="always-analytics-sources-spark">' + buildSparkline(s.trend, s.color) + '</td>';

                tbody.appendChild(tr);
            });


            if (info) {
                var msgs = [];
                var hasPreConsent = sources.some(function (s) { return s.source === 'pre_consent'; });
                var hasNoscript   = sources.some(function (s) { return s.source === 'noscript'; });
                var hasFallback   = sources.some(function (s) { return s.source === 'js_cookieless'; });
                if (hasPreConsent) msgs.push(I18N.preConsentInfo || 'The consent notice is active. Merged pre-consent hits are excluded from the primary count.');
                if (hasNoscript)   msgs.push(I18N.legacyNoScriptInfo || 'Historical data includes records created by the legacy no-JavaScript collection method.');
                if (hasFallback)   msgs.push(I18N.cookieFallbackInfo || 'Some visitors block cookies, so the tracker automatically falls back to cookieless hits.');
                if (!msgs.length)  msgs.push(I18N.cookielessSourceInfo || 'Standard cookieless mode is active, and the JavaScript tracker is the only hit source.');
                info.textContent = msgs.join(' ');
            }
        });
    }


    function sourcesIcon(type) {
        var icons = {
            js:       '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
            fallback: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            consent:  '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
            noscript: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>',
            cookie:   '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/></svg>',
            unknown:  '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        };
        return icons[type] || icons.unknown;
    }

    function buildSparkline(trend, color) {
        if (!trend || trend.length < 2) {
            return '<span class="always-analytics-muted" style="font-size:11px;">—</span>';
        }
        var W = 80, H = 28, pad = 2;
        var values = trend.map(function (t) { return t.hits; });
        var minV = Math.min.apply(null, values);
        var maxV = Math.max.apply(null, values);
        var range = maxV - minV || 1;
        var n = values.length;
        var pts = values.map(function (v, i) {
            var x = pad + (i / (n - 1)) * (W - 2 * pad);
            var y = H - pad - ((v - minV) / range) * (H - 2 * pad);
            return x.toFixed(1) + ',' + y.toFixed(1);
        });
        var first = pts[0].split(',');
        var last  = pts[pts.length - 1].split(',');
        var area  = 'M' + first[0] + ',' + H + ' L' + pts.join(' L') + ' L' + last[0] + ',' + H + ' Z';
        return '<svg width="' + W + '" height="' + H + '" viewBox="0 0 ' + W + ' ' + H + '" class="always-analytics-sparkline">' +
            '<path d="' + area + '" fill="' + color + '" opacity="0.12"/>' +
            '<polyline points="' + pts.join(' ') + '" fill="none" stroke="' + color + '" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
            '<circle cx="' + last[0] + '" cy="' + last[1] + '" r="2" fill="' + color + '"/>' +
        '</svg>';
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escAttr(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

})();
