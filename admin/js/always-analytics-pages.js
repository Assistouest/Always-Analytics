/**
 * Always Analytics — Page-specific scripts.
 *
 * Handles:
 *  - Top Pages view  (date filter + live search)
 *  - Settings view   (tab system + session-storage restore)
 *
 * All page URLs are passed via alwaysAnalyticsPages (wp_localize_script).
 *
 * @package Always_Analytics
 */

( function () {
	'use strict';

	// ── Top Pages view ──────────────────────────────────────────────────────

	var tpApply = document.getElementById( 'tp-apply' );
	if ( tpApply ) {
		tpApply.addEventListener( 'click', function () {
			var from = document.getElementById( 'tp-from' ).value;
			var to   = document.getElementById( 'tp-to' ).value;
			if ( from && to ) {
				window.location.href =
					alwaysAnalyticsPages.topPagesUrl +
					'&from=' + encodeURIComponent( from ) +
					'&to='   + encodeURIComponent( to );
			}
		} );
	}

	var tpSearch = document.getElementById( 'tp-search' );
	if ( tpSearch ) {
		tpSearch.addEventListener( 'input', function () {
			var q    = this.value.toLowerCase();
			var rows = document.querySelectorAll( '#tp-table .tp-row' );
			rows.forEach( function ( row ) {
				row.style.display = row.textContent.toLowerCase().includes( q ) ? '' : 'none';
			} );
		} );
	}

	// ── Settings view — tab system ──────────────────────────────────────────

	var tabs     = document.querySelectorAll( '.as-tab' );
	var panels   = document.querySelectorAll( '.as-panel' );
	var savebar  = document.getElementById( 'aa-save-bar' );
	var tabField = document.getElementById( 'aa-active-tab-field' );

	if ( tabs.length ) {
		var formTabs  = [ 'tracking', 'privacy', 'consent', 'performance' ];
		var STORE_KEY = 'aa_active_tab';

		function showTab( tab ) {
			tabs.forEach( function ( t ) {
				t.classList.toggle( 'active', t.dataset.tab === tab );
			} );
			panels.forEach( function ( p ) {
				p.classList.toggle( 'active', p.dataset.panel === tab );
			} );
			if ( savebar ) {
				savebar.style.display = formTabs.indexOf( tab ) !== -1 ? '' : 'none';
			}
			if ( tabField ) {
				tabField.value = tab;
			}
		}

		tabs.forEach( function ( t ) {
			t.addEventListener( 'click', function () {
				showTab( t.dataset.tab );
				try { sessionStorage.setItem( STORE_KEY, t.dataset.tab ); } catch ( e ) {}
			} );
		} );

		document.querySelectorAll( '.aa-settings-tab-link' ).forEach( function ( a ) {
			a.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( a.dataset.tab ) {
					showTab( a.dataset.tab );
					try { sessionStorage.setItem( STORE_KEY, a.dataset.tab ); } catch ( e ) {}
					window.scrollTo( { top: 0, behavior: 'smooth' } );
				}
			} );
		} );

		// Persist active tab across form submit.
		var settingsForm = document.getElementById( 'aa-settings-form' );
		if ( settingsForm ) {
			settingsForm.addEventListener( 'submit', function () {
				var active = document.querySelector( '.as-tab.active' );
				if ( active ) {
					try { sessionStorage.setItem( STORE_KEY, active.dataset.tab ); } catch ( e ) {}
				}
			} );
		}

		// Restore tab after save redirect (settings-updated=true in URL).
		var urlParams = new URLSearchParams( window.location.search );
		if ( urlParams.get( 'settings-updated' ) === 'true' ) {
			try {
				var saved = sessionStorage.getItem( STORE_KEY );
				if ( saved ) { showTab( saved ); sessionStorage.removeItem( STORE_KEY ); }
			} catch ( e ) {}
		} else if ( window.location.hash === '#tab-consent' ) {
			showTab( 'consent' );
		}
	}

}() );
