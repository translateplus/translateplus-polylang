<?php
/**
 * Settings for the Polylang addon.
 *
 * @package TranslatePlus_Polylang_Addon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TPPL_Settings {
	public const OPTION_API_KEY = 'tppl_api_key';
	private const REFRESH_ACTION    = 'tppl_refresh_summary';
	private const DISCONNECT_ACTION = 'tppl_disconnect_account';
	private const SAVE_API_KEY_ACTION = 'tppl_save_api_key';
	private const NOTICE_TRANSIENT    = 'tppl_settings_notice';

	/**
	 * While true, {@see sanitize_api_key()} only trims — remote validation already ran (e.g. AJAX save).
	 *
	 * @var bool
	 */
	private static $bypass_api_validation = false;

	public static function init(): void {
		add_action( 'admin_menu',             array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init',             array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts',  array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'load-settings_page_tppl-settings', array( __CLASS__, 'drain_core_settings_errors_transient' ), 0 );
		add_action( 'current_screen',         array( __CLASS__, 'suppress_default_wp_notices' ) );
		add_action( 'wp_ajax_tppl_refresh_summary', array( __CLASS__, 'ajax_refresh_summary' ) );
		add_action( 'wp_ajax_' . self::SAVE_API_KEY_ACTION, array( __CLASS__, 'ajax_save_api_key' ) );
		add_action( 'wp_ajax_' . self::DISCONNECT_ACTION, array( __CLASS__, 'ajax_disconnect_account' ) );
	}

	/**
	 * After options.php redirects with ?settings-updated=true, core stores a "Settings saved." notice
	 * in the settings_errors transient. That is printed from admin_notices before this page renders.
	 * Drain it early so only our inline notices (tppl_settings_notice) appear between hero and layout.
	 */
	public static function drain_core_settings_errors_transient(): void {
		if ( empty( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! function_exists( 'get_settings_errors' ) ) {
			require_once ABSPATH . 'wp-admin/includes/template.php';
		}

		get_settings_errors();
		global $wp_settings_errors;
		$wp_settings_errors = array();
	}

	/**
	 * Avoid duplicate "Settings saved." / settings_errors output in the admin header on this page.
	 */
	public static function suppress_default_wp_notices( $screen ): void {
		if ( ! is_object( $screen ) || empty( $screen->id ) ) {
			return;
		}
		if ( 'settings_page_tppl-settings' !== $screen->id ) {
			return;
		}
		remove_action( 'admin_notices', 'settings_errors' );
		remove_action( 'network_admin_notices', 'settings_errors' );
	}

	public static function enqueue_assets( string $hook ): void {
		if ( 'settings_page_tppl-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'tppl-settings',
			plugins_url( 'assets/tppl-settings.css', TRANSLATEPLUS_POLYLANG_ADDON_FILE ),
			array(),
			'0.3.7'
		);

		wp_enqueue_script(
			'tppl-settings',
			plugins_url( 'assets/tppl-settings.js', TRANSLATEPLUS_POLYLANG_ADDON_FILE ),
			array( 'jquery' ),
			'0.2.2',
			true
		);

		$bulk_strings = array();
		if ( class_exists( 'PLL_MO' ) && class_exists( 'TPPL_Translate_Helper' ) ) {
			$bulk_strings = array(
				'action' => TPPL_Translate_Helper::BULK_PLL_STRINGS_ACTION,
				'nonce'  => wp_create_nonce( TPPL_Translate_Helper::BULK_PLL_STRINGS_ACTION ),
			);
		}

		wp_localize_script(
			'tppl-settings',
			'TPPL_SETTINGS',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'action'           => 'tppl_refresh_summary',
				'nonce'            => wp_create_nonce( self::REFRESH_ACTION ),
				'saveAction'       => self::SAVE_API_KEY_ACTION,
				'saveNonce'        => wp_create_nonce( self::SAVE_API_KEY_ACTION ),
				'disconnectAction' => self::DISCONNECT_ACTION,
				'disconnectNonce'  => wp_create_nonce( self::DISCONNECT_ACTION ),
				'bulkStrings'      => $bulk_strings,
				'i18n'             => array(
					'refreshing'        => __( 'Refreshing…', 'translateplus-polylang-addon' ),
					'refresh'           => __( 'Refresh Status', 'translateplus-polylang-addon' ),
					'error'             => __( 'Unable to refresh status right now. Please try again.', 'translateplus-polylang-addon' ),
					'saving'            => __( 'Saving…', 'translateplus-polylang-addon' ),
					'save'              => __( 'Save & Connect', 'translateplus-polylang-addon' ),
					'saveError'         => __( 'Could not save your API key. Please try again.', 'translateplus-polylang-addon' ),
					'disconnecting'   => __( 'Disconnecting…', 'translateplus-polylang-addon' ),
					'disconnect'      => __( 'Disconnect Account', 'translateplus-polylang-addon' ),
					'disconnectError' => __( 'Could not disconnect your account. Please try again.', 'translateplus-polylang-addon' ),
					'disconnectConfirm' => __( 'Are you sure you want to disconnect TranslatePlus?', 'translateplus-polylang-addon' ),
					'bulkStringsWorking' => __( 'Translating strings…', 'translateplus-polylang-addon' ),
					'bulkStringsRun'     => __( 'Translate Polylang strings', 'translateplus-polylang-addon' ),
					'bulkStringsError'   => __( 'Could not translate Polylang strings.', 'translateplus-polylang-addon' ),
					'bulkStringsPick'    => __( 'Choose a language first.', 'translateplus-polylang-addon' ),
				),
			)
		);
	}

	public static function register_menu(): void {
		add_options_page(
			__( 'TranslatePlus (Polylang)', 'translateplus-polylang-addon' ),
			__( 'TranslatePlus (Polylang)', 'translateplus-polylang-addon' ),
			'manage_options',
			'tppl-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'tppl_settings',
			self::OPTION_API_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_api_key' ),
				'default'           => '',
			)
		);

		add_settings_section(
			'tppl_main',
			'',
			static function () {
				echo '<p class="tppl-subtle">'
					. esc_html__( 'Connect your TranslatePlus account to enable one-click translation in Polylang.', 'translateplus-polylang-addon' )
					. '</p>';
			},
			'tppl-settings'
		);

		add_settings_field(
			'tppl_api_key',
			__( 'API Key', 'translateplus-polylang-addon' ),
			array( __CLASS__, 'render_api_key_field' ),
			'tppl-settings',
			'tppl_main'
		);
	}

	public static function render_api_key_field(): void {
		$value = get_option( self::OPTION_API_KEY, '' );
		$value = is_string( $value ) ? $value : '';

		if ( self::has_api_key() ) {
			printf(
				'<input type="text" class="regular-text" value="%s" readonly disabled autocomplete="off" />',
				esc_attr( self::mask_api_key( $value ) )
			);
		} else {
			printf(
				'<input type="password" id="tppl-api-key-input" class="regular-text" name="%s" value="%s" autocomplete="off" />',
				esc_attr( self::OPTION_API_KEY ),
				esc_attr( $value )
			);
		}

		echo '<p class="description">';
		echo wp_kses(
			sprintf(
				/* translators: %s: TranslatePlus app URL (create account / dashboard) */
				__( 'Find your API key in your TranslatePlus dashboard.', 'translateplus-polylang-addon' ),
				esc_url( 'https://app.translateplus.io' )
			),
			array(
				'a' => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
			)
		);
		echo '</p>';
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap tppl-wrap">';

		// ── Hero ──────────────────────────────────────────────────────
		$hero_icon = plugins_url( 'assets/images/translateplus-icon.png', TRANSLATEPLUS_POLYLANG_ADDON_FILE );
		echo '<div class="tppl-hero">';
		echo '<div class="tppl-hero__head">';
		printf(
			'<img src="%s" alt="" class="tppl-hero__icon" width="40" height="40" decoding="async" />',
			esc_url( $hero_icon )
		);
		echo '<h1 class="tppl-hero__title">' . esc_html__( 'TranslatePlus for Polylang', 'translateplus-polylang-addon' ) . '</h1>';
		echo '</div>';
		echo '<p>' . esc_html__( 'Connect your account, monitor usage, and manage multilingual translation with confidence.', 'translateplus-polylang-addon' ) . '</p>';
		echo '</div>';

		// Success / error messages sit between hero and main layout (not inside hero).
		self::render_inline_notice();

		echo '<div class="tppl-layout">';

		// ── Main column ───────────────────────────────────────────────
		echo '<div class="tppl-main">';

		// Connection settings card
		echo '<div class="tppl-card" id="tppl-connection-card">';
		self::render_connection_card_inner();
		echo '</div>'; // #tppl-connection-card

		// Account status card
		self::render_status_panel();

		if ( self::has_api_key() && function_exists( 'pll_languages_list' ) && class_exists( 'PLL_MO' ) ) {
			echo '<div class="tppl-card" id="tppl-polylang-bulk-card">';
			self::render_polylang_strings_bulk_panel();
			echo '</div>';
		}

		echo '</div>'; // .tppl-main

		// ── Sidebar ───────────────────────────────────────────────────
		echo '<aside class="tppl-sidebar">';
		self::render_sidebar_signup_promo_card();
		echo '<div class="tppl-card tppl-marketing-card">';
		
		echo '<p class="tppl-hero__promo">' . esc_html__( 'Fast, accurate AI translation for websites and teams.', 'translateplus-polylang-addon' ) . '</p>';
		echo '<a class="button button-primary tppl-hero__cta" href="https://translateplus.io" target="_blank" rel="noopener noreferrer">'
			. esc_html__( 'Visit translateplus.io', 'translateplus-polylang-addon' )
			. '</a>';
			echo '<span class="tppl-badge">' . esc_html__( 'Powered by TranslatePlus', 'translateplus-polylang-addon' ) . '</span>';
		echo '</div>';
		echo '</aside>';

		echo '</div>'; // .tppl-layout
		echo '</div>'; // .tppl-wrap
	}

	/**
	 * Highlight card: free credits on sign-up (top of settings sidebar).
	 */
	private static function render_sidebar_signup_promo_card(): void {
		$signup_url = 'https://app.translateplus.io';

		echo '<div class="tppl-card tppl-signup-promo-card">';
		echo '<span class="tppl-signup-promo-card__eyebrow">' . esc_html__( 'Sign-up offer', 'translateplus-polylang-addon' ) . '</span>';
		echo '<p class="tppl-signup-promo-card__headline">';
		printf(
			'<span class="tppl-signup-promo-card__number">%s</span> <span class="tppl-signup-promo-card__headline-rest">%s</span>',
			esc_html( number_format_i18n( 5000, 0 ) ),
			esc_html__( 'free credits', 'translateplus-polylang-addon' )
		);
		echo '</p>';
		echo '<p class="tppl-signup-promo-card__sub">' . esc_html__( 'when you sign up.', 'translateplus-polylang-addon' ) . '</p>';
		printf(
			'<a class="button button-primary tppl-signup-promo-card__cta" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $signup_url ),
			esc_html__( 'Create your free account', 'translateplus-polylang-addon' )
		);
		echo '</div>';
	}

	/**
	 * Connection card inner markup (inside #tppl-connection-card).
	 */
	private static function render_connection_card_inner(): void {
		echo '<h2>' . esc_html__( 'Connection Settings', 'translateplus-polylang-addon' ) . '</h2>';

		if ( self::has_api_key() ) {
			echo '<form id="tppl-disconnect-form" method="post" action="">';
			do_settings_sections( 'tppl-settings' );
			submit_button(
				__( 'Disconnect Account', 'translateplus-polylang-addon' ),
				'delete',
				'tppl_disconnect',
				false,
				array( 'id' => 'tppl-disconnect-submit' )
			);
			echo '</form>';
			return;
		}

		echo '<form id="tppl-save-api-form" method="post" action="">';
		do_settings_sections( 'tppl-settings' );
		submit_button(
			__( 'Save & Connect', 'translateplus-polylang-addon' ),
			'primary',
			'tppl_save_api',
			false,
			array( 'id' => 'tppl-save-api-submit' )
		);
		echo '</form>';
	}

	/**
	 * @return string HTML inside #tppl-connection-card (no wrapper).
	 */
	private static function get_connection_card_inner_html(): string {
		ob_start();
		self::render_connection_card_inner();
		return (string) ob_get_clean();
	}

	private static function render_status_panel(): void {
		echo '<div class="tppl-card" id="tppl-account-status-card">';
		self::render_account_status_card_inner();
		echo '</div>'; // #tppl-account-status-card
	}

	/**
	 * Bulk Polylang string translations (Languages → String translations storage).
	 */
	private static function render_polylang_strings_bulk_panel(): void {
		echo '<h2>' . esc_html__( 'Polylang strings', 'translateplus-polylang-addon' ) . '</h2>';
		echo '<p class="tppl-subtle">'
			. esc_html__(
				'Send every string Polylang has stored for a language through TranslatePlus and save the results. Covers many widget texts and other registered strings. Navigation menus and arbitrary site options are not bulk-processed here.',
				'translateplus-polylang-addon'
			)
			. '</p>';

		$lang_map = array();
		if ( function_exists( 'pll_the_languages' ) ) {
			$raw = pll_the_languages( array( 'raw' => 1, 'hide_if_empty' => false ) );
			if ( is_array( $raw ) ) {
				foreach ( $raw as $row ) {
					if ( ! is_array( $row ) || empty( $row['slug'] ) || ! is_string( $row['slug'] ) ) {
						continue;
					}
					$slug            = $row['slug'];
					$name            = isset( $row['name'] ) && is_string( $row['name'] ) ? $row['name'] : $slug;
					$lang_map[ $slug ] = $name;
				}
			}
		}

		if ( count( $lang_map ) === 0 ) {
			echo '<p class="tppl-subtle">' . esc_html__( 'No Polylang languages found.', 'translateplus-polylang-addon' ) . '</p>';
			return;
		}

		echo '<p class="tppl-addon__row">';
		echo '<label for="tppl-bulk-strings-lang">' . esc_html__( 'Language to update', 'translateplus-polylang-addon' ) . '</label><br />';
		echo '<select id="tppl-bulk-strings-lang" class="widefat">';
		echo '<option value="">' . esc_html__( 'Select language…', 'translateplus-polylang-addon' ) . '</option>';
		foreach ( $lang_map as $slug => $name ) {
			printf( '<option value="%s">%s</option>', esc_attr( $slug ), esc_html( $name ) );
		}
		echo '</select>';
		echo '</p>';

		printf(
			'<p><button type="button" class="button button-primary" id="tppl-bulk-strings-btn">%s</button></p>',
			esc_html__( 'Translate Polylang strings', 'translateplus-polylang-addon' )
		);
		echo '<p class="tppl-subtle" id="tppl-bulk-strings-status" style="display:none;" role="status"></p>';
	}

	/**
	 * Account status card inner markup (inside #tppl-account-status-card).
	 */
	private static function render_account_status_card_inner(): void {
		$api_key = get_option( self::OPTION_API_KEY, '' );
		$api_key = is_string( $api_key ) ? trim( $api_key ) : '';

		echo '<h2>' . esc_html__( 'Account Status', 'translateplus-polylang-addon' ) . '</h2>';

		if ( '' === $api_key ) {
			echo '<div class="tppl-row">'
				. '<span class="tppl-pill tppl-pill-error">' . esc_html__( 'Not Connected', 'translateplus-polylang-addon' ) . '</span>'
				. '</div>';
			echo '<p class="tppl-subtle">'
				. esc_html__( 'No API key detected. Add your key above to connect your account.', 'translateplus-polylang-addon' )
				. '</p>';
			return;
		}

		echo '<div class="tppl-row">';
		echo '<span class="tppl-pill tppl-pill-ok">' . esc_html__( 'Connected', 'translateplus-polylang-addon' ) . '</span>';
		echo '<button type="button" class="button" id="tppl-refresh-status">'
			. esc_html__( 'Refresh Status', 'translateplus-polylang-addon' )
			. '</button>';
		echo '<span class="spinner tppl-spinner" id="tppl-refresh-spinner" aria-hidden="true"></span>';
		echo '</div>';

		echo '<div id="tppl-refresh-feedback" class="tppl-subtle" style="display:none;"></div>';
		echo '<div id="tppl-account-summary">';
		$summary = TPPL_API_Client::get_account_summary( false );
		self::render_summary_content( $summary );
		echo '</div>';
	}

	/**
	 * @return string HTML inside #tppl-account-status-card (no wrapper).
	 */
	private static function get_account_status_card_inner_html(): string {
		ob_start();
		self::render_account_status_card_inner();
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string,mixed>|WP_Error $summary
	 */
	private static function render_summary_content( $summary ): void {
		if ( is_wp_error( $summary ) ) {
			echo '<div class="tppl-row"><span class="tppl-pill tppl-pill-error">' . esc_html__( 'Connection Error', 'translateplus-polylang-addon' ) . '</span></div>';
			echo '<p class="tppl-subtle">' . esc_html( $summary->get_error_message() ) . '</p>';
			return;
		}

		$email             = isset( $summary['email'] )             && is_string( $summary['email'] )             ? $summary['email']             : '';
		$full_name         = isset( $summary['full_name'] )         && is_string( $summary['full_name'] )         ? $summary['full_name']         : '';
		$total_credits     = isset( $summary['total_credits'] )     && is_numeric( $summary['total_credits'] )    ? (float) $summary['total_credits']     : null;
		$credits_used      = isset( $summary['credits_used'] )      && is_numeric( $summary['credits_used'] )     ? (float) $summary['credits_used']      : null;
		$credits_remaining = isset( $summary['credits_remaining'] ) && is_numeric( $summary['credits_remaining'] )? (float) $summary['credits_remaining'] : null;
		$percentage = isset( $summary['credits_percentage'] ) && is_numeric( $summary['credits_percentage'] ) ? (float) $summary['credits_percentage'] : null;
		$rate       = isset( $summary['summary']['success_rate'] ) && is_numeric( $summary['summary']['success_rate'] ) ? (float) $summary['summary']['success_rate'] : null;

		$has_identity = '' !== $full_name || '' !== $email;
		$has_stats    = null !== $total_credits || null !== $credits_remaining || null !== $rate;

		echo '<div class="tppl-account-metrics">';

		if ( $has_identity ) {
			echo '<div class="tppl-account-metrics__row tppl-account-metrics__row--identity">';
			if ( '' !== $full_name ) {
				$name_class = 'tppl-metric' . ( '' === $email ? ' tppl-metric--identity-full' : '' );
				echo '<div class="' . esc_attr( $name_class ) . '">'
					. '<div class="tppl-metric-label">' . esc_html__( 'Account Name', 'translateplus-polylang-addon' ) . '</div>'
					. '<div class="tppl-metric-value tppl-metric-value--text">' . esc_html( $full_name ) . '</div>'
					. '</div>';
			}
			if ( '' !== $email ) {
				$email_class = 'tppl-metric' . ( '' === $full_name ? ' tppl-metric--identity-full' : '' );
				echo '<div class="' . esc_attr( $email_class ) . '">'
					. '<div class="tppl-metric-label">' . esc_html__( 'Email', 'translateplus-polylang-addon' ) . '</div>'
					. '<div class="tppl-metric-value tppl-metric-value--text">' . esc_html( $email ) . '</div>'
					. '</div>';
			}
			echo '</div>';
		}

		if ( $has_stats ) {
			echo '<div class="tppl-account-metrics__row tppl-account-metrics__row--stats">';
			if ( null !== $total_credits ) {
				echo '<div class="tppl-metric">'
					. '<div class="tppl-metric-label">' . esc_html__( 'Total Credits', 'translateplus-polylang-addon' ) . '</div>'
					. '<div class="tppl-metric-value">' . esc_html( number_format_i18n( $total_credits, 0 ) ) . '</div>'
					. '</div>';
			}
			if ( null !== $credits_remaining ) {
				echo '<div class="tppl-metric">'
					. '<div class="tppl-metric-label">' . esc_html__( 'Credits Remaining', 'translateplus-polylang-addon' ) . '</div>'
					. '<div class="tppl-metric-value">' . esc_html( number_format_i18n( $credits_remaining, 0 ) ) . '</div>'
					. '</div>';
			}
			if ( null !== $rate ) {
				echo '<div class="tppl-metric">'
					. '<div class="tppl-metric-label">' . esc_html__( 'Success Rate', 'translateplus-polylang-addon' ) . '</div>'
					. '<div class="tppl-metric-value">' . esc_html( number_format_i18n( $rate, 1 ) ) . '%</div>'
					. '</div>';
			}
			echo '</div>';
		}

		echo '</div>'; // .tppl-account-metrics

		// ── Credit usage progress bar ─────────────────────────────────
		if ( null !== $percentage && null !== $credits_used && null !== $total_credits ) {
			$pct_clamped  = max( 0.0, min( 100.0, $percentage ) );
			$bar_modifier = '';
			if ( $pct_clamped >= 90 ) {
				$bar_modifier = 'tppl-progress--danger';
			} elseif ( $pct_clamped >= 70 ) {
				$bar_modifier = 'tppl-progress--warn';
			}

			echo '<div class="tppl-progress-wrap">';
			echo '<div class="tppl-progress-label">';
			echo '<span>' . esc_html__( 'Credit Usage', 'translateplus-polylang-addon' ) . '</span>';
			printf(
				'<span>%s / %s</span>',
				esc_html( number_format_i18n( $credits_used, 0 ) ),
				esc_html( number_format_i18n( $total_credits, 0 ) )
			);
			echo '</div>';
			echo '<div class="tppl-progress-bar-track">';
			printf(
				'<div class="tppl-progress-bar-fill %s" style="width:%s%%" role="progressbar" aria-valuenow="%s" aria-valuemin="0" aria-valuemax="100"></div>',
				esc_attr( $bar_modifier ),
				esc_attr( number_format( $pct_clamped, 2, '.', '' ) ),
				esc_attr( number_format( $pct_clamped, 1, '.', '' ) )
			);
			echo '</div>';
			echo '</div>'; // .tppl-progress-wrap
		}
	}

	public static function ajax_refresh_summary(): void {
		check_ajax_referer( self::REFRESH_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'translateplus-polylang-addon' ),
			) );
		}

		TPPL_API_Client::clear_account_summary_cache();
		$summary = TPPL_API_Client::get_account_summary( true );

		ob_start();
		self::render_summary_content( $summary );
		$html = ob_get_clean();

		wp_send_json_success( array(
			'html' => is_string( $html ) ? $html : '',
		) );
	}

	public static function ajax_save_api_key(): void {
		check_ajax_referer( self::SAVE_API_KEY_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'translateplus-polylang-addon' ),
			) );
		}

		$api_key = isset( $_POST['api_key'] ) && is_string( $_POST['api_key'] )
			? trim( wp_unslash( $_POST['api_key'] ) )
			: '';

		$remote = self::validate_api_key_remote( $api_key );
		if ( is_wp_error( $remote ) ) {
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: API error */
					__( 'Unable to save API key: %s', 'translateplus-polylang-addon' ),
					$remote->get_error_message()
				),
			) );
		}

		self::$bypass_api_validation = true;
		update_option( self::OPTION_API_KEY, $api_key );
		self::$bypass_api_validation = false;

		$stored = get_option( self::OPTION_API_KEY, '' );
		if ( ! is_string( $stored ) || trim( $stored ) !== $api_key ) {
			wp_send_json_error( array(
				'message' => __( 'Could not save your API key. Please try again.', 'translateplus-polylang-addon' ),
			) );
		}

		TPPL_API_Client::clear_account_summary_cache();

		wp_send_json_success( array(
			'message'        => __( 'API key verified and saved successfully.', 'translateplus-polylang-addon' ),
			'connectionHtml' => self::get_connection_card_inner_html(),
			'accountHtml'    => self::get_account_status_card_inner_html(),
		) );
	}

	public static function ajax_disconnect_account(): void {
		check_ajax_referer( self::DISCONNECT_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'translateplus-polylang-addon' ),
			) );
		}

		delete_option( self::OPTION_API_KEY );
		TPPL_API_Client::clear_account_summary_cache();

		wp_send_json_success( array(
			'message'        => __( 'TranslatePlus account has been disconnected.', 'translateplus-polylang-addon' ),
			'connectionHtml' => self::get_connection_card_inner_html(),
			'accountHtml'    => self::get_account_status_card_inner_html(),
		) );
	}

	/**
	 * @return true|WP_Error
	 */
	private static function validate_api_key_remote( string $api_key ) {
		if ( '' === trim( $api_key ) ) {
			return new WP_Error(
				'tppl_empty_key',
				__( 'Please enter your TranslatePlus API key.', 'translateplus-polylang-addon' )
			);
		}

		$summary = TPPL_API_Client::get_account_summary_for_key( $api_key );
		if ( is_wp_error( $summary ) ) {
			return $summary;
		}

		return true;
	}

	private static function mask_api_key( string $api_key ): string {
		$api_key = trim( $api_key );
		if ( '' === $api_key ) {
			return __( 'Not connected', 'translateplus-polylang-addon' );
		}

		$length = strlen( $api_key );
		if ( $length <= 8 ) {
			return substr( $api_key, 0, 2 ) . '••••' . substr( $api_key, -2 );
		}

		return substr( $api_key, 0, 4 ) . '••••' . substr( $api_key, -4 );
	}

	private static function has_api_key(): bool {
		$api_key = get_option( self::OPTION_API_KEY, '' );
		return is_string( $api_key ) && '' !== trim( $api_key );
	}

	/**
	 * Sanitize and validate API key before storing.
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	public static function sanitize_api_key( $value ): string {
		$api_key = is_string( $value ) ? trim( $value ) : '';

		if ( self::$bypass_api_validation ) {
			if ( '' === $api_key ) {
				TPPL_API_Client::clear_account_summary_cache();
			}
			return $api_key;
		}

		if ( '' === $api_key ) {
			TPPL_API_Client::clear_account_summary_cache();
			return '';
		}

		$remote = self::validate_api_key_remote( $api_key );
		if ( is_wp_error( $remote ) ) {
			self::set_notice(
				'error',
				sprintf(
					/* translators: %s: API error */
					__( 'Unable to save API key: %s', 'translateplus-polylang-addon' ),
					$remote->get_error_message()
				)
			);

			$existing = get_option( self::OPTION_API_KEY, '' );
			return is_string( $existing ) ? trim( $existing ) : '';
		}

		TPPL_API_Client::clear_account_summary_cache();
		self::set_notice( 'success', __( 'API key verified and saved successfully.', 'translateplus-polylang-addon' ) );
		return $api_key;
	}

	private static function set_notice( string $type, string $message ): void {
		set_transient(
			self::NOTICE_TRANSIENT,
			array(
				'type'    => $type,
				'message' => $message,
			),
			5 * MINUTE_IN_SECONDS
		);
	}

	private static function render_inline_notice(): void {
		$notice = get_transient( self::NOTICE_TRANSIENT );
		if ( is_array( $notice ) ) {
			delete_transient( self::NOTICE_TRANSIENT );
		}

		echo '<div class="tppl-page-notices" id="tppl-page-notices" role="region" aria-label="' . esc_attr__( 'Notices', 'translateplus-polylang-addon' ) . '">';
		if ( is_array( $notice ) ) {
			$type    = isset( $notice['type'] ) && 'success' === $notice['type'] ? 'notice-success' : 'notice-error';
			$message = isset( $notice['message'] ) && is_string( $notice['message'] ) ? $notice['message'] : '';
			if ( '' !== $message ) {
				echo '<div class="notice ' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			}
		}
		echo '</div>';
	}
}