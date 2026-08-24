/* global watchspireNotices */
( function () {
	'use strict';

	if ( typeof watchspireNotices === 'undefined' ) {
		return;
	}

	function dismiss( noticeEl, permanent ) {
		var id = noticeEl ? noticeEl.getAttribute( 'data-watchspire-notice' ) : null;

		if ( ! id ) {
			return;
		}

		var body = new FormData();
		body.append( 'action', 'watchspire_dismiss_notice' );
		body.append( 'nonce', watchspireNotices.nonce );
		body.append( 'notice', id );

		if ( permanent ) {
			body.append( 'permanent', '1' );
		}

		fetch( watchspireNotices.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		} );
	}

	function closest( el, selector ) {
		return el && el.closest ? el.closest( selector ) : null;
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		var notice = closest( target, '.watchspire-admin-notice' );

		if ( ! notice ) {
			return;
		}

		// WordPress's own dismiss button — remember it, but only until the
		// next plugin update.
		if ( closest( target, '.notice-dismiss' ) ) {
			dismiss( notice, false );
			return;
		}

		// Opens the review form. Deliberately does not dismiss: the
		// "already done it" button is how someone says they're finished.
		if ( closest( target, '.watchspire-review-now' ) ) {
			window.open( watchspireNotices.reviewUrl, '_blank', 'noopener' );
			return;
		}

		if ( closest( target, '.watchspire-review-later' ) ) {
			dismiss( notice, false );
			notice.remove();
			return;
		}

		if ( closest( target, '.watchspire-review-never' ) ) {
			dismiss( notice, true );
			notice.remove();
		}
	} );
} )();
