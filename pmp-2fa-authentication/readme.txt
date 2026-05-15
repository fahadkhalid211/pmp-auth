=== PMP 2FA Authentication ===
Contributors: fahadkhalid
Tags: two-factor authentication, 2fa, otp, paid memberships pro, login security, sms, twilio, email verification, membership, authentication
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.0
Stable tag: 3.0.0
License: CodeCanyon Regular / Extended License
License URI: https://codecanyon.net/licenses/terms/regular

Adds Two-Factor Authentication (Email OTP & SMS via Twilio) to Paid Memberships Pro. Works with PMP frontend shortcode login forms and wp-login.php.

== Description ==

= 🛡️ Enterprise-Grade Security for Your Membership Site =

PMP 2FA Authentication adds a mandatory two-factor authentication step to all logins on your Paid Memberships Pro site. After entering valid credentials, users receive a one-time password (OTP) via email or SMS and must verify it before access is granted.

**Premium Features:**
* ✨ **Modern Minimal UI** - Elegant modal popup with smooth animations and professional design
* 📧 **Email OTP** - Branded HTML emails sent via WordPress wp_mail
* 📱 **SMS OTP via Twilio** - Deliver codes to any phone number worldwide
* 🔄 **Dual Method Support** - Let users choose between email and SMS at login
* 💾 **Trusted Device** - "Remember this device for N days" with secure cookie
* 🔒 **Brute-Force Protection** - Max 5 incorrect attempts per OTP, then lockout
* ⏱️ **Rate Limiting** - Configurable max OTP requests per hour per user
* 🔐 **Secure OTP Storage** - Hashed with wp_hash(), never stored in plain text
* 🎨 **Mobile Responsive** - Perfect display on all devices
* ♿ **Accessibility Ready** - WCAG 2.1 compliant with keyboard navigation
* 🌍 **Translation Ready** - Full internationalization support

= Why Choose PMP 2FA Authentication? =

In today's digital landscape, password-only authentication is no longer sufficient. Protect your membership site with enterprise-grade two-factor authentication that's both secure and user-friendly.

**Key Benefits:**
- Prevent unauthorized access even if passwords are compromised
- Reduce chargebacks and fraudulent memberships
- Build trust with your members by showing you take security seriously
- Comply with security best practices and regulations
- Seamless integration with Paid Memberships Pro

= Requirements =

* WordPress 5.0 or higher
* PHP 7.0 or higher
* Paid Memberships Pro (free version is sufficient)
* For SMS: A Twilio account with a valid phone number

= What's New in Version 3.0.0 =

* ✨ Complete UI redesign with modern minimal aesthetic
* ✨ SVG icons throughout the interface
* ✨ Smoother animations and micro-interactions
* ✨ Enhanced mobile responsiveness
* ✨ Improved accessibility features
* 🎨 Better focus states for keyboard navigation
* 🔧 Fixed minor CSS compatibility issues

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins → Add New**
3. Click **Upload Plugin** at the top
4. Choose the `pmp-2fa-authentication.zip` file
5. Click **Install Now**
6. After installation, click **Activate**

= Manual Installation =

1. Download the `pmp-2fa-authentication.zip` file from CodeCanyon
2. Extract the ZIP file on your computer
3. Upload the `pmp-2fa-authentication` folder to `/wp-content/plugins/`
4. Log in to WordPress admin
5. Go to **Plugins → Installed Plugins**
6. Find "PMP 2FA Authentication" and click **Activate**

= Post-Installation =

After activation:
1. Go to **Settings → PMP 2FA Auth**
2. Configure your preferred 2FA method
3. Save settings — 2FA is now active!

== Frequently Asked Questions ==

= Does this work with the PMP frontend login shortcode? =

Yes! The plugin intercepts authentication at the WordPress core level and works with all login methods including PMP's [pmpro_login] shortcode.

= Is the Twilio API key stored securely? =

Yes. Keys are stored in the WordPress options table using standard WordPress sanitization functions. They are never exposed in frontend code.

= What happens if a user doesn't have a phone number saved? =

If SMS is selected but no phone number is found on the user's profile, the plugin falls back to Email OTP automatically.

= How do I revoke trusted devices? =

Go to Settings > PMP 2FA Auth > Tools and click "Revoke My Trusted Devices". Administrators can also revoke devices for specific users.

= Can users disable 2FA for themselves? =

No. 2FA is enforced site-wide by the administrator. This ensures consistent security across all user accounts.

= Does this work with other membership plugins? =

This plugin is specifically designed for Paid Memberships Pro. While it may work with other plugins, compatibility is not guaranteed.

= What license do I need? =

- **Regular License**: For single end product (one website)
- **Extended License**: For multiple end products (client work)

Purchase the appropriate license for your needs on CodeCanyon.

== Screenshots ==

1. Modern minimal 2FA modal with elegant design
2. Email and SMS method selection tabs
3. Admin settings panel with test functionality
4. Mobile responsive design on smartphones
5. Trusted device option for seamless logins
6. Real-time countdown timer for code resend

== Changelog ==

= 3.0.0 =
* ✨ New: Modern minimal UI redesign with elegant animations
* ✨ New: SVG icons throughout the interface
* ✨ New: Improved mobile responsiveness
* ✨ New: Enhanced accessibility (WCAG 2.1 compliant)
* 🎨 Improved: Smoother transitions and micro-interactions
* 🎨 Improved: Better focus states for keyboard navigation
* 🔧 Fixed: Minor CSS compatibility issues with some themes
* 🔧 Fixed: Countdown timer display on mobile devices

= 2.0.0 =
* Complete rewrite for reliability
* Fixed compatibility with PMP frontend login shortcode
* OTP now sent after WordPress fully boots to ensure wp_mail reliability
* Switched to transient + cookie state management (no PHP sessions)
* Full standalone 2FA page rendered via template_redirect

= 1.0.0 =
* Initial release
* Email OTP support
* SMS OTP via Twilio
* Basic admin settings panel

== Upgrade Notice ==

= 3.0.0 =
Major UI update with modern minimal design. All existing functionality preserved. Recommended update for improved user experience.

= 2.0.0 =
Complete rewrite. Delete old version before installing.

== Additional Information ==

= Support =

We provide dedicated support for our CodeCanyon customers:
- Response time: 24-48 hours
- Support forum: CodeCanyon item comments
- Documentation: Comprehensive guide included

= Credits =

* Author: Fahad Khalid
* Plugin URI: https://codecanyon.net/user/fahadkhalid/portfolio
* Author URI: https://linktr.ee/fahadkhalid211

= License =

This plugin is sold exclusively on CodeCanyon under Envato's licensing terms.
Resale, redistribution, or sublicensing is strictly prohibited without a valid Extended License.

For full license terms, visit: https://codecanyon.net/licenses/terms/regular
