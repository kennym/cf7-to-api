( function () {
	function qsCf7OnReady( callback ) {
		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', callback );
			return;
		}

		callback();
	}

	function qsCf7Closest( node, selector ) {
		while ( node && node !== document ) {
			if ( node.matches && node.matches( selector ) ) {
				return node;
			}

			node = node.parentNode;
		}

		return null;
	}

	function qsCf7CheckInputType() {
		var select = document.getElementById( 'wpcf7-sf-input_type' );
		var nodes;
		var index;
		var keys;
		var inputType;

		if ( ! select ) {
			return;
		}

		inputType = select.value;
		nodes = document.querySelectorAll( '[data-qsindex]' );

		for ( index = 0; index < nodes.length; index++ ) {
			keys = ( nodes[ index ].getAttribute( 'data-qsindex' ) || '' ).split( ',' );
			nodes[ index ].style.display = ( -1 !== keys.indexOf( inputType ) ) ? 'block' : 'none';
		}
	}

	function qsCf7ToggleDebugLog( event ) {
		var trigger = qsCf7Closest( event.target, '.debug-log-trigger' );
		var wrap;

		if ( ! trigger ) {
			return;
		}

		wrap = trigger.parentNode.querySelector( '.debug-log-wrap' );

		if ( ! wrap ) {
			return;
		}

		wrap.style.display = ( 'block' === wrap.style.display ) ? 'none' : 'block';
	}

	function qsCf7DismissAdminNote( event ) {
		var notice = qsCf7Closest( event.target, '.qs-cf7-api-dismiss-notice-forever' );
		var body;

		if ( ! notice || ! notice.id || 'undefined' === typeof qsCf7ApiNotices ) {
			return;
		}

		body = new window.URLSearchParams();
		body.set( 'action', 'qs_cf7_api_admin_dismiss_notices' );
		body.set( 'id', notice.id );
		body.set( 'nonce', qsCf7ApiNotices.nonce );

		window.fetch( qsCf7ApiNotices.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		} );
	}

	qsCf7OnReady( function () {
		qsCf7CheckInputType();
		document.addEventListener( 'change', function ( event ) {
			if ( event.target && 'wpcf7-sf-input_type' === event.target.id ) {
				qsCf7CheckInputType();
			}
		} );
		document.addEventListener( 'click', qsCf7ToggleDebugLog );
		document.addEventListener( 'click', qsCf7DismissAdminNote );
	} );
}() );
