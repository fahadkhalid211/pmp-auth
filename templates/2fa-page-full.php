<?php
/**
 * 2FA Modal Overlay — injected into wp_footer on the existing login page.
 *
 * The site's own header, footer, and branding remain visible behind a
 * frosted-glass backdrop. A centred card contains the OTP form.
 *
 * Variables provided by pmp2fa_render_2fa_modal():
 *   $user         WP_User
 *   $settings     array
 *   $method       string  'email' | 'sms'
 *   $show_both    bool
 *   $has_phone    bool
 *   $remember_opt bool
 *   $expiry       int     minutes
 *   $masked       string
 *   $otp_length   int
 *   $nonce        string
 *   $cancel_url   string
 *   $ajax_url     string
 *   $site_name    string
 *   $site_url     string
 *   $logo_url     string
 *   $css          string  (inline fallback)
 *   $js           string  (inline fallback)
 *
 * @package PMP_2FA_Authentication
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pmp2fa_js_cfg = wp_json_encode(
	array(
		'ajax_url'   => $ajax_url,
		'nonce'      => $nonce,
		'is_overlay' => true,
		'i18n'       => array(
			'verifying'  => __( 'Verifying…',         'pmp-2fa-authentication' ),
			'sending'    => __( 'Sending code…',       'pmp-2fa-authentication' ),
			'verify_btn' => __( 'Verify Code',         'pmp-2fa-authentication' ),
			'resend_btn' => __( 'Resend Code',         'pmp-2fa-authentication' ),
			'enter_code' => __( 'Please enter the verification code.', 'pmp-2fa-authentication' ),
		),
	)
);
?>

<!-- PMP 2FA Authentication – modal overlay -->

<style id="pmp2fa-modal-styles">
/* ── Variables ──────────────────────────────────────────────────────────────── */
:root {
	--pmp2fa-accent:      #6366f1;
	--pmp2fa-accent-dk:   #4f46e5;
	--pmp2fa-accent-lt:   #eef2ff;
	--pmp2fa-accent-glow: rgba(99,102,241,.18);
	--pmp2fa-text:        #0f172a;
	--pmp2fa-muted:       #64748b;
	--pmp2fa-subtle:      #94a3b8;
	--pmp2fa-border:      #e2e8f0;
	--pmp2fa-bg:          #ffffff;
	--pmp2fa-surface:     #f8fafc;
	--pmp2fa-radius-card: 22px;
	--pmp2fa-radius-btn:  12px;
	--pmp2fa-radius-in:   10px;
	--pmp2fa-shadow-card: 0 20px 60px rgba(15,23,42,.14), 0 4px 16px rgba(15,23,42,.08);
	--pmp2fa-shadow-btn:  0 4px 16px rgba(99,102,241,.32);
	--pmp2fa-font:        -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
	--pmp2fa-mono:        'SF Mono', 'Fira Code', 'Fira Mono', 'Courier New', monospace;
	--pmp2fa-trans:       0.18s cubic-bezier(.4,0,.2,1);
}

/* ── Backdrop ───────────────────────────────────────────────────────────────── */
#pmp2fa-modal-backdrop {
	position: fixed;
	inset: 0;
	z-index: 999990 !important;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 20px 16px;
	background: rgba(15,23,42,.55);
	backdrop-filter: blur(6px) saturate(140%);
	-webkit-backdrop-filter: blur(6px) saturate(140%);
	animation: pmp2fa-fade .22s ease forwards;
}
@keyframes pmp2fa-fade { from { opacity:0 } to { opacity:1 } }

body.pmp2fa-locked { overflow: hidden !important; }

/* ── Card ───────────────────────────────────────────────────────────────────── */
.pmp2fa-modal {
	position: relative;
	width: 100%;
	max-width: 408px;
	background: var(--pmp2fa-bg);
	border-radius: var(--pmp2fa-radius-card);
	box-shadow: var(--pmp2fa-shadow-card);
	padding: 0;
	font-family: var(--pmp2fa-font);
	color: var(--pmp2fa-text);
	animation: pmp2fa-rise .28s cubic-bezier(.34,1.3,.64,1) forwards;
	overflow: hidden;
}
@keyframes pmp2fa-rise {
	from { opacity:0; transform:translateY(20px) scale(.97) }
	to   { opacity:1; transform:translateY(0)    scale(1)   }
}

/* ── Card header stripe ─────────────────────────────────────────────────────── */
.pmp2fa-modal__stripe {
	height: 4px;
	background: linear-gradient(90deg, var(--pmp2fa-accent) 0%, #818cf8 50%, var(--pmp2fa-accent-dk) 100%);
}

/* ── Card body padding ──────────────────────────────────────────────────────── */
.pmp2fa-modal__body {
	padding: 36px 36px 32px;
}

/* ── Logo / brand ───────────────────────────────────────────────────────────── */
.pmp2fa-modal__brand {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-bottom: 28px;
}
.pmp2fa-modal__logo-img {
	max-height: 36px;
	max-width: 140px;
	width: auto;
	height: auto;
	display: block;
}
.pmp2fa-modal__logo-text {
	font-size: 15px;
	font-weight: 700;
	color: var(--pmp2fa-text);
	letter-spacing: -0.2px;
	line-height: 1;
}
.pmp2fa-modal__brand-sep {
	width: 1px;
	height: 20px;
	background: var(--pmp2fa-border);
	margin: 0 4px;
}
.pmp2fa-modal__brand-tag {
	display: flex;
	align-items: center;
	gap: 5px;
	font-size: 11px;
	font-weight: 600;
	color: var(--pmp2fa-accent);
	letter-spacing: .4px;
	text-transform: uppercase;
}
.pmp2fa-modal__brand-tag svg {
	width: 13px;
	height: 13px;
	stroke: var(--pmp2fa-accent);
	flex-shrink: 0;
}

/* ── Heading ─────────────────────────────────────────────────────────────────── */
.pmp2fa-modal__title {
	margin: 0 0 6px !important;
	font-size: 21px !important;
	font-weight: 800 !important;
	color: var(--pmp2fa-text) !important;
	letter-spacing: -0.4px !important;
	line-height: 1.2 !important;
}
.pmp2fa-modal__subtitle {
	margin: 0 0 24px !important;
	font-size: 13.5px !important;
	color: var(--pmp2fa-muted) !important;
	line-height: 1.55 !important;
}
.pmp2fa-modal__subtitle strong {
	color: var(--pmp2fa-accent) !important;
	font-weight: 600 !important;
}

/* ── Notice ──────────────────────────────────────────────────────────────────── */
.pmp2fa-notice {
	border-radius: var(--pmp2fa-radius-in);
	padding: 11px 14px;
	font-size: 13px;
	margin-bottom: 18px;
	line-height: 1.5;
	font-weight: 500;
	display: none;
}
.pmp2fa-notice--success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d }
.pmp2fa-notice--error   { background:#fff1f2; border:1px solid #fecdd3; color:#be123c }
.pmp2fa-notice--info    { background:var(--pmp2fa-accent-lt); border:1px solid #c7d2fe; color:var(--pmp2fa-accent-dk) }

/* ── Method tabs ─────────────────────────────────────────────────────────────── */
.pmp2fa-tabs {
	display: flex;
	gap: 4px;
	background: var(--pmp2fa-surface);
	border: 1px solid var(--pmp2fa-border);
	border-radius: var(--pmp2fa-radius-in);
	padding: 3px;
	margin-bottom: 22px;
}
.pmp2fa-tab {
	flex: 1;
	padding: 8px 12px;
	border: none;
	border-radius: 8px;
	background: transparent;
	color: var(--pmp2fa-muted);
	font-family: var(--pmp2fa-font);
	font-size: 12.5px;
	font-weight: 600;
	cursor: pointer;
	transition: all var(--pmp2fa-trans);
	letter-spacing: .1px;
}
.pmp2fa-tab:hover     { color: var(--pmp2fa-accent); background: var(--pmp2fa-accent-lt) }
.pmp2fa-tab.is-active { background: var(--pmp2fa-bg); color: var(--pmp2fa-accent-dk); box-shadow: 0 1px 4px rgba(0,0,0,.09) }

/* ── OTP input ───────────────────────────────────────────────────────────────── */
.pmp2fa-field { margin-bottom: 16px }
.pmp2fa-label {
	display: block;
	font-size: 11px;
	font-weight: 700;
	color: var(--pmp2fa-subtle);
	text-transform: uppercase;
	letter-spacing: 1px;
	margin-bottom: 8px;
}
.pmp2fa-otp-wrap {
	position: relative;
}
.pmp2fa-otp-input {
	display: block !important;
	width: 100% !important;
	background: var(--pmp2fa-surface) !important;
	border: 1.5px solid var(--pmp2fa-border) !important;
	border-radius: var(--pmp2fa-radius-btn) !important;
	padding: 16px 18px !important;
	font-family: var(--pmp2fa-mono) !important;
	font-size: 28px !important;
	font-weight: 700 !important;
	letter-spacing: 10px !important;
	color: var(--pmp2fa-text) !important;
	text-align: center !important;
	outline: none !important;
	box-sizing: border-box !important;
	-webkit-appearance: none !important;
	transition: border-color var(--pmp2fa-trans), box-shadow var(--pmp2fa-trans) !important;
	text-indent: 10px !important;
}
.pmp2fa-otp-input:focus {
	background: var(--pmp2fa-bg) !important;
	border-color: var(--pmp2fa-accent) !important;
	box-shadow: 0 0 0 3px var(--pmp2fa-accent-glow) !important;
}
.pmp2fa-otp-input::placeholder { color: #d1d5db !important; letter-spacing: 6px }
.pmp2fa-hint {
	margin: 7px 0 0 !important;
	font-size: 11.5px !important;
	color: var(--pmp2fa-subtle) !important;
	text-align: center;
	line-height: 1.4;
}

/* ── Remember checkbox ───────────────────────────────────────────────────────── */
.pmp2fa-check-label {
	display: flex;
	align-items: center;
	gap: 9px;
	font-size: 13px;
	color: var(--pmp2fa-muted);
	cursor: pointer;
	margin-bottom: 20px;
	user-select: none;
	line-height: 1.4;
}
.pmp2fa-check-label input[type="checkbox"] {
	width: 15px;
	height: 15px;
	accent-color: var(--pmp2fa-accent);
	cursor: pointer;
	flex-shrink: 0;
}

/* ── Primary button ──────────────────────────────────────────────────────────── */
.pmp2fa-btn-primary {
	display: flex !important;
	align-items: center !important;
	justify-content: center !important;
	width: 100% !important;
	padding: 14px 20px !important;
	border: none !important;
	border-radius: var(--pmp2fa-radius-btn) !important;
	background: var(--pmp2fa-accent) !important;
	color: #fff !important;
	font-family: var(--pmp2fa-font) !important;
	font-size: 14.5px !important;
	font-weight: 700 !important;
	cursor: pointer !important;
	box-shadow: var(--pmp2fa-shadow-btn) !important;
	transition: background var(--pmp2fa-trans), box-shadow var(--pmp2fa-trans), transform var(--pmp2fa-trans) !important;
	position: relative !important;
	overflow: hidden !important;
	letter-spacing: .1px !important;
}
.pmp2fa-btn-primary:hover:not(:disabled) {
	background: var(--pmp2fa-accent-dk) !important;
	box-shadow: 0 6px 22px rgba(99,102,241,.42) !important;
	transform: translateY(-1px) !important;
}
.pmp2fa-btn-primary:active:not(:disabled) { transform: translateY(0) !important }
.pmp2fa-btn-primary:disabled {
	opacity: .55 !important;
	cursor: not-allowed !important;
	transform: none !important;
	box-shadow: none !important;
}
.pmp2fa-btn-primary.is-loading .pmp2fa-btn-text { opacity: 0 }
.pmp2fa-btn-primary.is-loading .pmp2fa-spinner  { opacity: 1 }

/* ── Spinner ─────────────────────────────────────────────────────────────────── */
.pmp2fa-spinner {
	position: absolute;
	width: 17px;
	height: 17px;
	border: 2px solid rgba(255,255,255,.3);
	border-top-color: #fff;
	border-radius: 50%;
	opacity: 0;
	animation: pmp2fa-spin .6s linear infinite;
	transition: opacity .12s;
}
@keyframes pmp2fa-spin { to { transform: rotate(360deg) } }

/* ── Footer row ──────────────────────────────────────────────────────────────── */
.pmp2fa-modal__footer {
	margin-top: 18px;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 10px;
}
.pmp2fa-link-btn {
	background: none;
	border: none;
	padding: 0;
	font-family: var(--pmp2fa-font);
	font-size: 13px;
	color: var(--pmp2fa-accent);
	cursor: pointer;
	transition: color var(--pmp2fa-trans), opacity var(--pmp2fa-trans);
	font-weight: 600;
}
.pmp2fa-link-btn:hover:not(:disabled) { color: var(--pmp2fa-accent-dk); text-decoration: underline }
.pmp2fa-link-btn:disabled             { color: var(--pmp2fa-subtle); cursor: not-allowed; opacity: .7 }
#pmp2fa-countdown                     { font-weight: 400; opacity: .7 }

.pmp2fa-divider-dot {
	width: 3px;
	height: 3px;
	border-radius: 50%;
	background: var(--pmp2fa-border);
	display: inline-block;
}
.pmp2fa-back-link {
	font-size: 12.5px;
	color: var(--pmp2fa-subtle);
	text-decoration: none;
	transition: color var(--pmp2fa-trans);
	font-weight: 500;
}
.pmp2fa-back-link:hover { color: var(--pmp2fa-muted) }

/* ── Secure badge ────────────────────────────────────────────────────────────── */
.pmp2fa-modal__secure {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 5px;
	padding: 12px 36px;
	border-top: 1px solid var(--pmp2fa-surface);
	background: var(--pmp2fa-surface);
	font-size: 11px;
	color: var(--pmp2fa-subtle);
	font-weight: 500;
	letter-spacing: .1px;
}
.pmp2fa-modal__secure svg {
	width: 11px;
	height: 11px;
	stroke: var(--pmp2fa-subtle);
	flex-shrink: 0;
}

/* ── Responsive ──────────────────────────────────────────────────────────────── */
@media (max-width: 480px) {
	.pmp2fa-modal__body { padding: 28px 22px 24px }
	.pmp2fa-otp-input   { font-size:22px !important; letter-spacing:7px !important }
}
</style>

<div id="pmp2fa-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="pmp2fa-modal-title">
	<div class="pmp2fa-modal">

		<!-- Top accent stripe -->
		<div class="pmp2fa-modal__stripe" aria-hidden="true"></div>

		<div class="pmp2fa-modal__body">

			<!-- Brand row -->
			<div class="pmp2fa-modal__brand">
				<a href="<?php echo esc_url( $site_url ); ?>" style="display:flex;align-items:center;text-decoration:none;">
					<?php if ( $logo_url ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" class="pmp2fa-modal__logo-img">
					<?php else : ?>
						<span class="pmp2fa-modal__logo-text"><?php echo esc_html( $site_name ); ?></span>
					<?php endif; ?>
				</a>
				<div class="pmp2fa-modal__brand-sep" aria-hidden="true"></div>
				<div class="pmp2fa-modal__brand-tag">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
					</svg>
					2-Step Verification
				</div>
			</div>

			<!-- Heading -->
			<h2 id="pmp2fa-modal-title" class="pmp2fa-modal__title"><?php esc_html_e( 'Check your inbox', 'pmp-2fa-authentication' ); ?></h2>
			<p class="pmp2fa-modal__subtitle" id="pmp2fa-dest-msg">
				<?php
				/* translators: %s: masked email address or phone number */
				printf(
					esc_html__( 'We sent a verification code to %s', 'pmp-2fa-authentication' ),
					'<strong>' . esc_html( $masked ) . '</strong>'
				);
				?>
			</p>

			<!-- Notice -->
			<div id="pmp2fa-notice" class="pmp2fa-notice" role="alert" aria-live="polite" aria-atomic="true"></div>

			<!-- Method tabs -->
			<?php if ( $show_both && $has_phone ) : ?>
			<div class="pmp2fa-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Delivery method', 'pmp-2fa-authentication' ); ?>">
				<button
					type="button"
					class="pmp2fa-tab<?php echo 'email' === $method ? ' is-active' : ''; ?>"
					data-method="email"
					role="tab"
					aria-selected="<?php echo 'email' === $method ? 'true' : 'false'; ?>"
				>
					&#9993;&nbsp; <?php esc_html_e( 'Email', 'pmp-2fa-authentication' ); ?>
				</button>
				<button
					type="button"
					class="pmp2fa-tab<?php echo 'sms' === $method ? ' is-active' : ''; ?>"
					data-method="sms"
					role="tab"
					aria-selected="<?php echo 'sms' === $method ? 'true' : 'false'; ?>"
				>
					&#128241;&nbsp; <?php esc_html_e( 'SMS', 'pmp-2fa-authentication' ); ?>
				</button>
			</div>
			<?php endif; ?>

			<!-- OTP form -->
			<form id="pmp2fa-form" novalidate>
				<input type="hidden" name="action" value="pmp2fa_verify_otp">
				<input type="hidden" name="nonce"  value="<?php echo esc_attr( $nonce ); ?>">

				<div class="pmp2fa-field">
					<label for="pmp2fa-otp" class="pmp2fa-label"><?php esc_html_e( 'Verification Code', 'pmp-2fa-authentication' ); ?></label>
					<div class="pmp2fa-otp-wrap">
						<input
							type="text"
							id="pmp2fa-otp"
							name="otp"
							class="pmp2fa-otp-input"
							inputmode="numeric"
							autocomplete="one-time-code"
							maxlength="<?php echo esc_attr( $otp_length ); ?>"
							placeholder="<?php echo esc_attr( str_repeat( '·', $otp_length ) ); ?>"
							autofocus
							required
							spellcheck="false"
							autocorrect="off"
						>
					</div>
					<p class="pmp2fa-hint">
						<?php
						printf(
							/* translators: %d: OTP expiry in minutes */
							esc_html( _n( 'Expires in %d minute', 'Expires in %d minutes', $expiry, 'pmp-2fa-authentication' ) ),
							absint( $expiry )
						);
						?>
					</p>
				</div>

				<?php if ( $remember_opt ) : ?>
				<label class="pmp2fa-check-label">
					<input type="checkbox" name="remember_device" value="1">
					<?php
					printf(
						/* translators: %d: number of days */
						esc_html( _n( 'Trust this device for %d day', 'Trust this device for %d days', (int) $settings['remember_days'], 'pmp-2fa-authentication' ) ),
						(int) $settings['remember_days']
					);
					?>
				</label>
				<?php endif; ?>

				<button type="submit" id="pmp2fa-submit" class="pmp2fa-btn-primary">
					<span class="pmp2fa-btn-text"><?php esc_html_e( 'Verify Code', 'pmp-2fa-authentication' ); ?></span>
					<span class="pmp2fa-spinner" aria-hidden="true"></span>
				</button>
			</form>

			<!-- Footer actions -->
			<div class="pmp2fa-modal__footer">
				<button type="button" id="pmp2fa-resend" class="pmp2fa-link-btn" disabled>
					<?php esc_html_e( 'Resend Code', 'pmp-2fa-authentication' ); ?><span id="pmp2fa-countdown"></span>
				</button>
				<span class="pmp2fa-divider-dot" aria-hidden="true"></span>
				<a id="pmp2fa-cancel" href="<?php echo esc_url( $cancel_url ); ?>" class="pmp2fa-back-link">
					&larr; <?php esc_html_e( 'Back to login', 'pmp-2fa-authentication' ); ?>
				</a>
			</div>

		</div><!-- /.pmp2fa-modal__body -->

		<!-- Secure footer -->
		<div class="pmp2fa-modal__secure" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
			</svg>
			<?php esc_html_e( 'Secured by PMP 2FA Authentication', 'pmp-2fa-authentication' ); ?>
		</div>

	</div><!-- /.pmp2fa-modal -->
</div><!-- /#pmp2fa-modal-backdrop -->

<script>
window.pmp2fa_cfg = <?php echo $pmp2fa_js_cfg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
</script>
<?php
global $wp_scripts;
$already_enqueued = isset( $wp_scripts ) && in_array( 'pmp2fa-overlay', (array) $wp_scripts->done, true );
if ( ! $already_enqueued && $js ) :
?>
<script id="pmp2fa-overlay-js"><?php echo $js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
<?php endif; ?>
