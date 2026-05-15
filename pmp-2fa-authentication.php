<?php
/**
 * Plugin Name:       PMP 2FA Authentication
 * Plugin URI:        https://github.com/fahadkhalid211/
 * Description:       Two-Factor Authentication (Email OTP & SMS via Twilio) for Paid Memberships Pro. Works with PMP frontend shortcode login forms and wp-login.php.
 * Version:           2.0.0
 * Requires at least: 5.0
 * Requires PHP:      5.6
 * Author:            Fahad Khalid
 * Author URI:        https://linktr.ee/fahadkhalid211
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pmp-2fa-authentication
 * Domain Path:       /languages
 *
 * @package PMP_2FA_Authentication
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PMP2FA_VERSION',     '2.0.0' );
define( 'PMP2FA_PLUGIN_FILE', __FILE__ );
define( 'PMP2FA_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'PMP2FA_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'PMP2FA_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// Load all includes — procedural only, no classes.
require_once PMP2FA_PLUGIN_DIR . 'includes/helpers.php';
require_once PMP2FA_PLUGIN_DIR . 'includes/otp.php';
require_once PMP2FA_PLUGIN_DIR . 'includes/email.php';
require_once PMP2FA_PLUGIN_DIR . 'includes/sms.php';
require_once PMP2FA_PLUGIN_DIR . 'includes/hooks.php';
require_once PMP2FA_PLUGIN_DIR . 'admin/settings.php';

// Boot on plugins_loaded so PMP functions are available.
function pmp2fa_boot() {
	pmp2fa_register_hooks();
	pmp2fa_admin_init();
}
add_action( 'plugins_loaded', 'pmp2fa_boot' );

// Activation defaults.
register_activation_hook( __FILE__, 'pmp2fa_activate' );
function pmp2fa_activate() {
	if ( ! get_option( 'pmp2fa_settings' ) ) {
		update_option( 'pmp2fa_settings', array(
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
		) );
	}
}
