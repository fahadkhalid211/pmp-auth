# Changelog - PMP 2FA Authentication

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2025-01-15

### ✨ Added
- Modern minimal UI redesign with elegant animations
- SVG icons throughout the interface for better visual clarity
- Enhanced mobile responsiveness for all screen sizes
- Accessibility improvements (WCAG 2.1 compliant)
- Smooth gradient backgrounds for notices and buttons
- Micro-interactions for better user feedback
- Keyboard navigation support with visible focus states
- Reduced motion support for users who prefer it

### 🎨 Changed
- Updated color scheme to modern purple gradient (#667eea to #764ba2)
- Improved modal card shadows for better depth perception
- Enhanced button hover effects with shimmer animation
- Refined typography and spacing throughout
- Better contrast ratios for improved readability
- Optimized animations with cubic-bezier timing functions

### 🔧 Fixed
- Minor CSS compatibility issues with some WordPress themes
- Countdown timer display on mobile devices
- Focus state visibility for accessibility
- Tab switching animation glitches
- Input field placeholder alignment

### 📦 Technical
- No breaking changes to existing functionality
- All existing hooks and filters preserved
- Backward compatible with previous versions

## [2.0.0] - 2024-06-20

### ✨ Added
- Complete plugin rewrite for improved reliability
- Full standalone 2FA page rendered via template_redirect
- Transient + cookie state management (replaced PHP sessions)
- Better error handling and user feedback

### 🔧 Fixed
- Compatibility with PMP frontend login shortcode `[pmpro_login]`
- OTP sending reliability by waiting for WordPress full boot
- wp_mail() reliability issues
- Session management conflicts with other plugins

### 🔄 Changed
- Switched from PHP sessions to WordPress transients
- Improved code structure and organization
- Enhanced security measures

## [1.0.0] - 2024-01-10

### ✨ Added
- Initial release
- Email OTP support with customizable templates
- SMS OTP via Twilio integration
- Basic admin settings panel
- Trusted device functionality
- Rate limiting protection
- Brute-force protection
- Test email and SMS functionality
- User-specific trusted device revocation

---

## Version Guidelines

### Version Numbers
- **Major** (X.0.0): Breaking changes or major new features
- **Minor** (1.X.0): New features, backward compatible
- **Patch** (1.0.X): Bug fixes and minor improvements

### Release Process
1. Update version number in main plugin file
2. Update version in documentation
3. Add changelog entry
4. Test thoroughly on clean WordPress installation
5. Create release ZIP package
6. Submit to CodeCanyon for review

---

## Support Timeline

- **Version 3.x**: Active support and updates
- **Version 2.x**: Security updates only
- **Version 1.x**: End of life (no longer supported)

Users are encouraged to update to the latest version for best security and features.
