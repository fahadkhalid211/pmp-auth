<?php
/**
 * OTP generation & verification.
 * @package PMP_2FA_Authentication
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function pmp2fa_generate_otp( $length = 6 ) {
	$length = max( 4, min( 8, (int) $length ) );
	$max    = (int) str_repeat( '9', $length );
	$min    = (int) ( '1' . str_repeat( '0', $length - 1 ) );
	if ( function_exists( 'random_int' ) ) {
		try { return (string) random_int( $min, $max ); } catch ( Exception $e ) {}
	}
	return (string) wp_rand( $min, $max );
}

function pmp2fa_store_otp( $user_id, $otp, $expiry_mins = 10 ) {
	set_transient( 'pmp2fa_otp_' . absint( $user_id ), array(
		'hash'     => wp_hash( $otp ),
		'attempts' => 0,
	), $expiry_mins * 60 );
}

/** Returns true on success or WP_Error on failure. */
function pmp2fa_verify_otp( $user_id, $supplied ) {
	$key  = 'pmp2fa_otp_' . absint( $user_id );
	$data = get_transient( $key );

	if ( ! is_array( $data ) ) {
		return new WP_Error( 'otp_expired', __( 'Code expired. Please request a new one.', 'pmp-2fa-authentication' ) );
	}
	if ( (int) $data['attempts'] >= 5 ) {
		delete_transient( $key );
		return new WP_Error( 'otp_locked', __( 'Too many incorrect attempts. Please request a new code.', 'pmp-2fa-authentication' ) );
	}
	if ( ! hash_equals( $data['hash'], wp_hash( $supplied ) ) ) {
		$data['attempts']++;
		$s = pmp2fa_get_settings();
		set_transient( $key, $data, $s['otp_expiry'] * 60 );
		$left = 5 - $data['attempts'];
		return new WP_Error( 'otp_invalid', sprintf(
			// translators: %d: number of OTP attempts remaining
			_n( 'Incorrect code. %d attempt remaining.', 'Incorrect code. %d attempts remaining.', $left, 'pmp-2fa-authentication' ),
			$left
		) );
	}
	delete_transient( $key );
	return true;
}

function pmp2fa_delete_otp( $user_id ) {
	delete_transient( 'pmp2fa_otp_' . absint( $user_id ) );
}

/** Returns true if allowed, false if rate-limited. */
function pmp2fa_rate_limit_ok( $user_id, $max = 5 ) {
	$key   = 'pmp2fa_rate_' . absint( $user_id );
	$count = (int) get_transient( $key );
	if ( $count >= $max ) return false;
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	return true;
}

/** Dispatch OTP to the right channel. Returns true or WP_Error. */
function pmp2fa_dispatch_otp( $user_id, $method = null ) {
	if ( $method === null ) $method = pmp2fa_get_method();
	$s   = pmp2fa_get_settings();
	$otp = pmp2fa_generate_otp( (int) $s['otp_length'] );
	pmp2fa_store_otp( $user_id, $otp, (int) $s['otp_expiry'] );
	$user = get_userdata( $user_id );

	if ( $method === 'sms' ) {
		// Check if Twilio credentials are configured before attempting SMS.
		if ( empty( $s['twilio_sid'] ) || empty( $s['twilio_token'] ) || empty( $s['twilio_from'] ) ) {
			// Credentials missing — fall back to email silently.
			pmp2fa_set_method( 'email' );
			return pmp2fa_send_email( $user, $otp );
		}
		$result = pmp2fa_send_sms( $user, $otp );
		// If SMS fails for any reason, fall back to email.
		if ( is_wp_error( $result ) ) {
			pmp2fa_set_method( 'email' );
			return pmp2fa_send_email( $user, $otp );
		}
		return $result;
	}

	return pmp2fa_send_email( $user, $otp );
}

/**
 * Check whether SMS (Twilio) is properly configured.
 */
function pmp2fa_sms_configured() {
	$s = pmp2fa_get_settings();
	return ! empty( $s['twilio_sid'] ) && ! empty( $s['twilio_token'] ) && ! empty( $s['twilio_from'] );
}
