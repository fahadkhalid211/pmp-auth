<?php
/**
 * Core plugin hooks.
 *
 * Authentication flow overview
 * ────────────────────────────
 * 1. User submits credentials on PMP login form or wp-login.php.
 * 2. pmp2fa_authenticate_filter() intercepts a valid WP_User, stores a
 *    pending-login transient + cookie, and returns WP_Error( 'pmp2fa_pending' ).
 * 3. PMP reloads the login page with ?action=pmp2fa_pending in the URL.
 * 4. pmp2fa_maybe_show_2fa_page() detects that action, sends the OTP, and
 *    injects the 2FA modal overlay into wp_footer.
 * 5. The user enters their OTP; pmp2fa_ajax_verify_otp() validates it and,
 *    on success, calls wp_set_auth_cookie() to complete the login.
 *
 * @package PMP_2FA_Authentication
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register all plugin hooks and AJAX handlers.
 *
 * @return void
 */
function pmp2fa_register_hooks() {
	// Core authentication intercept.
	add_filter( 'authenticate', 'pmp2fa_authenticate_filter', 100, 3 );

	// Show the 2FA modal when PMP reloads with ?action=pmp2fa_pending.
	add_action( 'template_redirect', 'pmp2fa_maybe_show_2fa_page', 1 );

	// AJAX: unauthenticated actions (user is not yet logged in).
	add_action( 'wp_ajax_nopriv_pmp2fa_send_otp',   'pmp2fa_ajax_send_otp' );
	add_action( 'wp_ajax_nopriv_pmp2fa_resend_otp', 'pmp2fa_ajax_send_otp' );
	add_action( 'wp_ajax_nopriv_pmp2fa_verify_otp', 'pmp2fa_ajax_verify_otp' );
	// Authenticated verify endpoint (edge-case: already-logged-in session).
	add_action( 'wp_ajax_pmp2fa_verify_otp', 'pmp2fa_ajax_verify_otp' );

	// User profile: phone field + trusted devices.
	add_action( 'show_user_profile',        'pmp2fa_phone_field' );
	add_action( 'edit_user_profile',        'pmp2fa_phone_field' );
	add_action( 'personal_options_update',  'pmp2fa_save_phone' );
	add_action( 'edit_user_profile_update', 'pmp2fa_save_phone' );
	add_action( 'show_user_profile',        'pmp2fa_profile_trusted_devices' );
	add_action( 'edit_user_profile',        'pmp2fa_profile_trusted_devices' );
	add_action( 'wp_ajax_pmp2fa_revoke_own_devices', 'pmp2fa_ajax_revoke_own_devices' );

	// Cleanup on logout.
	add_action( 'wp_logout', 'pmp2fa_on_logout' );
}

// ═══════════════════════════════════════════════════════════════════════════════
// 1. AUTHENTICATE FILTER
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Intercept a successful login and put it into 2FA pending state.
 *
 * Returns a WP_Error with code 'pmp2fa_pending' so PMP reloads the login page
 * with ?action=pmp2fa_pending — which we then capture in template_redirect.
 *
 * @param WP_User|WP_Error|null $user     Resolved user or existing error.
 * @param string                $username Username provided.
 * @param string                $password Password provided.
 * @return WP_User|WP_Error
 */
function pmp2fa_authenticate_filter( $user, $username, $password ) {
	// Pass through any existing errors or non-user values.
	if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
		return $user;
	}

	// Skip if 2FA already passed this request (e.g. after OTP verification).
	if ( defined( 'PMP2FA_PASS' ) ) {
		return $user;
	}

	// Skip if this device is already trusted.
	if ( pmp2fa_is_device_trusted( $user->ID ) ) {
		return $user;
	}

	// Store pending state and set initial method.
	pmp2fa_set_pending( $user->ID );
	$s      = pmp2fa_get_settings();
	$method = ( 'both' === $s['method'] ) ? 'email' : $s['method'];
	pmp2fa_set_method( $method );

	// We intentionally do NOT send the OTP here. wp_mail() can fail when called
	// inside the authenticate filter (too early in the boot cycle on some hosts).
	// The OTP is sent in pmp2fa_maybe_show_2fa_page() after WP has fully loaded.

	return new WP_Error( 'pmp2fa_pending', '' );
}

// ═══════════════════════════════════════════════════════════════════════════════
// 2. SHOW THE 2FA PAGE
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Detect the PMP login reload and inject the 2FA modal into wp_footer.
 *
 * @return void
 */
function pmp2fa_maybe_show_2fa_page() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
	$manual = ! empty( $_GET['pmp2fa'] );
	// phpcs:enable

	if ( 'pmp2fa_pending' !== $action && ! $manual ) {
		return;
	}

	$user_id = pmp2fa_get_pending();
	if ( ! $user_id ) {
		return;
	}

	// Send OTP now — WP is fully booted so wp_mail() works reliably.
	// Guard against re-sending on a plain page refresh.
	if ( false === get_transient( 'pmp2fa_otp_' . $user_id ) ) {
		pmp2fa_dispatch_otp( $user_id, pmp2fa_get_method() );
	}

	// Enqueue assets properly so they land in <head> / before </body>.
	add_action(
		'wp_enqueue_scripts',
		static function () {
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
		}
	);

	// Inject modal HTML at the very end of the page.
	add_action(
		'wp_footer',
		static function () use ( $user_id ) {
			pmp2fa_render_2fa_modal( $user_id );
		},
		100
	);
}

/**
 * Render the 2FA modal overlay template.
 *
 * @param int $user_id WordPress user ID.
 * @return void
 */
function pmp2fa_render_2fa_modal( $user_id ) {
	$user         = get_userdata( $user_id );
	$settings     = pmp2fa_get_settings();
	$method       = pmp2fa_get_method();
	$show_both    = ( 'both' === $settings['method'] ) && pmp2fa_sms_configured();
	$has_phone    = (bool) get_user_meta( $user_id, 'pmp2fa_phone', true );
	$remember_opt = ! empty( $settings['remember_device'] );
	$expiry       = (int) $settings['otp_expiry'];
	$otp_length   = (int) $settings['otp_length'];
	$nonce        = wp_create_nonce( 'pmp2fa_nonce' );
	$cancel_url   = esc_url( pmp2fa_login_url() );
	$ajax_url     = admin_url( 'admin-ajax.php' );
	$site_name    = get_bloginfo( 'name' );
	$site_url     = home_url( '/' );

	$masked = ( 'sms' === $method && $has_phone )
		? pmp2fa_mask_phone( get_user_meta( $user_id, 'pmp2fa_phone', true ) )
		: pmp2fa_mask_email( $user->user_email );

	// Logo.
	$logo_url = '';
	if ( has_custom_logo() ) {
		$logo_id  = get_theme_mod( 'custom_logo' );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
	}

	// Inline CSS/JS fallback — used when enqueue hooks fired too late.
	$css = file_exists( PMP2FA_PLUGIN_DIR . 'public/css/overlay.css' )
		? file_get_contents( PMP2FA_PLUGIN_DIR . 'public/css/overlay.css' ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		: '';
	$js  = file_exists( PMP2FA_PLUGIN_DIR . 'public/js/overlay.js' )
		? file_get_contents( PMP2FA_PLUGIN_DIR . 'public/js/overlay.js' ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		: '';

	include PMP2FA_PLUGIN_DIR . 'templates/2fa-page-full.php';
}

// ═══════════════════════════════════════════════════════════════════════════════
// 3. AJAX: Send / Resend OTP
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * AJAX handler: send (or resend) an OTP to the pending user.
 *
 * @return void
 */
function pmp2fa_ajax_send_otp() {
	if ( ! check_ajax_referer( 'pmp2fa_nonce', 'nonce', false ) ) {
		wp_send_json_error(
			array( 'message' => __( 'Security check failed. Please refresh and try again.', 'pmp-2fa-authentication' ) ),
			403
		);
	}

	$user_id = pmp2fa_get_pending();
	if ( ! $user_id ) {
		wp_send_json_error(
			array( 'message' => __( 'Session expired. Please log in again.', 'pmp-2fa-authentication' ) ),
			401
		);
	}

	$s = pmp2fa_get_settings();
	if ( ! pmp2fa_rate_limit_ok( $user_id, (int) $s['rate_limit'] ) ) {
		wp_send_json_error(
			array( 'message' => __( 'Too many requests. Please wait before requesting another code.', 'pmp-2fa-authentication' ) ),
			429
		);
	}

	$method = isset( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : pmp2fa_get_method();
	if ( ! in_array( $method, array( 'email', 'sms' ), true ) ) {
		$method = 'email';
	}
	pmp2fa_set_method( $method );

	$result = pmp2fa_dispatch_otp( $user_id, $method );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	$user   = get_userdata( $user_id );
	$masked = ( 'sms' === $method )
		? pmp2fa_mask_phone( (string) get_user_meta( $user_id, 'pmp2fa_phone', true ) )
		: pmp2fa_mask_email( $user->user_email );

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s: masked email address or phone number */
				__( 'Code sent to %s', 'pmp-2fa-authentication' ),
				'<strong>' . esc_html( $masked ) . '</strong>'
			),
		)
	);
}

// ═══════════════════════════════════════════════════════════════════════════════
// 4. AJAX: Verify OTP
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * AJAX handler: verify the submitted OTP and complete the login on success.
 *
 * @return void
 */
function pmp2fa_ajax_verify_otp() {
	if ( ! check_ajax_referer( 'pmp2fa_nonce', 'nonce', false ) ) {
		wp_send_json_error(
			array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'pmp-2fa-authentication' ) ),
			403
		);
	}

	$user_id = pmp2fa_get_pending();
	if ( ! $user_id ) {
		wp_send_json_error(
			array( 'message' => __( 'Session expired. Please go back and log in again.', 'pmp-2fa-authentication' ) ),
			401
		);
	}

	$otp = isset( $_POST['otp'] ) ? sanitize_text_field( wp_unslash( $_POST['otp'] ) ) : '';
	if ( '' === $otp ) {
		wp_send_json_error( array( 'message' => __( 'Please enter the verification code.', 'pmp-2fa-authentication' ) ) );
	}

	$result = pmp2fa_verify_otp( $user_id, $otp );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	// ── OTP correct: complete the login ──────────────────────────────────────
	pmp2fa_clear_pending();

	if ( ! defined( 'PMP2FA_PASS' ) ) {
		define( 'PMP2FA_PASS', true );
	}

	$remember_device = ! empty( $_POST['remember_device'] );
	wp_set_auth_cookie( $user_id, $remember_device, is_ssl() );
	wp_set_current_user( $user_id );

	if ( $remember_device ) {
		$s = pmp2fa_get_settings();
		pmp2fa_trust_device( $user_id, (int) $s['remember_days'] );
	}

	$user = get_userdata( $user_id );
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	do_action( 'wp_login', $user->user_login, $user );

	// Determine post-login redirect (PMP account page preferred).
	$redirect = '';

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

	/**
	 * Filter the URL users are redirected to after passing 2FA.
	 *
	 * @param string  $redirect Redirect URL.
	 * @param WP_User $user     Logged-in user object.
	 */
	$redirect = apply_filters( 'pmp2fa_login_redirect', $redirect, $user );

	wp_send_json_success( array( 'redirect' => esc_url_raw( $redirect ) ) );
}

// ═══════════════════════════════════════════════════════════════════════════════
// 5. USER PROFILE: Phone field
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Render the phone number input on the user profile screen.
 *
 * Only shown when the SMS method is enabled.
 *
 * @param WP_User $user The user being edited.
 * @return void
 */
function pmp2fa_phone_field( $user ) {
	$s = pmp2fa_get_settings();
	if ( ! in_array( $s['method'], array( 'sms', 'both' ), true ) ) {
		return;
	}
	$phone = esc_attr( (string) get_user_meta( $user->ID, 'pmp2fa_phone', true ) );
	?>
	<h2><?php esc_html_e( 'Two-Factor Authentication', 'pmp-2fa-authentication' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="pmp2fa_phone"><?php esc_html_e( 'Phone (SMS OTP)', 'pmp-2fa-authentication' ); ?></label></th>
			<td>
				<input
					type="tel"
					name="pmp2fa_phone"
					id="pmp2fa_phone"
					value="<?php echo esc_attr( $phone ); ?>"
					class="regular-text"
					placeholder="+12223334444"
				>
				<p class="description"><?php esc_html_e( 'E.164 format, e.g. +12223334444', 'pmp-2fa-authentication' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Save the phone number from the user profile form.
 *
 * @param int $user_id WordPress user ID.
 * @return void
 */
function pmp2fa_save_phone( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	if ( ! isset( $_POST['pmp2fa_phone'] ) ) {
		return;
	}
	if (
		! isset( $_POST['_wpnonce'] ) ||
		! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'update-user_' . $user_id )
	) {
		return;
	}

	$phone = sanitize_text_field( wp_unslash( $_POST['pmp2fa_phone'] ) );

	// Reject non-empty values that fail E.164 validation.
	if ( $phone && ! pmp2fa_validate_phone( $phone ) ) {
		return;
	}

	update_user_meta( $user_id, 'pmp2fa_phone', $phone );
}

// ═══════════════════════════════════════════════════════════════════════════════
// 5b. USER PROFILE: Trusted devices
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Render the trusted-devices section on the user profile screen.
 *
 * @param WP_User $user The user being viewed/edited.
 * @return void
 */
function pmp2fa_profile_trusted_devices( $user ) {
	$current_user_id = get_current_user_id();
	$is_own_profile  = ( $current_user_id === $user->ID );
	$is_admin        = current_user_can( 'manage_options' );

	if ( ! $is_own_profile && ! $is_admin ) {
		return;
	}

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
						<p><?php esc_html_e( 'No trusted devices on record.', 'pmp-2fa-authentication' ); ?></p>
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
						<button
							type="button"
							class="button pmp2fa-revoke-own"
							data-userid="<?php echo esc_attr( $user->ID ); ?>"
							data-nonce="<?php echo esc_attr( $nonce ); ?>"
							data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
						>
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
	( function () {
		var btns = document.querySelectorAll( '.pmp2fa-revoke-own' );
		btns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				if ( ! confirm( '<?php echo esc_js( __( 'Revoke all trusted devices? You will need OTP verification on next login.', 'pmp-2fa-authentication' ) ); ?>' ) ) {
					return;
				}
				var result = btn.parentNode.querySelector( '.pmp2fa-revoke-own-result' );
				btn.disabled = true;
				var xhr = new XMLHttpRequest();
				xhr.open( 'POST', btn.getAttribute( 'data-ajaxurl' ), true );
				xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
				xhr.onload = function () {
					try {
						var res = JSON.parse( xhr.responseText );
						result.style.color = res.success ? 'green' : 'red';
						result.textContent  = res.data.message;
						if ( res.success ) { btn.style.display = 'none'; }
						else { btn.disabled = false; }
					} catch ( e ) {
						result.textContent = '<?php echo esc_js( __( 'An error occurred.', 'pmp-2fa-authentication' ) ); ?>';
						btn.disabled = false;
					}
				};
				xhr.send(
					'action=pmp2fa_revoke_own_devices' +
					'&nonce='   + encodeURIComponent( btn.getAttribute( 'data-nonce' ) ) +
					'&user_id=' + encodeURIComponent( btn.getAttribute( 'data-userid' ) )
				);
			} );
		} );
	} )();
	</script>
	<?php
}

/**
 * AJAX handler: allow a user to revoke their own trusted devices.
 *
 * @return void
 */
function pmp2fa_ajax_revoke_own_devices() {
	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	if ( ! $user_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'pmp-2fa-authentication' ) ) );
	}

	if (
		! isset( $_POST['nonce'] ) ||
		! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'pmp2fa_revoke_own_' . $user_id )
	) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'pmp-2fa-authentication' ) ), 403 );
	}

	if ( get_current_user_id() !== $user_id && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pmp-2fa-authentication' ) ), 403 );
	}

	pmp2fa_revoke_trusted_devices( $user_id );

	wp_send_json_success(
		array( 'message' => __( 'All trusted devices have been revoked. OTP verification is required on your next login.', 'pmp-2fa-authentication' ) )
	);
}

// ═══════════════════════════════════════════════════════════════════════════════
// 6. LOGOUT CLEANUP
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Clean up OTP and pending-state data when a user logs out.
 *
 * @param int $user_id WordPress user ID.
 * @return void
 */
function pmp2fa_on_logout( $user_id ) {
	pmp2fa_delete_otp( $user_id );
	pmp2fa_clear_pending();
}
