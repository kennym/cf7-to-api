<?php
/**
 * Contact Form 7 to API.
 *
 * @link              https://github.com/kennym/cf7-to-api
 * @since             1.0.0
 * @package           qs_cf7_api
 *
 * @wordpress-plugin
 * Plugin Name:       Contact Form 7 to API
 * Plugin URI:        https://wordpress.org/plugins/cf7-to-api-basic-auth/
 * Description:       Send Contact Form 7 submissions to a remote API with GET or POST.
 * Version:           1.5.3
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Kenny Meyer
 * Author URI:        https://www.kennymeyer.net
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       qs-cf7-api
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// A second copy (GitHub folder cf7-to-api plus the .org folder
// cf7-to-api-basic-auth) used to fatal on activation. Bail out instead.
if ( defined( 'QS_CF7_API_LOADED' ) || function_exists( 'qs_cf7_textdomain' ) || class_exists( 'QS_CF7_atp_integration', false ) ) {
	return;
}

define( 'QS_CF7_API_LOADED', true );
define( 'QS_CF7_API_VERSION', '1.5.3' );
define( 'QS_CF7_API_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'QS_CF7_API_INCLUDES_PATH', plugin_dir_path( __FILE__ ) . 'includes/' );
define( 'QS_CF7_API_ADMIN_JS_URL', plugin_dir_url( __FILE__ ) . 'assets/js/' );
define( 'QS_CF7_API_ADMIN_CSS_URL', plugin_dir_url( __FILE__ ) . 'assets/css/' );

require_once QS_CF7_API_INCLUDES_PATH . 'class.cf7-api.php';

/**
 * Load translations.
 *
 * @return void
 */
function qs_cf7_api_load_textdomain() {
	load_plugin_textdomain( 'qs-cf7-api', false, basename( __DIR__ ) . '/languages' );
}
add_action( 'plugins_loaded', 'qs_cf7_api_load_textdomain', 10 );

/**
 * Create the plugin instance after Contact Form 7 has loaded.
 *
 * @return void
 */
function qs_cf7_api_bootstrap() {
	global $qs_cf7_api;

	$qs_cf7_api                  = new QS_CF7_atp_integration();
	$qs_cf7_api->version         = QS_CF7_API_VERSION;
	$qs_cf7_api->plugin_basename = plugin_basename( __FILE__ );
}
add_action( 'plugins_loaded', 'qs_cf7_api_bootstrap', 11 );

/**
 * Activation hook. Kept so existing listeners of cf7_api_activated still run.
 *
 * @return void
 */
function qs_cf7_api_activate() {
	do_action( 'cf7_api_activated' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook.
}

/**
 * Deactivation hook.
 *
 * @return void
 */
function qs_cf7_api_deactivate() {
	do_action( 'cf7_api_deactivated' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook.
}

register_activation_hook( __FILE__, 'qs_cf7_api_activate' );
register_deactivation_hook( __FILE__, 'qs_cf7_api_deactivate' );
