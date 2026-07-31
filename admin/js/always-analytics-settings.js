/**
 * Always Analytics settings interactions.
 *
 * The radio inputs remain native and visible. JavaScript only enhances the
 * interface by switching mode-specific panels and the active-profile summary.
 *
 * @package Always_Analytics
 */

( function () {
	'use strict';

	function initSettingsPage() {
		var page = document.querySelector( '.always-analytics-settings-page' );
		if ( ! page ) {
			return;
		}

		var form          = document.getElementById( 'always-analytics-settings-form' );
		var radios        = page.querySelectorAll( 'input[name="always_analytics_options[tracking_mode]"]' );
		var panels        = page.querySelectorAll( '[data-mode-panel]' );
		var cards         = page.querySelectorAll( '[data-mode-card]' );
		var summaryTitle  = page.querySelector( '[data-mode-summary-title]' );
		var summaryText   = page.querySelector( '[data-mode-summary-text]' );
		var summaryBanner = page.querySelector( '[data-summary-banner]' );

		function setPanelControls( panel, enabled ) {
			panel.querySelectorAll( 'input, select, textarea, button' ).forEach( function ( control ) {
				control.disabled = ! enabled;
			} );
		}

		function applyMode( mode ) {
			var normalizedMode = 'cookie' === mode ? 'cookie' : 'cookieless';
			page.dataset.trackingMode = normalizedMode;

			cards.forEach( function ( card ) {
				var selected = card.dataset.modeCard === normalizedMode;
				card.classList.toggle( 'is-selected', selected );
				card.setAttribute( 'aria-checked', selected ? 'true' : 'false' );
			} );

			panels.forEach( function ( panel ) {
				var active = panel.dataset.modePanel === normalizedMode;
				panel.hidden = ! active;
				setPanelControls( panel, active );
			} );

			if ( summaryTitle && summaryText && summaryBanner ) {
				if ( 'cookie' === normalizedMode ) {
					summaryTitle.textContent = page.dataset.cookieTitle || '';
					summaryText.textContent = page.dataset.cookieSummary || '';
					summaryBanner.innerHTML = '<i class="dashicons dashicons-yes"></i>' + ( page.dataset.cookieBanner || '' );
				} else {
					summaryTitle.textContent = page.dataset.cookielessTitle || '';
					summaryText.textContent = page.dataset.cookielessSummary || '';
					summaryBanner.innerHTML = '<i class="dashicons dashicons-yes"></i>' + ( page.dataset.cookielessBanner || '' );
				}
			}
		}

		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				if ( radio.checked ) {
					applyMode( radio.value );
				}
			} );
		} );

		// A direct click handler is kept as a compatibility fallback for admin
		// interfaces that interfere with the native label/radio behaviour.
		cards.forEach( function ( card ) {
			card.addEventListener( 'click', function ( event ) {
				if ( event.target.closest( 'a, button, select, textarea' ) ) {
					return;
				}

				var radio = card.querySelector( 'input[type="radio"]' );
				if ( radio && ! radio.checked ) {
					radio.checked = true;
					radio.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			} );
		} );

		if ( form ) {
			form.addEventListener( 'submit', function () {
				var selected = page.querySelector( 'input[name="always_analytics_options[tracking_mode]"]:checked' );
				if ( ! selected && radios.length ) {
					radios[0].checked = true;
				}
			} );
		}

		var selected = page.querySelector( 'input[name="always_analytics_options[tracking_mode]"]:checked' );
		applyMode( selected ? selected.value : 'cookieless' );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initSettingsPage );
	} else {
		initSettingsPage();
	}
}() );
