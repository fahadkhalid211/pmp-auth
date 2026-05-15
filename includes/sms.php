<?php
/**
 * SMS OTP via Twilio REST API.
 * @package PMP_2FA_Authentication
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function pmp2fa_send_sms( $user, $otp ) {
	$s     = pmp2fa_get_settings();
	$sid   = $s['twilio_sid'];
	$token = $s['twilio_token'];
	$from  = $s['twilio_from'];

	if ( ! $sid || ! $token || ! $from ) {
		return new WP_Error( 'sms_not_configured', __( 'SMS is not configured. Please contact the administrator.', 'pmp-2fa-authentication' ) );
	}
	$phone = get_user_meta( $user->ID, 'pmp2fa_phone', true );
	if ( ! $phone ) {
		return new WP_Error( 'no_phone', __( 'No phone number on your account. Please use Email OTP or add a phone number in your profile.', 'pmp-2fa-authentication' ) );
	}

	$body = sprintf(
		'[%s] Login code: %s  (expires in %d min). Do not share.',
		get_bloginfo( 'name' ), $otp, (int) $s['otp_expiry']
	);

	$resp = wp_remote_post(
		'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode( $sid ) . '/Messages.json',
		array(
			'headers'   => array( 'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $token ), 'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'      => array( 'From' => $from, 'To' => $phone, 'Body' => $body ),
			'timeout'   => 15,
			'sslverify' => true,
		)
	);

	if ( is_wp_error( $resp ) ) return new WP_Error( 'sms_failed', $resp->get_error_message() );
	$code = wp_remote_retrieve_response_code( $resp );
	if ( $code < 200 || $code >= 300 ) {
		$b = json_decode( wp_remote_retrieve_body( $resp ), true );
		return new WP_Error( 'sms_api', esc_html( isset( $b['message'] ) ? $b['message'] : 'Twilio error.' ) );
	}
	return true;
}

function pmp2fa_validate_phone( $phone ) {
	return (bool) preg_match( '/^\+[1-9]\d{6,14}$/', $phone );
}
