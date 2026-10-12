<?php
/**
 * Pure helpers for request building.
 *
 * This file is loaded by the plugin and by PHPUnit. It does not call WordPress.
 *
 * @package qs_cf7_api
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'QS_CF7_API_TESTING' ) ) {
	exit;
}

/**
 * Append an associative array to a URL as a query string.
 *
 * A URL that already has a query keeps it and joins the new pairs with "&".
 *
 * @param string $url    Base URL.
 * @param mixed  $fields Query fields.
 * @return string
 */
function qs_cf7_api_append_query( $url, $fields ) {
	$url = (string) $url;

	if ( ! is_array( $fields ) || array() === $fields ) {
		return $url;
	}

	$query = http_build_query( $fields, '', '&' );

	if ( '' === $query ) {
		return $url;
	}

	$separator = ( false === strpos( $url, '?' ) ) ? '?' : '&';

	return $url . $separator . $query;
}

/**
 * Whether an HTTP status is in the 2xx range.
 *
 * @param mixed $code Status code.
 * @return bool
 */
function qs_cf7_api_is_success_code( $code ) {
	$code = (int) $code;

	return ( $code >= 200 && $code <= 299 );
}

/**
 * Whether a completed call should fail the form submission.
 *
 * @param mixed $code               HTTP status. Ignored for transport errors.
 * @param bool  $is_transport_error True when wp_remote returned a WP_Error.
 * @return bool
 */
function qs_cf7_api_response_failed( $code, $is_transport_error ) {
	if ( $is_transport_error ) {
		return true;
	}

	return ! qs_cf7_api_is_success_code( $code );
}

/**
 * Clamp a per-form timeout to 1-60 seconds. Empty values become 15.
 *
 * @param mixed $timeout Raw timeout.
 * @return int
 */
function qs_cf7_api_sanitize_timeout( $timeout ) {
	$timeout = (int) $timeout;

	if ( $timeout < 1 ) {
		return 15;
	}

	if ( $timeout > 60 ) {
		return 60;
	}

	return $timeout;
}

/**
 * Turn a stored template into a string.
 *
 * Arrays are flattened so a corrupted property can be shown and sent
 * without an "Array to string conversion" warning.
 *
 * @param mixed $template Template property.
 * @return string
 */
function qs_cf7_api_flatten_template( $template ) {
	if ( is_string( $template ) ) {
		return $template;
	}

	if ( ! is_array( $template ) ) {
		return '';
	}

	$lines = array();

	foreach ( $template as $part ) {
		if ( is_scalar( $part ) ) {
			$lines[] = (string) $part;
		}
	}

	return implode( "\n", $lines );
}

/**
 * Mail-tag tokens used inside a JSON or XML template, without brackets.
 *
 * @param mixed $template Template text.
 * @return string[]
 */
function qs_cf7_api_extract_template_tokens( $template ) {
	$template = qs_cf7_api_flatten_template( $template );

	if ( '' === $template ) {
		return array();
	}

	$found = preg_match_all( '/\[([^\[\]]+)\]/', $template, $matches );

	if ( ! $found || empty( $matches[1] ) ) {
		return array();
	}

	return array_values( array_unique( $matches[1] ) );
}

/**
 * Merge posted field-map values over tokens discovered in a template.
 *
 * A missing or non-array posted map is treated as empty instead of being
 * passed to array_merge(), which throws on PHP 8.
 *
 * @param mixed $posted_map Posted map.
 * @param mixed $template   Template text.
 * @return array
 */
function qs_cf7_api_merge_template_map( $posted_map, $template ) {
	if ( ! is_array( $posted_map ) ) {
		$posted_map = array();
	}

	$from_template = array();

	foreach ( qs_cf7_api_extract_template_tokens( $template ) as $token ) {
		$from_template[ $token ] = '';
	}

	return array_merge( $from_template, $posted_map );
}

/**
 * Parse "Name: value" lines into a header array.
 *
 * Invalid names and values that contain newlines are dropped.
 *
 * @param mixed $text Header block.
 * @return array<string,string>
 */
function qs_cf7_api_parse_headers( $text ) {
	$headers = array();
	$lines   = preg_split( "/\r\n|\n|\r/", (string) $text );

	if ( ! is_array( $lines ) ) {
		return $headers;
	}

	foreach ( $lines as $line ) {
		$line = trim( $line );

		if ( '' === $line || false === strpos( $line, ':' ) ) {
			continue;
		}

		$parts = explode( ':', $line, 2 );
		$name  = trim( $parts[0] );
		$value = trim( $parts[1] );

		if ( '' === $name || '' === $value ) {
			continue;
		}

		if ( ! preg_match( '/^[A-Za-z0-9-]+$/', $name ) ) {
			continue;
		}

		if ( preg_match( '/[\r\n]/', $value ) ) {
			continue;
		}

		$headers[ $name ] = $value;
	}

	return $headers;
}

/**
 * Set a header, replacing any existing header with the same name.
 *
 * @param array  $headers Header map.
 * @param string $name    Header name.
 * @param string $value   Header value.
 * @return array
 */
function qs_cf7_api_set_header( $headers, $name, $value ) {
	if ( ! is_array( $headers ) ) {
		$headers = array();
	}

	foreach ( array_keys( $headers ) as $existing ) {
		if ( strtolower( (string) $existing ) === strtolower( $name ) ) {
			unset( $headers[ $existing ] );
		}
	}

	$headers[ $name ] = $value;

	return $headers;
}

/**
 * Build one Authorization header value.
 *
 * Bearer wins when both credentials are present.
 *
 * @param mixed $basic  user:password string.
 * @param mixed $bearer Bearer token.
 * @return string Empty when neither credential is set.
 */
function qs_cf7_api_auth_header( $basic, $bearer ) {
	$bearer = trim( (string) $bearer );
	$basic  = trim( (string) $basic );

	if ( '' !== $bearer ) {
		return 'Bearer ' . $bearer;
	}

	if ( '' !== $basic ) {
		return 'Basic ' . base64_encode( $basic );
	}

	return '';
}

/**
 * JSON-encode a template value.
 *
 * @param mixed $value Field value.
 * @return string
 */
function qs_cf7_api_json_encode( $value ) {
	$encoded = json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	if ( false === $encoded ) {
		return 'null';
	}

	return $encoded;
}

/**
 * Escape a value for insertion into an XML template.
 *
 * @param mixed $value Field value.
 * @return string
 */
function qs_cf7_api_xml_escape( $value ) {
	if ( is_bool( $value ) ) {
		return $value ? 'true' : 'false';
	}

	if ( is_array( $value ) ) {
		$parts = array();

		foreach ( $value as $part ) {
			$parts[] = qs_cf7_api_xml_escape( $part );
		}

		return implode( ';', $parts );
	}

	return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
}

/**
 * Replace [tokens] in a JSON or XML template.
 *
 * JSON replacements use json_encode and consume a surrounding pair of
 * quotes, so booleans stay booleans and quotes inside strings stay valid.
 * Longer tokens are replaced first.
 *
 * @param mixed  $template Template text.
 * @param array  $values   Token => value.
 * @param string $type     "json" or "xml".
 * @return string
 */
function qs_cf7_api_fill_template( $template, $values, $type ) {
	$template = qs_cf7_api_flatten_template( $template );

	if ( '' === $template ) {
		return '';
	}

	if ( ! is_array( $values ) ) {
		$values = array();
	}

	$tokens = array_keys( $values );

	usort(
		$tokens,
		static function ( $left, $right ) {
			return strlen( (string) $right ) - strlen( (string) $left );
		}
	);

	foreach ( $tokens as $token ) {
		$token = (string) $token;

		if ( '' === $token ) {
			continue;
		}

		$quoted = preg_quote( $token, '/' );

		if ( 'json' === $type ) {
			$replacement = qs_cf7_api_json_encode( $values[ $token ] );
			// Callback, not a replacement string: json_encode emits "\" and "$".
			$updated = preg_replace_callback(
				'/"\[' . $quoted . '\]"|\\[' . $quoted . '\\]/',
				static function () use ( $replacement ) {
					return $replacement;
				},
				$template
			);

			if ( null !== $updated ) {
				$template = $updated;
			}
			continue;
		}

		$template = str_replace(
			'[' . $token . ']',
			qs_cf7_api_xml_escape( $values[ $token ] ),
			$template
		);
	}

	return (string) $template;
}
