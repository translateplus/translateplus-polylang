<?php
/**
 * Minimal TranslatePlus API client for the Polylang addon.
 *
 * @package TranslatePlus_Polylang_Addon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TPPL_API_Client {
	public const TEXT_ENDPOINT    = 'https://api.translateplus.io/v2/translate';
	public const HTML_ENDPOINT    = 'https://api.translateplus.io/v2/translate/html';
	public const BATCH_ENDPOINT   = 'https://api.translateplus.io/v2/translate/batch';
	public const SUMMARY_ENDPOINT = 'https://api.translateplus.io/v2/account/summary';

	private const TIMEOUT = 45;
	private const SUMMARY_TRANSIENT = 'tppl_account_summary';
	private const SUMMARY_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * @return string
	 */
	public static function get_api_key(): string {
		$key = get_option( TPPL_Settings::OPTION_API_KEY, '' );
		return is_string( $key ) ? trim( $key ) : '';
	}

	/**
	 * @return string|WP_Error
	 */
	public static function translate_text( string $text, string $target, string $source ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return '';
		}

		$payload = array(
			'text'   => $text,
			'source' => $source,
			'target' => $target,
		);

		$decoded = self::request_json( 'POST', self::TEXT_ENDPOINT, $payload );
		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		if ( isset( $decoded['translations']['translation'] ) && is_string( $decoded['translations']['translation'] ) ) {
			$out = trim( (string) $decoded['translations']['translation'] );
			return '' !== $out ? $out : $text;
		}

		return new WP_Error( 'tppl_missing_translation', __( 'No translation was returned by TranslatePlus.', 'translateplus-polylang-addon' ) );
	}

	/**
	 * @param string[] $texts
	 * @return string[]|WP_Error
	 */
	public static function translate_batch( array $texts, string $target, string $source ) {
		$texts = array_values( array_map( 'strval', $texts ) );
		if ( count( $texts ) === 0 ) {
			return array();
		}
		if ( count( $texts ) > 100 ) {
			return new WP_Error( 'tppl_batch_too_large', __( 'Batch translation supports up to 100 texts per request.', 'translateplus-polylang-addon' ) );
		}

		$payload = array(
			'texts'  => $texts,
			'source' => $source,
			'target' => $target,
		);

		$decoded = self::request_json( 'POST', self::BATCH_ENDPOINT, $payload );
		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		if ( ! isset( $decoded['translations'] ) || ! is_array( $decoded['translations'] ) ) {
			return new WP_Error( 'tppl_batch_bad_shape', __( 'TranslatePlus returned an invalid batch response.', 'translateplus-polylang-addon' ) );
		}

		$out = array();
		foreach ( $decoded['translations'] as $i => $row ) {
			$row = is_array( $row ) ? $row : array();

			$success     = isset( $row['success'] ) ? (bool) $row['success'] : true;
			$translation = isset( $row['translation'] ) && is_string( $row['translation'] ) ? $row['translation'] : '';

			if ( ! $success ) {
				return new WP_Error(
					'tppl_batch_item_failed',
					sprintf(
						/* translators: %d: index (1-based) */
						__( 'TranslatePlus failed to translate item %d in the batch.', 'translateplus-polylang-addon' ),
						(int) $i + 1
					),
					array( 'item' => $row )
				);
			}

			$out[] = '' !== $translation ? $translation : ( $texts[ $i ] ?? '' );
		}

		if ( count( $out ) !== count( $texts ) ) {
			return new WP_Error( 'tppl_batch_count_mismatch', __( 'TranslatePlus batch response size mismatch.', 'translateplus-polylang-addon' ) );
		}

		return $out;
	}

	/**
	 * @return string|WP_Error
	 */
	public static function translate_html( string $html, string $target, string $source ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return '';
		}

		$payload = array(
			'html'   => $html,
			'source' => $source,
			'target' => $target,
		);

		$decoded = self::request_json( 'POST', self::HTML_ENDPOINT, $payload );
		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		if ( isset( $decoded['html'] ) && is_string( $decoded['html'] ) ) {
			return (string) $decoded['html'];
		}

		return new WP_Error( 'tppl_missing_html', __( 'No HTML translation was returned by TranslatePlus.', 'translateplus-polylang-addon' ) );
	}

	/**
	 * Account summary (cached).
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function get_account_summary( bool $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::SUMMARY_TRANSIENT );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$decoded = self::request_json( 'GET', self::SUMMARY_ENDPOINT );
		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		set_transient( self::SUMMARY_TRANSIENT, $decoded, self::SUMMARY_TTL );
		return $decoded;
	}

	/**
	 * Validate and fetch account summary for a provided API key.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function get_account_summary_for_key( string $api_key ) {
		return self::request_json( 'GET', self::SUMMARY_ENDPOINT, array(), $api_key );
	}

	public static function clear_account_summary_cache(): void {
		delete_transient( self::SUMMARY_TRANSIENT );
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>|WP_Error
	 */
	private static function request_json( string $method, string $url, array $payload = array(), string $api_key_override = '' ) {
		$api_key = '' !== trim( $api_key_override ) ? trim( $api_key_override ) : self::get_api_key();
		if ( '' === $api_key ) {
			return new WP_Error( 'tppl_no_api_key', __( 'TranslatePlus API key is not set. Add it under Settings → TranslatePlus (Polylang).', 'translateplus-polylang-addon' ) );
		}

		$args = array(
			'method'  => $method,
			'timeout' => self::TIMEOUT,
			'headers' => array(
				'X-API-KEY'     => $api_key,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json; charset=utf-8',
			),
		);

		if ( 'GET' !== strtoupper( $method ) ) {
			$args['body'] = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = (string) wp_remote_retrieve_body( $response );

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'tppl_bad_json',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Invalid JSON from TranslatePlus API (HTTP %d).', 'translateplus-polylang-addon' ),
					$status
				),
				array( 'status' => $status, 'body' => $body )
			);
		}

		if ( $status >= 200 && $status < 300 ) {
			return $decoded;
		}

		if ( 401 === $status || 403 === $status ) {
			return new WP_Error( 'tppl_auth_failure', __( 'Authentication failure. Please check your TranslatePlus API key.', 'translateplus-polylang-addon' ) );
		}

		$message = self::extract_api_error_message( $decoded );
		if ( '' === $message ) {
			$message = sprintf(
				/* translators: %d: HTTP status code */
				__( 'TranslatePlus API returned HTTP %d.', 'translateplus-polylang-addon' ),
				$status
			);
		}

		return new WP_Error( 'tppl_http_error', $message, array( 'status' => $status, 'response' => $decoded ) );
	}

	/**
	 * @param array<string,mixed> $decoded
	 */
	private static function extract_api_error_message( array $decoded ): string {
		foreach ( array( 'message', 'error' ) as $key ) {
			if ( isset( $decoded[ $key ] ) && is_string( $decoded[ $key ] ) ) {
				$out = trim( $decoded[ $key ] );
				if ( '' !== $out ) {
					return $out;
				}
			}
		}

		if ( isset( $decoded['detail'] ) && is_string( $decoded['detail'] ) ) {
			$out = trim( $decoded['detail'] );
			if ( '' !== $out ) {
				return $out;
			}
		}

		if ( isset( $decoded['detail'] ) && is_array( $decoded['detail'] ) ) {
			$parts = array();
			foreach ( $decoded['detail'] as $item ) {
				if ( is_string( $item ) ) {
					$parts[] = trim( $item );
					continue;
				}
				if ( ! is_array( $item ) ) {
					continue;
				}
				foreach ( array( 'msg', 'message', 'detail' ) as $ik ) {
					if ( isset( $item[ $ik ] ) && is_string( $item[ $ik ] ) && '' !== trim( $item[ $ik ] ) ) {
						$parts[] = trim( $item[ $ik ] );
						break;
					}
				}
			}
			$joined = trim( implode( ' ', array_unique( array_filter( $parts ) ) ) );
			if ( '' !== $joined ) {
				return $joined;
			}
		}

		return '';
	}
}

