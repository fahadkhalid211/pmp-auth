<?php
/**
 * 2FA Modal Overlay — Modern Minimal Design
 * Injected into wp_footer on the existing login page.
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
		'code_sent'  => __( 'Code sent successfully!', 'pmp-2fa-authentication' ),
		'code_error' => __( 'Invalid code. Please try again.', 'pmp-2fa-authentication' ),
	),
) );
?>

<!-- =====================================================================
     PMP 2FA Modal Overlay — Modern Minimal Design
     ===================================================================== -->

<style id="pmp2fa-modal-styles">
/* ── Reset & Backdrop ─────────────────────────────────────────────────── */
#pmp2fa-modal-backdrop {
	position: fixed;
	inset: 0;
	z-index: 999990;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 20px 16px;
	background: rgba(15, 23, 42, 0.6);
	backdrop-filter: blur(6px);
	-webkit-backdrop-filter: blur(6px);
	animation: pmp2fa-fade-in 0.25s ease forwards;
}

@keyframes pmp2fa-fade-in {
	from { opacity: 0; }
	to   { opacity: 1; }
}

body.pmp2fa-locked {
	overflow: hidden !important;
}

/* ── Modal Card ───────────────────────────────────────────────────────── */
.pmp2fa-modal {
	position: relative;
	width: 100%;
	max-width: 440px;
	background: #ffffff;
	border-radius: 16px;
	box-shadow: 
		0 4px 6px rgba(0, 0, 0, 0.05),
		0 12px 24px rgba(0, 0, 0, 0.08),
		0 24px 48px rgba(0, 0, 0, 0.12);
	padding: 40px 36px 32px;
	font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
	color: #1e293b;
	animation: pmp2fa-slide-up 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
	overflow: hidden;
	border: 1px solid rgba(0, 0, 0, 0.04);
}

@keyframes pmp2fa-slide-up {
	from { 
		opacity: 0; 
		transform: translateY(20px) scale(0.98); 
	}
	to { 
		opacity: 1; 
		transform: translateY(0) scale(1); 
	}
}

/* ── Logo Area ────────────────────────────────────────────────────────── */
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
	max-height: 48px;
	max-width: 180px;
	width: auto;
	height: auto;
	display: block;
}

.pmp2fa-modal__logo-text {
	font-size: 20px;
	font-weight: 700;
	color: #0f172a;
	letter-spacing: -0.3px;
}

/* ── Divider ──────────────────────────────────────────────────────────── */
.pmp2fa-modal__divider {
	width: 100%;
	height: 1px;
	background: linear-gradient(to right, transparent, #e2e8f0, transparent);
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
	width: 56px;
	height: 56px;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	border-radius: 16px;
	margin-bottom: 16px;
	box-shadow: 0 8px 20px rgba(102, 126, 234, 0.35);
}

.pmp2fa-modal__icon svg {
	width: 26px;
	height: 26px;
	stroke: #ffffff;
}

.pmp2fa-modal__title {
	margin: 0 0 8px !important;
	font-size: 22px !important;
	font-weight: 700 !important;
	color: #0f172a !important;
	letter-spacing: -0.3px;
	line-height: 1.3 !important;
}

.pmp2fa-modal__subtitle {
	margin: 0 !important;
	font-size: 14px !important;
	color: #64748b !important;
	line-height: 1.6 !important;
}

.pmp2fa-modal__subtitle strong {
	color: #667eea !important;
	font-weight: 600;
}

/* ── Notice ───────────────────────────────────────────────────────────── */
.pmp2fa-notice {
	border-radius: 12px;
	padding: 12px 16px;
	font-size: 14px;
	margin-bottom: 20px;
	line-height: 1.5;
	font-weight: 500;
	display: none;
	animation: pmp2fa-notice-in 0.2s ease;
}

@keyframes pmp2fa-notice-in {
	from { opacity: 0; transform: translateY(-8px); }
	to { opacity: 1; transform: translateY(0); }
}

.pmp2fa-notice--success { 
	background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); 
	border: 1px solid #bbf7d0; 
	color: #166534; 
}

.pmp2fa-notice--error { 
	background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); 
	border: 1px solid #fecaca; 
	color: #991b1b; 
}

.pmp2fa-notice--info { 
	background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); 
	border: 1px solid #bfdbfe; 
	color: #1d4ed8; 
}

/* ── Tabs ─────────────────────────────────────────────────────────────── */
.pmp2fa-tabs {
	display: flex;
	gap: 8px;
	background: #f8fafc;
	border-radius: 12px;
	padding: 5px;
	margin-bottom: 24px;
	border: 1px solid #e2e8f0;
}

.pmp2fa-tab {
	flex: 1;
	padding: 10px 16px;
	border: none;
	border-radius: 8px;
	background: transparent;
	color: #64748b;
	font-family: inherit;
	font-size: 14px;
	font-weight: 600;
	cursor: pointer;
	transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
}

.pmp2fa-tab:hover { 
	color: #667eea; 
	background: rgba(102, 126, 234, 0.08); 
}

.pmp2fa-tab.is-active { 
	background: #ffffff; 
	color: #667eea; 
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); 
}

/* ── Field ────────────────────────────────────────────────────────────── */
.pmp2fa-field { 
	margin-bottom: 20px; 
}

.pmp2fa-label {
	display: block;
	font-size: 12px;
	font-weight: 600;
	color: #64748b;
	text-transform: uppercase;
	letter-spacing: 0.8px;
	margin-bottom: 10px;
}

.pmp2fa-otp-input {
	display: block !important;
	width: 100% !important;
	background: #f8fafc !important;
	border: 2px solid #e2e8f0 !important;
	border-radius: 14px !important;
	padding: 16px 20px !important;
	font-family: 'SF Mono', 'Courier New', Courier, monospace !important;
	font-size: 28px !important;
	font-weight: 600 !important;
	letter-spacing: 10px !important;
	color: #0f172a !important;
	text-align: center !important;
	outline: none !important;
	box-sizing: border-box !important;
	-webkit-appearance: none !important;
	transition: all 0.2s ease !important;
}

.pmp2fa-otp-input:focus {
	background: #ffffff !important;
	border-color: #667eea !important;
	box-shadow: 
		0 0 0 4px rgba(102, 126, 234, 0.1),
		0 4px 12px rgba(102, 126, 234, 0.15) !important;
}

.pmp2fa-otp-input::placeholder { 
	color: #cbd5e1 !important; 
	letter-spacing: 8px; 
}

.pmp2fa-hint {
	font-size: 13px !important;
	color: #94a3b8 !important;
	margin: 8px 0 0 !important;
	text-align: center;
	font-weight: 500;
}

/* ── Remember Checkbox ────────────────────────────────────────────────── */
.pmp2fa-check-label {
	display: flex;
	align-items: center;
	gap: 12px;
	font-size: 14px;
	color: #475569;
	cursor: pointer;
	margin-bottom: 20px;
	user-select: none;
	line-height: 1.5;
	padding: 12px 16px;
	background: #f8fafc;
	border-radius: 10px;
	transition: background 0.2s;
}

.pmp2fa-check-label:hover {
	background: #f1f5f9;
}

.pmp2fa-check-label input[type="checkbox"] {
	width: 18px;
	height: 18px;
	accent-color: #667eea;
	cursor: pointer;
	flex-shrink: 0;
}

/* ── Primary Button ───────────────────────────────────────────────────── */
.pmp2fa-btn-primary {
	display: flex !important;
	align-items: center !important;
	justify-content: center !important;
	width: 100% !important;
	padding: 15px 24px !important;
	border: none !important;
	border-radius: 12px !important;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
	color: #ffffff !important;
	font-family: inherit !important;
	font-size: 15px !important;
	font-weight: 600 !important;
	cursor: pointer !important;
	box-shadow: 0 4px 14px rgba(102, 126, 234, 0.4) !important;
	transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
	position: relative !important;
	overflow: hidden !important;
}

.pmp2fa-btn-primary::before {
	content: '';
	position: absolute;
	top: 0;
	left: -100%;
	width: 100%;
	height: 100%;
	background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
	transition: left 0.5s;
}

.pmp2fa-btn-primary:hover:not(:disabled)::before {
	left: 100%;
}

.pmp2fa-btn-primary:hover:not(:disabled) {
	transform: translateY(-2px);
	box-shadow: 0 8px 24px rgba(102, 126, 234, 0.5) !important;
	background: linear-gradient(135deg, #7c8ef5 0%, #8a5fb5 100%) !important;
}

.pmp2fa-btn-primary:active:not(:disabled) {
	transform: translateY(0);
}

.pmp2fa-btn-primary:disabled {
	opacity: 0.6 !important;
	cursor: not-allowed !important;
	transform: none !important;
}

.pmp2fa-btn-primary.is-loading .pmp2fa-btn-text { 
	opacity: 0; 
}

.pmp2fa-btn-primary.is-loading .pmp2fa-spinner { 
	opacity: 1; 
}

/* ── Spinner ──────────────────────────────────────────────────────────── */
.pmp2fa-spinner {
	position: absolute;
	width: 20px;
	height: 20px;
	border: 2.5px solid rgba(255,255,255,0.3);
	border-top-color: #fff;
	border-radius: 50%;
	opacity: 0;
	animation: pmp2fa-spin 0.7s linear infinite;
	transition: opacity 0.2s;
}

@keyframes pmp2fa-spin { 
	to { transform: rotate(360deg); } 
}

/* ── Link Buttons & Footer ────────────────────────────────────────────── */
.pmp2fa-modal__actions {
	margin-top: 20px;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 12px;
}

.pmp2fa-link-btn {
	background: none;
	border: none;
	padding: 0;
	font-family: inherit;
	font-size: 14px;
	color: #667eea;
	cursor: pointer;
	transition: all 0.2s;
	font-weight: 600;
	display: flex;
	align-items: center;
	gap: 6px;
}

.pmp2fa-link-btn:hover:not(:disabled) { 
	color: #764ba2; 
	text-decoration: none;
	transform: translateX(2px);
}

.pmp2fa-link-btn:disabled { 
	color: #94a3b8; 
	cursor: not-allowed; 
}

#pmp2fa-countdown { 
	font-size: 13px; 
	color: #94a3b8; 
	font-weight: 500; 
}

.pmp2fa-back-link {
	font-size: 14px;
	color: #94a3b8;
	text-decoration: none;
	transition: all 0.2s;
	font-weight: 500;
	display: flex;
	align-items: center;
	gap: 6px;
}

.pmp2fa-back-link:hover { 
	color: #64748b; 
	text-decoration: none;
}

/* ── Responsive ───────────────────────────────────────────────────────── */
@media (max-width: 480px) {
	.pmp2fa-modal {
		padding: 32px 24px 28px;
		border-radius: 14px;
	}
	
	.pmp2fa-modal__title {
		font-size: 20px !important;
	}
	
	.pmp2fa-otp-input {
		font-size: 24px !important;
		letter-spacing: 8px !important;
		padding: 14px 16px !important;
	}
	
	.pmp2fa-tabs {
		flex-direction: row;
	}
	
	.pmp2fa-tab {
		font-size: 13px;
		padding: 9px 12px;
	}
}

/* ── Accessibility ────────────────────────────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
	.pmp2fa-modal,
	.pmp2fa-notice,
	.pmp2fa-btn-primary,
	.pmp2fa-tab {
		animation-duration: 0.01ms !important;
		transition-duration: 0.01ms !important;
	}
}

.pmp2fa-modal *:focus-visible {
	outline: 2px solid #667eea;
	outline-offset: 2px;
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
					<circle cx="12" cy="12" r="3"/>
				</svg>
			</div>
			<h2 id="pmp2fa-modal-title" class="pmp2fa-modal__title"><?php esc_html_e( 'Two-Step Verification', 'pmp-2fa-authentication' ); ?></h2>
			<p class="pmp2fa-modal__subtitle" id="pmp2fa-dest-msg">
				<?php
				/* translators: %s: masked email or phone */
				printf( esc_html__( 'Enter the code sent to %s', 'pmp-2fa-authentication' ), '<strong>' . esc_html( $masked ) . '</strong>' );
				?>
			</p>
		</div>

		<!-- Notice -->
		<div id="pmp2fa-notice" class="pmp2fa-notice" role="alert" aria-live="polite"></div>

		<!-- Method tabs (only when both email + SMS enabled and user has a phone) -->
		<?php if ( $show_both && $has_phone ) : ?>
		<div class="pmp2fa-tabs" role="tablist">
			<button type="button" class="pmp2fa-tab<?php echo $method === 'email' ? ' is-active' : ''; ?>" data-method="email" role="tab" aria-selected="<?php echo $method === 'email' ? 'true' : 'false'; ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
					<polyline points="22,6 12,13 2,6"/>
				</svg>
				<?php esc_html_e( 'Email', 'pmp-2fa-authentication' ); ?>
			</button>
			<button type="button" class="pmp2fa-tab<?php echo $method === 'sms'   ? ' is-active' : ''; ?>" data-method="sms"   role="tab" aria-selected="<?php echo $method === 'sms' ? 'true' : 'false'; ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
					<line x1="12" y1="18" x2="12.01" y2="18"/>
				</svg>
				<?php esc_html_e( 'SMS', 'pmp-2fa-authentication' ); ?>
			</button>
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
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
					<path d="M7 11V7a5 5 0 0 1 10 0v4"/>
				</svg>
				<span>
					<?php
					/* translators: %d: number of days */
					printf( esc_html( _n( 'Trust this device for %d day', 'Trust this device for %d days', (int) $settings['remember_days'], 'pmp-2fa-authentication' ) ), (int) $settings['remember_days'] );
					?>
				</span>
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
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<polyline points="23 4 23 10 17 10"/>
					<path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
				</svg>
				<?php esc_html_e( 'Resend Code', 'pmp-2fa-authentication' ); ?>
				<span id="pmp2fa-countdown"></span>
			</button>
			<a id="pmp2fa-cancel" href="<?php echo esc_url( $cancel_url ); ?>" class="pmp2fa-back-link">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<line x1="19" y1="12" x2="5" y2="12"/>
					<polyline points="12 19 5 12 12 5"/>
				</svg>
				<?php esc_html_e( 'Back to Login', 'pmp-2fa-authentication' ); ?>
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
