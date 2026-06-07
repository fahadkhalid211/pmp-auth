<?php
/**
 * Admin settings page registration and AJAX handlers.
 *
 * @package PMP_2FA_Authentication
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register all admin-side hooks.
 *
 * Called from pmp2fa_boot() via plugins_loaded.
 *
 * @return void
 */
function pmp2fa_admin_init() {
	add_action( 'admin_menu',            'pmp2fa_admin_menu' );
	add_action( 'admin_notices',         'pmp2fa_admin_notices' );
	add_action( 'admin_init',            'pmp2fa_register_settings' );
	add_action( 'admin_enqueue_scripts', 'pmp2fa_admin_assets' );
	add_filter( 'plugin_action_links_' . PMP2FA_PLUGIN_BASE, 'pmp2fa_plugin_action_links' );

	// AJAX.
	add_action( 'wp_ajax_pmp2fa_test_email',          'pmp2fa_ajax_test_email' );
	add_action( 'wp_ajax_pmp2fa_test_sms',            'pmp2fa_ajax_test_sms' );
	add_action( 'wp_ajax_pmp2fa_revoke_devices',      'pmp2fa_ajax_revoke_devices' );
	add_action( 'wp_ajax_pmp2fa_revoke_user_devices', 'pmp2fa_ajax_revoke_user_devices' );
	add_action( 'wp_ajax_pmp2fa_lookup_user',         'pmp2fa_ajax_lookup_user' );
}

// ── Admin notices ─────────────────────────────────────────────────────────────

/**
 * Show a warning notice when SMS is enabled without Twilio credentials.
 *
 * @return void
 */
function pmp2fa_admin_notices() {
	$s      = pmp2fa_get_settings();
	$screen = get_current_screen();

	if ( ! $screen ) {
		return;
	}

	$show_on = array( 'settings_page_pmp2fa-settings', 'plugins' );
	if ( ! in_array( $screen->id, $show_on, true ) ) {
		return;
	}

	if ( ! in_array( $s['method'], array( 'sms', 'both' ), true ) || pmp2fa_sms_configured() ) {
		return;
	}

	$url = admin_url( 'options-general.php?page=pmp2fa-settings' );

	echo '<div class="notice notice-warning is-dismissible"><p>';
	echo '<strong>' . esc_html__( 'PMP 2FA Authentication:', 'pmp-2fa-authentication' ) . '</strong> ';

	if ( 'sms' === $s['method'] ) {
		printf(
			wp_kses(
				/* translators: %s: URL to the plugin settings page */
				__( 'SMS OTP is selected but Twilio credentials are not configured. OTPs will fall back to email until you <a href="%s">add your Twilio credentials</a>.', 'pmp-2fa-authentication' ),
				array( 'a' => array( 'href' => array() ) )
			),
			esc_url( $url )
		);
	} else {
		printf(
			wp_kses(
				/* translators: %s: URL to the plugin settings page */
				__( '"Both" method is selected but Twilio credentials are missing. The SMS tab will be hidden from users until you <a href="%s">add your Twilio credentials</a>.', 'pmp-2fa-authentication' ),
				array( 'a' => array( 'href' => array() ) )
			),
			esc_url( $url )
		);
	}

	echo '</p></div>';
}

// ── Menu ──────────────────────────────────────────────────────────────────────

/**
 * Register the plugin settings page under Settings.
 *
 * @return void
 */
function pmp2fa_admin_menu() {
	add_options_page(
		__( 'PMP 2FA Settings', 'pmp-2fa-authentication' ),
		__( 'PMP 2FA Auth', 'pmp-2fa-authentication' ),
		'manage_options',
		'pmp2fa-settings',
		'pmp2fa_render_settings_page'
	);
}

// ── Settings API ──────────────────────────────────────────────────────────────

/**
 * Register the settings group.
 *
 * @return void
 */
function pmp2fa_register_settings() {
	register_setting( 'pmp2fa_group', 'pmp2fa_settings', 'pmp2fa_sanitize_settings' );
}

/**
 * Sanitize and validate all plugin settings on save.
 *
 * @param array $input Raw posted input.
 * @return array Sanitized settings.
 */
function pmp2fa_sanitize_settings( $input ) {
	$clean = array();

	$clean['method']    = in_array( $input['method'] ?? '', array( 'email', 'sms', 'both' ), true )
		? $input['method']
		: 'email';
	$clean['otp_length']  = min( 8,   max( 4,  (int) ( $input['otp_length']  ?? 6  ) ) );
	$clean['otp_expiry']  = min( 60,  max( 1,  (int) ( $input['otp_expiry']  ?? 10 ) ) );
	$clean['rate_limit']  = min( 20,  max( 1,  (int) ( $input['rate_limit']  ?? 5  ) ) );
	$clean['remember_days'] = min( 365, max( 1, (int) ( $input['remember_days'] ?? 30 ) ) );
	$clean['remember_device'] = ! empty( $input['remember_device'] ) ? 1 : 0;

	$clean['twilio_sid']       = sanitize_text_field( $input['twilio_sid']       ?? '' );
	$clean['twilio_token']     = sanitize_text_field( $input['twilio_token']     ?? '' );
	$clean['twilio_from']      = sanitize_text_field( $input['twilio_from']      ?? '' );
	$clean['email_subject']    = sanitize_text_field( $input['email_subject']    ?? '' );
	$clean['email_from_name']  = sanitize_text_field( $input['email_from_name']  ?? '' );
	$clean['email_from_email'] = sanitize_email(      $input['email_from_email'] ?? '' );

	return $clean;
}

// ── Assets ────────────────────────────────────────────────────────────────────

/**
 * Enqueue admin CSS and JS on the plugin settings page only.
 *
 * @param string $hook Current admin page hook.
 * @return void
 */
function pmp2fa_admin_assets( $hook ) {
	if ( 'settings_page_pmp2fa-settings' !== $hook ) {
		return;
	}
	wp_enqueue_style(
		'pmp2fa-admin',
		PMP2FA_PLUGIN_URL . 'public/css/admin.css',
		array(),
		PMP2FA_VERSION
	);
	wp_enqueue_script(
		'pmp2fa-admin',
		PMP2FA_PLUGIN_URL . 'public/js/admin.js',
		array( 'jquery' ),
		PMP2FA_VERSION,
		true
	);
	wp_localize_script(
		'pmp2fa-admin',
		'pmp2fa_admin',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'pmp2fa_admin_nonce' ),
			'i18n'     => array(
				'sending'    => __( 'Sending…', 'pmp-2fa-authentication' ),
				'sent_email' => __( 'Test email sent.', 'pmp-2fa-authentication' ),
				'sent_sms'   => __( 'Test SMS sent.', 'pmp-2fa-authentication' ),
				'error'      => __( 'Request failed. Please try again.', 'pmp-2fa-authentication' ),
				'confirm_revoke' => __( 'Revoke all your trusted devices? You will need OTP verification on next login.', 'pmp-2fa-authentication' ),
				'confirm_revoke_user' => __( 'Revoke all trusted devices for this user?', 'pmp-2fa-authentication' ),
			),
		)
	);
}

/**
 * Add a Settings link on the Plugins list screen.
 *
 * @param array $links Existing action links.
 * @return array
 */
function pmp2fa_plugin_action_links( $links ) {
	array_unshift(
		$links,
		'<a href="' . esc_url( admin_url( 'options-general.php?page=pmp2fa-settings' ) ) . '">'
			. esc_html__( 'Settings', 'pmp-2fa-authentication' )
		. '</a>'
	);
	return $links;
}

// ── AJAX handlers ─────────────────────────────────────────────────────────────

/**
 * Send a test email OTP to the current admin user.
 *
 * @return void
 */
function pmp2fa_ajax_test_email() {
	check_ajax_referer( 'pmp2fa_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( -1 );
	}

	$user   = wp_get_current_user();
	$otp    = pmp2fa_generate_otp( 6 );
	$result = pmp2fa_send_email( $user, $otp );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s: email address */
				__( 'Test email sent to %s.', 'pmp-2fa-authentication' ),
				$user->user_email
			),
		)
	);
}

/**
 * Send a test SMS OTP to the current admin user's saved phone number.
 *
 * @return void
 */
function pmp2fa_ajax_test_sms() {
	check_ajax_referer( 'pmp2fa_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( -1 );
	}

	$user   = wp_get_current_user();
	$otp    = pmp2fa_generate_otp( 6 );
	$result = pmp2fa_send_sms( $user, $otp );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( array( 'message' => __( 'Test SMS sent successfully.', 'pmp-2fa-authentication' ) ) );
}

/**
 * Revoke the current admin user's own trusted devices.
 *
 * @return void
 */
function pmp2fa_ajax_revoke_devices() {
	check_ajax_referer( 'pmp2fa_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( -1 );
	}

	pmp2fa_revoke_trusted_devices( get_current_user_id() );

	wp_send_json_success(
		array( 'message' => __( 'Your trusted devices have been revoked. OTP verification will be required on your next login.', 'pmp-2fa-authentication' ) )
	);
}

/**
 * Revoke trusted devices for a specific user (admin action).
 *
 * @return void
 */
function pmp2fa_ajax_revoke_user_devices() {
	check_ajax_referer( 'pmp2fa_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( -1 );
	}

	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	if ( ! $user_id || ! get_userdata( $user_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid user.', 'pmp-2fa-authentication' ) ) );
	}

	pmp2fa_revoke_trusted_devices( $user_id );
	$user = get_userdata( $user_id );

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s: display name of the user */
				__( 'Trusted devices revoked for %s.', 'pmp-2fa-authentication' ),
				esc_html( $user->display_name )
			),
		)
	);
}

/**
 * Look up a user by login, email, or ID for the admin revoke tool.
 *
 * @return void
 */
function pmp2fa_ajax_lookup_user() {
	check_ajax_referer( 'pmp2fa_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( -1 );
	}

	$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
	if ( ! $search ) {
		wp_send_json_error( array( 'message' => __( 'Please enter a username, email, or user ID.', 'pmp-2fa-authentication' ) ) );
	}

	$user = null;
	if ( is_numeric( $search ) ) {
		$user = get_userdata( (int) $search );
	}
	if ( ! $user ) {
		$user = get_user_by( 'login', $search );
	}
	if ( ! $user ) {
		$user = get_user_by( 'email', $search );
	}
	if ( ! $user ) {
		wp_send_json_error( array( 'message' => __( 'User not found.', 'pmp-2fa-authentication' ) ) );
	}

	$devices = (array) get_user_meta( $user->ID, '_pmp2fa_trusted', true );
	$active  = 0;
	foreach ( $devices as $d ) {
		if ( ! empty( $d['exp'] ) && $d['exp'] > time() ) {
			++$active;
		}
	}

	wp_send_json_success(
		array(
			'user_id'      => $user->ID,
			'display_name' => $user->display_name,
			'email'        => $user->user_email,
			'devices'      => $active,
		)
	);
}

// ── Settings page renderer ────────────────────────────────────────────────────

/**
 * Render the plugin settings page.
 *
 * @return void
 */
function pmp2fa_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = pmp2fa_get_settings();
	include PMP2FA_PLUGIN_DIR . 'templates/admin.php';
}
