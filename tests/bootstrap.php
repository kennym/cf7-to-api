<?php
/**
 * PHPUnit bootstrap. Optionally loads WordPress for integration tests.
 *
 * @package qs_cf7_api
 */

define( 'QS_CF7_API_TESTING', true );

// Set WP_LOAD_PATH to wp-load.php to also run the settings integration tests.
$wp_load_path = getenv( 'WP_LOAD_PATH' );

if ( $wp_load_path ) {
	define( 'DISABLE_WP_CRON', true );
	require_once $wp_load_path;
}

require_once dirname( __DIR__ ) . '/includes/qs-cf7-api-functions.php';
