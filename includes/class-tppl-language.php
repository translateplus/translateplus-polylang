<?php
/**
 * Map Polylang language slugs / locales to TranslatePlus API codes.
 *
 * @package TranslatePlus_Polylang_Addon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TPPL_Language {
	/**
	 * @var array<string,string>|null
	 */
	private static $lookup = null;

	/**
	 * @return array<string,string> lower_underscore => canonical API code
	 */
	private static function canonical_lookup(): array {
		if ( null !== self::$lookup ) {
			return self::$lookup;
		}

		$path = plugin_dir_path( TRANSLATEPLUS_POLYLANG_ADDON_FILE ) . 'assets/tppl-api-language-codes.json';
		if ( ! is_readable( $path ) ) {
			self::$lookup = array();
			return self::$lookup;
		}

		$json = file_get_contents( $path );
		$list = is_string( $json ) ? json_decode( $json, true ) : null;
		if ( ! is_array( $list ) ) {
			self::$lookup = array();
			return self::$lookup;
		}

		$lookup = array();
		foreach ( $list as $code ) {
			if ( ! is_string( $code ) || '' === $code ) {
				continue;
			}
			$key            = self::normalize_key( $code );
			$lookup[ $key ] = $code;
		}

		self::$lookup = $lookup;
		return self::$lookup;
	}

	private static function normalize_key( string $code ): string {
		return strtolower( str_replace( '-', '_', trim( $code ) ) );
	}

	/**
	 * Convert Polylang slug or locale (e.g. en_US, pt-br, he) to a TranslatePlus API language code.
	 *
	 * @param string $slug_or_locale From pll_get_post_language( ..., 'slug' ) or 'locale'.
	 * @return string|WP_Error Canonical code for API requests.
	 */
	public static function map_for_translate_api( string $slug_or_locale ) {
		$raw = trim( $slug_or_locale );
		if ( '' === $raw ) {
			return new WP_Error(
				'tppl_lang_empty',
				__( 'Language code is empty.', 'translateplus-polylang-addon' )
			);
		}

		$norm = self::normalize_key( $raw );

		$aliases = array(
			'he'      => 'iw',
			'nb'      => 'no',
			'nn'      => 'no',
			'zh_cn'   => 'zh-CN',
			'zh_sg'   => 'zh-CN',
			'zh_hans' => 'zh-CN',
			'zh_tw'   => 'zh-TW',
			'zh_hk'   => 'zh-TW',
			'zh_mo'   => 'zh-TW',
			'zh_hant' => 'zh-TW',
		);

		if ( isset( $aliases[ $norm ] ) ) {
			return $aliases[ $norm ];
		}

		$lookup = self::canonical_lookup();
		if ( isset( $lookup[ $norm ] ) ) {
			return $lookup[ $norm ];
		}

		// en_US, de_DE, pt_BR → primary subtag (except zh, already handled).
		if ( false !== strpos( $norm, '_' ) ) {
			$primary = strstr( $norm, '_', true );
			if ( false !== $primary && '' !== $primary && 'zh' !== $primary && isset( $lookup[ $primary ] ) ) {
				return $lookup[ $primary ];
			}
		}

		return new WP_Error(
			'tppl_lang_unsupported',
			sprintf(
				/* translators: %s: Polylang language slug or locale */
				__(
					'Language "%s" is not supported by the TranslatePlus API. In Polylang, use a language whose code matches TranslatePlus (for example en, fr, de), or adjust the locale under Languages.',
					'translateplus-polylang-addon'
				),
				$raw
			)
		);
	}

	/**
	 * Prefer WordPress locale from Polylang for a language slug (helps map en_GB → en).
	 *
	 * @param string $slug Polylang language slug.
	 * @return string Locale (e.g. en_US) or the slug if unknown.
	 */
	public static function polylang_locale_or_slug( string $slug ): string {
		$slug = trim( $slug );
		if ( '' === $slug ) {
			return '';
		}

		if ( function_exists( 'PLL' ) ) {
			$pll = PLL();
			if ( is_object( $pll ) && isset( $pll->model ) && is_object( $pll->model ) && method_exists( $pll->model, 'get_language' ) ) {
				$lang = $pll->model->get_language( $slug );
				if ( is_object( $lang ) && isset( $lang->locale ) && is_string( $lang->locale ) && '' !== trim( $lang->locale ) ) {
					return trim( $lang->locale );
				}
			}
		}

		return $slug;
	}
}
