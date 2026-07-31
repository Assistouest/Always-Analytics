(function () {
    'use strict';
    if (typeof alwaysAnalyticsConfig === 'undefined')
        return;
    var config = alwaysAnalyticsConfig, COOKIE_VID = 'always_analytics_vid', COOKIE_CONS = 'always_analytics_consent', COOKIE_OPT_OUT = config.optOutCookie || 'always_analytics_opt_out', COOKIE_DAYS = 395, _pageStartTime = Date.now(), _engagementMs = 0, _lastVisibleTime = Date.now(), _lastActivity = Date.now(), ALWAYS_ANALYTICS_IDLE_MS = 60000, _isPageVisible = !document.hidden, _hitSent = false, _finalPingSent = false, _preConsentPromise = null;
    function updateEngagement() { if (_isPageVisible) {
        var now = Date.now(), until = Math.min(now, _lastActivity + ALWAYS_ANALYTICS_IDLE_MS);
        _engagementMs += Math.max(0, until - _lastVisibleTime);
        _lastVisibleTime = now;
    } }
    function onUserActivity() { var now = Date.now(); if (now - _lastActivity > 1000) {
        updateEngagement();
        _lastActivity = now;
        _lastVisibleTime = now;
    }
    else {
        _lastActivity = now;
    } }
    function engagementSeconds() { updateEngagement(); return Math.round(_engagementMs / 1000); }
    function consentStatus() { if (getCookie(COOKIE_OPT_OUT) === '1')
        return 'opted_out'; if (config.trackingMode !== 'cookie')
        return 'not_required'; if (!config.preConsentEnabled)
        return 'not_required'; if (window.alwaysAnalyticsConsentStatus)
        return window.alwaysAnalyticsConsentStatus; var stored = getCookie(COOKIE_CONS); if (stored === 'granted') {
        window.alwaysAnalyticsConsentStatus = 'granted';
        return 'granted';
    } if (stored === 'denied') {
        window.alwaysAnalyticsConsentStatus = 'denied';
        return 'denied';
    } return 'pending'; }
    function persistentVisitorAllowed(status) {
        return config.trackingMode === 'cookie'
            && (status === 'granted' || (status === 'not_required' && !config.preConsentEnabled));
    }
    function track() { var cs = consentStatus(); if (cs === 'opted_out') {
        deleteVisitorCookie();
        return;
    } if (cs === 'pending') {
        doSendPreConsent();
        window.alwaysAnalyticsOnConsent = function (status) { if (status === 'granted') {
            onConsentGranted();
        }
        else {
            onConsentDenied();
        } };
        return;
    } if (cs === 'denied') {
        deleteVisitorCookie();
    } doSendHit('js', persistentVisitorAllowed(cs)); }
    function doSendPreConsent() { var data = collectBaseData(); if (!data)
        return; data.action = 'pre_consent'; data.hitSource = 'pre_consent'; _preConsentPromise = sendPost(data); _hitSent = true; initScrollTracker(); initVisibilityTracker(); startHeartbeat(); }
    function onConsentGranted() { var vid = getOrCreateVisitorId(), data = collectBaseData(); if (!data)
        return; if (vid)
        data.visitorId = vid; var preSessionId = getSessionId(); if (preSessionId) {
        data.preConsentSessionId = preSessionId;
    } Promise.resolve(_preConsentPromise).then(function () { sendPost(data); }); }
    function onConsentDenied() { deleteVisitorCookie(); }
    function doSendHit(source, usePersistentVisitor) { var data = collectBaseData(); if (!data)
        return; if (usePersistentVisitor) {
        var vid = getOrCreateVisitorId();
        if (vid)
            data.visitorId = vid;
    } data.hitSource = source || 'js'; sendPost(data); _hitSent = true; initScrollTracker(); initVisibilityTracker(); startHeartbeat(); }
    function collectBaseData() { var data = { url: window.location.href, title: document.title, referrer: getEntryReferrer(), screenWidth: screen.width, screenHeight: screen.height, colorDepth: screen.colorDepth || 0, timezone: (Intl && Intl.DateTimeFormat) ? (Intl.DateTimeFormat().resolvedOptions().timeZone || '') : '', hardwareConcurrency: navigator.hardwareConcurrency || 0, touchPoints: navigator.maxTouchPoints || 0, sessionId: getSessionId() }; if (config.postId)
        data.postId = config.postId; try {
        var p = new URLSearchParams(window.location.search);
        if (p.get('utm_source'))
            data.utmSource = p.get('utm_source');
        if (p.get('utm_medium'))
            data.utmMedium = p.get('utm_medium');
        if (p.get('utm_campaign'))
            data.utmCampaign = p.get('utm_campaign');
    }
    catch (e) { } if (config.challengeNonce) {
        try {
            var navLang = (navigator.language || '').substring(0, 10), challengeInput = config.challengeNonce + '|' + screen.width + '|' + navLang;
            data.challengeToken = alwaysAnalyticsSha256Last8(challengeInput);
            data.navigatorLanguage = navLang;
        }
        catch (e) { }
    } try {
        if (navigator.webdriver === true)
            data.webdriver = 1;
    }
    catch (e) { } try {
        data.pluginsCount = navigator.plugins ? navigator.plugins.length : 0;
        data.navigatorLanguages = navigator.languages ? navigator.languages.length : 0;
    }
    catch (e) { } if (typeof window.alwaysAnalyticsBeforeTrack === 'function') {
        data = window.alwaysAnalyticsBeforeTrack(data);
        if (!data)
            return null;
    } return data; }
    function alwaysAnalyticsSha256Last8(msg) { var K = [0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5, 0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174, 0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da, 0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967, 0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85, 0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070, 0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3, 0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2], H = [0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19], b = []; for (var i = 0; i < msg.length; i++) {
        var c = msg.charCodeAt(i);
        if (c < 128)
            b.push(c);
        else if (c < 2048)
            b.push(192 | (c >> 6), 128 | (c & 63));
        else
            b.push(224 | (c >> 12), 128 | ((c >> 6) & 63), 128 | (c & 63));
    } var l = b.length * 8; b.push(128); while (b.length % 64 !== 56)
        b.push(0); b.push(0, 0, 0, 0, (l >>> 24) & 255, (l >>> 16) & 255, (l >>> 8) & 255, l & 255); function ror(n, x) { return (n >>> x) | (n << (32 - x)); } for (var off = 0; off < b.length; off += 64) {
        var W = new Array(64);
        for (var t = 0; t < 16; t++)
            W[t] = (b[off + t * 4] << 24) | (b[off + t * 4 + 1] << 16) | (b[off + t * 4 + 2] << 8) | b[off + t * 4 + 3];
        for (var t = 16; t < 64; t++) {
            var s0 = ror(W[t - 15], 7) ^ ror(W[t - 15], 18) ^ (W[t - 15] >>> 3), s1 = ror(W[t - 2], 17) ^ ror(W[t - 2], 19) ^ (W[t - 2] >>> 10);
            W[t] = (W[t - 16] + s0 + W[t - 7] + s1) | 0;
        }
        var a = H[0], b2 = H[1], c2 = H[2], d = H[3], e = H[4], f = H[5], g = H[6], h = H[7];
        for (var t = 0; t < 64; t++) {
            var S1 = ror(e, 6) ^ ror(e, 11) ^ ror(e, 25), ch = (e & f) ^ (~e & g), t1 = (h + S1 + ch + K[t] + W[t]) | 0, S0 = ror(a, 2) ^ ror(a, 13) ^ ror(a, 22), mj = (a & b2) ^ (a & c2) ^ (b2 & c2), t2 = (S0 + mj) | 0;
            h = g;
            g = f;
            f = e;
            e = (d + t1) | 0;
            d = c2;
            c2 = b2;
            b2 = a;
            a = (t1 + t2) | 0;
        }
        H[0] = (H[0] + a) | 0;
        H[1] = (H[1] + b2) | 0;
        H[2] = (H[2] + c2) | 0;
        H[3] = (H[3] + d) | 0;
        H[4] = (H[4] + e) | 0;
        H[5] = (H[5] + f) | 0;
        H[6] = (H[6] + g) | 0;
        H[7] = (H[7] + h) | 0;
    } var hex = ''; for (var i = 0; i < 8; i++)
        hex += ('00000000' + (H[i] >>> 0).toString(16)).slice(-8); return hex.slice(-8); }
    var _visibilityInited = false;
    function initVisibilityTracker() { if (_visibilityInited)
        return; _visibilityInited = true; document.addEventListener('visibilitychange', onVisibilityChange); window.addEventListener('pagehide', onPageHide); window.addEventListener('beforeunload', onBeforeUnload); var evs = ['pointermove', 'pointerdown', 'keydown', 'wheel', 'touchstart', 'click', 'scroll']; for (var i = 0; i < evs.length; i++)
        document.addEventListener(evs[i], onUserActivity, { passive: true, capture: true }); }
    function onVisibilityChange() { if (document.visibilityState === 'hidden') {
        onPageBecameHidden();
    }
    else {
        onPageBecameVisible();
    } }
    function onPageBecameHidden() { if (_isPageVisible) {
        updateEngagement();
        _isPageVisible = false;
    } sendFinalPing(); }
    function onPageBecameVisible() { if (!_isPageVisible) {
        _isPageVisible = true;
        _lastVisibleTime = Date.now();
        _finalPingSent = false;
        sendPing();
    } }
    function onPageHide() { onPageBecameHidden(); }
    function onBeforeUnload() { onPageBecameHidden(); }
    var _scrollSent = { 10: false, 25: false, 50: false, 75: false, 100: false }, _scrollInited = false, _scrollRaf = null, _currentScrollPct = 0;
    function initScrollTracker() { if (_scrollInited)
        return; _scrollInited = true; window.addEventListener('scroll', onScroll, { passive: true }); requestAnimationFrame(checkScrollDepth); }
    function onScroll() { if (_scrollRaf)
        return; _scrollRaf = requestAnimationFrame(function () { _scrollRaf = null; checkScrollDepth(); }); }
    function checkScrollDepth() { var docH = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight, document.body.offsetHeight, document.documentElement.offsetHeight), viewH = window.innerHeight || document.documentElement.clientHeight, scrolled = window.pageYOffset || document.documentElement.scrollTop || 0, scrollable = docH - viewH, pct = scrollable > 10 ? Math.min(100, Math.round(((scrolled + viewH) / docH) * 100)) : 100; _currentScrollPct = pct; var thresholds = [10, 25, 50, 75, 100]; for (var i = 0; i < thresholds.length; i++) {
        var t = thresholds[i];
        if (!_scrollSent[t] && pct >= t) {
            _scrollSent[t] = true;
            sendScrollEvent(t);
        }
    } }
    function sendScrollEvent(depth) { var cs = consentStatus(); if (cs === 'opted_out')
        return; if (!_hitSent)
        return; var data = { action: 'scroll', sessionId: getSessionId(), scrollDepth: depth, url: window.location.href }; if (persistentVisitorAllowed(cs)) {
        var visitorId = getCookie(COOKIE_VID);
        if (visitorId)
            data.visitorId = visitorId;
    } sendPost(data); }
    function startHeartbeat() { setTimeout(function () { sendPing(); setInterval(sendPing, 15000); }, 5000); }
    function sendPing() { var cs = consentStatus(); if (cs === 'opted_out')
        return; if (!_hitSent)
        return; if (document.hidden)
        return; var data = { action: 'ping', sessionId: getSessionId(), scrollDepth: _currentScrollPct, clientDuration: engagementSeconds(), engagementTime: engagementSeconds() }; if (persistentVisitorAllowed(cs)) {
        var visitorId = getCookie(COOKIE_VID);
        if (visitorId)
            data.visitorId = visitorId;
    } sendPost(data); }
    function sendFinalPing() { var cs = consentStatus(); if (cs === 'opted_out')
        return; if (!_hitSent)
        return; if (_finalPingSent)
        return; _finalPingSent = true; var data = { action: 'ping', sessionId: getSessionId(), scrollDepth: _currentScrollPct, clientDuration: engagementSeconds(), engagementTime: engagementSeconds(), isFinal: true }; if (persistentVisitorAllowed(cs)) {
        var visitorId = getCookie(COOKIE_VID);
        if (visitorId)
            data.visitorId = visitorId;
    } var payload = JSON.stringify(data); try {
        if (navigator.sendBeacon) {
            var blob = new Blob([payload], { type: 'application/json' });
            if (navigator.sendBeacon(config.endpoint, blob))
                return;
        }
    }
    catch (e) { } try {
        fetch(config.endpoint, { method: 'POST', keepalive: true, headers: { 'Content-Type': 'application/json' }, body: payload }).catch(function () { });
    }
    catch (e) { } }
    function sendPost(data) { try {
        return fetch(config.endpoint, { method: 'POST', keepalive: true, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).catch(function () { });
    }
    catch (e) {
        return Promise.resolve();
    } }
    var _REFERRER_KEY = 'always_analytics_entry_referrer';
    function _hostnameOf(url) { if (!url)
        return ''; try {
        return new URL(url).hostname.replace(/^www\./, '').toLowerCase();
    }
    catch (e) {
        return '';
    } }
    function _isInternalHost(refHost) { if (!refHost)
        return false; var siteHost = _hostnameOf(config.siteUrl || window.location.origin); if (!siteHost)
        return false; return refHost === siteHost || refHost.slice(-(siteHost.length + 1)) === '.' + siteHost; }
    function getEntryReferrer() { try {
        var stored = sessionStorage.getItem(_REFERRER_KEY);
        if (stored !== null)
            return stored;
        var raw = document.referrer || '', refHost = _hostnameOf(raw), entry = _isInternalHost(refHost) ? '' : raw;
        sessionStorage.setItem(_REFERRER_KEY, entry);
        return entry;
    }
    catch (e) {
        var raw = document.referrer || '', refHost = _hostnameOf(raw);
        return _isInternalHost(refHost) ? '' : raw;
    } }
    function getSessionId() { var KEY = 'always_analytics_sid_v2', now = Date.now(), timeout = 30 * 60 * 1000, day = new Date().toISOString().slice(0, 10), record, sid; try {
        record = JSON.parse(sessionStorage.getItem(KEY) || 'null');
        var expired = !record || !record.id || !record.last || (now - record.last) > timeout;
        var dailyRotation = config.trackingMode === 'cookieless' && config.cookielessWindow === 'daily' && record && record.day !== day;
        if (expired || dailyRotation) {
            record = { id: genId(), last: now, day: day };
            sessionStorage.removeItem(_REFERRER_KEY);
        }
        else {
            record.last = now;
        }
        sessionStorage.setItem(KEY, JSON.stringify(record));
        sid = record.id;
    }
    catch (e) {
        if (!window.alwaysAnalyticsSessionFallback || (now - (window.alwaysAnalyticsSessionFallbackLast || 0)) > timeout) {
            window.alwaysAnalyticsSessionFallback = genId();
        }
        window.alwaysAnalyticsSessionFallbackLast = now;
        sid = window.alwaysAnalyticsSessionFallback;
    } return sid; }
    function getOrCreateVisitorId() { var vid = getCookie(COOKIE_VID); if (vid)
        return vid; var candidate = genId(); setVisitorCookie(candidate); var persisted = getCookie(COOKIE_VID); if (persisted)
        return persisted; return null; }
    function setVisitorCookie(value) { var expires = new Date(); expires.setTime(expires.getTime() + COOKIE_DAYS * 86400000); var c = COOKIE_VID + '=' + encodeURIComponent(value) + ';expires=' + expires.toUTCString() + ';path=/;SameSite=Lax'; if (window.location.protocol === 'https:')
        c += ';Secure'; document.cookie = c; }
    function deleteVisitorCookie() { var c = COOKIE_VID + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;SameSite=Lax'; if (window.location.protocol === 'https:')
        c += ';Secure'; document.cookie = c; }
    function genId() { try {
        if (crypto && crypto.randomUUID)
            return crypto.randomUUID();
    }
    catch (e) { } return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) { var r = (Math.random() * 16) | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16); }); }
    function getCookie(name) { var m = document.cookie.match('(?:^|;)\\s*' + name + '=([^;]*)'); return m ? decodeURIComponent(m[1]) : null; }
    var _linkClickInited = false;
    function initLinkClickTracker() { if (_linkClickInited)
        return; _linkClickInited = true; document.addEventListener('click', function (e) { var cs = consentStatus(); if (cs === 'opted_out' || !_hitSent)
        return; var anchor = e.target.closest('a'); if (!anchor)
        return; var href = anchor.getAttribute('href'); if (!href || href.charAt(0) === '#' || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0 || href.indexOf('javascript:') === 0)
        return; var linkType; try {
        var linkUrl = new URL(href, window.location.origin), linkHost = linkUrl.hostname.replace(/^www\./, '').toLowerCase(), siteHost = _hostnameOf(config.siteUrl || window.location.origin);
        if (linkHost === siteHost || linkHost.slice(-(siteHost.length + 1)) === '.' + siteHost) {
            linkType = 'internal';
        }
        else {
            linkType = 'outbound';
        }
        href = linkUrl.href;
    }
    catch (ex) {
        linkType = 'internal';
        try {
            href = new URL(href, window.location.origin).href;
        }
        catch (ex2) { }
    } var data = { action: 'link_click', sessionId: getSessionId(), pageUrl: window.location.href, linkUrl: href, linkType: linkType }; try {
        if (navigator.sendBeacon) {
            var blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
            navigator.sendBeacon(config.endpoint, blob);
        }
        else {
            fetch(config.endpoint, { method: 'POST', keepalive: true, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).catch(function () { });
        }
    }
    catch (ex) { } }, true); }
    function initTrack() { if (typeof window.requestIdleCallback === 'function') {
        window.requestIdleCallback(function () { track(); initLinkClickTracker(); });
    }
    else {
        setTimeout(function () { track(); initLinkClickTracker(); }, 1);
    } }
    if (document.readyState === 'complete') {
        initTrack();
    }
    else {
        window.addEventListener('load', initTrack);
    }
})();

