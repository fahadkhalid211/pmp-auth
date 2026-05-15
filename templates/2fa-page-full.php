<?php
/**
 * 2FA Modal Overlay — injected into wp_footer on the existing login page.
 * The site's own header, footer, and branding remain fully visible behind
 * a semi-transparent backdrop. A centred white card contains the OTP form.
 *
 * Variables provided by pmp2fa_render_2fa_modal():
 *   $user, $settings, $method, $show_both, $has_phone,
 *   $remember_opt, $expiry, $masked, $otp_length,
 *   $nonce, $cancel_url, $ajax_url, $site_name, $site_url, $logo_url,
 *   $css (string), $js (string)
 *
 * @package PMP_2FA_Authentication
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Config for the JS — output before the script block.
$pmp2fa_js_cfg = wp_json_encode( array(
	'ajax_url'   => $ajax_url,
	'nonce'      => $nonce,
	'is_overlay' => true,
	'i18n'       => array(
		'verifying'  => __( 'Verifying…',    'pmp-2fa-authentication' ),
		'sending'    => __( 'Sending code…', 'pmp-2fa-authentication' ),
		'verify_btn' => __( 'Verify Code',   'pmp-2fa-authentication' ),
		'resend_btn' => __( 'Resend Code',   'pmp-2fa-authentication' ),
		'enter_code' => __( 'Please enter the verification code.', 'pmp-2fa-authentication' ),
	),
) );
?>

<!-- =====================================================================
     PMP 2FA Modal Overlay — injected by PMP 2FA Authentication plugin
     ===================================================================== -->

<style id="pmp2fa-modal-styles">
/* ── Reset & backdrop ─────────────────────────────────────────────────── */
#pmp2fa-modal-backdrop {
	position: fixed;
	inset: 0;
	z-index: 999990;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 20px 16px;
	background: rgba(15, 23, 42, 0.65);
	backdrop-filter: blur(4px);
	-webkit-backdrop-filter: blur(4px);
	animation: pmp2fa-fade-in 0.2s ease forwards;
}
@keyframes pmp2fa-fade-in {
	from { opacity: 0; }
	to   { opacity: 1; }
}
body.pmp2fa-locked {
	overflow: hidden !important;
}

/* ── Modal card ───────────────────────────────────────────────────────── */
.pmp2fa-modal {
	position: relative;
	width: 100%;
	max-width: 420px;
	background: #ffffff;
	border-radius: 20px;
	box-shadow:
		0 25px 60px rgba(0, 0, 0, 0.25),
		0 6px 20px rgba(0, 0, 0, 0.12);
	padding: 40px 36px 32px;
	font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
	color: #1e293b;
	animation: pmp2fa-slide-up 0.25s cubic-bezier(0.34, 1.3, 0.64, 1) forwards;
	overflow: hidden;
}
@keyframes pmp2fa-slide-up {
	from { opacity: 0; transform: translateY(24px) scale(0.97); }
	to   { opacity: 1; transform: translateY(0)    scale(1);    }
}

/* ── Logo area ────────────────────────────────────────────────────────── */
.pmp2fa-modal__logo {
	display: flex;
	justify-content: center;
	margin-bottom: 24px;
}
.pmp2fa-modal__logo a {
	display: inline-block;
	text-decoration: none;
	line-height: 1;
}
.pmp2fa-modal__logo img {
	max-height: 52px;
	max-width: 180px;
	width: auto;
	height: auto;
	display: block;
}
.pmp2fa-modal__logo-text {
	font-size: 20px;
	font-weight: 800;
	color: #0f172a;
	letter-spacing: -0.4px;
}

/* ── Divider ──────────────────────────────────────────────────────────── */
.pmp2fa-modal__divider {
	width: 100%;
	height: 1px;
	background: #f1f5f9;
	margin: 0 0 24px;
}

/* ── Header ───────────────────────────────────────────────────────────── */
.pmp2fa-modal__header {
	text-align: center;
	margin-bottom: 28px;
}
.pmp2fa-modal__icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 52px;
	height: 52px;
	background: linear-gradient(135deg, #6366f1, #4f46e5);
	border-radius: 14px;
	margin-bottom: 14px;
	box-shadow: 0 6px 18px rgba(99, 102, 241, 0.38);
}
.pmp2fa-modal__icon svg {
	width: 24px;
	height: 24px;
	stroke: #ffffff;
}
.pmp2fa-modal__title {
	margin: 0 0 8px !important;
	font-size: 20px !important;
	font-weight: 800 !important;
	color: #0f172a !important;
	letter-spacing: -0.3px;
	line-height: 1.2 !important;
}
.pmp2fa-modal__subtitle {
	margin: 0 !important;
	font-size: 14px !important;
	color: #64748b !important;
	line-height: 1.5 !important;
}
.pmp2fa-modal__subtitle strong {
	color: #4f46e5 !important;
	font-weight: 600;
}

/* ── Notice ───────────────────────────────────────────────────────────── */
.pmp2fa-notice {
	border-radius: 10px;
	padding: 11px 14px;
	font-size: 13.5px;
	margin-bottom: 18px;
	line-height: 1.5;
	font-weight: 500;
	display: none;
}
.pmp2fa-notice--success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
.pmp2fa-notice--error   { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
.pmp2fa-notice--info    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }

/* ── Tabs ─────────────────────────────────────────────────────────────── */
.pmp2fa-tabs {
	display: flex;
	gap: 6px;
	background: #f1f5f9;
	border-radius: 10px;
	padding: 4px;
	margin-bottom: 22px;
}
.pmp2fa-tab {
	flex: 1;
	padding: 9px 12px;
	border: none;
	border-radius: 7px;
	background: transparent;
	color: #64748b;
	font-family: inherit;
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
	transition: all 0.18s;
}
.pmp2fa-tab:hover     { color: #4f46e5; background: #e0e7ff; }
.pmp2fa-tab.is-active { background: #ffffff; color: #4f46e5; box-shadow: 0 1px 4px rgba(0,0,0,.1); }

/* ── Field ────────────────────────────────────────────────────────────── */
.pmp2fa-field { margin-bottom: 18px; }
.pmp2fa-label {
	display: block;
	font-size: 11px;
	font-weight: 700;
	color: #64748b;
	text-transform: uppercase;
	letter-spacing: .9px;
	margin-bottom: 9px;
}
.pmp2fa-otp-input {
	display: block !important;
	width: 100% !important;
	background: #f8fafc !important;
	border: 2px solid #e2e8f0 !important;
	border-radius: 12px !important;
	padding: 14px 18px !important;
	font-family: 'Courier New', Courier, monospace !important;
	font-size: 30px !important;
	font-weight: 700 !important;
	letter-spacing: 12px !important;
	color: #0f172a !important;
	text-align: center !important;
	outline: none !important;
	box-sizing: border-box !important;
	-webkit-appearance: none !important;
	transition: border-color .2s, box-shadow .2s !important;
}
.pmp2fa-otp-input:focus {
	background: #fafbff !important;
	border-color: #6366f1 !important;
	box-shadow: 0 0 0 4px rgba(99, 102, 241, .12) !important;
}
.pmp2fa-otp-input::placeholder { color: #cbd5e1 !important; letter-spacing: 8px; }
.pmp2fa-hint {
	font-size: 12px !important;
	color: #94a3b8 !important;
	margin: 7px 0 0 !important;
	text-align: center;
}

/* ── Remember checkbox ────────────────────────────────────────────────── */
.pmp2fa-check-label {
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 13px;
	color: #475569;
	cursor: pointer;
	margin-bottom: 18px;
	user-select: none;
	line-height: 1.4;
}
.pmp2fa-check-label input[type="checkbox"] {
	width: 16px;
	height: 16px;
	accent-color: #6366f1;
	cursor: pointer;
	flex-shrink: 0;
}

/* ── Primary button ───────────────────────────────────────────────────── */
.pmp2fa-btn-primary {
	display: flex !important;
	align-items: center !important;
	justify-content: center !important;
	width: 100% !important;
	padding: 13px 20px !important;
	border: none !important;
	border-radius: 12px !important;
	background: linear-gradient(135deg, #6366f1, #4f46e5) !important;
	color: #ffffff !important;
	font-family: inherit !important;
	font-size: 15px !important;
	font-weight: 700 !important;
	cursor: pointer !important;
	box-shadow: 0 4px 14px rgba(99, 102, 241, .38) !important;
	transition: all .2s !important;
	position: relative !important;
	overflow: hidden !important;
}
.pmp2fa-btn-primary:hover:not(:disabled) {
	transform: translateY(-1px) !important;
	box-shadow: 0 6px 20px rgba(99, 102, 241, .5) !important;
	background: linear-gradient(135deg, #818cf8, #6366f1) !important;
}
.pmp2fa-btn-primary:disabled {
	opacity: .6 !important;
	cursor: not-allowed !important;
	transform: none !important;
}
.pmp2fa-btn-primary.is-loading .pmp2fa-btn-text { opacity: 0; }
.pmp2fa-btn-primary.is-loading .pmp2fa-spinner  { opacity: 1; }

/* ── Spinner ──────────────────────────────────────────────────────────── */
.pmp2fa-spinner {
	position: absolute;
	width: 18px;
	height: 18px;
	border: 2.5px solid rgba(255,255,255,.3);
	border-top-color: #fff;
	border-radius: 50%;
	opacity: 0;
	animation: pmp2fa-spin .65s linear infinite;
	transition: opacity .15s;
}
@keyframes pmp2fa-spin { to { transform: rotate(360deg); } }

/* ── Link buttons & footer ────────────────────────────────────────────── */
.pmp2fa-modal__actions {
	margin-top: 16px;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
}
.pmp2fa-link-btn {
	background: none;
	border: none;
	padding: 0;
	font-family: inherit;
	font-size: 13px;
	color: #6366f1;
	cursor: pointer;
	transition: color .18s;
	font-weight: 500;
}
.pmp2fa-link-btn:hover:not(:disabled) { color: #4f46e5; text-decoration: underline; }
.pmp2fa-link-btn:disabled             { color: #94a3b8; cursor: not-allowed; }
#pmp2fa-countdown { font-size: 12px; color: #94a3b8; font-weight: 400; }

.pmp2fa-back-link {
	font-size: 13px;
	color: #94a3b8;
	text-decoration: none;
	transition: color .18s;
	font-weight: 500;
}
.pmp2fa-back-link:hover { color: #64748b; }

/* ── Responsive ───────────────────────────────────────────────────────── */
@media (max-width: 480px) {
	.pmp2fa-modal {
		padding: 28px 20px 24px;
		border-radius: 16px;
	}
	.pmp2fa-otp-input {
		font-size: 24px !important;
		letter-spacing: 8px !important;
	}
}
</style>

<div id="pmp2fa-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="pmp2fa-modal-title">

	<div class="pmp2fa-modal">

		<!-- Logo -->
		<div class="pmp2fa-modal__logo">
			<a href="<?php echo esc_url( $site_url ); ?>">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>">
				<?php else : ?>
					<span class="pmp2fa-modal__logo-text"><?php echo esc_html( $site_name ); ?></span>
				<?php endif; ?>
			</a>
		</div>

		<div class="pmp2fa-modal__divider"></div>

		<!-- Header -->
		<div class="pmp2fa-modal__header">
			<div class="pmp2fa-modal__icon" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
				</svg>
			</div>
			<h2 id="pmp2fa-modal-title" class="pmp2fa-modal__title"><?php esc_html_e( 'Two-Step Verification', 'pmp-2fa-authentication' ); ?></h2>
			<p class="pmp2fa-modal__subtitle" id="pmp2fa-dest-msg">
				<?php
				/* translators: %s: masked email or phone */
				printf( esc_html__( 'A code was sent to %s', 'pmp-2fa-authentication' ), '<strong>' . esc_html( $masked ) . '</strong>' );
				?>
			</p>
		</div>

		<!-- Notice -->
		<div id="pmp2fa-notice" class="pmp2fa-notice" role="alert" aria-live="polite"></div>

		<!-- Method tabs (only when both email + SMS enabled and user has a phone) -->
		<?php if ( $show_both && $has_phone ) : ?>
		<div class="pmp2fa-tabs" role="tablist">
			<button type="button" class="pmp2fa-tab<?php echo $method === 'email' ? ' is-active' : ''; ?>" data-method="email" role="tab">&#x2709; <?php esc_html_e( 'Email', 'pmp-2fa-authentication' ); ?></button>
			<button type="button" class="pmp2fa-tab<?php echo $method === 'sms'   ? ' is-active' : ''; ?>" data-method="sms"   role="tab">&#x1F4F1; <?php esc_html_e( 'SMS', 'pmp-2fa-authentication' ); ?></button>
		</div>
		<?php endif; ?>

		<!-- OTP Form -->
		<form id="pmp2fa-form" novalidate>
			<input type="hidden" name="action" value="pmp2fa_verify_otp">
			<input type="hidden" name="nonce"  value="<?php echo esc_attr( $nonce ); ?>">

			<div class="pmp2fa-field">
				<label for="pmp2fa-otp" class="pmp2fa-label"><?php esc_html_e( 'Verification Code', 'pmp-2fa-authentication' ); ?></label>
				<input
					type="text"
					id="pmp2fa-otp"
					name="otp"
					class="pmp2fa-otp-input"
					inputmode="numeric"
					autocomplete="one-time-code"
					maxlength="<?php echo esc_attr( $otp_length ); ?>"
					placeholder="<?php echo esc_attr( str_repeat( '-', $otp_length ) ); ?>"
					autofocus
					required
				>
				<p class="pmp2fa-hint">
					<?php
					/* translators: %d: number of minutes */
					printf( esc_html( _n( 'Expires in %d minute', 'Expires in %d minutes', $expiry, 'pmp-2fa-authentication' ) ), absint( $expiry ) );
					?>
				</p>
			</div>

			<?php if ( $remember_opt ) : ?>
			<label class="pmp2fa-check-label">
				<input type="checkbox" name="remember_device" value="1">
				<?php
				/* translators: %d: number of days */
				printf( esc_html( _n( 'Trust this device for %d day', 'Trust this device for %d days', (int) $settings['remember_days'], 'pmp-2fa-authentication' ) ), (int) $settings['remember_days'] );
				?>
			</label>
			<?php endif; ?>

			<button type="submit" id="pmp2fa-submit" class="pmp2fa-btn-primary">
				<span class="pmp2fa-btn-text"><?php esc_html_e( 'Verify Code', 'pmp-2fa-authentication' ); ?></span>
				<span class="pmp2fa-spinner" aria-hidden="true"></span>
			</button>
		</form>

		<!-- Actions -->
		<div class="pmp2fa-modal__actions">
			<button type="button" id="pmp2fa-resend" class="pmp2fa-link-btn" disabled>
				<?php esc_html_e( 'Resend Code', 'pmp-2fa-authentication' ); ?><span id="pmp2fa-countdown"></span>
			</button>
			<a id="pmp2fa-cancel" href="<?php echo esc_url( $cancel_url ); ?>" class="pmp2fa-back-link">
				&larr; <?php esc_html_e( 'Cancel &amp; Back to Login', 'pmp-2fa-authentication' ); ?>
			</a>
		</div>

	</div><!-- /.pmp2fa-modal -->

</div><!-- /#pmp2fa-modal-backdrop -->

<script>
window.pmp2fa_cfg = <?php echo $pmp2fa_js_cfg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
</script>
<?php
// Output inline JS only if the enqueued script hasn't already been output.
global $wp_scripts;
$pmp2fa_already_done = isset( $wp_scripts ) && in_array( 'pmp2fa-overlay', (array) $wp_scripts->done, true );
if ( ! $pmp2fa_already_done && $js ) :
?>
<script id="pmp2fa-overlay-js"><?php echo $js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
<?php endif; ?>
