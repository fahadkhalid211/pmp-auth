# PMP 2FA Authentication - Installation Guide

## Quick Start (5 Minutes)

### Step 1: Download the Plugin
1. Log in to your CodeCanyon account
2. Go to your **Downloads** page
3. Find "PMP 2FA Authentication"
4. Click **Download** and choose **All Files & Documentation**
5. Extract the ZIP file on your computer

### Step 2: Install WordPress Plugin
1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins → Add New**
3. Click **Upload Plugin** button at the top
4. Click **Choose File** and select `pmp-2fa-authentication.zip`
5. Click **Install Now**
6. After installation completes, click **Activate Plugin**

### Step 3: Configure Basic Settings
1. Go to **Settings → PMP 2FA Auth**
2. Choose your 2FA method:
   - **Email OTP only** (recommended for getting started)
   - **SMS only** (requires Twilio setup)
   - **Both** (users choose at login)
3. Adjust OTP settings if needed:
   - OTP Length: 6 digits (recommended)
   - OTP Expiry: 10 minutes (recommended)
4. Click **Save Settings**

### Step 4: Test the Plugin
1. Open a new browser window (or incognito mode)
2. Go to your WordPress login page
3. Enter valid username and password
4. You should see the 2FA modal popup
5. Check your email for the verification code
6. Enter the code and verify

**Congratulations! Your plugin is now active.** 🎉

---

## Detailed Setup

### Email Configuration (Recommended First Step)

The plugin uses WordPress's built-in `wp_mail()` function. For reliable email delivery:

#### Option A: Use Your Hosting Email (Basic)
- Works out of the box on most hosting
- May have deliverability issues with some providers
- Emails might go to spam folder

#### Option B: Configure SMTP (Recommended)
For better deliverability, install an SMTP plugin:

1. **Install WP Mail SMTP** (free):
   - Go to Plugins → Add New
   - Search for "WP Mail SMTP"
   - Install and activate

2. **Configure SMTP**:
   - Go to WP Mail SMTP → Settings
   - Choose your email provider (Gmail, SendGrid, etc.)
   - Follow the setup wizard
   - Send a test email to verify

3. **Test PMP 2FA Email**:
   - Go to Settings → PMP 2FA Auth
   - Click **Send Test Email**
   - Check your inbox

### SMS Configuration (Optional)

To enable SMS OTP via Twilio:

#### Step 1: Create Twilio Account
1. Visit [https://www.twilio.com](https://www.twilio.com)
2. Click **Sign Up**
3. Complete account verification
4. Get $15 free trial credit

#### Step 2: Get Twilio Credentials
1. Log in to Twilio Console
2. Go to **Dashboard** or **Account Settings**
3. Copy your **Account SID** (starts with AC...)
4. Click **Show** to reveal **Auth Token**
5. Copy the Auth Token

#### Step 3: Buy a Phone Number
1. Go to **Phone Numbers → Buy a Number**
2. Search for available numbers
3. Choose one with **SMS capability**
4. Complete purchase (~$1/month)
5. Copy the number in E.164 format (e.g., +12223334444)

#### Step 4: Configure in WordPress
1. Go to **Settings → PMP 2FA Auth**
2. Scroll to **SMS Settings (Twilio)**
3. Enter:
   - Account SID
   - Auth Token
   - From Number
4. Click **Save Settings**
5. Click **Send Test SMS** to verify

#### Step 5: Enable SMS for Users
Users need to add their phone number:
1. Users go to **Profile → Edit Profile**
2. Add phone number in E.164 format (+country code)
3. Save profile

---

## Advanced Configuration

### Trusted Device Settings

Allow users to skip 2FA on trusted devices:

1. Go to **Settings → PMP 2FA Auth**
2. Under **Trust Device**, check the box
3. Set trust duration (default: 30 days)
4. Click **Save Settings**

**Note:** Trust is stored in browser cookies. Clearing cookies resets trust.

### Security Settings

#### OTP Length
- **4-5 digits**: Less secure, easier to enter
- **6 digits**: Recommended balance (default)
- **7-8 digits**: Maximum security, harder to enter

#### OTP Expiry
- **1-5 minutes**: High security, may expire before user enters
- **10 minutes**: Recommended (default)
- **15-60 minutes**: Convenient but less secure

#### Rate Limiting
Prevents abuse by limiting OTP requests:
- **Default**: 5 requests per hour
- **Increase** if users complain about not getting codes
- **Decrease** if you're concerned about SMS costs

### Customization

#### Change Colors
Add to your theme's **Additional CSS**:

```css
/* Change primary button gradient */
.pmp2fa-btn-primary {
    background: linear-gradient(135deg, #your-color-1, #your-color-2) !important;
}

/* Change icon background */
.pmp2fa-modal__icon {
    background: linear-gradient(135deg, #your-color, #your-color-2);
}
```

#### Force Custom Logo
Add to your theme's `functions.php`:

```php
add_filter('pmp2fa_logo_url', 'custom_pmp2fa_logo');
function custom_pmp2fa_logo($url) {
    return get_stylesheet_directory_uri() . '/images/my-logo.png';
}
```

---

## User Management

### Revoke Trusted Devices

#### For Yourself:
1. Go to **Settings → PMP 2FA Auth**
2. In sidebar, click **Revoke My Devices**
3. Next login will require 2FA

#### For Another User:
1. Go to **Settings → PMP 2FA Auth**
2. In sidebar, enter username, email, or user ID
3. Click **Revoke**
4. That user will need 2FA on next login

### Bulk Revoke (Database Method)

To revoke all trusted devices:

```sql
DELETE FROM wp_usermeta WHERE meta_key = '_pmp2fa_trusted';
```

**Warning:** This revokes trust for ALL users. Use with caution!

---

## Troubleshooting

### Email Not Sending

**Symptoms:** Users don't receive OTP emails

**Solutions:**
1. Test with **Send Test Email** button
2. Check spam/junk folder
3. Install SMTP plugin (WP Mail SMTP)
4. Verify From Email is valid
5. Check hosting email limits

### SMS Not Sending

**Symptoms:** Users don't receive OTP SMS

**Solutions:**
1. Verify Twilio credentials are correct
2. Check Twilio dashboard for errors
3. Ensure phone number has SMS capability
4. Verify user phone number format (+country code)
5. Check Twilio account balance/credit

### Modal Not Appearing

**Symptoms:** Login works without 2FA prompt

**Solutions:**
1. Verify Paid Memberships Pro is active
2. Check browser console for JavaScript errors (F12)
3. Temporarily disable other plugins to test conflicts
4. Switch to default WordPress theme to test
5. Clear all caching (browser, plugin, server)

### "Invalid Code" Errors

**Symptoms:** Valid codes are rejected

**Solutions:**
1. Increase OTP expiry time
2. Check server time is synchronized
3. Verify no caching on login pages
4. Ask user to request new code
5. Check rate limit isn't too restrictive

### Trusted Device Not Working

**Symptoms:** Users prompted for 2FA every time

**Solutions:**
1. Verify "Trust Device" option is enabled
2. User must check the checkbox during login
3. Browser must accept cookies
4. No private/incognito mode (cookies deleted on close)
5. Check trust duration setting

---

## Best Practices

### Security Recommendations

1. ✅ Use 6-digit OTP minimum
2. ✅ Set OTP expiry to 10 minutes or less
3. ✅ Enable rate limiting (5/hour)
4. ✅ Use SMTP for reliable email delivery
5. ✅ Regularly update the plugin
6. ✅ Monitor Twilio usage and costs
7. ✅ Educate users about 2FA importance

### User Experience Tips

1. ✅ Provide clear instructions to users
2. ✅ Offer both email and SMS options
3. ✅ Enable trusted device for convenience
4. ✅ Test the flow yourself before launching
5. ✅ Have support ready for first week
6. ✅ Create FAQ for common questions

### Performance Optimization

1. ✅ Use object caching if available
2. ✅ Minimize customizations that add queries
3. ✅ Keep rate limits reasonable
4. ✅ Monitor server logs for errors
5. ✅ Use CDN for static assets

---

## Getting Help

### Before Contacting Support

Please check:
- ✅ This installation guide thoroughly
- ✅ WordPress version meets requirements (5.0+)
- ✅ PHP version meets requirements (7.0+)
- ✅ Paid Memberships Pro is installed and active
- ✅ No JavaScript errors in browser console
- ✅ Tested with default WordPress theme

### What to Include in Support Request

To help us assist you faster:

1. **WordPress version**: (e.g., 6.4.2)
2. **PHP version**: (e.g., 8.1)
3. **Plugin version**: (e.g., 3.0.0)
4. **Active theme**: (name and version)
5. **List of active plugins**: (screenshot or list)
6. **Error messages**: (exact text from console or logs)
7. **Steps to reproduce**: (detailed description)
8. **Screenshots**: (if applicable)

### Support Channels

- **CodeCanyon Comments**: Post public questions
- **Email**: support@yourdomain.com (for customers)
- **Documentation**: Refer to full documentation

**Response Time:** 24-48 hours on business days

---

## Next Steps

After successful installation:

1. ✅ Test with different user accounts
2. ✅ Test on mobile devices
3. ✅ Create user guide for your members
4. ✅ Announce 2FA rollout to users
5. ✅ Monitor for any issues in first week
6. ✅ Collect user feedback
7. ✅ Consider leaving a review on CodeCanyon ⭐

---

**Need more help?** Check the full documentation included in the download package.

**Enjoying the plugin?** Please consider leaving a review on CodeCanyon to help others discover it!
