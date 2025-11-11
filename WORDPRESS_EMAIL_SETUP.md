# WordPress Email Setup Guide

## Quick Setup for Form Builder Email Notifications

### 1. WordPress Mail Configuration

To ensure your form emails are delivered properly, configure WordPress mail settings:

#### Option A: Use WordPress Default (Recommended for Testing)
WordPress will use your server's default mail configuration. This works for most shared hosting providers.

#### Option B: SMTP Configuration (Recommended for Production)
Install an SMTP plugin like "WP Mail SMTP" or add SMTP settings to your `wp-config.php`:

```php
// Add to wp-config.php
define('SMTP_USER',   'your-email@domain.com');
define('SMTP_PASS',   'your-app-password');
define('SMTP_HOST',   'smtp.gmail.com');
define('SMTP_FROM',   'your-email@domain.com');
define('SMTP_NAME',   'Your Site Name');
define('SMTP_PORT',   '587');
define('SMTP_SECURE', 'tls');
define('SMTP_AUTH',    true);
define('SMTP_DEBUG',   false);
```

### 2. Form Builder Email Settings

#### Default Email Templates
The plugin now includes improved email templates with:

**Step Completion Email:**
```
Hello,

A step has been completed in the form "{{form_name}}".

Step {{page_number}} was completed on {{date}}.

Submission ID: {{submission_uuid}}

{{dynamic_fields}}

Best regards,
{{site_name}}
```

**Final Submission Email:**
```
Hello,

A new form submission has been received for "{{form_name}}".

Submitted on: {{date}}.

Submission ID: {{submission_uuid}}

{{dynamic_fields}}

Best regards,
{{site_name}}
```

#### Available Placeholders

**System Placeholders:**
- `{{form_name}}` - The name of your form
- `{{page_number}}` - Current step number
- `{{submission_uuid}}` - Unique submission identifier
- `{{date}}` - Current date and time
- `{{site_name}}` - Your WordPress site name
- `{{site_url}}` - Your WordPress site URL
- `{{admin_email}}` - WordPress admin email

**Dynamic Field Placeholders:**
- `{{dynamic_fields}}` - Automatically includes all form fields from the current step
- `{{field_name}}` - Individual field placeholders (e.g., `{{first_name}}`, `{{email}}`, `{{phone}}`)

### 3. Email Configuration in Form Builder

1. **Access Form Builder:** Go to WordPress Admin → Forms → Edit your form
2. **Navigate to Email Tab:** Click on "Email Notifications" tab in the page settings
3. **Configure Step Notifications:**
   - ✅ Enable step notifications
   - Add recipient emails (comma-separated)
   - Choose a form field for dynamic recipients (optional)
   - ✅ Include site administrator
   - Customize subject and message

4. **Configure Final Submission Notifications:**
   - ✅ Enable submission notifications
   - Add recipient emails
   - ✅ Include site administrator
   - Customize subject and message

### 4. Testing Email Setup

Use the built-in test functionality:

1. Go to the "Email Notifications" tab
2. Enter your test email address
3. Click "Send Test Email"
4. Check both your inbox and spam folder
5. Use "Debug Form Config" if emails aren't working

### 5. Common WordPress Email Settings

#### Site Identity Settings
Go to **Settings → General** and ensure:
- Site Title is set (used in `{{site_name}}`)
- Admin Email Address is correct
- Timezone is properly configured

#### Recommended Plugins for Better Email Delivery
- **WP Mail SMTP** - For reliable SMTP configuration
- **Mail Log** - To log and debug email sending
- **Post SMTP** - Alternative SMTP solution with detailed logging

### 6. Troubleshooting

#### Emails Not Sending?
1. Check WordPress admin email settings
2. Verify server mail configuration
3. Use the debug feature in Form Builder
4. Check server mail logs
5. Test with a simple WordPress email (like password reset)

#### Emails Going to Spam?
1. Set up proper SMTP authentication
2. Add SPF/DKIM records to your domain
3. Use a professional email address as sender
4. Avoid spam trigger words in subject lines

### 7. Advanced Configuration

#### Custom Email Templates
Edit the templates in the form builder admin interface. The system supports:
- HTML formatting (automatically applied)
- Line breaks (use actual line breaks, not `\n`)
- Dynamic field replacement
- Conditional content based on form data

#### Multiple Recipients
- Separate multiple email addresses with commas
- Use form fields to dynamically set recipients
- Combine static emails with dynamic form field emails
- Include/exclude admin email as needed

---

**Need Help?** Check the WordPress admin debug logs or contact your hosting provider for server-specific email configuration assistance.