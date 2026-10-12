<?php
/**
 * Regression coverage using a real WordPress runtime.
 *
 * @package qs_cf7_api
 */

use PHPUnit\Framework\TestCase;

class SettingsIntegrationTest extends TestCase {
	private function saved_url( $url ) {
		if ( ! function_exists( 'esc_url_raw' ) ) {
			$this->markTestSkipped( 'Set WP_LOAD_PATH to wp-load.php to run this integration test.' );
		}
		require_once dirname( __DIR__ ) . '/includes/class-cf7-api-admin.php';
		$admin = ( new ReflectionClass( QS_CF7_api_admin::class ) )->newInstanceWithoutConstructor();
		$form = new class {
			public $properties = array();
			public function get_properties() {
				return $this->properties;
			}
			public function set_properties( $properties ) {
				$this->properties = $properties;
			}
		};
		$previous = $_POST;
		try {
			$_POST = wp_slash( array( 'wpcf7-sf' => array( 'send_to_api' => 'on', 'base_url' => $url ) ) );
			$admin->qs_save_contact_form_details( $form );
			$this->assertSame( 'on', $form->properties['wpcf7_api_data']['send_to_api'] );
			return $form->properties['wpcf7_api_data']['base_url'];
		} finally {
			$_POST = $previous;
		}
	}

	public function test_save_trims_surrounding_url_whitespace() {
		$this->assertSame( 'https://example.com/hook', $this->saved_url( " \thttps://example.com/hook \r\n" ) );
	}

	public function test_save_preserves_explicit_percent_encoded_spaces() {
		$this->assertSame( 'https://example.com/hook%20', $this->saved_url( 'https://example.com/hook%20' ) );
	}

	public function test_save_preserves_clean_url() {
		$this->assertSame( 'https://example.com/hook', $this->saved_url( 'https://example.com/hook' ) );
	}
}
