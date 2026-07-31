


( function () {
	'use strict';


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


	var tabs     = document.querySelectorAll( '.always-analytics-tab' );
	var panels   = document.querySelectorAll( '.always-analytics-panel' );
	var savebar  = document.getElementById( 'always-analytics-save-bar' );
	var tabField = document.getElementById( 'always-analytics-active-tab-field' );

	if ( tabs.length ) {
		var formTabs  = [ 'tracking', 'privacy', 'consent', 'performance' ];
		var STORE_KEY = 'always_analytics_active_tab';

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

		document.querySelectorAll( '.always-analytics-settings-tab-link' ).forEach( function ( a ) {
			a.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( a.dataset.tab ) {
					showTab( a.dataset.tab );
					try { sessionStorage.setItem( STORE_KEY, a.dataset.tab ); } catch ( e ) {}
					window.scrollTo( { top: 0, behavior: 'smooth' } );
				}
			} );
		} );


		var settingsForm = document.getElementById( 'always-analytics-settings-form' );
		if ( settingsForm ) {
			settingsForm.addEventListener( 'submit', function () {
				var active = document.querySelector( '.always-analytics-tab.active' );
				if ( active ) {
					try { sessionStorage.setItem( STORE_KEY, active.dataset.tab ); } catch ( e ) {}
				}
			} );
		}


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
