<?php
/**
 * Plugin loader.
 *
 * @package qs_cf7_api
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots admin UI and the submission handler.
 */
class QS_CF7_atp_integration {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version = '';

	/**
	 * Plugin basename.
	 *
	 * @var string
	 */
	public $plugin_basename = '';

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	public $plugin_name = '';

	/**
	 * Admin and request handler.
	 *
	 * @var QS_CF7_api_admin
	 */
	public $admin;

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	public static $instance;

	/**
	 * Load dependencies and register hooks.
	 */
	public function __construct() {
		$this->plugin_name = 'qs-cf7-api';
		$this->load_dependencies();
		$this->admin              = new QS_CF7_api_admin();
		$this->admin->plugin_name = $this->plugin_name;
		self::$instance           = $this;
	}

	/**
	 * Require class files.
	 *
	 * @return void
	 */
	public function load_dependencies() {
		require_once QS_CF7_API_INCLUDES_PATH . 'qs-cf7-api-functions.php';
		require_once QS_CF7_API_INCLUDES_PATH . 'class.qs-admin-notices.php';
		require_once QS_CF7_API_INCLUDES_PATH . 'class-cf7-api-admin.php';
	}

	/**
	 * Current plugin instance.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
