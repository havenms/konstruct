# Troubleshooting Email Notifications Feature

If you're not seeing the email notification changes in your WordPress admin, here are some steps to troubleshoot:

## 1. Clear Browser Cache
- **Hard refresh**: Press `Ctrl+F5` (Windows) or `Cmd+Shift+R` (Mac)
- **Clear browser cache**: Go to browser settings and clear cache/cookies for your site
- **Try incognito/private browsing mode**

## 2. Check Plugin Status
1. Go to **Plugins** in WordPress admin
2. Make sure "Form Builder Microsaas" is activated
3. If it's activated, try deactivating and reactivating it

## 3. Check for JavaScript Errors
1. Open browser Developer Tools (F12)
2. Go to the **Console** tab
3. Navigate to **Form Builder** → **Add New** 
4. Look for any JavaScript errors (red text)
5. You should see: `Form Builder: JavaScript loaded - Version 1.1.0`

## 4. Verify File Updates
Check that these files have been updated with the new code:

### `/includes/class-email-handler.php`
- This file should exist and contain the `Form_Builder_Email_Handler` class
- Size should be around 10KB

### `/admin/builder.js`
- Should contain console.log statements like "Form Builder: JavaScript loaded - Version 1.1.0"
- Should have tab functionality and email notification settings

### `/admin/builder.css`
- Should contain CSS for `.form-builder-tabs`, `.tab-nav`, `.tab-link`, etc.

### `/form-builder-plugin.php`
- Version should be `1.1.0`
- Should load `class-email-handler.php`

## 5. Check WordPress Debug Mode
Add these lines to `wp-config.php` to enable debug mode:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Then check `/wp-content/debug.log` for any PHP errors.

## 6. Manual Verification Steps

### Step 1: Check Plugin Version
In WordPress admin, go to **Plugins** and verify the Form Builder plugin shows version 1.1.0.

### Step 2: Check Form Builder Page
1. Go to **Form Builder** → **Add New**
2. You should see three tabs in the right sidebar: "Webhook", "Email Notifications", "Custom JS"
3. Click on "Email Notifications" tab
4. You should see options for step and submission notifications

### Step 3: Test JavaScript Console
Open browser console and look for these messages:
- `Form Builder: JavaScript loaded - Version 1.1.0`
- `Form Builder: Initializing...`
- `Form Builder: Checking notifications...`

## 7. Alternative Activation Method

If the above doesn't work, try this:

1. **Deactivate** the plugin
2. **Delete** the plugin folder from `/wp-content/plugins/`
3. **Re-upload** the entire plugin folder
4. **Activate** the plugin again

## 8. Check File Permissions
Ensure the web server can read the updated files:
- Files should have 644 permissions
- Folders should have 755 permissions

## 9. WordPress Cache
If you're using a caching plugin:
1. Clear all caches (WP Rocket, W3 Total Cache, etc.)
2. Temporarily disable caching plugins
3. Test the form builder again

## 10. Test Email Functionality
Once you can see the email notification settings:

1. Configure a test form with email notifications
2. Use the "Send Test Email" button in the Email Notifications tab
3. Check your email and WordPress debug logs

## Expected Behavior

When working correctly, you should see:

1. **Three tabs** in the form builder sidebar: Webhook, Email Notifications, Custom JS
2. **Email configuration options** including:
   - Step completion notifications toggle
   - Submission notifications toggle
   - Recipients field
   - Subject and message templates
   - Test email functionality
3. **Console messages** confirming JavaScript is loaded
4. **Version 1.1.0** in plugin list

## Contact Information

If none of these steps resolve the issue, the problem might be:
- Server-side PHP errors preventing the plugin from loading
- File upload issues (files not actually updated)
- WordPress version compatibility
- Theme/plugin conflicts

Check the browser console and WordPress debug logs for specific error messages.