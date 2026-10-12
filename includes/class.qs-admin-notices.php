<?php
/**
 * Admin notices.
 *
 * @package qs_cf7_api
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays and dismisses admin notices.
 */
class QS_Admin_notices {

	/**
	 * Notices waiting to be printed.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $notices = array();

	/**
	 * Stored notice options.
	 *
	 * @var array<string,mixed>
	 */
	private $notices_options = array();

	/**
	 * Register hooks and load stored options.
	 */
	public function __construct() {
		$this->register_hooks();
		$this->get_plugin_options();
	}

	/**
	 * Register display, dismiss, and asset hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_notices', array( $this, 'qs_admin_notices' ) );
		add_action( 'wp_ajax_qs_cf7_api_admin_dismiss_notices', array( $this, 'qs_admin_dismiss_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );
	}

	/**
	 * Styles and script for the Contact Form 7 editor only.
	 *
	 * @return void
	 */
	public function load_admin_scripts() {
		if ( ! $this->is_contact_form_screen() ) {
			return;
		}

		wp_register_style(
			'qs-cf7-api-admin-notices-css',
			QS_CF7_API_ADMIN_CSS_URL . 'admin-notices-style.css',
			array(),
			QS_CF7_API_VERSION
		);
		wp_enqueue_style( 'qs-cf7-api-admin-notices-css' );

		wp_register_script(
			'qs-cf7-api-admin-notices-script',
			QS_CF7_API_ADMIN_JS_URL . 'admin-notices-script.js',
			array(),
			QS_CF7_API_VERSION,
			true
		);
		wp_enqueue_script( 'qs-cf7-api-admin-notices-script' );

		wp_localize_script(
			'qs-cf7-api-admin-notices-script',
			'qsCf7ApiNotices',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'qs_cf7_api_dismiss_notice' ),
			)
		);
	}

	/**
	 * Whether the current screen is a Contact Form 7 admin screen.
	 *
	 * @return bool
	 */
	private function is_contact_form_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen || empty( $screen->id ) ) {
			return false;
		}

		return false !== strpos( (string) $screen->id, 'wpcf7' );
	}

	/**
	 * Persist a dismissed notice id.
	 *
	 * @return void
	 */
	public function qs_admin_dismiss_notices() {
		check_ajax_referer( 'qs_cf7_api_dismiss_notice', 'nonce' );

		if ( ! current_user_can( 'wpcf7_edit_contact_forms' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => 'forbidden',
				),
				403
			);
		}

		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';

		if ( '' !== $id ) {
			if ( ! isset( $this->notices_options['dismiss_notices'] ) || ! is_array( $this->notices_options['dismiss_notices'] ) ) {
				$this->notices_options['dismiss_notices'] = array();
			}

			$this->notices_options['dismiss_notices'][ $id ] = true;
			$this->update_plugin_options();
		}

		wp_send_json_success(
			array(
				'message' => 'updated',
			)
		);
	}

	/**
	 * Load stored options.
	 *
	 * @return void
	 */
	private function get_plugin_options() {
		$stored = get_option( 'qs_cf7_api_notices_options', array() );
		$stored = apply_filters( 'qs_cf7_api_notice_options', $stored );

		$this->notices_options = is_array( $stored ) ? $stored : array();
	}

	/**
	 * Save stored options.
	 *
	 * @return void
	 */
	private function update_plugin_options() {
		update_option( 'qs_cf7_api_notices_options', $this->notices_options );
	}

	/**
	 * Print queued notices.
	 *
	 * @return void
	 */
	public function qs_admin_notices() {
		if ( ! $this->notices ) {
			return;
		}

		foreach ( $this->notices as $admin_notice ) {
			$id        = (string) $admin_notice['id'];
			$dismissed = false;

			if (
				! empty( $admin_notice['dismissable_forever'] )
				&& isset( $this->notices_options['dismiss_notices'][ $id ] )
				&& $this->notices_options['dismiss_notices'][ $id ]
			) {
				$dismissed = true;
			}

			if ( $dismissed ) {
				continue;
			}

			$classes = array(
				'notice',
				'notice-' . $admin_notice['type'],
				'is-dismissible',
			);

			if ( ! empty( $admin_notice['dismissable_forever'] ) ) {
				$classes[] = 'qs-cf7-api-dismiss-notice-forever';
			}

			printf(
				'<div id="%1$s" class="%2$s"><p>%3$s</p></div>',
				esc_attr( $id ),
				esc_attr( implode( ' ', $classes ) ),
				esc_html( $admin_notice['notice'] )
			);
		}
	}

	/**
	 * Queue a notice.
	 *
	 * @param array<string,mixed>|string $notice Notice definition.
	 * @return void
	 */
	public function wp_add_notice( $notice = '' ) {
		if ( ! is_array( $notice ) || empty( $notice['id'] ) || empty( $notice['notice'] ) ) {
			return;
		}

		$type = isset( $notice['type'] ) ? (string) $notice['type'] : 'warning';

		if ( ! in_array( $type, array( 'error', 'warning', 'success', 'info' ), true ) ) {
			$type = 'warning';
		}

		$this->notices[] = array(
			'id'                  => (string) $notice['id'],
			'notice'              => (string) $notice['notice'],
			'type'                => $type,
			'dismissable_forever' => ! empty( $notice['dismissable_forever'] ),
		);
	}
}
