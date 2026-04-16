<?php
/**
 * Extended translation: post meta, slug, featured image, Polylang strings.
 *
 * @package TranslatePlus_Polylang_Addon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TPPL_Translate_Helper {

	public const BULK_PLL_STRINGS_ACTION = 'tppl_bulk_pll_strings';

	/**
	 * Meta keys never sent to the translation API (IDs, locks, binary, sync).
	 *
	 * @return string[]
	 */
	public static function excluded_meta_keys(): array {
		$keys = array(
			'_edit_lock',
			'_edit_last',
			'_thumbnail_id',
			'_wp_attached_file',
			'_wp_attachment_metadata',
			'_wp_attachment_image_alt',
			'_wp_old_slug',
			'_encloseme',
			'_pingme',
			'_pll_clone_post',
			'_pll_sync_reference',
			'_pll_sync_post',
			'_pll_sidebar_widgets',
			'_pll_menu_item',
			'_menu_item_menu_item_parent',
			'_menu_item_type',
			'_menu_item_object_id',
			'_menu_item_object',
			'_menu_item_target',
			'_menu_item_classes',
			'_menu_item_xfn',
			'_menu_item_url',
			'_menu_item_orphan',
			'_sku',
			'_stock',
			'_stock_status',
			'_manage_stock',
			'_download_limit',
			'_download_expiry',
			'_product_version',
			'_product_image_gallery',
			'_regular_price',
			'_sale_price',
			'_price',
		);

		/**
		 * Filter meta keys excluded from automatic translation when cloning a post.
		 *
		 * @param string[] $keys Meta keys.
		 */
		return apply_filters( 'tppl_excluded_post_meta_keys', $keys );
	}

	/**
	 * Copy all post meta from source to target with deep string translation.
	 *
	 * @return true|WP_Error
	 */
	public static function copy_translated_post_meta( int $source_post_id, int $target_post_id, string $source_api, string $target_api ) {
		$exclude = array_flip( self::excluded_meta_keys() );

		$meta = get_post_custom( $source_post_id );
		if ( ! is_array( $meta ) ) {
			return true;
		}

		foreach ( $meta as $key => $values ) {
			if ( ! is_string( $key ) || isset( $exclude[ $key ] ) ) {
				continue;
			}
			// Polylang stores sync / menu linkage in _pll_* post meta; never copy to the translation draft.
			if ( 0 === strpos( $key, '_pll' ) ) {
				continue;
			}

			foreach ( (array) $values as $raw ) {
				if ( ! is_string( $raw ) ) {
					continue;
				}

				$translated = self::translate_meta_value_deep( $raw, $source_api, $target_api );
				if ( is_wp_error( $translated ) ) {
					return $translated;
				}

				add_post_meta( $target_post_id, $key, $translated );
			}
		}

		return true;
	}

	/**
	 * @param mixed  $value
	 * @return mixed|WP_Error
	 */
	public static function translate_meta_value_deep( $value, string $source_api, string $target_api ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				$t = self::translate_meta_value_deep( $v, $source_api, $target_api );
				if ( is_wp_error( $t ) ) {
					return $t;
				}
				$out[ $k ] = $t;
			}
			return $out;
		}

		if ( ! is_string( $value ) ) {
			return $value;
		}

		if ( '' === $value ) {
			return '';
		}

		if ( is_serialized( $value ) ) {
			$un = maybe_unserialize( $value );
			if ( 'b:0;' !== $value && false === $un && 'N;' !== $value ) {
				return $value;
			}
			$deep = self::translate_meta_value_deep( $un, $source_api, $target_api );
			if ( is_wp_error( $deep ) ) {
				return $deep;
			}
			return serialize( $deep );
		}

		if ( self::should_skip_string_translation( $value ) ) {
			return $value;
		}

		if ( strlen( $value ) > 50000 ) {
			return $value;
		}

		$out = TPPL_API_Client::translate_text( $value, $target_api, $source_api );
		if ( is_wp_error( $out ) ) {
			return $out;
		}

		return (string) $out;
	}

	private static function should_skip_string_translation( string $value ): bool {
		$t = trim( $value );
		if ( '' === $t ) {
			return true;
		}
		if ( strlen( $t ) <= 64 && preg_match( '/^[a-z0-9_\-:.]+$/i', $t ) ) {
			return true;
		}
		if ( preg_match( '/^[0-9]+$/', $t ) ) {
			return true;
		}
		if ( preg_match( '#^https?://#i', $t ) ) {
			return true;
		}

		return (bool) apply_filters( 'tppl_skip_meta_string_translation', false, $value );
	}

	public static function set_unique_slug_from_title( int $post_id, string $translated_title, string $post_type, int $post_parent ): void {
		$base = sanitize_title( $translated_title );
		if ( '' === $base ) {
			$base = 'translation-' . $post_id;
		}

		$slug = wp_unique_post_slug( $base, $post_id, 'draft', $post_type, $post_parent );
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => $slug,
			)
		);
	}

	/**
	 * Duplicate featured image file, translate attachment fields, attach to target post.
	 */
	public static function duplicate_featured_image( int $source_post_id, int $target_post_id, string $source_api, string $target_api ): void {
		$thumb_id = (int) get_post_thumbnail_id( $source_post_id );
		if ( $thumb_id <= 0 ) {
			return;
		}

		$new_att = self::duplicate_attachment_translated( $thumb_id, $source_api, $target_api );
		if ( is_wp_error( $new_att ) || $new_att <= 0 ) {
			return;
		}

		set_post_thumbnail( $target_post_id, (int) $new_att );
	}

	/**
	 * @return int|WP_Error
	 */
	public static function duplicate_attachment_translated( int $attachment_id, string $source_api, string $target_api ) {
		$path = get_attached_file( $attachment_id );
		if ( ! is_string( $path ) || '' === $path || ! file_exists( $path ) ) {
			return new WP_Error( 'tppl_att_path', __( 'Could not read the featured image file.', 'translateplus-polylang-addon' ) );
		}

		$post = get_post( $attachment_id );
		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
			return new WP_Error( 'tppl_att_post', __( 'Invalid attachment.', 'translateplus-polylang-addon' ) );
		}

		$dir      = dirname( $path );
		$basename = wp_basename( $path );
		$new_name = wp_unique_filename( $dir, $basename );
		$new_path = path_join( $dir, $new_name );

		if ( ! @copy( $path, $new_path ) ) {
			return new WP_Error( 'tppl_att_copy', __( 'Could not copy the featured image file.', 'translateplus-polylang-addon' ) );
		}

		$title   = (string) $post->post_title;
		$excerpt = (string) $post->post_excerpt;
		$content = (string) $post->post_content;

		$batch_keys  = array();
		$batch_texts = array();
		if ( '' !== trim( $title ) ) {
			$batch_keys[]  = 'title';
			$batch_texts[] = $title;
		}
		if ( '' !== trim( $excerpt ) ) {
			$batch_keys[]  = 'excerpt';
			$batch_texts[] = $excerpt;
		}
		if ( '' !== trim( $content ) ) {
			$batch_keys[]  = 'content';
			$batch_texts[] = $content;
		}

		if ( count( $batch_texts ) > 0 ) {
			$translated_texts = TPPL_API_Client::translate_batch( $batch_texts, $target_api, $source_api );
			if ( is_wp_error( $translated_texts ) ) {
				@unlink( $new_path );
				return $translated_texts;
			}
			foreach ( $batch_keys as $i => $key ) {
				if ( 'title' === $key && isset( $translated_texts[ $i ] ) ) {
					$title = (string) $translated_texts[ $i ];
				}
				if ( 'excerpt' === $key && isset( $translated_texts[ $i ] ) ) {
					$excerpt = (string) $translated_texts[ $i ];
				}
				if ( 'content' === $key && isset( $translated_texts[ $i ] ) ) {
					$content = (string) $translated_texts[ $i ];
				}
			}
		}

		$filetype = wp_check_filetype( $new_name, null, $new_path );
		$new_post = array(
			'post_title'     => $title,
			'post_excerpt'   => $excerpt,
			'post_content'   => $content,
			'post_status'    => 'inherit',
			'post_mime_type' => $filetype['type'] ? $filetype['type'] : $post->post_mime_type,
			'post_type'      => 'attachment',
			'post_parent'    => 0,
		);

		$new_id = wp_insert_attachment( $new_post, $new_path, 0, true );
		if ( is_wp_error( $new_id ) ) {
			@unlink( $new_path );
			return $new_id;
		}

		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( is_string( $alt ) && '' !== trim( $alt ) && ! self::should_skip_string_translation( $alt ) ) {
			$t_alt = TPPL_API_Client::translate_text( $alt, $target_api, $source_api );
			if ( ! is_wp_error( $t_alt ) && is_string( $t_alt ) ) {
				update_post_meta( (int) $new_id, '_wp_attachment_image_alt', $t_alt );
			}
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$metadata = wp_generate_attachment_metadata( (int) $new_id, $new_path );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( (int) $new_id, $metadata );
		}

		return (int) $new_id;
	}

	/**
	 * Overwrite Polylang string translations for one language using TranslatePlus.
	 *
	 * @return array{updated:int,skipped:int}|WP_Error
	 */
	public static function bulk_translate_pll_strings_for_language( string $target_lang_slug, string $source_api, string $target_api ) {
		if ( ! class_exists( 'PLL_MO' ) || ! function_exists( 'PLL' ) || ! is_object( PLL() ) || ! isset( PLL()->model ) ) {
			return new WP_Error( 'tppl_pll', __( 'Polylang is not available.', 'translateplus-polylang-addon' ) );
		}

		$lang = PLL()->model->get_language( $target_lang_slug );
		if ( ! $lang ) {
			return new WP_Error( 'tppl_pll_lang', __( 'Unknown Polylang language.', 'translateplus-polylang-addon' ) );
		}

		$mo = new PLL_MO();
		$mo->import_from_db( $lang );

		$singulars = array();
		foreach ( $mo->entries as $entry ) {
			if ( ! is_object( $entry ) || ! isset( $entry->singular ) || ! is_string( $entry->singular ) ) {
				continue;
			}
			$s = $entry->singular;
			if ( '' === $s || self::should_skip_string_translation( $s ) ) {
				continue;
			}
			if ( strlen( $s ) > 50000 ) {
				continue;
			}
			$singulars[] = $s;
		}

		$singulars = array_values( array_unique( $singulars ) );

		if ( count( $singulars ) === 0 ) {
			return array(
				'updated' => 0,
				'skipped' => 0,
			);
		}

		$translations = array();
		$chunk_size   = 100;
		$updated      = 0;

		for ( $i = 0; $i < count( $singulars ); $i += $chunk_size ) {
			$chunk = array_slice( $singulars, $i, $chunk_size );
			$out   = TPPL_API_Client::translate_batch( $chunk, $target_api, $source_api );
			if ( is_wp_error( $out ) ) {
				return $out;
			}
			foreach ( $chunk as $idx => $singular ) {
				$j = (int) $idx;
				if ( isset( $out[ $j ] ) && is_string( $out[ $j ] ) ) {
					$translations[ $singular ] = $out[ $j ];
					++$updated;
				}
			}
		}

		if ( ! class_exists( 'Translation_Entry', false ) ) {
			require_once ABSPATH . WPINC . '/pomo/entry.php';
		}

		$out_mo = new PLL_MO();
		foreach ( $singulars as $singular ) {
			$translated = isset( $translations[ $singular ] ) ? $translations[ $singular ] : $singular;
			$out_mo->add_entry(
				new Translation_Entry(
					array(
						'singular'     => $singular,
						'translations' => array( $translated ),
					)
				)
			);
		}

		$out_mo->export_to_db( $lang );

		return array(
			'updated' => $updated,
			'skipped' => max( 0, count( $singulars ) - $updated ),
		);
	}

	public static function ajax_bulk_pll_strings(): void {
		check_ajax_referer( self::BULK_PLL_STRINGS_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'translateplus-polylang-addon' ),
				)
			);
		}

		$target = isset( $_POST['target'] ) && is_string( $_POST['target'] )
			? sanitize_text_field( wp_unslash( $_POST['target'] ) )
			: '';

		if ( '' === $target ) {
			wp_send_json_error( array( 'message' => __( 'Select a language.', 'translateplus-polylang-addon' ) ) );
		}

		$default_slug = function_exists( 'pll_default_language' ) ? pll_default_language() : '';
		$default_slug = is_string( $default_slug ) ? $default_slug : '';

		$source_descriptor = '';
		if ( '' !== $default_slug && function_exists( 'PLL' ) && is_object( PLL() ) && isset( PLL()->model ) ) {
			$lang_obj = PLL()->model->get_language( $default_slug );
			if ( is_object( $lang_obj ) && isset( $lang_obj->locale ) && is_string( $lang_obj->locale ) && '' !== trim( $lang_obj->locale ) ) {
				$source_descriptor = trim( $lang_obj->locale );
			}
		}
		if ( '' === $source_descriptor ) {
			$source_descriptor = $default_slug;
		}

		$target_descriptor = TPPL_Language::polylang_locale_or_slug( $target );

		$source_api = TPPL_Language::map_for_translate_api( $source_descriptor );
		if ( is_wp_error( $source_api ) ) {
			wp_send_json_error( array( 'message' => $source_api->get_error_message() ) );
		}

		$target_api = TPPL_Language::map_for_translate_api( $target_descriptor );
		if ( is_wp_error( $target_api ) ) {
			wp_send_json_error( array( 'message' => $target_api->get_error_message() ) );
		}

		$result = self::bulk_translate_pll_strings_for_language( $target, $source_api, $target_api );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: number of strings updated, 2: number skipped */
					__( 'Polylang strings updated: %1$d translated, %2$d unchanged or skipped.', 'translateplus-polylang-addon' ),
					(int) $result['updated'],
					(int) $result['skipped']
				),
			)
		);
	}
}
