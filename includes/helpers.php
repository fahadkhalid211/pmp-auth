<?php
/**
 * Helpers – pending state via transients + cookies (NO PHP sessions).
 * PHP sessions require session_start() before any output, which causes
 * "headers already sent" fatal errors on many hosts/themes.
 *
 * @package PMP_2FA_Authentication
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Settings helper ──────────────────────────────────────────────────────────

function pmp2fa_get_settings() {
	$defaults = array(
		'method'           => 'email',
		'otp_length'       => 6,
		'otp_expiry'       => 10,
		'twilio_sid'       => '',
		'twilio_token'     => '',
		'twilio_from'      => '',
		'email_subject'    => 'Your Login Verification Code',
		'email_from_name'  => get_bloginfo( 'name' ),
		'email_from_email' => get_bloginfo( 'admin_email' ),
		'remember_device'  => 1,
		'remember_days'    => 30,
		'rate_limit'       => 5,
	);
	return wp_parse_args( (array) get_option( 'pmp2fa_settings', array() ), $defaults );
}

// ── Pending login state ───────────────────────────────────────────────────────
//
// Flow:
//   1. User submits credentials → authenticate filter fires → we call pmp2fa_set_pending()
//   2. A unique token is stored in a transient keyed by the token value
//   3. The token is placed in a cookie so subsequent requests can look it up
//   4. wp_footer renders the overlay HTML (reads pending via cookie → transient)
//   5. On AJAX verify: pmp2fa_get_pending() finds the user ID, OTP is checked
//   6. On success: pmp2fa_clear_pending() deletes transient + expires cookie

function pmp2fa_set_pending( $user_id ) {
	$user_id = absint( $user_id );
	$token   = pmp2fa_make_token();
	$expiry  = 600; // 10 minutes

	// Store user ID in a transient keyed by the token.
	set_transient( 'pmp2fa_p_' . $token, $user_id, $expiry );

	// Write cookie — httponly so JS cannot steal it.
	pmp2fa_set_cookie( 'pmp2fa_token', $token, time() + $expiry, true );

	// Keep a reverse lookup on the user so we can revoke from admin.
	update_user_meta( $user_id, '_pmp2fa_token', $token );
}

function pmp2fa_get_pending() {
	$token = pmp2fa_read_cookie( 'pmp2fa_token' );
	if ( ! $token ) return 0;
	$user_id = (int) get_transient( 'pmp2fa_p_' . $token );
	return $user_id > 0 ? $user_id : 0;
}

function pmp2fa_clear_pending() {
	$token = pmp2fa_read_cookie( 'pmp2fa_token' );
	if ( $token ) {
		$user_id = (int) get_transient( 'pmp2fa_p_' . $token );
		delete_transient( 'pmp2fa_p_' . $token );
		if ( $user_id ) {
			delete_user_meta( $user_id, '_pmp2fa_token' );
		}
	}
	// Expire both cookies.
	pmp2fa_set_cookie( 'pmp2fa_token',  '', time() - 3600, true );
	pmp2fa_set_cookie( 'pmp2fa_method', '', time() - 3600, false );
}

// ── Method (email / sms) stored in a plain cookie ────────────────────────────

function pmp2fa_set_method( $method ) {
	$method = in_array( $method, array( 'email', 'sms' ), true ) ? $method : 'email';
	pmp2fa_set_cookie( 'pmp2fa_method', $method, time() + 600, false );
	$_COOKIE['pmp2fa_method'] = $method; // available in same request
}

function pmp2fa_get_method() {
	$cookie = pmp2fa_read_cookie( 'pmp2fa_method' );
	if ( $cookie && in_array( $cookie, array( 'email', 'sms' ), true ) ) return $cookie;
	$s = pmp2fa_get_settings();
	return ( $s['method'] === 'both' ) ? 'email' : $s['method'];
}

// ── Trusted device ────────────────────────────────────────────────────────────

function pmp2fa_trust_device( $user_id, $days ) {
	$days   = max( 1, (int) $days );
	$token  = pmp2fa_make_token( 40 );
	$hashed = wp_hash( $token );
	$expiry = time() + $days * DAY_IN_SECONDS;
	$ua     = wp_hash( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' );

	$list = (array) get_user_meta( $user_id, '_pmp2fa_trusted', true );
	// Prune expired entries.
	foreach ( $list as $k => $t ) {
		if ( empty( $t['exp'] ) || $t['exp'] < time() ) unset( $list[ $k ] );
	}
	$list[] = array( 'h' => $hashed, 'exp' => $expiry, 'ua' => $ua );
	update_user_meta( $user_id, '_pmp2fa_trusted', array_values( $list ) );

	pmp2fa_set_cookie( 'pmp2fa_td_' . $user_id, $token, $expiry, true );
}

function pmp2fa_is_device_trusted( $user_id ) {
	$s = pmp2fa_get_settings();
	if ( empty( $s['remember_device'] ) ) return false;

	$token = pmp2fa_read_cookie( 'pmp2fa_td_' . $user_id );
	if ( ! $token ) return false;

	$hashed = wp_hash( $token );
	$ua     = wp_hash( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' );
	$list   = (array) get_user_meta( $user_id, '_pmp2fa_trusted', true );

	foreach ( $list as $t ) {
		if (
			! empty( $t['h'] )   && hash_equals( $t['h'], $hashed ) &&
			! empty( $t['exp'] ) && $t['exp'] > time() &&
			! empty( $t['ua'] )  && hash_equals( $t['ua'], $ua )
		) {
			return true;
		}
	}
	return false;
}

function pmp2fa_revoke_trusted_devices( $user_id ) {
	delete_user_meta( $user_id, '_pmp2fa_trusted' );
}

// ── Cookie helpers ────────────────────────────────────────────────────────────

function pmp2fa_set_cookie( $name, $value, $expires, $httponly ) {
	// Use positional args for PHP 5.6 / 7.x compatibility (no array form).
	$path   = COOKIEPATH  ? COOKIEPATH  : '/';
	$domain = COOKIE_DOMAIN ? COOKIE_DOMAIN : '';
	$secure = is_ssl();
	setcookie( $name, $value, $expires, $path, $domain, $secure, (bool) $httponly );
}

function pmp2fa_read_cookie( $name ) {
	if ( isset( $_COOKIE[ $name ] ) && $_COOKIE[ $name ] !== '' ) {
		return sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
	}
	return '';
}

// ── Misc ──────────────────────────────────────────────────────────────────────

function pmp2fa_make_token( $length = 32 ) {
	if ( function_exists( 'random_bytes' ) ) {
		try { return bin2hex( random_bytes( intval( $length / 2 ) ) ); } catch ( Exception $e ) {}
	}
	return wp_generate_password( $length, false );
}

function pmp2fa_mask_email( $email ) {
	$parts = explode( '@', $email );
	if ( count( $parts ) < 2 ) return $email;
	$name    = $parts[0];
	$visible = min( 3, (int) floor( strlen( $name ) / 2 ) );
	return substr( $name, 0, $visible ) . str_repeat( '*', max( 2, strlen( $name ) - $visible ) ) . '@' . $parts[1];
}

function pmp2fa_mask_phone( $phone ) {
	if ( strlen( $phone ) < 6 ) return $phone;
	return substr( $phone, 0, 3 ) . str_repeat( '*', strlen( $phone ) - 6 ) . substr( $phone, -4 );
}

function pmp2fa_login_url() {
	if ( function_exists( 'pmpro_url' ) ) {
		$url = pmpro_url( 'login' );
		if ( $url ) return $url;
	}
	$pid = get_option( 'pmpro_login_page_id' );
	if ( $pid ) {
		$url = get_permalink( $pid );
		if ( $url ) return $url;
	}
	return wp_login_url();
}

function pmp2fa_post_login_redirect( $user ) {
	$default = admin_url();
	if ( function_exists( 'pmpro_url' ) ) {
		$acc = pmpro_url( 'account' );
		if ( $acc ) $default = $acc;
	}
	return apply_filters( 'login_redirect', $default, $default, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
}
