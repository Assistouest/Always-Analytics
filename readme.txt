=== Always Analytics ===
Contributors: adrien
Tags: analytics, statistics, cookieless, dashboard, reports
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 3.6.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://buymeacoffee.com/assistouest

Self-hosted audience analytics with cookieless measurement, local assets, reliable source grouping, and WordPress-native reports.

== Description ==

Always Analytics records audience statistics in dedicated tables in the WordPress database. Runtime assets and bundled lookup data are served locally. The plugin does not send analytics data to the plugin author or to an external analytics service.

Main features:

* Page views, visitors, sessions, engagement time, and scroll depth
* Reports for pages, referrers, devices, campaigns, and link clicks
* Cookieless mode with configurable short-lived visitor windows
* Optional persistent visitor cookie, enabled only after an affirmative choice when privacy controls are active
* Persistent visitor opt-out control
* Local bot filtering
* CSV and JSON exports for administrators
* Configurable data retention and optional data removal during uninstall

= Tracking modes =

Cookieless mode is the recommended default. It does not create a persistent analytics visitor cookie. Cookie mode is available as an advanced option for projects that need persistent first-party visitor recognition. The settings screen warns administrators before they use that mode.

= External services =

The plugin does not contact an external analytics, advertising, font, geolocation, or telemetry service during normal operation. Optional external favicons are disabled by default.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/`, or install the ZIP from Plugins > Add New.
2. Activate Always Analytics.
3. Open Always Analytics > Settings.
4. Review the tracking mode, excluded roles, source reports, retention, and uninstall settings.


= Optional external favicon service =

Always Analytics can display favicons for referrer domains through Google S2 Favicons. This feature is disabled by default and is activated only when an administrator explicitly enables "External favicons" in the plugin settings. When enabled, the visitor viewing the WordPress administration screen connects directly to Google, which may receive technical data such as the IP address, browser information, requested domain, and request time. No analytics records are sent by the plugin author.

Service endpoint: https://www.google.com/s2/favicons
Google Privacy Policy: https://policies.google.com/privacy
Google Terms of Service: https://policies.google.com/terms

== Frequently Asked Questions ==

= How are traffic sources grouped? =

Known variants are grouped under one source. For example, `facebook.com`, `l.facebook.com`, `lm.facebook.com`, and `m.facebook.com` are reported as Facebook. AI assistants such as Gemini, ChatGPT, Claude, and Perplexity are separated from search engines. Sessions and unique visitors are recalculated server-side for each grouped source.

= Does the plugin send data to the developer? =

No. Analytics records stay in the site's WordPress database. There is no usage telemetry or remote account connection.

= Does the plugin use cookies? =

Cookieless mode does not create the persistent analytics visitor cookie. It may use a functional opt-out cookie after a visitor chooses to opt out. Cookie mode uses a persistent visitor cookie only after acceptance when privacy controls are enabled.

= Can visitors opt out? =

Yes. When privacy controls are enabled in cookieless mode, the notice includes an opt-out action. The choice is stored in a first-party functional cookie so the browser remains excluded on later visits.

= What happens when the plugin is deleted? =

Data is retained by default. Enable "Delete plugin data on uninstall" before deletion to remove the plugin tables, options, scheduled events, and transients.

== Third-Party Libraries and Data ==

The following components are bundled locally. Full notices are included in `THIRD-PARTY-NOTICES.txt`.

* Chart.js 4.5.1, MIT License
* cloud-provider-ip-addresses-derived range data, CC0-1.0

== Changelog ==

= 3.6.3 =

* Added a friendly, dismissible support notice on the plugin's own admin screens, shown only after the site has used the plugin long enough to see real value, linking to https://buymeacoffee.com/assistouest for administrators who want to support development. A "Donate link" was also added to the plugin header.
* Removed the redundant in-page "Always Analytics vX.X.X" footer from the Dashboard and Top Pages screens now that the native WordPress admin footer carries the same information.
* Completed the French translation, including newly added admin strings.

= 3.2.2 =

* Fixed a regression where session duration always displayed 0s and visitors disappeared from "Recent visitors" shortly after their first page view. A missing table reference in the periodic session-ping handler silently prevented live duration, engagement, and scroll-depth updates from ever being saved.

= 3.2.1 =

* Narrow PHPCS suppressions to the exact advisory database sniffs required by plugin-owned analytics tables.
* Harden administrator reports with explicit capability checks and strict validation of read-only filters.
* Replace broad SQL-preparation exclusions with prepared values, fixed internal fragments, and documented allowlists.
* Align the coding-standard configuration with the declared WordPress 5.8 minimum and include public shortcodes in audits.

= 3.2.0 =

* Fixed external referrer favicons staying hidden behind their fallback letter badge after loading, caused by a CSS specificity conflict; the real favicon now correctly replaces the fallback once it loads.
* Added optional Google S2 favicons to the browser and operating system breakdowns in the Devices report, matching existing referrer favicon behavior.
* Added a safeguard against CSV formula injection in the CSV export (values starting with `=`, `+`, `-`, or `@` are now safely escaped).
* Reviewed and hardened output escaping, nonce and capability checks, and prepared-statement usage across the REST API, tracking pipeline, admin views, and privacy/export code.
* Removed the country geolocation feature and its bundled DB-IP Country Lite data and flag icons, for privacy and to reduce the plugin footprint.
* Reduced database footprint: dropped two write-path indexes made redundant by existing composite indexes, and added automatic cleanup of already-superseded tracking rows to the daily retention job.

= 3.1.1 =

* Make collection profile cards natively clickable with visible radio controls and a dedicated cache-busted settings script.
* Add a CSS-only mode-selection fallback so mode panels remain usable if another administration extension interferes with JavaScript.
* Load Google S2 favicons with the broadly compatible `domain` parameter and CSP-compatible delegated load/error handling.
* Bust dashboard JavaScript caches with both the plugin version and file modification time.
* Detect internal navigation dynamically from the configured `home_url()` and `site_url()` hosts.
* Stop treating every subdomain as internal unless that host is explicitly configured by WordPress.

= 3.1.0 =

* Exclude the WordPress home and site hosts from external acquisition reports.
* Clean historical self-referrals during upgrade while keeping internal-link reporting separate.
* Rotate browser sessions after 30 minutes of inactivity and at the UTC day boundary for daily cookieless measurement.
* Reset the entry referrer whenever a new analytics session starts.
* Add Fetch Metadata, browser-environment, origin, datacenter, user-agent, and behavioural signals to layered bot scoring.
* Add fingerprint-based collector burst limiting to reduce residential proxy and rotating-session spam.
* Preserve conservative scoring so one weak privacy-related signal cannot block a legitimate visit by itself.

= 3.0.10 =
* Keep third-party WordPress admin notices in the native notice area above the plugin interface.

= 3.0.8 =

* Redesign the settings page around two clear collection profiles: cookieless and cookie.
* Hide irrelevant controls automatically and enforce mode-specific safeguards during option sanitization.
* Disable the visitor banner in cookieless mode and require consent handling in cookie mode.
* Add a clearer data, accuracy, proxy, favicon, performance, and uninstall configuration experience.
* Classify PrivacyWall as a search engine.
* Normalize known websites and merge Programmez AMP referrals with the main Programmez source.
* Update the bundled French translation.

= 3.0.5 =

* Fix external favicons in analytics panels by separating the image and fallback styles.
* Add Google S2 favicon rendering with an administrator opt-in and a local fallback.
* Merge `lnkd.in` traffic into LinkedIn before session and unique visitor counts are calculated.
* Merge DuckDuckGo's official Onion referrer into DuckDuckGo.
* Add optional Google S2 favicons behind an administrator opt-in that is disabled by default.
* Document the external favicon service and its privacy implications.

= 3.0.3 =

* Fixed activation failures caused by `dbDelta()` index parsing warnings under PHP 8.5 and exception-converting frameworks such as Laravel Acorn.
* Replaced schema creation through `dbDelta()` with direct idempotent table creation and retained explicit migrations for existing installations.

= 3.0.1 =

* Removed the legal-assessment interface from the administration experience.
* Kept concise warnings only for advanced persistent-cookie tracking.
* Grouped referrer aliases by source on the server instead of only in the browser.
* Recalculated distinct sessions and visitors after grouping to prevent duplicate unique counts.
* Classified Gemini, Google AI Studio, NotebookLM, and other assistants as AI rather than search traffic.
* Merged Facebook referral hosts such as `l.facebook.com`, `lm.facebook.com`, `m.facebook.com`, and `facebook.com`.

= 3.0.0 =

* Refactored PHP, JavaScript, CSS, identifiers, and comments to English.
* Replaced short global prefixes with the `always_analytics` prefix and added migration from legacy tables and scheduled hooks.
* Preserved local cookieless collection before consent while enforcing network truncation and preventing WordPress-user association in cookieless and pre-decision records.
* Added persistent visitor opt-out handling and neutral privacy notices.
* Removed remote assets, public promotional output, and external favicon requests.
* Restricted admin assets to relevant plugin screens.
* Added stricter REST validation, permissions, sanitization, and rate limiting.
* Replaced MySQL 8-only query functions with MySQL 5.7-compatible expressions.
* Replaced encoded datacenter data with reviewable local range files.
* Replaced the legacy approximate IP table with compact local DB-IP Country Lite indexes and documented attribution.
* Made uninstall data deletion explicitly opt-in.
* Updated bundled licenses, documentation, and French translations.

== Upgrade Notice ==

= 3.1.1 =

Fixes settings profile selection, external favicon rendering, asset caching, and dynamic internal-host detection.
