# Email Notifications Feature

This document describes the new email notification functionality added to the Konstruct Form Builder plugin.

## Features

The Konstruct Form Builder now supports automatic email notifications for:

1. **Step Completion Notifications** - Sent when a user completes any step/page of a form
2. **Final Submission Notifications** - Sent when a user completes the entire form

## Configuration

Email notifications are configured in the Konstruct Form Builder admin interface:

### Accessing Email Settings

1. Go to **Konstruct Form Builder** > **Add New** or edit an existing form
2. In the page settings panel on the right, click the **Email Notifications** tab
3. Configure your notification settings

### Step Completion Notifications

Configure notifications sent after each page/step completion:

- **Enable step completion emails**: Toggle to enable/disable
- **Recipients**: Comma-separated list of email addresses (e.g., `admin@example.com, manager@example.com`)
- **Or get recipient from field**: Select an email field from your form to use as recipient
- **Include site admin email**: Include the WordPress admin email as a recipient
- **Subject**: Email subject line (supports placeholders)
- **Message**: Email body content (supports placeholders)

### Final Submission Notifications

Configure notifications sent when the entire form is completed:

- **Enable final submission emails**: Toggle to enable/disable
- **Recipients**: Comma-separated list of email addresses
- **Or get recipient from field**: Select an email field from your form to use as recipient
- **Include site admin email**: Include the WordPress admin email as a recipient
- **Subject**: Email subject line (supports placeholders)
- **Message**: Email body content (supports placeholders)

## Available Placeholders

You can use these placeholders in your email subjects and messages:

- `{{form_name}}` - The name of the form
- `{{page_number}}` - Current page number (for step notifications)
- `{{submission_uuid}}` - Unique submission identifier
- `{{date}}` - Current date and time
- `{{site_name}}` - WordPress site name
- `{{site_url}}` - WordPress site URL
- `{{admin_email}}` - WordPress admin email address
- `{{field_name}}` - Any form field value (replace `field_name` with actual field name)

### Example Usage

**Subject**: `New enquiry from {{form_name}} - {{site_name}}`

**Message**:
```
Hello,

A new enquiry has been received through {{form_name}} on {{date}}.

Customer Details:
- Name: {{customer_name}}
- Email: {{customer_email}}
- Phone: {{customer_phone}}

Please review the complete submission details.

Best regards,
{{site_name}} Team
```

## WordPress Mail Integration

The plugin uses WordPress's built-in `wp_mail()` function, which means:

- **No SendGrid dependency** - Works with any WordPress mail configuration
- **Compatible with mail plugins** - Works with WP Mail SMTP, Mailgun, etc.
- **Follows WordPress standards** - Uses WordPress hooks and filters
- **HTML emails** - Automatically formatted with basic HTML styling

## Email Template

Emails are sent with a professional HTML template that includes:

- Clean, responsive design
- Site branding (site name and URL)
- Form data summary table
- Proper HTML formatting for all content

## Technical Implementation

### Files Added/Modified

- `includes/class-email-handler.php` - New email handler class
- `includes/class-webhook-handler.php` - Modified to include email notifications
- `form-builder-plugin.php` - Updated to load email handler and add notifications to submissions
- `admin/builder.js` - Added email configuration UI
- `admin/builder.css` - Added styles for email configuration tabs
- `includes/class-form-builder.php` - Updated default form structure

### Email Triggers

1. **Step Completion**: Triggered in `Form_Builder_Webhook_Handler::process_webhook()` after successful webhook processing
2. **Final Submission**: Triggered in `Form_Builder_Microsaas::save_submission()` after successful form submission

### Data Storage

Email notification settings are stored in the form configuration JSON in the database, nested under the `notifications` key:

```json
{
  "notifications": {
    "step_notifications": {
      "enabled": false,
      "recipients": "",
      "recipient_field": "",
      "include_admin": true,
      "subject": "Form Step Completed - {{form_name}}",
      "message": "..."
    },
    "submission_notifications": {
      "enabled": false,
      "recipients": "",
      "recipient_field": "",
      "include_admin": true,
      "subject": "New Form Submission - {{form_name}}",
      "message": "..."
    }
  }
}
```

## Migration Notes

- **SendGrid Removed**: The plugin no longer depends on SendGrid or any external email service
- **Backward Compatibility**: Existing forms will work without modification - email notifications are opt-in
- **WordPress Mail**: All emails now use WordPress's native mail system

## Testing Email Configuration

The email handler includes a `test_email_config()` method that can be used to verify email functionality. This can be called programmatically or added to the admin interface for testing purposes.

## Troubleshooting

### Emails Not Being Sent

1. Check WordPress mail configuration
2. Verify notification settings are enabled in form configuration
3. Check recipient email addresses are valid
4. Test with a WordPress mail plugin like WP Mail SMTP
5. Check server mail logs for delivery issues

### Emails Going to Spam

1. Configure proper WordPress mail settings (From name, From email)
2. Use a mail service plugin (WP Mail SMTP, Mailgun, etc.)
3. Set up SPF, DKIM, and DMARC records for your domain
4. Test with different email providers

### Missing Form Data in Emails

1. Ensure placeholders match exact field names from your form
2. Check that form fields have proper `name` attributes
3. Verify form data is being saved correctly in submissions