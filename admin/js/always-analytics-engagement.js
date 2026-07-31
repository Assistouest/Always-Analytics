


( function () {
	'use strict';


	function waitForConfig( cb ) {
		if ( typeof alwaysAnalyticsEngagement !== 'undefined' ) {
			cb();
			return;
		}
		var attempts = 0;
		var interval = setInterval( function () {
			attempts++;
			if ( typeof alwaysAnalyticsEngagement !== 'undefined' ) {
				clearInterval( interval );
				cb();
			} else if ( attempts > 20 ) {
				clearInterval( interval );
				console.warn( '[Always Analytics] alwaysAnalyticsEngagement not found.' );
			}
		}, 100 );
	}

	waitForConfig( function () {

		var API    = alwaysAnalyticsEngagement.restBase;
		var NONCE  = alwaysAnalyticsEngagement.nonce;
		var I18N   = alwaysAnalyticsEngagement.i18n || {};
		var LOCALE = alwaysAnalyticsEngagement.locale || document.documentElement.lang || 'en-US';

		var state = {
			from: dateOffset( 0 ),
			to:   dateOffset( 0 ),
		};

		var engChart       = null;
		var currentDataset = 'engaged';


		function init() {
			bindPeriodSelector();
			bindChartToggles();
			loadAll();
		}

		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', init );
		} else {
			init();
		}


		function buildPeriodMap() {
			var today = dateOffset( 0 );
			return {
				today:     { from: today,                               to: today },
				yesterday: { from: dateOffset( -1 ),                    to: dateOffset( -1 ) },
				'7days':   { from: dateOffset( -7 ),                    to: today },
				'30days':  { from: dateOffset( -30 ),                   to: today },
				'90days':  { from: dateOffset( -90 ),                   to: today },
				year:      { from: new Date().getFullYear() + '-01-01', to: today },
			};
		}

		function bindPeriodSelector() {
			var sel = document.getElementById( 'eng-period' );
			if ( ! sel ) { return; }

			sel.addEventListener( 'change', function () {
				var map    = buildPeriodMap();
				var period = map[ this.value ];
				if ( period ) {
					state.from = period.from;
					state.to   = period.to;
					loadAll();
				}
			} );
		}

		function bindChartToggles() {
			document.querySelectorAll( '[data-eng-dataset]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					document.querySelectorAll( '[data-eng-dataset]' ).forEach( function ( b ) {
						b.classList.remove( 'active' );
					} );
					this.classList.add( 'active' );
					currentDataset = this.getAttribute( 'data-eng-dataset' );
					if ( engChart ) { updateEngChartDataset(); }
				} );
			} );
		}


		function apiFetch( endpoint, extra, cb ) {
			var qs = 'from=' + enc( state.from ) + '&to=' + enc( state.to ) + '&_t=' + Date.now();
			if ( extra ) { qs += '&' + extra; }
			var sep = API.indexOf('?') !== -1 ? '&' : '?';
			fetch( API + endpoint + sep + qs, {
				cache: 'no-store',
				headers: {
					'X-WP-Nonce':     NONCE,
					'Cache-Control':  'no-cache, no-store',
					'Pragma':         'no-cache',
				},
			} )
				.then( function ( r ) { return r.json(); } )
				.then( cb )
				.catch( function ( e ) { console.error( '[AA Engagement]', e ); } );
		}


		var _overviewPageViews = 0;

		function loadAll() {
			apiFetch( 'engagement', null, renderMain );
			apiFetch( 'engagement/pages', 'limit=50', renderPagesTable );
			apiFetch( 'reader-profiles', null, renderReaderProfiles );
			apiFetch( 'overview', null, function ( d ) {
				_overviewPageViews = d.page_views || 0;
				if ( window._lastScrollDist ) {
					renderScrollDist( window._lastScrollDist, _overviewPageViews );
				}
			} );
		}


		function renderMain( d ) {
			var k = d.kpis || {};
			setText( 'eng-kpi-rate',     ( k.engagement_rate     || 0 ) + '%' );
			setText( 'eng-kpi-duration', fmtDuration( k.avg_duration     || 0 ) );
			setText( 'eng-kpi-pages',    parseFloat( k.avg_pages         || 0 ).toFixed( 1 ) );
			setText( 'eng-kpi-scroll',   parseFloat( k.avg_scroll_depth  || 0 ).toFixed( 0 ) + '%' );
			setText( 'eng-kpi-deepread', ( k.deep_read_rate      || 0 ) + '%' );

			renderEngChart( d.chart || [] );
			renderScrollDist( d.scroll_distribution || {}, _overviewPageViews );
		}


		function renderEngChart( data ) {
			var ctx = document.getElementById( 'eng-chart' );
			if ( ! ctx ) { return; }
			if ( engChart ) { engChart.destroy(); }

			var labels   = data.map( function ( d ) { return d.label; } );
			var colors   = { engaged: '#6c63ff', avg_dur: '#10b981', avg_scroll: '#f59e0b' };
			var titles   = {
				engaged:    I18N.engagedSessions || 'Engaged sessions',
				avg_dur:    I18N.averageDuration || 'Average duration (s)',
				avg_scroll: I18N.averageScroll || 'Average scroll (%)',
			};
			var datasets = {
				engaged:    data.map( function ( d ) { return d.future ? null : d.engaged; } ),
				avg_dur:    data.map( function ( d ) { return d.future ? null : d.avg_dur; } ),
				avg_scroll: data.map( function ( d ) { return d.future ? null : d.avg_scroll; } ),
			};

			engChart = new Chart( ctx, {
				type: 'line',
				data: {
					labels:   labels,
					datasets: Object.keys( datasets ).map( function ( key ) {
						return {
							label:            titles[ key ],
							data:             datasets[ key ],
							borderColor:      colors[ key ],
							backgroundColor:  colors[ key ] + '18',
							fill:             true,
							tension:          0.4,
							borderWidth:      2.5,
							hidden:           key !== currentDataset,
							spanGaps:         false,
							pointRadius:      0,
							pointHoverRadius: 5,
						};
					} ),
				},
				options: {
					responsive:          true,
					maintainAspectRatio: false,
					interaction:         { intersect: false, mode: 'index' },
					scales: {
						x: {
							grid:   { display: false },
							border: { display: false },
							ticks:  { font: { size: 11 } },
						},
						y: {
							beginAtZero: true,
							border:      { display: false },
							grid:        { color: 'rgba(0,0,0,0.04)' },
							ticks:       { font: { size: 11 } },
						},
					},
					plugins: {
						legend:  { display: false },
						tooltip: {
							backgroundColor: '#1d2327',
							cornerRadius:    8,
							padding:         10,
							callbacks: {
								label: function ( ctx ) {
									if ( ctx.parsed.y === null ) { return null; }
									if ( currentDataset === 'avg_dur' ) {
										return ctx.dataset.label + ': ' + fmtDuration( ctx.parsed.y );
									}
									if ( currentDataset === 'avg_scroll' ) {
										return ctx.dataset.label + ': ' + ctx.parsed.y + '%';
									}
									return ctx.dataset.label + ': ' + ctx.parsed.y;
								},
							},
						},
					},
				},
			} );
		}

		function updateEngChartDataset() {
			if ( ! engChart ) { return; }
			var labelMap = {
				engaged:    I18N.engagedSessions || 'Engaged sessions',
				avg_dur:    I18N.averageDuration || 'Average duration (s)',
				avg_scroll: I18N.averageScroll || 'Average scroll (%)',
			};
			engChart.data.datasets.forEach( function ( ds ) {
				ds.hidden = ( ds.label !== labelMap[ currentDataset ] );
			} );
			engChart.update();
		}


		function renderScrollDist( dist, totalPageViews ) {
			window._lastScrollDist = dist;

			var container = document.getElementById( 'eng-scroll-dist' );
			if ( ! container ) { return; }

			var measured   = ( dist[10] || 0 ) + ( dist[25] || 0 ) + ( dist[50] || 0 )
			               + ( dist[75] || 0 ) + ( dist[100] || 0 );
			var grandTotal = totalPageViews > 0 ? totalPageViews : measured;
			var noScroll   = Math.max( 0, grandTotal - measured );

			var buckets = [
				{ label: I18N.notMeasured || 'Not measured', val: noScroll,        color: '#e2e4e7', faded: true  },
				{ label: '< 25 %',        val: dist[10]  || 0,  color: '#94a3b8', faded: false },
				{ label: '25  49 %',     val: dist[25]  || 0,  color: '#60a5fa', faded: false },
				{ label: '50 – 74 %',     val: dist[50]  || 0,  color: '#34d399', faded: false },
				{ label: '75 – 99 %',     val: dist[75]  || 0,  color: '#10b981', faded: false },
				{ label: '100 %',         val: dist[100] || 0,  color: '#059669', faded: false },
			];

			var max  = Math.max.apply( null, buckets.map( function ( b ) { return b.val; } ) ) || 1;
			var html = '<div class="always-analytics-scroll-dist">';

			buckets.forEach( function ( b ) {
				var pct      = Math.round( ( b.val / max ) * 100 );
				var sharePct = grandTotal > 0
					? ' (' + Math.round( b.val / grandTotal * 100 ) + '%)'
					: '';
				var labelCls = 'always-analytics-scroll-dist__label' + ( b.faded ? ' always-analytics-scroll-dist__label--faded' : '' );
				var countCls = 'always-analytics-scroll-dist__count' + ( b.faded ? ' always-analytics-scroll-dist__count--faded' : '' );
				var fillCls  = 'always-analytics-scroll-dist__fill'  + ( b.faded ? ' always-analytics-scroll-dist__fill--faded'  : '' );

				html += '<div class="always-analytics-scroll-dist__item">'
					  +   '<div class="always-analytics-scroll-dist__head">'
					  +     '<span class="' + labelCls + '">' + b.label + '</span>'
					  +     '<span class="' + countCls + '">'
					  +       ( I18N.pageViewsCount || '%s page views' ).replace( '%s', b.val.toLocaleString( LOCALE ) ) + sharePct
					  +     '</span>'
					  +   '</div>'
					  +   '<div class="always-analytics-scroll-dist__bar">'
					  +     '<div class="' + fillCls + '" style="width:' + pct + '%;background:' + b.color + ';"></div>'
					  +   '</div>'
					  + '</div>';
			} );

			html += '</div>';
			html += '<p class="always-analytics-scroll-dist__note">'
				  + ( I18N.highestScrollNote || 'Each page view is counted <strong>only once</strong> in the highest scroll range reached.' )
				  + '</p>';

			container.innerHTML = html;
		}


		function renderReaderProfiles( data ) {
			var container = document.getElementById( 'eng-reader-profiles' );
			if ( ! container ) { return; }

			var profiles        = data.profiles || [];
			var total           = data.total_sessions || 0;

			if ( ! profiles.length || total === 0 ) {
				container.innerHTML = '<p class="always-analytics-no-data">' + htmlEscape( I18N.noData || 'No data is available for this period.' ) + '</p>';
				return;
			}


			var copy = {
				bouncer: {
					insight: I18N.highBounceRate || 'High bounce rate',
					action: I18N.bouncerAction || 'The content may not engage this segment, or the traffic targeting may be mismatched.',
				},
				explorer: {
					insight: I18N.partialReading || 'Partial content reading',
					action: I18N.explorerAction || 'The visitor browses quickly without deep engagement.',
				},
				rapid_scanner: {
					insight: I18N.specificAnswer || 'Search for a specific answer',
					action: I18N.scannerAction || 'Visitors may be comparing options or looking for a specific answer efficiently.',
				},
				deep_reader: {
					insight: I18N.completeReading || 'Complete reading and strong engagement',
					action: I18N.deepReaderAction || 'This segment engages deeply with the content, which may indicate a strong match with expectations.',
				},
			};


			var profileIcons = {
				bouncer:       '<svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>',
				explorer:       '<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
				rapid_scanner:     '<svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
				deep_reader: '<svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
			};


			var cardsHtml = '<div class="always-analytics-rp-cards">'
				+ profiles.map( function ( p ) {
					var c       = copy[ p.key ] || { insight: '', action: '' };
					var isEmpty = p.count === 0;
					return '<div class="always-analytics-rp-card always-analytics-rp-card--' + p.key + ( isEmpty ? ' always-analytics-rp-card--empty' : '' ) + '">'
						+   '<div class="always-analytics-rp-card__header">'
						+     '<span class="always-analytics-rp-card__label"><span class="always-analytics-rp-card__icon">' + ( profileIcons[ p.key ] || '' ) + '</span>' + htmlEscape( p.label ) + '</span>'
						+     '<span class="always-analytics-rp-card__range always-analytics-rp-badge--' + p.key + '">' + htmlEscape( p.range ) + '</span>'
						+   '</div>'
						+   '<div class="always-analytics-rp-card__pct">'
						+     ( isEmpty ? '' : p.pct + '\u202f%' )
						+   '</div>'
						+   '<div class="always-analytics-rp-card__count">'
						+     ( isEmpty
						? ( I18N.noSessions || 'No sessions' )
						: ( p.count === 1 ? ( I18N.sessionCount || '%s session' ) : ( I18N.sessionsCount || '%s sessions' ) ).replace( '%s', fmtInt( p.count ) ) )
						+   '</div>'
						+   '<div class="always-analytics-rp-card__copy">'
						+     '<p class="always-analytics-rp-card__insight">' + htmlEscape( c.insight ) + '</p>'
						+     '<p class="always-analytics-rp-card__action">' + htmlEscape( c.action ) + '</p>'
						+   '</div>'
						+ '</div>';
				} ).join( '' )
				+ '</div>';


			container.innerHTML = cardsHtml ;
		}


		var _pagesData   = [];
		var _visibleRows = 10;
		var PAGE_SIZE    = 10;

		function buildRowHtml( p, idx ) {
			var sig   = p.score_signals || {};
			var title = p.page_title || p.page_url || '—';
			var rank  = idx < 3 ? ( idx + 1 ) + '. ' : '';


			var durScore = parseFloat( ( sig.duration || {} ).score || 0 );
			var durCls   = durScore >= 70 ? 'always-analytics-metric--good' : durScore >= 40 ? 'always-analytics-metric--mid' : 'always-analytics-metric--low';
			var durHtml  = '<div class="always-analytics-metric ' + durCls + '">'
				+ '<span class="always-analytics-metric__val">' + fmtDuration( p.avg_duration || 0 ) + '</span>'
				+ '</div>';


			var scrollRaw   = ( sig.scroll && ( sig.scroll.raw != null ) ) ? sig.scroll.raw : null;
			var scrollScore = parseFloat( ( sig.scroll || {} ).score || 0 );
			var scrollCls   = scrollScore >= 70 ? 'always-analytics-metric--good' : scrollScore >= 40 ? 'always-analytics-metric--mid' : 'always-analytics-metric--low';
			var scrollHtml  = '<div class="always-analytics-metric ' + scrollCls + '">'
				+ '<span class="always-analytics-metric__val">' + ( scrollRaw !== null ? scrollRaw + '%' : '—' ) + '</span>'
				+ '</div>';


			var profileHtml;
			if ( scrollRaw === null ) {
				profileHtml = '<span class="always-analytics-profile always-analytics-profile--unknown">—</span>';
			} else {
				var profileKey, profileLabel, profileIcon;
				if ( scrollRaw < 20 ) {
					profileKey   = 'bouncer';
					profileLabel = I18N.bouncer || 'Bouncer';
					profileIcon  = '<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-top:-2px"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>';
				} else if ( scrollRaw < 75 ) {
					profileKey   = 'explorer';
					profileLabel = I18N.explorer || 'Explorer';
					profileIcon  = '<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-top:-2px"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
				} else {
					profileKey   = 'deep_reader';
					profileLabel = I18N.deepReader || 'Deep reader';
					profileIcon  = '<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-top:-2px"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>';
				}
				profileHtml = '<span class="always-analytics-profile always-analytics-profile--' + profileKey + '"'
					+ ' title="' + htmlEscape( ( I18N.averageScrollTitle || 'Average scroll: %s%%' ).replace( '%s', scrollRaw ) ) + '">'
					+ profileIcon + ' ' + profileLabel
					+ '</span>';
			}


			var wilsonScore = parseFloat( ( sig.confidence || {} ).score || 0 );
			var wilsonCls   = wilsonScore >= 70 ? 'always-analytics-wilson--high'
			                : wilsonScore >= 40 ? 'always-analytics-wilson--mid'
			                : 'always-analytics-wilson--low';
			var wilsonHtml  = '<div class="always-analytics-wilson ' + wilsonCls + '"'
				+ ' title="' + htmlEscape( ( I18N.sessionsReliability || '%1$s sessions · reliability: %2$s%%' )
					.replace( '%1$s', fmtInt( p.total_sessions ) )
					.replace( '%2$s', Math.round( wilsonScore ) ) ) + '">'
				+ '<div class="always-analytics-wilson-bar"><div class="always-analytics-wilson-bar__fill" style="width:' + Math.round( wilsonScore ) + '%;"></div></div>'
				+ '<span class="always-analytics-wilson__pct">' + Math.round( wilsonScore ) + ' %</span>'
				+ '</div>';


			var adminBase = ( typeof alwaysAnalyticsEngagement !== 'undefined' && alwaysAnalyticsEngagement.adminUrl )
				? alwaysAnalyticsEngagement.adminUrl
				: '/wp-admin/';
			var editLink = p.post_id > 0
				? adminBase + 'post.php?post=' + p.post_id + '&action=edit'
				: null;

			var titleHtml = editLink
				? '<a class="always-analytics-page-edit-link" href="' + editLink + '" target="_blank" rel="noopener noreferrer" title="' + htmlEscape( I18N.editInWordPress || 'Edit in WordPress' ) + '">'
					+ rank + htmlEscape( title )
					+ '<svg class="always-analytics-edit-icon" xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>'
					+ '</a>'
				: rank + htmlEscape( title );

			return '<tr class="always-analytics-eng-row">'
				+ '<td class="always-analytics-eng-cell-page">'
				+   '<div class="always-analytics-page-detail-title" title="' + htmlEscape( p.page_url ) + '">'
				+     titleHtml
				+   '</div>'
				+   '<div class="always-analytics-page-detail-url">' + htmlEscape( p.page_url ) + '</div>'
				+   '<div class="always-analytics-page-detail-views">'
				+     ( I18N.viewsSessions || '%1$s views · %2$s sessions' )
					.replace( '%1$s', fmtInt( p.page_views ) )
					.replace( '%2$s', fmtInt( p.total_sessions ) )
				+   '</div>'
				+ '</td>'
				+ '<td class="always-analytics-eng-cell-duration">' + durHtml + '</td>'
				+ '<td class="always-analytics-eng-cell-scroll">' + scrollHtml + '</td>'
				+ '<td class="always-analytics-eng-cell-profile">' + profileHtml + '</td>'
				+ '<td class="always-analytics-eng-cell-wilson">' + wilsonHtml + '</td>'
				+ '</tr>';
		}

		function renderPagesTableVisible() {
			var tbody    = document.querySelector( '#eng-pages-table tbody' );
			var wrapBtn  = document.getElementById( 'eng-show-more-wrap' );
			var remSpan  = document.getElementById( 'eng-show-more-remaining' );
			if ( ! tbody ) { return; }

			var visible = _pagesData.slice( 0, _visibleRows );
			tbody.innerHTML = visible.map( function ( p, i ) {
				return buildRowHtml( p, i );
			} ).join( '' );

			var remaining = _pagesData.length - _visibleRows;
			if ( wrapBtn ) {
				wrapBtn.style.display = remaining > 0 ? '' : 'none';
			}
			if ( remSpan ) {
				remSpan.textContent = remaining > 0 ? ( I18N.remaining || '(%s remaining)' ).replace( '%s', remaining ) : '';
			}
		}

		function renderPagesTable( data ) {
			var tbody = document.querySelector( '#eng-pages-table tbody' );
			if ( ! tbody ) { return; }

			if ( ! data || ! data.length ) {
				tbody.innerHTML = '<tr><td colspan="5" class="always-analytics-no-data">' + htmlEscape( I18N.noData || 'No data is available for this period.' ) + '</td></tr>';
				var wrapBtn = document.getElementById( 'eng-show-more-wrap' );
				if ( wrapBtn ) { wrapBtn.style.display = 'none'; }
				return;
			}

			_pagesData   = data;
			_visibleRows = PAGE_SIZE;
			renderPagesTableVisible();
			bindShowMore();
			bindExplainerToggle();
		}

		function bindShowMore() {
			var btn = document.getElementById( 'eng-show-more-btn' );
			if ( ! btn || btn._boundShowMore ) { return; }
			btn._boundShowMore = true;
			btn.addEventListener( 'click', function () {
				_visibleRows += PAGE_SIZE;
				renderPagesTableVisible();
			} );
		}

		function bindExplainerToggle() {
			var btn = document.getElementById( 'eng-score-toggle' );
			var box = document.getElementById( 'eng-score-explainer' );
			if ( ! btn || ! box || btn._bound ) { return; }
			btn._bound = true;


			if ( window.innerWidth <= 768 ) {
				box.hidden = true;
				btn.setAttribute( 'aria-expanded', 'false' );
			}

			btn.addEventListener( 'click', function () {

				if ( window.innerWidth > 768 ) { return; }
				var isOpen = ! box.hidden;
				box.hidden = isOpen;
				btn.setAttribute( 'aria-expanded', String( ! isOpen ) );
				btn.querySelector( '.always-analytics-score-info-chevron' ).classList.toggle( 'is-open', ! isOpen );
			} );
		}


		function setText( id, v ) {
			var el = document.getElementById( id );
			if ( el ) { el.textContent = v; }
		}

		function fmtInt( n ) {
			return ( parseInt( n, 10 ) || 0 ).toLocaleString( locale );
		}

		function fmtDuration( s ) {
			s = Math.round( +s || 0 );
			if ( s <= 0 ) { return '0s'; }
			var m = Math.floor( s / 60 );
			return m > 0 ? m + 'm ' + ( s % 60 ) + 's' : s + 's';
		}

		function dateOffset( d ) {
			var dt = new Date();
			dt.setDate( dt.getDate() + d );
			return dt.getFullYear() + '-'
				+ String( dt.getMonth() + 1 ).padStart( 2, '0' ) + '-'
				+ String( dt.getDate() ).padStart( 2, '0' );
		}

		function enc( s ) {
			return encodeURIComponent( s || '' );
		}

		function htmlEscape( s ) {
			var div = document.createElement( 'div' );
			div.appendChild( document.createTextNode( s || '' ) );
			return div.innerHTML;
		}

	} ); // end waitForConfig

}() );
