<?php
/**
 * Static referrer domain to source-name/category map, consumed by the admin dashboard.
 *
 * @package Always_Analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(





	array(
		'pattern' => '(^|\.)google\.',
		'cat'     => 'search',
		'label'   => 'Google',
		'color'   => '#4285F4',
	),
	array(
		'pattern' => '(^|\.)bing\.',
		'cat'     => 'search',
		'label'   => 'Bing',
		'color'   => '#00897B',
	),
	array(
		'pattern' => '(^|\.)yahoo\.',
		'cat'     => 'search',
		'label'   => 'Yahoo',
		'color'   => '#720E9E',
	),
	array(
		'pattern'     => '^duckduckgogg42xjoc72x3sjasowoarfbgcmvfimaftt6twagswzczad\.onion$',
		'cat'         => 'search',
		'label'       => 'DuckDuckGo',
		'color'       => '#DE5833',
		'icon_domain' => 'duckduckgo.com',
	),
	array(
		'pattern'     => '(^|\.)duckduckgo\.',
		'cat'         => 'search',
		'label'       => 'DuckDuckGo',
		'color'       => '#DE5833',
		'icon_domain' => 'duckduckgo.com',
	),
	array(
		'pattern' => '(^|\.)qwant\.',
		'cat'     => 'search',
		'label'   => 'Qwant',
		'color'   => '#9963EA',
	),
	array(
		'pattern' => '(^|\.)qwantjunior\.',
		'cat'     => 'search',
		'label'   => 'Qwant Junior',
		'color'   => '#9963EA',
	),
	array(
		'pattern' => '(^|\.)ecosia\.',
		'cat'     => 'search',
		'label'   => 'Ecosia',
		'color'   => '#2E7D32',
	),
	array(
		'pattern' => '(^|\.)yandex\.',
		'cat'     => 'search',
		'label'   => 'Yandex',
		'color'   => '#FF0000',
	),
	array(
		'pattern' => '(^|\.)baidu\.',
		'cat'     => 'search',
		'label'   => 'Baidu',
		'color'   => '#2932E1',
	),
	array(
		'pattern' => '(^|\.)naver\.',
		'cat'     => 'search',
		'label'   => 'Naver',
		'color'   => '#03C75A',
	),
	array(
		'pattern' => '(^|\.)brave\.com',
		'cat'     => 'search',
		'label'   => 'Brave Search',
		'color'   => '#FB542B',
	),
	array(
		'pattern' => '(^|\.)startpage\.',
		'cat'     => 'search',
		'label'   => 'Startpage',
		'color'   => '#5CB85C',
	),
	array(
		'pattern' => '(^|\.)kagi\.',
		'cat'     => 'search',
		'label'   => 'Kagi',
		'color'   => '#FF4F64',
	),
	array(
		'pattern' => '(^|\.)ask\.',
		'cat'     => 'search',
		'label'   => 'Ask',
		'color'   => '#E65100',
	),
	array(
		'pattern' => '(^|\.)seznam\.',
		'cat'     => 'search',
		'label'   => 'Seznam',
		'color'   => '#CC0000',
	),
	array(
		'pattern' => '(^|\.)swisscows\.',
		'cat'     => 'search',
		'label'   => 'Swisscows',
		'color'   => '#9C27B0',
	),
	array(
		'pattern' => '(^|\.)lilo\.',
		'cat'     => 'search',
		'label'   => 'Lilo',
		'color'   => '#FF6B35',
	),
	array(
		'pattern' => '(^|\.)sogou\.',
		'cat'     => 'search',
		'label'   => 'Sogou',
		'color'   => '#E74C3C',
	),
	array(
		'pattern' => '(^|\.)so\.com',
		'cat'     => 'search',
		'label'   => '360 Search',
		'color'   => '#1ABC9C',
	),
	array(
		'pattern'     => '^search\.nortonsafesearch\.com$',
		'cat'         => 'search',
		'label'       => 'Norton Safe Search',
		'color'       => '#F5B400',
		'icon_domain' => 'nortonsafesearch.com',
	),
	array(
		'pattern'     => '^search\.avastbrowser\.com$',
		'cat'         => 'search',
		'label'       => 'Avast Search',
		'color'       => '#FF7800',
		'icon_domain' => 'avast.com',
	),
	array(
		'pattern'     => '(^|\.)privacywall\.org$',
		'cat'         => 'search',
		'label'       => 'PrivacyWall',
		'color'       => '#2563EB',
		'icon_domain' => 'privacywall.org',
	),





	array(
		'pattern' => '(^|\.)facebook\.',
		'cat'     => 'social',
		'label'   => 'Facebook',
		'color'   => '#1877F2',
	),
	array(
		'pattern' => '^l\.facebook\.',
		'cat'     => 'social',
		'label'   => 'Facebook',
		'color'   => '#1877F2',
	),
	array(
		'pattern' => '^fb\.com',
		'cat'     => 'social',
		'label'   => 'Facebook',
		'color'   => '#1877F2',
	),
	array(
		'pattern' => '(^|\.)instagram\.',
		'cat'     => 'social',
		'label'   => 'Instagram',
		'color'   => '#E1306C',
	),
	array(
		'pattern' => '(^|\.)twitter\.',
		'cat'     => 'social',
		'label'   => 'Twitter / X',
		'color'   => '#000000',
	),
	array(
		'pattern' => '^t\.co$',
		'cat'     => 'social',
		'label'   => 'Twitter / X',
		'color'   => '#000000',
	),
	array(
		'pattern' => '^x\.com',
		'cat'     => 'social',
		'label'   => 'X',
		'color'   => '#000000',
	),
	array(
		'pattern'     => '^lnkd\.in$',
		'cat'         => 'social',
		'label'       => 'LinkedIn',
		'color'       => '#0A66C2',
		'icon_domain' => 'linkedin.com',
	),
	array(
		'pattern'     => '(^|\.)linkedin\.',
		'cat'         => 'social',
		'label'       => 'LinkedIn',
		'color'       => '#0A66C2',
		'icon_domain' => 'linkedin.com',
	),
	array(
		'pattern' => '(^|\.)pinterest\.',
		'cat'     => 'social',
		'label'   => 'Pinterest',
		'color'   => '#E60023',
	),
	array(
		'pattern' => '(^|\.)tiktok\.',
		'cat'     => 'social',
		'label'   => 'TikTok',
		'color'   => '#010101',
	),
	array(
		'pattern' => '(^|\.)youtube\.',
		'cat'     => 'social',
		'label'   => 'YouTube',
		'color'   => '#FF0000',
	),
	array(
		'pattern' => '^youtu\.be',
		'cat'     => 'social',
		'label'   => 'YouTube',
		'color'   => '#FF0000',
	),
	array(
		'pattern' => '(^|\.)reddit\.',
		'cat'     => 'social',
		'label'   => 'Reddit',
		'color'   => '#FF4500',
	),
	array(
		'pattern' => '(^|\.)discord\.',
		'cat'     => 'social',
		'label'   => 'Discord',
		'color'   => '#5865F2',
	),
	array(
		'pattern' => '(^|\.)snapchat\.',
		'cat'     => 'social',
		'label'   => 'Snapchat',
		'color'   => '#FFFC00',
	),
	array(
		'pattern' => '(^|\.)whatsapp\.',
		'cat'     => 'social',
		'label'   => 'WhatsApp',
		'color'   => '#25D366',
	),
	array(
		'pattern' => '(^|\.)telegram\.',
		'cat'     => 'social',
		'label'   => 'Telegram',
		'color'   => '#2AABEE',
	),
	array(
		'pattern' => '(^|\.)mastodon\.',
		'cat'     => 'social',
		'label'   => 'Mastodon',
		'color'   => '#6364FF',
	),
	array(
		'pattern' => '(^|\.)bsky\.',
		'cat'     => 'social',
		'label'   => 'Bluesky',
		'color'   => '#0085FF',
	),
	array(
		'pattern' => '(^|\.)bluesky\.',
		'cat'     => 'social',
		'label'   => 'Bluesky',
		'color'   => '#0085FF',
	),
	array(
		'pattern' => '(^|\.)threads\.net',
		'cat'     => 'social',
		'label'   => 'Threads',
		'color'   => '#000000',
	),
	array(
		'pattern' => '(^|\.)vk\.com',
		'cat'     => 'social',
		'label'   => 'VKontakte',
		'color'   => '#0077FF',
	),
	array(
		'pattern' => '(^|\.)tumblr\.',
		'cat'     => 'social',
		'label'   => 'Tumblr',
		'color'   => '#35465C',
	),
	array(
		'pattern' => '(^|\.)twitch\.',
		'cat'     => 'social',
		'label'   => 'Twitch',
		'color'   => '#9146FF',
	),
	array(
		'pattern' => '(^|\.)quora\.',
		'cat'     => 'social',
		'label'   => 'Quora',
		'color'   => '#B92B27',
	),
	array(
		'pattern' => '(^|\.)medium\.',
		'cat'     => 'social',
		'label'   => 'Medium',
		'color'   => '#000000',
	),
	array(
		'pattern' => '(^|\.)substack\.',
		'cat'     => 'social',
		'label'   => 'Substack',
		'color'   => '#FF6719',
	),
	array(
		'pattern' => '(^|\.)producthunt\.',
		'cat'     => 'social',
		'label'   => 'Product Hunt',
		'color'   => '#DA552F',
	),
	array(
		'pattern' => '(^|\.)github\.',
		'cat'     => 'social',
		'label'   => 'GitHub',
		'color'   => '#24292F',
	),
	array(
		'pattern' => 'news\.ycombinator\.',
		'cat'     => 'social',
		'label'   => 'Hacker News',
		'color'   => '#FF6600',
	),
	array(
		'pattern'     => '^teams\.public\.onecdn\.static\.microsoft$',
		'cat'         => 'social',
		'label'       => 'Microsoft Teams',
		'color'       => '#6264A7',
		'icon_domain' => 'teams.microsoft.com',
	),
	array(
		'pattern'     => '^teams\.public\.onecdn\.static\.microsoft\.com$',
		'cat'         => 'social',
		'label'       => 'Microsoft Teams',
		'color'       => '#6264A7',
		'icon_domain' => 'teams.microsoft.com',
	),
	array(
		'pattern'     => '^statics\.teams\.cdn\.office\.net$',
		'cat'         => 'social',
		'label'       => 'Microsoft Teams',
		'color'       => '#6264A7',
		'icon_domain' => 'teams.microsoft.com',
	),






	array(
		'pattern' => '(^|\.)copilot\.microsoft\.',
		'cat'     => 'ai',
		'label'   => 'Copilot',
		'color'   => '#0078D4',
	),
	array(
		'pattern' => 'chat\.openai\.',
		'cat'     => 'ai',
		'label'   => 'ChatGPT',
		'color'   => '#10A37F',
	),
	array(
		'pattern' => '(^|\.)chatgpt\.',
		'cat'     => 'ai',
		'label'   => 'ChatGPT',
		'color'   => '#10A37F',
	),
	array(
		'pattern' => '(^|\.)openai\.',
		'cat'     => 'ai',
		'label'   => 'OpenAI',
		'color'   => '#10A37F',
	),
	array(
		'pattern' => '(^|\.)claude\.ai',
		'cat'     => 'ai',
		'label'   => 'Claude',
		'color'   => '#D97706',
	),
	array(
		'pattern' => '(^|\.)anthropic\.',
		'cat'     => 'ai',
		'label'   => 'Claude',
		'color'   => '#D97706',
	),
	array(
		'pattern' => 'gemini\.google\.',
		'cat'     => 'ai',
		'label'   => 'Gemini',
		'color'   => '#4285F4',
	),
	array(
		'pattern' => 'aistudio\.google\.',
		'cat'     => 'ai',
		'label'   => 'Google AI Studio',
		'color'   => '#4285F4',
	),
	array(
		'pattern' => 'notebooklm\.google\.',
		'cat'     => 'ai',
		'label'   => 'NotebookLM',
		'color'   => '#4285F4',
	),
	array(
		'pattern' => '(^|\.)bard\.',
		'cat'     => 'ai',
		'label'   => 'Gemini',
		'color'   => '#4285F4',
	),
	array(
		'pattern' => '(^|\.)perplexity\.',
		'cat'     => 'ai',
		'label'   => 'Perplexity',
		'color'   => '#20B2AA',
	),
	array(
		'pattern' => '^you\.com',
		'cat'     => 'ai',
		'label'   => 'You.com',
		'color'   => '#8B5CF6',
	),
	array(
		'pattern'     => '(^|\.)mistral\.ai$',
		'cat'         => 'ai',
		'label'       => 'Mistral',
		'color'       => '#FF7000',
		'icon_domain' => 'mistral.ai',
	),
	array(
		'pattern' => '(^|\.)huggingface\.',
		'cat'     => 'ai',
		'label'   => 'HuggingFace',
		'color'   => '#FFD21E',
	),
	array(
		'pattern' => '(^|\.)grok\.',
		'cat'     => 'ai',
		'label'   => 'Grok',
		'color'   => '#000000',
	),
	array(
		'pattern' => '(^|\.)x\.ai',
		'cat'     => 'ai',
		'label'   => 'Grok',
		'color'   => '#000000',
	),
	array(
		'pattern' => '^meta\.ai',
		'cat'     => 'ai',
		'label'   => 'Meta AI',
		'color'   => '#0082FB',
	),
	array(
		'pattern' => '(^|\.)phind\.',
		'cat'     => 'ai',
		'label'   => 'Phind',
		'color'   => '#7C3AED',
	),
	array(
		'pattern' => '^poe\.com',
		'cat'     => 'ai',
		'label'   => 'Poe',
		'color'   => '#6366F1',
	),
	array(
		'pattern' => '(^|\.)deepseek\.',
		'cat'     => 'ai',
		'label'   => 'DeepSeek',
		'color'   => '#1E88E5',
	),

	// Known websites and alternate delivery domains.
	array(
		'pattern'     => '(^|\.)debian-fr\.org$',
		'cat'         => 'site',
		'label'       => 'Debian-fr.org',
		'color'       => '#A80030',
		'icon_domain' => 'debian-fr.org',
	),
	array(
		'pattern'     => '^(www\.)?programmez\.com$',
		'cat'         => 'site',
		'label'       => 'Programmez',
		'color'       => '#2563EB',
		'icon_domain' => 'programmez.com',
	),
	array(
		'pattern'     => '^www-programmez-com\.cdn\.ampproject\.org$',
		'cat'         => 'site',
		'label'       => 'Programmez',
		'color'       => '#2563EB',
		'icon_domain' => 'programmez.com',
	),
	array(
		'pattern'     => '(^|\.)inoreader\.com$',
		'cat'         => 'site',
		'label'       => 'Inoreader',
		'color'       => '#0EA5E9',
		'icon_domain' => 'inoreader.com',
	),
	array(
		'pattern'     => '(^|\.)annuaire-informaticiens\.fr$',
		'cat'         => 'site',
		'label'       => 'Annuaire Informaticiens',
		'color'       => '#64748B',
		'icon_domain' => 'annuaire-informaticiens.fr',
	),
	array(
		'pattern'     => '(^|\.)bonpote\.com$',
		'cat'         => 'site',
		'label'       => 'Bon Pote',
		'color'       => '#16A34A',
		'icon_domain' => 'bonpote.com',
	),
	array(
		'pattern'     => '(^|\.)linuxquimper\.org$',
		'cat'         => 'site',
		'label'       => 'Linux Quimper',
		'color'       => '#F59E0B',
		'icon_domain' => 'linuxquimper.org',
	),
	array(
		'pattern'     => '(^|\.)privazer\.com$',
		'cat'         => 'site',
		'label'       => 'PrivaZer',
		'color'       => '#0F766E',
		'icon_domain' => 'privazer.com',
	),
	array(
		'pattern'     => '(^|\.)wikipedia\.org$',
		'cat'         => 'site',
		'label'       => 'Wikipedia',
		'color'       => '#111827',
		'icon_domain' => 'wikipedia.org',
	),
	array(
		'pattern'     => '(^|\.)dealabs\.com$',
		'cat'         => 'site',
		'label'       => 'Dealabs',
		'color'       => '#E11D48',
		'icon_domain' => 'dealabs.com',
	),
	array(
		'pattern'     => '(^|\.)amd-osx\.com$',
		'cat'         => 'site',
		'label'       => 'AMD OS X',
		'color'       => '#DC2626',
		'icon_domain' => 'amd-osx.com',
	),
	array(
		'pattern'     => '(^|\.)linuxfr\.org$',
		'cat'         => 'site',
		'label'       => 'LinuxFr.org',
		'color'       => '#1D4ED8',
		'icon_domain' => 'linuxfr.org',
	),








);
