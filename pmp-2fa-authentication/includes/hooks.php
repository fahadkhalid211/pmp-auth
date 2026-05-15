<?php
/**
 * Core Hooks - v4
 *
 * Key insight: PMP's login form, on WP_Error, reloads the same login page
 * with the error code as ?action=pmp2fa_pending in the URL. It does NOT
 * redirect elsewhere. So we just detect that action on template_redirect
 * and replace the page output with the 2FA form.
 *
 * @package PMP_2FA_Authentication
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function pmp2fa_register_hooks() {
	// Block login and set pending state.
	add_filter( 'authenticate', 'pmp2fa_authenticate_filter', 100, 3 );

	// Detect the reload with ?action=pmp2fa_pending and show 2FA form.
	add_action( 'template_redirect', 'pmp2fa_maybe_show_2fa_page', 1 );

	// AJAX endpoints.
	add_action( 'wp_ajax_nopriv_pmp2fa_send_otp',   'pmp2fa_ajax_send_otp' );
	add_action( 'wp_ajax_nopriv_pmp2fa_resend_otp', 'pmp2fa_ajax_send_otp' );
	add_action( 'wp_ajax_nopriv_pmp2fa_verify_otp', 'pmp2fa_ajax_verify_otp' );
	add_action( 'wp_ajax_pmp2fa_verify_otp',        'pmp2fa_ajax_verify_otp' );

	// Profile phone field and trusted devices section.
	add_action( 'show_user_profile',        'pmp2fa_phone_field' );
	add_action( 'edit_user_profile',        'pmp2fa_phone_field' );
	add_action( 'personal_options_update',  'pmp2fa_save_phone' );
	add_action( 'edit_user_profile_update', 'pmp2fa_save_phone' );
	add_action( 'show_user_profile',        'pmp2fa_profile_trusted_devices' );
	add_action( 'edit_user_profile',        'pmp2fa_profile_trusted_devices' );
	add_action( 'wp_ajax_pmp2fa_revoke_own_devices', 'pmp2fa_ajax_revoke_own_devices' );

	// Logout cleanup.
	add_action( 'wp_logout', 'pmp2fa_on_logout' );

	// Debug endpoint.
	add_action( 'wp_ajax_nopriv_pmp2fa_debug', 'pmp2fa_ajax_debug' );
	add_action( 'wp_ajax_pmp2fa_debug',        'pmp2fa_ajax_debug' );
}

// ═══════════════════════════════════════════════════════════════════════════
// 1. AUTHENTICATE FILTER
// ═══════════════════════════════════════════════════════════════════════════

function pmp2fa_authenticate_filter( $user, $username, $password ) {
	if ( is_wp_error( $user ) )           return $user;
	if ( ! ( $user instanceof WP_User ) ) return $user;
	if ( defined( 'PMP2FA_PASS' ) )       return $user;

	if ( pmp2fa_is_device_trusted( $user->ID ) ) return $user;

	// Store pending state.
	pmp2fa_set_pending( $user->ID );
	$s      = pmp2fa_get_settings();
	$method = ( $s['method'] === 'both' ) ? 'email' : $s['method'];
	pmp2fa_set_method( $method );

	// Store method so template_redirect can send OTP after WP fully loads.
	// We do NOT send OTP here because wp_mail can fail when called inside
	// the authenticate filter (too early in the boot process on some hosts).
	// The OTP is sent in pmp2fa_maybe_show_2fa_page() instead.

	// Return WP_Error with code 'pmp2fa_pending'.
	// PMP will reload /login/?action=pmp2fa_pending&username=...
	// We intercept that in template_redirect below.
	return new WP_Error( 'pmp2fa_pending', '' );
}

// ═══════════════════════════════════════════════════════════════════════════
// 2. DETECT PMP RELOAD AND SHOW 2FA FORM
// ═══════════════════════════════════════════════════════════════════════════

function pmp2fa_maybe_show_2fa_page() {
	// PMP reloads the login page with ?action=pmp2fa_pending after our error.
	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	// Also support our own ?pmp2fa=1 param as fallback.
	$manual = ! empty( $_GET['pmp2fa'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( $action !== 'pmp2fa_pending' && ! $manual ) return;

	// Must have a valid pending state.
	$user_id = pmp2fa_get_pending();
	if ( ! $user_id ) {
		// No pending state — just let the login page render normally.
		return;
	}

	// Send OTP now — we are fully booted so wp_mail works reliably here.
	// Only send if OTP not already stored (avoid re-sending on page refresh).
	$otp_exists = ( false !== get_transient( 'pmp2fa_otp_' . $user_id ) );
	if ( ! $otp_exists ) {
		$method = pmp2fa_get_method();
		$result = pmp2fa_dispatch_otp( $user_id, $method );
		if ( is_wp_error( $result ) ) {
			// OTP send failed — continue to render the page; user will see Resend option.
		}
	}

	// Hook into wp_footer to inject the modal overlay HTML + assets into the
	// existing page (which keeps the site's header, footer, and branding).
	add_action( 'wp_footer', function() use ( $user_id ) {
		pmp2fa_render_2fa_modal( $user_id );
	}, 100 );

	// Also enqueue assets via wp_enqueue_scripts so they load in <head>/<footer>
	// properly. The inline fallback inside the modal handles cases where this
	// hook fires too late.
	add_action( 'wp_enqueue_scripts', function() {
		wp_enqueue_style(
			'pmp2fa-overlay',
			PMP2FA_PLUGIN_URL . 'public/css/overlay.css',
			array(),
			PMP2FA_VERSION
		);
		wp_enqueue_script(
			'pmp2fa-overlay',
			PMP2FA_PLUGIN_URL . 'public/js/overlay.js',
			array(),
			PMP2FA_VERSION,
			true
		);
	} );
}

function pmp2fa_render_2fa_modal( $user_id ) {
	$user         = get_userdata( $user_id );
	$settings     = pmp2fa_get_settings();
	$method       = pmp2fa_get_method();
	// Only show SMS tab if Twilio credentials are configured.
	$show_both    = ( $settings['method'] === 'both' ) && pmp2fa_sms_configured();
	$has_phone    = (bool) get_user_meta( $user_id, 'pmp2fa_phone', true );
	$remember_opt = ! empty( $settings['remember_device'] );
	$expiry       = (int) $settings['otp_expiry'];
	$masked       = ( $method === 'sms' && $has_phone )
		? pmp2fa_mask_phone( get_user_meta( $user_id, 'pmp2fa_phone', true ) )
		: pmp2fa_mask_email( $user->user_email );
	$otp_length   = (int) $settings['otp_length'];
	$nonce        = wp_create_nonce( 'pmp2fa_nonce' );
	$cancel_url   = esc_url( pmp2fa_login_url() );
	$ajax_url     = admin_url( 'admin-ajax.php' );
	$site_name    = get_bloginfo( 'name' );
	$site_url     = home_url( '/' );

	// Logo.
	$logo_url = '';
	if ( has_custom_logo() ) {
		$logo_id  = get_theme_mod( 'custom_logo' );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
	}

	// Inline CSS and JS as fallback in case enqueue hooks fired too late.
	$css = file_exists( PMP2FA_PLUGIN_DIR . 'public/css/overlay.css' )
		? file_get_contents( PMP2FA_PLUGIN_DIR . 'public/css/overlay.css' ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$js  = file_exists( PMP2FA_PLUGIN_DIR . 'public/js/overlay.js' )
		? file_get_contents( PMP2FA_PLUGIN_DIR . 'public/js/overlay.js' ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

	include PMP2FA_PLUGIN_DIR . 'templates/2fa-page-full.php';
}

// ═══════════════════════════════════════════════════════════════════════════
// 3. AJAX: Send / Resend OTP
// ═══════════════════════════════════════════════════════════════════════════

function pmp2fa_ajax_send_otp() {
	if ( ! check_ajax_referer( 'pmp2fa_nonce', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'pmp-2fa-authentication' ) ) );
	}

	$user_id = pmp2fa_get_pending();
	if ( ! $user_id ) {
		wp_send_json_error( array( 'message' => __( 'Session expired. Please log in again.', 'pmp-2fa-authentication' ) ) );
	}

	$s = pmp2fa_get_settings();
	if ( ! pmp2fa_rate_limit_ok( $user_id, (int) $s['rate_limit'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait before requesting another code.', 'pmp-2fa-authentication' ) ) );
	}

	$method = isset( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : pmp2fa_get_method();
	if ( ! in_array( $method, array( 'email', 'sms' ), true ) ) $method = 'email';
	pmp2fa_set_method( $method );

	$result = pmp2fa_dispatch_otp( $user_id, $method );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	$user   = get_userdata( $user_id );
	$masked = ( $method === 'sms' )
		? pmp2fa_mask_phone( get_user_meta( $user_id, 'pmp2fa_phone', true ) )
		: pmp2fa_mask_email( $user->user_email );

	wp_send_json_success( array(
		// translators: %s: masked email address or phone number
		'message' => sprintf( __( 'Code sent to %s', 'pmp-2fa-authentication' ), '<strong>' . esc_html( $masked ) . '</strong>' ),
	) );
}

// ═══════════════════════════════════════════════════════════════════════════
// 4. AJAX: Verify OTP
// ═══════════════════════════════════════════════════════════════════════════

function pmp2fa_ajax_verify_otp() {
	if ( ! check_ajax_referer( 'pmp2fa_nonce', 'nonce', false ) ) {
		wp_send_json_error( array(
			'message' => __( 'Security check failed. Please refresh the page and try again.', 'pmp-2fa-authentication' ),
			'debug'   => 'nonce_fail',
		) );
	}

	$user_id = pmp2fa_get_pending();
	if ( ! $user_id ) {
		wp_send_json_error( array(
			'message' => __( 'Session expired. Please go back and log in again.', 'pmp-2fa-authentication' ),
			'debug'   => 'no_pending_user',
			'cookie'  => isset( $_COOKIE['pmp2fa_token'] ) ? 'present' : 'missing',
		) );
	}

	$otp = isset( $_POST['otp'] ) ? sanitize_text_field( wp_unslash( $_POST['otp'] ) ) : '';
	if ( $otp === '' ) {
		wp_send_json_error( array( 'message' => __( 'Please enter the verification code.', 'pmp-2fa-authentication' ) ) );
	}

	$result = pmp2fa_verify_otp( $user_id, $otp );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	// OTP correct — complete the login.
	pmp2fa_clear_pending();
	if ( ! defined( 'PMP2FA_PASS' ) ) define( 'PMP2FA_PASS', true );

	$remember = ! empty( $_POST['remember_device'] );
	wp_set_auth_cookie( $user_id, $remember, is_ssl() );
	wp_set_current_user( $user_id );

	if ( $remember ) {
		$s = pmp2fa_get_settings();
		pmp2fa_trust_device( $user_id, (int) $s['remember_days'] );
	}

	$user = get_userdata( $user_id );
	do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

	// Redirect to membership account page.
	$redirect    = '';
	$acc_page_id = get_option( 'pmpro_account_page_id' );
	if ( $acc_page_id ) {
		$redirect = get_permalink( $acc_page_id );
	}
	if ( empty( $redirect ) && function_exists( 'pmpro_url' ) ) {
		$redirect = pmpro_url( 'account' );
	}
	if ( empty( $redirect ) ) {
		$page     = get_page_by_path( 'membership-account' );
		$redirect = $page ? get_permalink( $page->ID ) : home_url( '/membership-account/' );
	}

	$redirect = apply_filters( 'pmp2fa_login_redirect', $redirect, $user );
	wp_send_json_success( array( 'redirect' => esc_url_raw( $redirect ) ) );
}

// ═══════════════════════════════════════════════════════════════════════════
// 5. Profile phone field
// ═══════════════════════════════════════════════════════════════════════════

function pmp2fa_phone_field( $user ) {
	$s = pmp2fa_get_settings();
	if ( ! in_array( $s['method'], array( 'sms', 'both' ), true ) ) return;
	$phone = esc_attr( get_user_meta( $user->ID, 'pmp2fa_phone', true ) );
	?>
	<h2><?php esc_html_e( 'Two-Factor Authentication', 'pmp-2fa-authentication' ); ?></h2>
	<table class="form-table" role="presentation"><tr>
		<th><label for="pmp2fa_phone"><?php esc_html_e( 'Phone (SMS OTP)', 'pmp-2fa-authentication' ); ?></label></th>
		<td>
			<input type="tel" name="pmp2fa_phone" id="pmp2fa_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" placeholder="+12223334444">
			<p class="description"><?php esc_html_e( 'E.164 format, e.g. +12223334444', 'pmp-2fa-authentication' ); ?></p>
		</td>
	</tr></table>
	<?php
}

function pmp2fa_save_phone( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) return;
	if ( ! isset( $_POST['pmp2fa_phone'] ) ) return; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'update-user_' . $user_id ) ) return;
	$phone = sanitize_text_field( wp_unslash( $_POST['pmp2fa_phone'] ) );
	if ( $phone && ! pmp2fa_validate_phone( $phone ) ) return;
	update_user_meta( $user_id, 'pmp2fa_phone', $phone );
}

// ═══════════════════════════════════════════════════════════════════════════
// 5b. Profile: Trusted devices section
// ═══════════════════════════════════════════════════════════════════════════

function pmp2fa_profile_trusted_devices( $user ) {
	// Only show for the user's own profile or if admin.
	$current_user_id = get_current_user_id();
	$is_own_profile  = ( $current_user_id === $user->ID );
	$is_admin        = current_user_can( 'manage_options' );
	if ( ! $is_own_profile && ! $is_admin ) return;

	// Count active trusted devices.
	$devices = (array) get_user_meta( $user->ID, '_pmp2fa_trusted', true );
	$active  = array();
	foreach ( $devices as $d ) {
		if ( ! empty( $d['exp'] ) && $d['exp'] > time() ) {
			$active[] = $d;
		}
	}

	$nonce = wp_create_nonce( 'pmp2fa_revoke_own_' . $user->ID );
	?>
	<div id="pmp2fa-trusted-devices-section">
	<h2><?php esc_html_e( 'Two-Factor Authentication — Trusted Devices', 'pmp-2fa-authentication' ); ?></h2>
	<table class="form-table" role="presentation">
	<tr>
		<th><?php esc_html_e( 'Trusted Devices', 'pmp-2fa-authentication' ); ?></th>
		<td>
			<?php if ( empty( $active ) ) : ?>
				<p><?php esc_html_e( 'No trusted devices.', 'pmp-2fa-authentication' ); ?></p>
			<?php else : ?>
				<p>
					<?php
					printf(
						/* translators: %d: number of trusted devices */
						esc_html( _n( 'You have %d trusted device.', 'You have %d trusted devices.', count( $active ), 'pmp-2fa-authentication' ) ),
						count( $active )
					);
					?>
				</p>
				<button type="button" class="button pmp2fa-revoke-own"
					data-userid="<?php echo esc_attr( $user->ID ); ?>"
					data-nonce="<?php echo esc_attr( $nonce ); ?>"
					data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
					<?php esc_html_e( 'Revoke All Trusted Devices', 'pmp-2fa-authentication' ); ?>
				</button>
				<span class="pmp2fa-revoke-own-result" style="margin-left:8px;font-size:13px;"></span>
			<?php endif; ?>
			<p class="description" style="margin-top:8px;">
				<?php esc_html_e( 'Revoking trusted devices means you will need to verify via OTP on your next login from any device.', 'pmp-2fa-authentication' ); ?>
			</p>
		</td>
	</tr>
	</table>
	</div>
	<script>
	(function() {
		var btns = document.querySelectorAll( '.pmp2fa-revoke-own' );
		btns.forEach( function( btn ) {
			btn.addEventListener( 'click', function() {
				if ( ! confirm( 'Revoke all trusted devices? You will need OTP verification on next login.' ) ) return;
				var b = this;
				var result = b.parentNode.querySelector( '.pmp2fa-revoke-own-result' );
				b.disabled = true;
				var xhr = new XMLHttpRequest();
				xhr.open( 'POST', b.getAttribute('data-ajaxurl'), true );
				xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
				xhr.onload = function() {
					try {
						var res = JSON.parse( xhr.responseText );
						result.style.color = res.success ? 'green' : 'red';
						result.textContent  = res.data.message;
						if ( res.success ) b.style.display = 'none';
						else b.disabled = false;
					} catch(e) { result.textContent = 'Error.'; b.disabled = false; }
				};
				xhr.send(
					'action=pmp2fa_revoke_own_devices' +
					'&nonce=' + encodeURIComponent( b.getAttribute('data-nonce') ) +
					'&user_id=' + encodeURIComponent( b.getAttribute('data-userid') )
				);
			} );
		} );
	} )();
	</script>
	<?php
}

function pmp2fa_ajax_revoke_own_devices() {
	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	if ( ! $user_id ) wp_send_json_error( array( 'message' => 'Invalid request.' ) );

	// Verify nonce scoped to this user.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'pmp2fa_revoke_own_' . $user_id ) ) {
		wp_send_json_error( array( 'message' => 'Security check failed.' ) );
	}

	// User can only revoke their own; admins can revoke anyone.
	if ( get_current_user_id() !== $user_id && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied.' ) );
	}

	pmp2fa_revoke_trusted_devices( $user_id );
	wp_send_json_success( array( 'message' => __( 'All trusted devices have been revoked. You will need OTP verification on next login.', 'pmp-2fa-authentication' ) ) );
}

// ═══════════════════════════════════════════════════════════════════════════
// 6. Logout cleanup
// ═══════════════════════════════════════════════════════════════════════════

function pmp2fa_on_logout( $user_id ) {
	pmp2fa_delete_otp( $user_id );
	pmp2fa_clear_pending();
}

// ═══════════════════════════════════════════════════════════════════════════
// 7. Debug
// ═══════════════════════════════════════════════════════════════════════════

function pmp2fa_ajax_debug() {
	$token   = isset( $_COOKIE['pmp2fa_token'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['pmp2fa_token'] ) ) : '';
	$user_id = $token ? (int) get_transient( 'pmp2fa_p_' . $token ) : 0;
	$acc_id  = get_option( 'pmpro_account_page_id' );
	wp_send_json( array(
		'token_cookie'    => $token ? 'present' : 'MISSING',
		'pending_user_id' => $user_id ?: 'NOT FOUND',
		'otp_stored'      => $user_id ? ( false !== get_transient( 'pmp2fa_otp_' . $user_id ) ? 'yes' : 'no' ) : 'n/a',
		'pmpro_acc_page'  => $acc_id ? get_permalink( $acc_id ) : 'not set',
		'is_ssl'          => is_ssl() ? 'yes' : 'no',
		'fresh_nonce'     => wp_create_nonce( 'pmp2fa_nonce' ),
		'current_url'     => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
	) );
}
