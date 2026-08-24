/* global watchspireAdmin */
( function () {
	'use strict';

	if ( typeof watchspireAdmin === 'undefined' ) {
		return;
	}

	function post( body ) {
		body.append( 'nonce', watchspireAdmin.nonce );
		return fetch( watchspireAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function onClick( selector, handler ) {
		document.addEventListener( 'click', function ( event ) {
			var target = event.target.closest( selector );
			if ( target ) {
				handler( target, event );
			}
		} );
	}

	// Check now.
	onClick( '.watchspire-check-now', function ( button ) {
		var monitorId = button.getAttribute( 'data-monitor' );
		var originalText = button.textContent;
		button.disabled = true;
		button.textContent = watchspireAdmin.i18n.running;

		var body = new FormData();
		body.append( 'action', 'watchspire_run_check_now' );
		body.append( 'monitor_id', monitorId );

		post( body ).then( function () {
			window.location.reload();
		} ).catch( function () {
			button.disabled = false;
			button.textContent = originalText;
		} );
	} );

	// Run all checks now (dashboard header).
	onClick( '#wpdash-run-all', function ( button ) {
		var originalHtml = button.innerHTML;
		button.disabled = true;
		button.innerHTML = watchspireAdmin.i18n.running;

		var body = new FormData();
		body.append( 'action', 'watchspire_run_all_checks' );

		post( body ).then( function () {
			window.location.reload();
		} ).catch( function () {
			button.disabled = false;
			button.innerHTML = originalHtml;
		} );
	} );

	// Dashboard header dropdowns (date range / filters).
	//
	// These are native <details> elements, which handle their own open and
	// close on the summary but otherwise stay open until that summary is
	// clicked again. Dismiss them the way a menu is expected to behave:
	// clicking anywhere outside, or pressing Escape. Clicks landing inside
	// an open dropdown are left alone so the panel's own links, and the
	// custom-range date inputs, still work.
	function closeFilterDropdowns( except ) {
		var open = document.querySelectorAll( '.wpdash-filter[open]' );

		for ( var i = 0; i < open.length; i++ ) {
			if ( except && open[ i ].contains( except ) ) {
				continue;
			}

			open[ i ].removeAttribute( 'open' );
		}
	}

	document.addEventListener( 'click', function ( event ) {
		closeFilterDropdowns( event.target );
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' !== event.key && 'Esc' !== event.key ) {
			return;
		}

		var open = document.querySelector( '.wpdash-filter[open]' );

		if ( ! open ) {
			return;
		}

		closeFilterDropdowns( null );

		// Return focus to the control that was open, so keyboard users
		// aren't dropped back at the top of the document.
		var summary = open.querySelector( 'summary' );

		if ( summary ) {
			summary.focus();
		}
	} );

	// Link scan controls.
	[ 'start', 'pause', 'resume', 'cancel' ].forEach( function ( action ) {
		onClick( '#watchspire-scan-' + action, function () {
			if ( 'cancel' === action && ! window.confirm( watchspireAdmin.i18n.confirmCancel ) ) {
				return;
			}

			var body = new FormData();
			body.append( 'action', 'watchspire_link_scan_action' );
			body.append( 'scan_action', action );

			post( body ).then( function () {
				window.location.reload();
			} );
		} );
	} );

	// Live scan progress polling. Besides refreshing the displayed
	// percentage/gauge/elapsed time, each poll also nudges the scan
	// forward by one batch server-side — so progress keeps moving as
	// long as this screen stays open, regardless of whether WP-Cron or
	// a loopback request ever fires in the background.
	( function () {
		var scanControls = document.getElementById( 'watchspire-scan-controls' );

		if ( ! scanControls ) {
			return;
		}

		var tickTimer  = null;
		var startedAt  = parseInt( scanControls.getAttribute( 'data-started-at' ), 10 ) || 0;
		var finishedAt = parseInt( scanControls.getAttribute( 'data-finished-at' ), 10 ) || 0;

		function pad( n ) {
			return ( n < 10 ? '0' : '' ) + n;
		}

		function formatElapsed( seconds ) {
			seconds = Math.max( 0, seconds );
			var h = Math.floor( seconds / 3600 );
			var m = Math.floor( ( seconds % 3600 ) / 60 );
			var s = Math.floor( seconds % 60 );
			return pad( h ) + ':' + pad( m ) + ':' + pad( s );
		}

		function renderElapsed() {
			var el = document.getElementById( 'watchspire-scan-elapsed' );
			if ( ! el || ! startedAt ) {
				return;
			}
			var end = finishedAt || Math.floor( Date.now() / 1000 );
			el.textContent = watchspireAdmin.i18n.elapsed.replace( '%s', formatElapsed( end - startedAt ) );
		}

		function scanTitleFor( state ) {
			if ( 'extracting' === state.status || 'checking' === state.status ) {
				return watchspireAdmin.i18n.scanTitleBusy;
			}
			if ( 'paused' === state.status ) {
				return watchspireAdmin.i18n.scanTitlePaused;
			}
			if ( 'completed' === state.status ) {
				return watchspireAdmin.i18n.scanTitleCompleted;
			}
			return watchspireAdmin.i18n.scanTitleIdle;
		}

		function scanSubtitleFor( state ) {
			if ( 'extracting' === state.status ) {
				return watchspireAdmin.i18n.subtitleExtracting;
			}
			if ( 'checking' === state.status ) {
				return watchspireAdmin.i18n.subtitleChecking;
			}
			if ( 'paused' === state.status ) {
				return watchspireAdmin.i18n.subtitlePaused;
			}
			if ( 'completed' === state.status ) {
				var broken = state.broken || 0;
				var tpl = 1 === broken ? watchspireAdmin.i18n.subtitleFoundOne : watchspireAdmin.i18n.subtitleFoundMany;
				return tpl.replace( '%d', broken );
			}
			return watchspireAdmin.i18n.subtitleReady;
		}

		function applyState( state ) {
			var total   = Math.max( state.total, state.checked );
			var percent = total > 0 ? Math.min( 100, Math.round( ( state.checked / total ) * 100 ) ) : 0;

			var percentEl = document.getElementById( 'watchspire-scan-percent' );
			if ( percentEl ) {
				percentEl.textContent = percent + '%';
			}

			var gaugeFill = scanControls.querySelector( '.watchspire-gauge-fill' );
			if ( gaugeFill ) {
				gaugeFill.setAttribute( 'stroke-dasharray', percent + ' 100' );
			}

			var barFill = document.getElementById( 'watchspire-scan-progress-fill' );
			if ( barFill ) {
				barFill.style.width = percent + '%';
			}

			var titleEl = document.getElementById( 'watchspire-scan-status' );
			if ( titleEl ) {
				titleEl.textContent = scanTitleFor( state );
			}

			var subtitleEl = document.getElementById( 'watchspire-scan-progress' );
			if ( subtitleEl ) {
				subtitleEl.textContent = scanSubtitleFor( state );
			}

			var scannedEl = document.getElementById( 'watchspire-scan-meta-scanned' );
			if ( scannedEl ) {
				scannedEl.textContent = watchspireAdmin.i18n.scannedOf
					.replace( '%1$s', state.checked )
					.replace( '%2$s', total );
			}

			startedAt  = state.started_at || startedAt;
			finishedAt = state.finished_at || 0;
			renderElapsed();

			scanControls.setAttribute( 'data-state', state.status );

			var isBusy    = 'extracting' === state.status || 'checking' === state.status;
			var startBtn  = document.getElementById( 'watchspire-scan-start' );
			var pauseBtn  = document.getElementById( 'watchspire-scan-pause' );
			var resumeBtn = document.getElementById( 'watchspire-scan-resume' );
			var cancelBtn = document.getElementById( 'watchspire-scan-cancel' );
			var spinnerEl = document.getElementById( 'watchspire-scan-spinner' );
			var warningEl = document.getElementById( 'watchspire-scan-warning' );
			if ( startBtn ) { startBtn.disabled = isBusy; }
			if ( pauseBtn ) { pauseBtn.disabled = ! isBusy; }
			if ( resumeBtn ) { resumeBtn.disabled = 'paused' !== state.status; }
			if ( cancelBtn ) { cancelBtn.disabled = 'idle' === state.status || 'completed' === state.status; }
			if ( spinnerEl ) { spinnerEl.classList.toggle( 'is-visible', isBusy ); }
			if ( warningEl ) { warningEl.classList.toggle( 'is-visible', isBusy ); }

			return isBusy;
		}

		function poll() {
			var body = new FormData();
			body.append( 'action', 'watchspire_link_scan_poll' );

			post( body ).then( function ( response ) {
				if ( ! response || ! response.success ) {
					return;
				}

				var stillBusy = applyState( response.data );

				if ( stillBusy ) {
					window.setTimeout( poll, 1500 );
				} else {
					// poll() only ever runs while a scan was active, so
					// getting here means it just finished — reload once to
					// pick up refreshed stat cards and rows.
					window.location.reload();
				}
			} );
		}

		var initialState = scanControls.getAttribute( 'data-state' );

		renderElapsed();

		if ( startedAt && ! finishedAt ) {
			tickTimer = window.setInterval( renderElapsed, 1000 );
		}

		if ( 'extracting' === initialState || 'checking' === initialState ) {
			poll();
		}
	} )();

	// Dismiss the "system cron recommended" notice.
	onClick( '#watchspire-dismiss-cron-notice', function () {
		var notice = document.getElementById( 'watchspire-cron-notice' );

		var body = new FormData();
		body.append( 'action', 'watchspire_dismiss_cron_notice' );
		post( body );

		if ( notice ) {
			notice.remove();
		}
	} );

	// Link row actions (ignore/recheck).
	onClick( '.watchspire-link-action', function ( button ) {
		var row = button.closest( 'tr' );
		var id = row ? row.getAttribute( 'data-id' ) : null;
		var action = button.getAttribute( 'data-action' );

		if ( ! id || ! action ) {
			return;
		}

		var body = new FormData();
		body.append( 'action', 'watchspire_link_row_action' );
		body.append( 'row_action', action );
		body.append( 'id', id );

		post( body ).then( function () {
			if ( row ) {
				row.remove();
			}
		} );
	} );
} )();
