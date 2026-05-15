<?php
/**
 * Email OTP sender.
 * @package PMP_2FA_Authentication
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function pmp2fa_send_email( $user, $otp ) {
	$s         = pmp2fa_get_settings();
	$site      = get_bloginfo( 'name' );
	$expiry    = (int) $s['otp_expiry'];
	$subject   = ! empty( $s['email_subject'] ) ? $s['email_subject'] : 'Your Login Verification Code';
	$from_name = ! empty( $s['email_from_name'] )  ? $s['email_from_name']  : $site;
	$from_addr = ! empty( $s['email_from_email'] ) ? $s['email_from_email'] : get_bloginfo( 'admin_email' );

	// Set headers directly — more reliable than filters on some hosts.
	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: ' . $from_name . ' <' . $from_addr . '>',
	);

	$body = pmp2fa_email_body( $user, $otp, $expiry, $site );
	$sent = wp_mail( $user->user_email, $subject, $body, $headers );

	if ( ! $sent ) {
		return new WP_Error( 'email_failed', __( 'Could not send the verification email. Please try again or contact support.', 'pmp-2fa-authentication' ) );
	}

	return true;
}

function pmp2fa_email_body( $user, $otp, $expiry, $site ) {
	$name = esc_html( $user->display_name ? $user->display_name : $user->user_login );
	// translators: %d: number of minutes until OTP expires
	$et   = sprintf( _n( '%d minute', '%d minutes', $expiry, 'pmp-2fa-authentication' ), $expiry );
	$s    = esc_html( $site );
	$o    = esc_html( $otp );

	return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 20px;">
<tr><td align="center">
<table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">

  <!-- Header -->
  <tr>
    <td style="background:linear-gradient(135deg,#6366f1 0%,#4f46e5 100%);padding:32px 40px;text-align:center;">
      <div style="font-size:42px;margin-bottom:10px;">&#x1F510;</div>
      <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;letter-spacing:-0.3px;">' . $s . '</h1>
      <p style="margin:6px 0 0;color:rgba(255,255,255,0.85);font-size:14px;">Two-Factor Authentication</p>
    </td>
  </tr>

  <!-- Body -->
  <tr>
    <td style="padding:36px 40px;">
      <p style="margin:0 0 12px;font-size:16px;color:#1e293b;">Hi ' . $name . ',</p>
      <p style="margin:0 0 28px;font-size:15px;color:#475569;line-height:1.6;">
        Use the code below to complete your login. This code expires in
        <strong style="color:#6366f1;">' . esc_html( $et ) . '</strong>.
      </p>

      <!-- OTP Box -->
      <div style="background:#f8faff;border:2px solid #6366f1;border-radius:12px;padding:28px 20px;text-align:center;margin:0 0 28px;">
        <p style="margin:0 0 10px;font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:2px;">Verification Code</p>
        <div style="font-size:42px;font-weight:800;letter-spacing:14px;color:#1e293b;font-family:Courier New,Courier,monospace;">' . $o . '</div>
      </div>

      <p style="margin:0 0 8px;font-size:13px;color:#94a3b8;">&#x26A0;&#xFE0F; Do not share this code with anyone.</p>
      <p style="margin:0;font-size:13px;color:#94a3b8;">If you did not attempt to log in, please change your password immediately.</p>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="background:#f8fafc;padding:18px 40px;text-align:center;border-top:1px solid #e2e8f0;">
      <p style="margin:0;font-size:12px;color:#94a3b8;">Secured by PMP 2FA Authentication</p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';
}
