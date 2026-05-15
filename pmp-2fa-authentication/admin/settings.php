<?php
/**
 * Admin Settings – procedural, no PHP 8 syntax.
 *
 * @package PMP_2FA_Authentication
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function pmp2fa_admin_init() {
	add_action( 'admin_menu',            'pmp2fa_admin_menu' );
	add_action( 'admin_notices',         'pmp2fa_admin_notices' );
	add_action( 'admin_init',            'pmp2fa_register_settings' );
	add_action( 'admin_enqueue_scripts', 'pmp2fa_admin_assets' );
	add_filter( 'plugin_action_links_' . PMP2FA_PLUGIN_BASE, 'pmp2fa_plugin_links' );
	add_action( 'wp_ajax_pmp2fa_test_email',      'pmp2fa_ajax_test_email' );
	add_action( 'wp_ajax_pmp2fa_test_sms',        'pmp2fa_ajax_test_sms' );
	add_action( 'wp_ajax_pmp2fa_revoke_devices',      'pmp2fa_ajax_revoke_devices' );
	add_action( 'wp_ajax_pmp2fa_revoke_user_devices', 'pmp2fa_ajax_revoke_user_devices' );
	add_action( 'wp_ajax_pmp2fa_lookup_user',         'pmp2fa_ajax_lookup_user' );
}

function pmp2fa_admin_notices() {
	$s = pmp2fa_get_settings();

	// Only show on our settings page or the plugins list page.
	$screen = get_current_screen();
	if ( ! $screen ) return;
	$relevant = in_array( $screen->id, array( 'settings_page_pmp2fa-settings', 'plugins' ), true );
	if ( ! $relevant ) return;

	// Warn if SMS or Both is selected but Twilio credentials are missing.
	if ( in_array( $s['method'], array( 'sms', 'both' ), true ) && ! pmp2fa_sms_configured() ) {
		$url = admin_url( 'options-general.php?page=pmp2fa-settings' );
		echo '<div class="notice notice-warning is-dismissible"><p>';
		echo '<strong>' . esc_html__( 'PMP 2FA Authentication:', 'pmp-2fa-authentication' ) . '</strong> ';
		if ( $s['method'] === 'sms' ) {
			// translators: %s: URL to the plugin settings page
			$msg1 = __( 'SMS OTP is selected but Twilio credentials are not configured. OTPs will fall back to email until you <a href="%s">add your Twilio credentials</a>.', 'pmp-2fa-authentication' );
			printf( wp_kses( $msg1, array( 'a' => array( 'href' => array() ) ) ), esc_url( $url ) );
		} else {
			// translators: %s: URL to the plugin settings page
			$msg2 = __( '"Both" OTP method is selected but Twilio credentials are missing. The SMS tab will be hidden from users until you <a href="%s">add your Twilio credentials</a>.', 'pmp-2fa-authentication' );
			printf( wp_kses( $msg2, array( 'a' => array( 'href' => array() ) ) ), esc_url( $url ) );
		}
		echo '</p></div>';
	}
}

function pmp2fa_admin_menu() {
	add_options_page(
		__( 'PMP 2FA Settings', 'pmp-2fa-authentication' ),
		__( 'PMP 2FA Auth', 'pmp-2fa-authentication' ),
		'manage_options',
		'pmp2fa-settings',
		'pmp2fa_render_settings_page'
	);
}

function pmp2fa_register_settings() {
	register_setting( 'pmp2fa_group', 'pmp2fa_settings', 'pmp2fa_sanitize_settings' );
}

function pmp2fa_sanitize_settings( $input ) {
	$c = array();
	$c['method']           = in_array( $input['method'] ?? '', array( 'email','sms','both' ), true ) ? $input['method'] : 'email';
	$c['otp_length']       = min( 8, max( 4, (int) ( $input['otp_length'] ?? 6 ) ) );
	$c['otp_expiry']       = min( 60, max( 1, (int) ( $input['otp_expiry'] ?? 10 ) ) );
	$c['rate_limit']       = min( 20, max( 1, (int) ( $input['rate_limit'] ?? 5 ) ) );
	$c['twilio_sid']       = sanitize_text_field( $input['twilio_sid']   ?? '' );
	$c['twilio_token']     = sanitize_text_field( $input['twilio_token'] ?? '' );
	$c['twilio_from']      = sanitize_text_field( $input['twilio_from']  ?? '' );
	$c['email_subject']    = sanitize_text_field( $input['email_subject']    ?? '' );
	$c['email_from_name']  = sanitize_text_field( $input['email_from_name']  ?? '' );
	$c['email_from_email'] = sanitize_email( $input['email_from_email'] ?? '' );
	$c['remember_device']  = ! empty( $input['remember_device'] ) ? 1 : 0;
	$c['remember_days']    = min( 365, max( 1, (int) ( $input['remember_days'] ?? 30 ) ) );
	return $c;
}

function pmp2fa_admin_assets( $hook ) {
	if ( $hook !== 'settings_page_pmp2fa-settings' ) return;
	wp_enqueue_style( 'pmp2fa-admin', PMP2FA_PLUGIN_URL . 'public/css/admin.css', array(), PMP2FA_VERSION );
	wp_enqueue_script( 'pmp2fa-admin', PMP2FA_PLUGIN_URL . 'public/js/admin.js', array( 'jquery' ), PMP2FA_VERSION, true );
	wp_localize_script( 'pmp2fa-admin', 'pmp2fa_admin', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'pmp2fa_admin_nonce' ),
	) );
}

function pmp2fa_plugin_links( $links ) {
	array_unshift( $links, '<a href="' . admin_url( 'options-general.php?page=pmp2fa-settings' ) . '">' . __( 'Settings', 'pmp-2fa-authentication' ) . '</a>' );
	return $links;
}

function pmp2fa_ajax_test_email() {
	check_ajax_referer( 'pmp2fa_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_die(-1);
	$user   = wp_get_current_user();
	$otp    = pmp2fa_generate_otp(6);
	$result = pmp2fa_send_email( $user, $otp );
	if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	wp_send_json_success( array( 'message' => 'Test email sent to ' . $user->user_email ) );
}

function pmp2fa_ajax_test_sms() {
	check_ajax_referer( 'pmp2fa_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_die(-1);
	$user   = wp_get_current_user();
	$otp    = pmp2fa_generate_otp(6);
	$result = pmp2fa_send_sms( $user, $otp );
	if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	wp_send_json_success( array( 'message' => 'Test SMS sent.' ) );
}

function pmp2fa_ajax_revoke_devices() {
	check_ajax_referer( 'pmp2fa_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );
	pmp2fa_revoke_trusted_devices( get_current_user_id() );
	wp_send_json_success( array( 'message' => __( 'Your trusted devices have been revoked. You will need to verify via OTP on next login.', 'pmp-2fa-authentication' ) ) );
}

function pmp2fa_ajax_revoke_user_devices() {
	check_ajax_referer( 'pmp2fa_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );
	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	if ( ! $user_id || ! get_userdata( $user_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid user.', 'pmp-2fa-authentication' ) ) );
	}
	pmp2fa_revoke_trusted_devices( $user_id );
	$user = get_userdata( $user_id );
	wp_send_json_success( array( 'message' => sprintf(
		/* translators: %s: display name of the user */
		__( 'Trusted devices revoked for %s.', 'pmp-2fa-authentication' ),
		esc_html( $user->display_name )
	) ) );
}

function pmp2fa_ajax_lookup_user() {
	check_ajax_referer( 'pmp2fa_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

	$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
	if ( ! $search ) {
		wp_send_json_error( array( 'message' => __( 'Please enter a username, email, or user ID.', 'pmp-2fa-authentication' ) ) );
	}

	// Try ID first, then login, then email.
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

	// Count trusted devices.
	$devices = (array) get_user_meta( $user->ID, '_pmp2fa_trusted', true );
	$active  = 0;
	foreach ( $devices as $d ) {
		if ( ! empty( $d['exp'] ) && $d['exp'] > time() ) $active++;
	}

	wp_send_json_success( array(
		'user_id'      => $user->ID,
		'display_name' => $user->display_name,
		'email'        => $user->user_email,
		'devices'      => $active,
	) );
}

function pmp2fa_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$s = pmp2fa_get_settings();
	include PMP2FA_PLUGIN_DIR . 'templates/admin.php';
}
