(function () {
	'use strict';

	if (typeof window.alwaysAnalyticsPrivacyConfig === 'undefined') {
		return;
	}

	var config = window.alwaysAnalyticsPrivacyConfig;
	var infoDismissedKey = 'always_analytics_info_dismissed';
	var infoBanner = document.getElementById('always-analytics-info-banner');
	var consentBanner = document.getElementById('always-analytics-consent-banner');

	function getCookie(name) {
		var escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
		var match = document.cookie.match(new RegExp('(?:^|; )' + escaped + '=([^;]*)'));
		return match ? decodeURIComponent(match[1]) : null;
	}

	function setCookie(name, value, days) {
		var expires = new Date(Date.now() + days * 86400000).toUTCString();
		var cookie = name + '=' + encodeURIComponent(value) + ';expires=' + expires + ';path=/;SameSite=Lax';
		if (window.location.protocol === 'https:') {
			cookie += ';Secure';
		}
		document.cookie = cookie;
	}

	function deleteCookie(name) {
		document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;SameSite=Lax';
	}


	function isInfoDismissed() {
		try {
			return window.sessionStorage.getItem(infoDismissedKey) === '1';
		} catch (error) {
			return false;
		}
	}

	function rememberInfoDismissal() {
		try {
			window.sessionStorage.setItem(infoDismissedKey, '1');
		} catch (error) {
			// Session storage can be unavailable in restricted browser contexts.
		}
	}

	function hideBanner(banner) {
		if (banner) {
			banner.hidden = true;
		}
	}

	function notify(status) {
		window.alwaysAnalyticsConsentStatus = status;
		if (typeof window.alwaysAnalyticsOnConsent === 'function') {
			window.alwaysAnalyticsOnConsent(status);
		}
	}

	if (infoBanner) {
		if (getCookie(config.optOutCookie) !== '1' && !isInfoDismissed()) {
			infoBanner.hidden = false;
		}

		var continueButton = document.getElementById('always-analytics-info-ok');
		var optOutButton = document.getElementById('always-analytics-info-opt-out');

		if (continueButton) {
			continueButton.addEventListener('click', function () {
				rememberInfoDismissal();
				hideBanner(infoBanner);
			});
		}

		if (optOutButton) {
			optOutButton.addEventListener('click', function () {
				setCookie(config.optOutCookie, '1', config.cookieDays);
				deleteCookie(config.visitorCookie);
				hideBanner(infoBanner);
				notify('opted_out');
			});
		}
	}

	if (consentBanner) {
		var storedConsent = getCookie(config.consentCookie);
		if (storedConsent) {
			notify(storedConsent);
			return;
		}

		consentBanner.hidden = false;
		var acceptButton = document.getElementById('always-analytics-consent-accept');
		var declineButton = document.getElementById('always-analytics-consent-decline');

		if (acceptButton) {
			acceptButton.addEventListener('click', function () {
				setCookie(config.consentCookie, 'granted', 182);
				deleteCookie(config.optOutCookie);
				hideBanner(consentBanner);
				notify('granted');
			});
		}

		if (declineButton) {
			declineButton.addEventListener('click', function () {
				setCookie(config.consentCookie, 'denied', 182);
				deleteCookie(config.visitorCookie);
				hideBanner(consentBanner);
				notify('denied');
			});
		}
	}
}());
