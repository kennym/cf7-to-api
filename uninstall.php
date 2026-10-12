<?php
/**
 * Uninstall cleanup.
 *
 * Removes the dismiss-notice option only. Contact Form 7 posts are left
 * in place, including API settings stored on those forms.
 *
 * @package qs_cf7_api
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'qs_cf7_api_notices_options' );
