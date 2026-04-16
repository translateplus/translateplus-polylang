<?php
/**
 * Plugin Name:       TranslatePlus for Polylang
 * Plugin URI:        https://translateplus.io/wordpress-polylang-integration
 * Description:       Translate WordPress content automatically using TranslatePlus. Integrates with Polylang to generate multilingual posts using a cost-optimized translation API (DeepL & Google alternative).
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins: polylang
 * Author:            TranslatePlus
 * Author URI:        https://translateplus.io
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       translateplus-polylang-addon
 *
 * @package TranslatePlus_Polylang_Addon
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TRANSLATEPLUS_POLYLANG_ADDON_FILE', __FILE__ );

final class TranslatePlus_Polylang_Addon {
	private const SLUG = 'translateplus-polylang-addon';

	private const AJAX_ACTION = 'tppl_translate_post';

	public static function init(): void {
		require_once __DIR__ . '/includes/class-tppl-settings.php';
		require_once __DIR__ . '/includes/class-tppl-language.php';
		require_once __DIR__ . '/includes/class-tppl-api-client.php';
		require_once __DIR__ . '/includes/class-tppl-translate-helper.php';

		TPPL_Settings::init();

		add_action( 'plugins_loaded', array( __CLASS__, 'register_ajax_hooks' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_boot' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'add_plugin_action_links' ) );
	}

	/**
	 * Register AJAX handlers early so admin-ajax.php always has them (not only after admin_init).
	 */
	public static function register_ajax_hooks(): void {
		if ( ! self::deps_ok() ) {
			return;
		}

		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_translate_post' ) );
		add_action( 'wp_ajax_' . TPPL_Translate_Helper::BULK_PLL_STRINGS_ACTION, array( 'TPPL_Translate_Helper', 'ajax_bulk_pll_strings' ) );
	}

	/**
	 * Add quick action links on the Plugins list.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public static function add_plugin_action_links( array $links ): array {
		$settings_url = admin_url( 'options-general.php?page=tppl-settings' );
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $settings_url ),
			esc_html__( 'Settings', 'translateplus-polylang-addon' )
		);

		array_unshift( $links, $settings_link );
		return $links;
	}

	public static function maybe_boot(): void {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! self::deps_ok() ) {
			return;
		}

		add_action( 'add_meta_boxes', array( __CLASS__, 'register_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	private static function deps_ok(): bool {
		$has_polylang     = function_exists( 'pll_languages_list' ) && function_exists( 'pll_get_post_language' ) && function_exists( 'pll_save_post_translations' );
		return $has_polylang;
	}

	public static function register_metabox(): void {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);

		unset( $post_types['attachment'] );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'tppl_translateplus_metabox',
				__( 'TranslatePlus', 'translateplus-polylang-addon' ),
				array( __CLASS__, 'render_metabox' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	public static function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->post_type ) ) {
			return;
		}

		wp_enqueue_script(
			'tppl-addon',
			plugins_url( 'assets/tppl-addon.js', TRANSLATEPLUS_POLYLANG_ADDON_FILE ),
			array( 'jquery' ),
			'0.1.0',
			true
		);

		wp_localize_script(
			'tppl-addon',
			'TPPL_ADDON',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::AJAX_ACTION ),
				'i18n'    => array(
					'selectLang' => __( 'Select a target language.', 'translateplus-polylang-addon' ),
					'working'    => __( 'Translating…', 'translateplus-polylang-addon' ),
					'error'      => __( 'Translation failed.', 'translateplus-polylang-addon' ),
				),
			)
		);
	}

	public static function render_metabox( \WP_Post $post ): void {
		$source = pll_get_post_language( $post->ID, 'slug' );

		if ( empty( $source ) || ! is_string( $source ) ) {
			echo '<p>' . esc_html__( 'Polylang language is not set for this content.', 'translateplus-polylang-addon' ) . '</p>';
			return;
		}

		$lang_slugs = pll_languages_list();
		if ( empty( $lang_slugs ) || ! is_array( $lang_slugs ) ) {
			echo '<p>' . esc_html__( 'No Polylang languages found.', 'translateplus-polylang-addon' ) . '</p>';
			return;
		}

		$lang_names = pll_languages_list( array( 'fields' => 'name' ) );
		$lang_map   = array();
		foreach ( $lang_slugs as $idx => $slug ) {
			if ( ! is_string( $slug ) || $slug === '' ) {
				continue;
			}
			$name = isset( $lang_names[ $idx ] ) && is_string( $lang_names[ $idx ] ) ? $lang_names[ $idx ] : $slug;
			$lang_map[ $slug ] = $name;
		}

		echo '<p class="tppl-addon__row">';
		echo '<label class="screen-reader-text" for="tppl_target_lang">' . esc_html__( 'Target language', 'translateplus-polylang-addon' ) . '</label>';
		echo '<select id="tppl_target_lang" class="widefat">';
		echo '<option value="">' . esc_html__( 'Target language…', 'translateplus-polylang-addon' ) . '</option>';
		foreach ( $lang_map as $slug => $name ) {
			if ( $slug === $source ) {
				continue;
			}
			printf( '<option value="%s">%s</option>', esc_attr( $slug ), esc_html( $name ) );
		}
		echo '</select>';
		echo '</p>';

		printf(
			'<p><button type="button" class="button button-primary tppl-translate-btn" data-post-id="%d" data-source-lang="%s">%s</button></p>',
			(int) $post->ID,
			esc_attr( $source ),
			esc_html__( 'Translate with TranslatePlus', 'translateplus-polylang-addon' )
		);

		echo '<p class="tppl-addon__status" style="display:none;"></p>';
	}

	public static function ajax_translate_post(): void {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		$post_id = isset( $_POST['postId'] ) ? (int) $_POST['postId'] : 0;
		$target  = isset( $_POST['target'] ) && is_string( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : '';

		if ( $post_id <= 0 || '' === $target ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'translateplus-polylang-addon' ) ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to translate this content.', 'translateplus-polylang-addon' ) ) );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			wp_send_json_error( array( 'message' => __( 'Post not found.', 'translateplus-polylang-addon' ) ) );
		}

		$source = pll_get_post_language( $post_id, 'slug' );
		if ( empty( $source ) || ! is_string( $source ) ) {
			wp_send_json_error( array( 'message' => __( 'Source language is not set.', 'translateplus-polylang-addon' ) ) );
		}

		$source_descriptor = function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $post_id, 'locale' ) : '';
		if ( ! is_string( $source_descriptor ) || '' === trim( $source_descriptor ) ) {
			$source_descriptor = $source;
		} else {
			$source_descriptor = trim( $source_descriptor );
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

		// Avoid duplicates if translation already exists for that target.
		$existing = function_exists( 'pll_get_post_translations' ) ? pll_get_post_translations( $post_id ) : array();
		if ( is_array( $existing ) && isset( $existing[ $target ] ) ) {
			$edit = get_edit_post_link( (int) $existing[ $target ], 'raw' );
			wp_send_json_success(
				array(
					'message' => __( 'A translation already exists for that language.', 'translateplus-polylang-addon' ),
					'editUrl' => $edit ? $edit : '',
				)
			);
		}

		$title   = (string) $post->post_title;
		$excerpt = (string) $post->post_excerpt;

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

		if ( count( $batch_texts ) > 0 ) {
			$translated_texts = TPPL_API_Client::translate_batch( $batch_texts, $target_api, $source_api );
			if ( is_wp_error( $translated_texts ) ) {
				wp_send_json_error( array( 'message' => $translated_texts->get_error_message() ) );
			}
			foreach ( $batch_keys as $i => $key ) {
				if ( 'title' === $key && isset( $translated_texts[ $i ] ) ) {
					$title = (string) $translated_texts[ $i ];
				}
				if ( 'excerpt' === $key && isset( $translated_texts[ $i ] ) ) {
					$excerpt = (string) $translated_texts[ $i ];
				}
			}
		}

		$content = TPPL_API_Client::translate_html( (string) $post->post_content, $target_api, $source_api );

		foreach ( array( $content ) as $maybe_error ) {
			if ( is_wp_error( $maybe_error ) ) {
				wp_send_json_error( array( 'message' => $maybe_error->get_error_message() ) );
			}
		}

		$new_id = wp_insert_post(
			array(
				'post_type'    => $post->post_type,
				'post_status'  => 'draft',
				'post_title'   => (string) $title,
				'post_excerpt' => (string) $excerpt,
				'post_content' => (string) $content,
				'post_parent'  => (int) $post->post_parent,
				'menu_order'   => (int) $post->menu_order,
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error( array( 'message' => $new_id->get_error_message() ) );
		}

		$new_id = (int) $new_id;

		// Copy basic taxonomies to keep structure consistent.
		$taxes = get_object_taxonomies( $post->post_type, 'names' );
		foreach ( $taxes as $tax ) {
			$terms = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) ) {
				wp_set_object_terms( $new_id, $terms, $tax, false );
			}
		}

		/**
		 * Whether to copy translated post meta, translated slug, and duplicated featured image after creating the draft.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( apply_filters( 'tppl_translate_post_extended_enabled', true ) ) {
			$meta_result = TPPL_Translate_Helper::copy_translated_post_meta( $post_id, $new_id, $source_api, $target_api );
			if ( is_wp_error( $meta_result ) ) {
				wp_delete_post( $new_id, true );
				wp_send_json_error( array( 'message' => $meta_result->get_error_message() ) );
			}

			TPPL_Translate_Helper::duplicate_featured_image( $post_id, $new_id, $source_api, $target_api );
			TPPL_Translate_Helper::set_unique_slug_from_title( $new_id, $title, $post->post_type, (int) $post->post_parent );
		}

		pll_set_post_language( $new_id, $target );

		$translations            = is_array( $existing ) ? $existing : array();
		$translations[ $source ] = $post_id;
		$translations[ $target ] = $new_id;
		pll_save_post_translations( $translations );

		$edit_url = get_edit_post_link( $new_id, 'raw' );
		wp_send_json_success(
			array(
				'message' => __( 'Translation draft created.', 'translateplus-polylang-addon' ),
				'editUrl' => $edit_url ? $edit_url : '',
			)
		);
	}
}

TranslatePlus_Polylang_Addon::init();

