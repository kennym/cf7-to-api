<?php
/**
 * Tests for the request-building helpers.
 *
 * @package qs_cf7_api
 */

use PHPUnit\Framework\TestCase;

/**
 * Covers query joining, templates, headers, and HTTP success rules.
 */
class FunctionsTest extends TestCase {

	/**
	 * A URL that already has a query must gain fields with "&".
	 */
	public function test_append_query_uses_ampersand_when_url_already_has_one() {
		$url = qs_cf7_api_append_query(
			'https://example.test/build?token=abc',
			array(
				'name' => 'Ada',
			)
		);

		$this->assertSame( 'https://example.test/build?token=abc&name=Ada', $url );
	}

	/**
	 * A URL without a query starts with "?".
	 */
	public function test_append_query_adds_question_mark() {
		$url = qs_cf7_api_append_query(
			'https://example.test/build',
			array(
				'name' => 'Ada',
			)
		);

		$this->assertSame( 'https://example.test/build?name=Ada', $url );
	}

	/**
	 * Quotes, newlines, and dollar signs survive JSON substitution.
	 */
	public function test_json_template_encodes_scalars_and_booleans() {
		$template = '{ "name": "[your-name]", "ok": "[accept]", "note": [note] }';
		$filled   = qs_cf7_api_fill_template(
			$template,
			array(
				'your-name' => "a\"b\n\$5",
				'accept'    => true,
				'note'      => false,
			),
			'json'
		);

		$decoded = json_decode( $filled, true );

		$this->assertSame( JSON_ERROR_NONE, json_last_error() );
		$this->assertSame( "a\"b\n\$5", $decoded['name'] );
		$this->assertTrue( $decoded['ok'] );
		$this->assertFalse( $decoded['note'] );
	}

	/**
	 * A longer token is replaced before the shorter field name inside it.
	 */
	public function test_json_template_replaces_longer_tokens_first() {
		$filled = qs_cf7_api_fill_template(
			'{ "a": "[name]", "b": "[name-extra]" }',
			array(
				'name'       => 'short',
				'name-extra' => 'long',
			),
			'json'
		);

		$decoded = json_decode( $filled, true );

		$this->assertSame( 'short', $decoded['a'] );
		$this->assertSame( 'long', $decoded['b'] );
	}

	/**
	 * XML values escape reserved characters.
	 */
	public function test_xml_template_escapes_ampersand() {
		$filled = qs_cf7_api_fill_template(
			'<field value="[name]" />',
			array(
				'name' => 'a&b<c>',
			),
			'xml'
		);

		$this->assertStringContainsString( 'a&amp;b&lt;c&gt;', $filled );
		$this->assertStringNotContainsString( 'a&b', $filled );
	}

	/**
	 * A missing posted map must not be merged as a string.
	 */
	public function test_merge_map_accepts_missing_posted_map() {
		$map = qs_cf7_api_merge_template_map( '', '{ "name": "[your-name]" }' );

		$this->assertSame( array( 'your-name' => '' ), $map );
	}

	/**
	 * Posted map values win over the empty template placeholders.
	 */
	public function test_merge_map_keeps_posted_values() {
		$map = qs_cf7_api_merge_template_map(
			array(
				'your-name' => 'full_name',
			),
			'{ "name": "[your-name]" }'
		);

		$this->assertSame( 'full_name', $map['your-name'] );
	}

	/**
	 * An array template flattens instead of casting to the word Array.
	 */
	public function test_flatten_template_joins_array_parts() {
		$this->assertSame( "line-1\nline-2", qs_cf7_api_flatten_template( array( 'line-1', 'line-2' ) ) );
	}

	/**
	 * 200 and 204 succeed. 409 and transport errors fail.
	 */
	public function test_http_success_range() {
		$this->assertFalse( qs_cf7_api_response_failed( 200, false ) );
		$this->assertFalse( qs_cf7_api_response_failed( 204, false ) );
		$this->assertTrue( qs_cf7_api_response_failed( 409, false ) );
		$this->assertTrue( qs_cf7_api_response_failed( 500, false ) );
		$this->assertTrue( qs_cf7_api_response_failed( 200, true ) );
	}

	/**
	 * Bearer replaces a custom Authorization header instead of stacking.
	 */
	public function test_auth_header_prefers_bearer_and_replaces_custom_authorization() {
		$this->assertSame( 'Bearer token', qs_cf7_api_auth_header( 'user:secret', 'token' ) );
		$this->assertSame( 'Basic ' . base64_encode( 'user:secret' ), qs_cf7_api_auth_header( 'user:secret', '' ) );

		$headers = qs_cf7_api_parse_headers( "Authorization: custom\nX-Api-Key: abc" );
		$headers = qs_cf7_api_set_header( $headers, 'Authorization', 'Bearer token' );

		$this->assertSame(
			array(
				'X-Api-Key'     => 'abc',
				'Authorization' => 'Bearer token',
			),
			$headers
		);
	}

	/**
	 * Header lines with a bad name or a break are dropped.
	 */
	public function test_parse_headers_rejects_invalid_lines() {
		$headers = qs_cf7_api_parse_headers( "X-Api-Key: abc\nBad Name: no\n\nOnlyName" );

		$this->assertSame( array( 'X-Api-Key' => 'abc' ), $headers );
	}

	/**
	 * Timeout falls back to 15 and caps at 60.
	 */
	public function test_timeout_bounds() {
		$this->assertSame( 15, qs_cf7_api_sanitize_timeout( '' ) );
		$this->assertSame( 15, qs_cf7_api_sanitize_timeout( 0 ) );
		$this->assertSame( 5, qs_cf7_api_sanitize_timeout( 5 ) );
		$this->assertSame( 60, qs_cf7_api_sanitize_timeout( 90 ) );
	}
}
