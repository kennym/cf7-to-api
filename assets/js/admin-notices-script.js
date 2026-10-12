jQuery( document ).ready( function () {
	qsCf7DismissAdminNote();
	qsCf7CheckInputType();
	qsCf7ToggleDebugLog();
	qsCf7ToggleRequestType();
} );

function qsCf7ToggleDebugLog() {
	jQuery( document ).on( 'click', '.debug-log-trigger', function () {
		jQuery( '.debug-log-wrap' ).slideToggle();
	} );
}

function qsCf7ToggleRequestType() {
	jQuery( document ).on( 'change', '#wpcf7-sf-input_type', function () {
		qsCf7CheckInputType();
	} );
}

function qsCf7CheckInputType() {
	if ( jQuery( '#wpcf7-sf-input_type' ).length ) {
		var inputType = jQuery( '#wpcf7-sf-input_type' ).val();

		jQuery( '[data-qsindex]' ).hide();
		jQuery( '[data-qsindex*="' + inputType + '"]' ).show();
	}
}

function qsCf7DismissAdminNote() {
	jQuery( '.qs-cf7-api-dismiss-notice-forever' ).on( 'click', function () {
		if ( 'undefined' === typeof qsCf7ApiNotices ) {
			return;
		}

		jQuery.ajax( {
			type: 'POST',
			url: qsCf7ApiNotices.ajaxUrl,
			data: {
				action: 'qs_cf7_api_admin_dismiss_notices',
				id: jQuery( this ).attr( 'id' ),
				nonce: qsCf7ApiNotices.nonce
			}
		} );
	} );
}
