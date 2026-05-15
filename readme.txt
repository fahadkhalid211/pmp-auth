=== PMP 2FA Authentication ===
Contributors: fahadkhalid
Tags: two-factor authentication, 2fa, otp, paid memberships pro, login security
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 5.6
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds Two-Factor Authentication (Email OTP & SMS via Twilio) to Paid Memberships Pro. Works with PMP frontend shortcode login forms.

== Description ==

PMP 2FA Authentication adds a mandatory two-factor authentication step to all logins on your Paid Memberships Pro site. After entering valid credentials, users receive a one-time password (OTP) via email or SMS and must verify it before access is granted.

= Features =

* Email OTP — branded HTML emails sent via WordPress wp_mail
* SMS OTP via Twilio — deliver codes to any phone number worldwide
* Both methods — let users choose between email and SMS at login
* Trusted device — "Remember this device for N days" with secure cookie
* Brute-force protection — max 5 incorrect attempts per OTP, then lockout
* Rate limiting — configurable max OTP requests per hour per user
* Secure OTP storage — hashed with wp_hash(), never stored in plain text
* Works with PMP frontend login shortcode AND wp-login.php
* Full admin settings panel with test email and test SMS buttons
* Translation ready

= Requirements =

* WordPress 5.0 or higher
* PHP 5.6 or higher
* Paid Memberships Pro (free version is sufficient)
* For SMS: A Twilio account with a valid phone number

== Installation ==

1. Upload the `pmp-2fa-authentication` folder to `/wp-content/plugins/`
2. Activate the plugin via Plugins > Installed Plugins
3. Go to Settings > PMP 2FA Auth
4. Choose your preferred 2FA method (Email, SMS, or Both)
5. If using SMS, enter your Twilio credentials
6. Save settings — 2FA is now active on all logins

== Frequently Asked Questions ==

= Does this work with the PMP frontend login shortcode? =
Yes. The plugin intercepts authentication at the WordPress core level and works with all login methods including PMP's [pmpro_login] shortcode.

= Is the Twilio API key stored securely? =
Keys are stored in the WordPress options table using standard WordPress sanitization functions.

= What happens if a user doesn't have a phone number saved? =
If SMS is selected but no phone number is found on the user's profile, the plugin falls back to Email OTP.

= How do I revoke trusted devices? =
Go to Settings > PMP 2FA Auth > Tools and click "Revoke My Trusted Devices".

== Changelog ==

= 2.0.0 =
* Complete rewrite for reliability
* Fixed compatibility with PMP frontend login shortcode
* OTP now sent after WordPress fully boots to ensure wp_mail reliability
* Switched to transient + cookie state management (no PHP sessions)
* Full standalone 2FA page rendered via template_redirect

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 2.0.0 =
Complete rewrite. Delete old version before installing.
