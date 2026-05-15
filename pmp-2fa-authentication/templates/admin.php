<?php
/**
 * Admin settings page template.
 * Variable $s = current settings array (from pmp2fa_get_settings()).
 *
 * @package PMP_2FA_Authentication
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap pmp2fa-admin">

  <div class="pmp2fa-admin-header">
    <span style="font-size:36px;">🔐</span>
    <div>
      <h1><?php esc_html_e( 'PMP 2FA Authentication', 'pmp-2fa-authentication' ); ?></h1>
      <p><?php esc_html_e( 'Two-factor authentication for Paid Memberships Pro.', 'pmp-2fa-authentication' ); ?></p>
    </div>
  </div>

  <?php settings_errors( 'pmp2fa_group' ); ?>

  <div class="pmp2fa-layout">
    <div class="pmp2fa-main">
      <form method="post" action="options.php">
        <?php settings_fields( 'pmp2fa_group' ); ?>

        <!-- General -->
        <div class="pmp2fa-box">
          <h2>⚙️ <?php esc_html_e( 'General', 'pmp-2fa-authentication' ); ?></h2>

          <table class="form-table">
            <tr>
              <th><label for="pmp2fa-method"><?php esc_html_e( '2FA Method', 'pmp-2fa-authentication' ); ?></label></th>
              <td>
                <select name="pmp2fa_settings[method]" id="pmp2fa-method">
                  <option value="email" <?php selected( $s['method'], 'email' ); ?>><?php esc_html_e( 'Email OTP only', 'pmp-2fa-authentication' ); ?></option>
                  <option value="sms"   <?php selected( $s['method'], 'sms' );   ?>><?php esc_html_e( 'SMS only (Twilio)', 'pmp-2fa-authentication' ); ?></option>
                  <option value="both"  <?php selected( $s['method'], 'both' );  ?>><?php esc_html_e( 'Both (user chooses)', 'pmp-2fa-authentication' ); ?></option>
                </select>
              </td>
            </tr>
            <tr>
              <th><label for="pmp2fa-otp-length"><?php esc_html_e( 'OTP Length (digits)', 'pmp-2fa-authentication' ); ?></label></th>
              <td>
                <input type="number" name="pmp2fa_settings[otp_length]" id="pmp2fa-otp-length" value="<?php echo esc_attr( $s['otp_length'] ); ?>" min="4" max="8" class="small-text">
                <p class="description"><?php esc_html_e( '4–8 digits', 'pmp-2fa-authentication' ); ?></p>
              </td>
            </tr>
            <tr>
              <th><label for="pmp2fa-otp-expiry"><?php esc_html_e( 'OTP Expiry (minutes)', 'pmp-2fa-authentication' ); ?></label></th>
              <td>
                <input type="number" name="pmp2fa_settings[otp_expiry]" id="pmp2fa-otp-expiry" value="<?php echo esc_attr( $s['otp_expiry'] ); ?>" min="1" max="60" class="small-text">
              </td>
            </tr>
            <tr>
              <th><label for="pmp2fa-rate-limit"><?php esc_html_e( 'Max OTP requests / hour', 'pmp-2fa-authentication' ); ?></label></th>
              <td>
                <input type="number" name="pmp2fa_settings[rate_limit]" id="pmp2fa-rate-limit" value="<?php echo esc_attr( $s['rate_limit'] ); ?>" min="1" max="20" class="small-text">
              </td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'Trust Device', 'pmp-2fa-authentication' ); ?></th>
              <td>
                <label>
                  <input type="checkbox" name="pmp2fa_settings[remember_device]" value="1" id="pmp2fa-remember" <?php checked( $s['remember_device'], 1 ); ?>>
                  <?php esc_html_e( 'Allow "Trust this device" option at login', 'pmp-2fa-authentication' ); ?>
                </label>
                <div id="pmp2fa-days-row" style="margin-top:8px;<?php echo empty( $s['remember_device'] ) ? 'display:none' : ''; ?>">
                  <label>
                    <?php esc_html_e( 'Trust duration (days):', 'pmp-2fa-authentication' ); ?>
                    <input type="number" name="pmp2fa_settings[remember_days]" value="<?php echo esc_attr( $s['remember_days'] ); ?>" min="1" max="365" class="small-text">
                  </label>
                </div>
              </td>
            </tr>
          </table>
        </div>

        <!-- Email -->
        <div class="pmp2fa-box" id="pmp2fa-email-box">
          <h2>✉️ <?php esc_html_e( 'Email Settings', 'pmp-2fa-authentication' ); ?></h2>
          <table class="form-table">
            <tr>
              <th><label for="pmp2fa-email-subject"><?php esc_html_e( 'Email Subject', 'pmp-2fa-authentication' ); ?></label></th>
              <td><input type="text" name="pmp2fa_settings[email_subject]" id="pmp2fa-email-subject" value="<?php echo esc_attr( $s['email_subject'] ); ?>" class="regular-text"></td>
            </tr>
            <tr>
              <th><label for="pmp2fa-from-name"><?php esc_html_e( 'From Name', 'pmp-2fa-authentication' ); ?></label></th>
              <td><input type="text" name="pmp2fa_settings[email_from_name]" id="pmp2fa-from-name" value="<?php echo esc_attr( $s['email_from_name'] ); ?>" class="regular-text"></td>
            </tr>
            <tr>
              <th><label for="pmp2fa-from-email"><?php esc_html_e( 'From Email', 'pmp-2fa-authentication' ); ?></label></th>
              <td><input type="email" name="pmp2fa_settings[email_from_email]" id="pmp2fa-from-email" value="<?php echo esc_attr( $s['email_from_email'] ); ?>" class="regular-text"></td>
            </tr>
          </table>
          <p>
            <button type="button" id="pmp2fa-test-email" class="button"><?php esc_html_e( '📧 Send Test Email', 'pmp-2fa-authentication' ); ?></button>
            <span id="pmp2fa-email-result" style="margin-left:8px;font-size:13px;"></span>
          </p>
        </div>

        <!-- SMS -->
        <div class="pmp2fa-box" id="pmp2fa-sms-box">
          <h2>📱 <?php esc_html_e( 'SMS Settings (Twilio)', 'pmp-2fa-authentication' ); ?></h2>
          <?php if ( in_array( $s['method'], array( 'sms', 'both' ), true ) && ! pmp2fa_sms_configured() ) : ?>
          <div class="notice notice-warning inline" style="margin:0 0 12px;"><p>
            <strong><?php esc_html_e( 'Twilio credentials required.', 'pmp-2fa-authentication' ); ?></strong>
            <?php esc_html_e( 'Enter your Account SID, Auth Token, and From Number below, then save. Until configured, all OTPs will be sent via email.', 'pmp-2fa-authentication' ); ?>
          </p></div>
          <?php endif; ?>
          <p class="description">
            <?php
            // translators: %s: Twilio website URL
            $pmp2fa_twilio_msg = __( 'Requires a <a href="%s" target="_blank">Twilio</a> account. Users must save their phone number in their profile.', 'pmp-2fa-authentication' );
            printf( wp_kses( $pmp2fa_twilio_msg, array( 'a' => array( 'href' => array(), 'target' => array() ) ) ), 'https://www.twilio.com/' );
            ?>
          </p>
          <table class="form-table">
            <tr>
              <th><label for="pmp2fa-twilio-sid"><?php esc_html_e( 'Account SID', 'pmp-2fa-authentication' ); ?></label></th>
              <td><input type="text" name="pmp2fa_settings[twilio_sid]" id="pmp2fa-twilio-sid" value="<?php echo esc_attr( $s['twilio_sid'] ); ?>" class="regular-text" placeholder="ACxxxx"></td>
            </tr>
            <tr>
              <th><label for="pmp2fa-twilio-token"><?php esc_html_e( 'Auth Token', 'pmp-2fa-authentication' ); ?></label></th>
              <td><input type="password" name="pmp2fa_settings[twilio_token]" id="pmp2fa-twilio-token" value="<?php echo esc_attr( $s['twilio_token'] ); ?>" class="regular-text"></td>
            </tr>
            <tr>
              <th><label for="pmp2fa-twilio-from"><?php esc_html_e( 'From Number', 'pmp-2fa-authentication' ); ?></label></th>
              <td>
                <input type="tel" name="pmp2fa_settings[twilio_from]" id="pmp2fa-twilio-from" value="<?php echo esc_attr( $s['twilio_from'] ); ?>" class="regular-text" placeholder="+12223334444">
                <p class="description"><?php esc_html_e( 'E.164 format, e.g. +12223334444', 'pmp-2fa-authentication' ); ?></p>
              </td>
            </tr>
          </table>
          <p>
            <button type="button" id="pmp2fa-test-sms" class="button"><?php esc_html_e( '📱 Send Test SMS', 'pmp-2fa-authentication' ); ?></button>
            <span id="pmp2fa-sms-result" style="margin-left:8px;font-size:13px;"></span>
          </p>
        </div>

        <?php submit_button( __( 'Save Settings', 'pmp-2fa-authentication' ) ); ?>
      </form>
    </div>

    <aside class="pmp2fa-admin-sidebar">
      <div class="pmp2fa-box">
        <h3>🛡 <?php esc_html_e( 'Plugin Info', 'pmp-2fa-authentication' ); ?></h3>
        <p><strong><?php esc_html_e( 'Version:', 'pmp-2fa-authentication' ); ?></strong> <?php echo esc_html( PMP2FA_VERSION ); ?></p>
        <p><strong><?php esc_html_e( 'Author:', 'pmp-2fa-authentication' ); ?></strong> <a href="https://linktr.ee/fahadkhalid" target="_blank" rel="noopener noreferrer">Fahad Khalid</a></p>
        <p><strong><?php esc_html_e( 'GitHub:', 'pmp-2fa-authentication' ); ?></strong> <a href="https://github.com/fahadkhalid211/" target="_blank" rel="noopener noreferrer">fahadkhalid211</a></p>
      </div>
      <div class="pmp2fa-box">
        <h3>🔧 <?php esc_html_e( 'Revoke Trusted Devices', 'pmp-2fa-authentication' ); ?></h3>

        <p class="description" style="margin-bottom:10px;"><?php esc_html_e( 'Revoke your own trusted devices — you will need to verify via OTP on your next login.', 'pmp-2fa-authentication' ); ?></p>
        <button type="button" id="pmp2fa-revoke" class="button button-secondary"><?php esc_html_e( 'Revoke My Devices', 'pmp-2fa-authentication' ); ?></button>
        <span id="pmp2fa-revoke-result" style="display:block;margin-top:6px;font-size:13px;"></span>

        <hr style="margin:16px 0;">

        <p class="description" style="margin-bottom:10px;"><strong><?php esc_html_e( 'Revoke for a specific user:', 'pmp-2fa-authentication' ); ?></strong><br>
        <?php esc_html_e( 'Enter a username, email, or user ID to revoke their trusted devices.', 'pmp-2fa-authentication' ); ?></p>
        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
          <input type="text" id="pmp2fa-revoke-user-input" class="regular-text" placeholder="<?php esc_attr_e( 'Username, email, or user ID', 'pmp-2fa-authentication' ); ?>" style="flex:1;min-width:200px;">
          <button type="button" id="pmp2fa-revoke-user" class="button button-secondary"><?php esc_html_e( 'Revoke', 'pmp-2fa-authentication' ); ?></button>
        </div>
        <span id="pmp2fa-revoke-user-result" style="display:block;margin-top:6px;font-size:13px;"></span>
      </div>
      <div class="pmp2fa-box">
        <h3>💡 <?php esc_html_e( 'Tips', 'pmp-2fa-authentication' ); ?></h3>
        <ul style="list-style:disc;padding-left:18px;font-size:13px;color:#555;line-height:1.8;">
          <li><?php esc_html_e( 'Works with PMP frontend login form.', 'pmp-2fa-authentication' ); ?></li>
          <li><?php esc_html_e( 'OTPs are hashed — never stored plain.', 'pmp-2fa-authentication' ); ?></li>
          <li><?php esc_html_e( 'For SMS, users must add their phone in their profile.', 'pmp-2fa-authentication' ); ?></li>
        </ul>
      </div>
    </aside>
  </div>
</div>
