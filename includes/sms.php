<?php
/**
 * SMS OTP delivery via the Twilio REST API.
 *
 * @package PMP_2FA_Authentication
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send an OTP to the user's saved phone number via Twilio.
 *
 * @param WP_User $user WordPress user object.
 * @param string  $otp  Plain-text OTP.
 * @return true|WP_Error
 */
function pmp2fa_send_sms( $user, $otp ) {
	$s     = pmp2fa_get_settings();
	$sid   = $s['twilio_sid'];
	$token = $s['twilio_token'];
	$from  = $s['twilio_from'];

	if ( ! $sid || ! $token || ! $from ) {
		return new WP_Error(
			'sms_not_configured',
			__( 'SMS is not configured. Please contact the site administrator.', 'pmp-2fa-authentication' )
		);
	}

	$phone = get_user_meta( $user->ID, 'pmp2fa_phone', true );
	if ( ! $phone ) {
		return new WP_Error(
			'no_phone',
			__( 'No phone number is saved on your account. Please use Email OTP or add a phone number in your profile.', 'pmp-2fa-authentication' )
		);
	}

	$body = sprintf(
		/* translators: 1: site name, 2: OTP code, 3: expiry minutes */
		__( '[%1$s] Your login code: %2$s  (expires in %3$d min). Do not share this code.', 'pmp-2fa-authentication' ),
		get_bloginfo( 'name' ),
		$otp,
		(int) $s['otp_expiry']
	);

	$response = wp_remote_post(
		'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode( $sid ) . '/Messages.json',
		array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $token ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'      => array(
				'From' => $from,
				'To'   => $phone,
				'Body' => $body,
			),
			'timeout'   => 15,
			'sslverify' => true,
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'sms_failed', $response->get_error_message() );
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		$parsed  = json_decode( wp_remote_retrieve_body( $response ), true );
		$message = isset( $parsed['message'] ) ? $parsed['message'] : __( 'Twilio API error.', 'pmp-2fa-authentication' );
		return new WP_Error( 'sms_api_error', esc_html( $message ) );
	}

	return true;
}

/**
 * Validate a phone number is in E.164 format.
 *
 * @param string $phone Phone number to validate.
 * @return bool
 */
function pmp2fa_validate_phone( $phone ) {
	return (bool) preg_match( '/^\+[1-9]\d{6,14}$/', $phone );
}
