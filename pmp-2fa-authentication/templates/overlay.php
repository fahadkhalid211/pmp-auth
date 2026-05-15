<?php // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Frontend overlay template.
 * Injected via wp_footer. Variables: $method, $masked, $show_tabs,
 * $remember, $expiry, $otp_len, $trust_days.
 *
 * @package PMP2FA_Auth
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$nonce = wp_create_nonce( 'pmp2fa_nonce' );
$ph    = str_repeat( '-', $otp_len );
?>
<div id="pmp2fa-overlay" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Two-Factor Authentication', 'pmp-2fa-authentication' ); ?>">
  <div class="pmp2fa-backdrop"></div>
  <div class="pmp2fa-card">

    <div class="pmp2fa-head">
      <div class="pmp2fa-icon" aria-hidden="true">🔐</div>
      <h2 class="pmp2fa-title"><?php esc_html_e( 'Verify Your Identity', 'pmp-2fa-authentication' ); ?></h2>
      <p class="pmp2fa-sub">
        <?php /* translators: %s: masked email or phone */ printf( esc_html__( 'A verification code was sent to %s', 'pmp-2fa-authentication' ), '<strong id="pmp2fa-dest">' . esc_html( $masked ) . '</strong>' ); ?>
      </p>
    </div>

    <div id="pmp2fa-notice" class="pmp2fa-notice" style="display:none;" role="alert" aria-live="polite"></div>

    <?php if ( $show_tabs ) : ?>
    <div class="pmp2fa-tabs" role="tablist">
      <button type="button" class="pmp2fa-tab <?php echo ( 'email' === $method ) ? 'active' : ''; ?>" data-method="email" role="tab">
        &#9993; <?php esc_html_e( 'Email', 'pmp-2fa-authentication' ); ?>
      </button>
      <button type="button" class="pmp2fa-tab <?php echo ( 'sms' === $method ) ? 'active' : ''; ?>" data-method="sms" role="tab">
        &#128241; <?php esc_html_e( 'SMS', 'pmp-2fa-authentication' ); ?>
      </button>
    </div>
    <?php endif; ?>

    <form id="pmp2fa-form" novalidate autocomplete="off">
      <input type="hidden" id="pmp2fa-nonce" value="<?php echo esc_attr( $nonce ); ?>">

      <div class="pmp2fa-field">
        <label for="pmp2fa-input" class="pmp2fa-label"><?php esc_html_e( 'Verification Code', 'pmp-2fa-authentication' ); ?></label>
        <input
          type="text"
          id="pmp2fa-input"
          class="pmp2fa-input"
          inputmode="numeric"
          autocomplete="one-time-code"
          maxlength="<?php echo esc_attr( $otp_len ); ?>"
          placeholder="<?php echo esc_attr( $ph ); ?>"
          autofocus
          required
        >
        <p class="pmp2fa-hint">
          <?php /* translators: %d: number of minutes */ printf( esc_html( _n( 'Expires in %d minute.', 'Expires in %d minutes.', $expiry, 'pmp-2fa-authentication' ) ), absint( $expiry ) ); ?>
        </p>
      </div>

      <?php if ( $remember ) : ?>
      <label class="pmp2fa-check-wrap">
        <input type="checkbox" id="pmp2fa-trust" name="trust" value="1">
        <span class="pmp2fa-checkmark"></span>
        <?php /* translators: %d: number of days */ printf( esc_html( _n( 'Trust this device for %d day', 'Trust this device for %d days', $trust_days, 'pmp-2fa-authentication' ) ), absint( $trust_days ) ); ?>
      </label>
      <?php endif; ?>

      <button type="submit" id="pmp2fa-submit" class="pmp2fa-btn">
        <span class="pmp2fa-btn-txt"><?php esc_html_e( 'Verify Code', 'pmp-2fa-authentication' ); ?></span>
        <span class="pmp2fa-spinner" aria-hidden="true"></span>
      </button>
    </form>

    <div class="pmp2fa-bottom">
      <button type="button" id="pmp2fa-resend" class="pmp2fa-link" disabled>
        <?php esc_html_e( 'Resend Code', 'pmp-2fa-authentication' ); ?><span id="pmp2fa-cd"></span>
      </button>
    </div>
    <div class="pmp2fa-bottom">
      <a href="<?php echo esc_url( wp_login_url() ); ?>" id="pmp2fa-cancel" class="pmp2fa-cancel">
        &larr; <?php esc_html_e( 'Cancel &amp; back to login', 'pmp-2fa-authentication' ); ?>
      </a>
    </div>

  </div><!-- /.pmp2fa-card -->
</div><!-- /#pmp2fa-overlay -->
