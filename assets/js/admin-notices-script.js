jQuery(document).ready(function(){
    //dismiss admin notice forever
    qs_cf7_dismiss_admin_note();

    check_input_type();

    toggle_debug_log();

    toggle_request_type();

});

function toggle_debug_log(){
    jQuery( document ).on( 'click' , '.debug-log-trigger' , function(){
        jQuery( '.debug-log-wrap' ).slideToggle();
    });
}
function toggle_request_type(){
    jQuery( document ).on( 'change' , '#wpcf7-sf-input_type', function(){
        check_input_type();
    });
}
// Check input type on API Integration TAB
function check_input_type(){
    if( jQuery( '#wpcf7-sf-input_type' ).length ){
        var input_type = jQuery( '#wpcf7-sf-input_type' ).val();

        jQuery( '[data-qsindex]').fadeOut();

        jQuery( '[data-qsindex*="'+input_type+'"]' ).fadeIn();
    }
}

function qs_cf7_dismiss_admin_note(){
    jQuery(".qs-cf7-api-dismiss-notice-forever").click(function(){

        var id = jQuery( this ).attr( 'id' );

        jQuery.ajax({
            type: "post",
            url: ajaxurl,
            data: {
                action: 'qs_cf7_api_admin_dismiss_notices',
                id : id
            },

        });
    });
}
