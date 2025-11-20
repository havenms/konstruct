# Konstruct Form Builder WordPress Plugin

A standalone HTML-CSS-JS form builder that creates paginated forms with configurable per-page webhooks. All data is stored in the WordPress database.

## Features

- **Simple Form Builder**: Clean admin interface for creating forms
- **All Input Types**: Supports text, email, tel, number, textarea, select, radio, checkbox, file, date
- **Paginated Forms**: Multi-page forms with Next/Back navigation
- **Per-Page Webhooks**: Configure webhook URL for each page
- **Email Notifications**: Automatic notifications for step completion and final submission
- **Form Persistence**: Auto-saves form data to localStorage
- **Shortcode Embedding**: Easy form embedding via `[form_builder id="form-slug"]`
- **WordPress Mail Integration**: Uses WordPress native mail system (no SendGrid dependency)
- **WordPress Database**: All form definitions and submissions stored in WordPress database

## Installation

1. Upload the `form-builder-plugin` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Konstruct Form Builder** in the WordPress admin menu

## Usage

### Creating a Form

1. Go to **Konstruct Form Builder** → **Add New**
2. Enter a form name and slug
3. Click field types in the sidebar to add fields
4. Configure each field: label, name, placeholder, required status
5. Add more pages using the "Add Page" button
6. Configure webhook URL for each page (optional)
7. Configure email notifications in the "Email Notifications" tab
8. Click "Save Form"

### Import & Export Forms

**Exporting Forms**

1. Go to **Konstruct Form Builder** (forms list)
2. Click **Export** next to any form
3. A JSON file will be downloaded with the complete form configuration

**Importing Forms**

1. Go to **Konstruct Form Builder** (forms list)
2. Click **Import Form**
3. Select a JSON file exported from Form Builder
4. Click **Import**
5. The form will be imported and you'll be redirected to edit it

**Import Features:**

- Automatically handles naming conflicts (adds "Copy" suffix)
- Validates JSON format and required fields
- Preserves all form structure, fields, and settings
- Creates new form IDs to avoid conflicts

### Embedding a Form

Use the shortcode in any post or page:

```
[form_builder id="your-form-slug"]
```

Or by form ID:

```
[form_builder id="1"]
```

### Webhook Configuration

Each page can have its own webhook URL configured. When a user clicks "Next" on a page with a webhook enabled, all accumulated form data is sent to that webhook URL as:

```json
{
  "formData": {
    "field1": "value1",
    "field2": "value2"
  }
}
```

### Email Notifications

Configure automatic email notifications for form interactions:

1. **Step Completion**: Sent when users complete any form page
2. **Final Submission**: Sent when the entire form is submitted

#### Configuration Options

- **Recipients**: Static email addresses or dynamic from form fields
- **Custom Messages**: Personalize subject and content with placeholders
- **WordPress Integration**: Uses `wp_mail()` - compatible with all mail plugins

#### Available Placeholders

- `{{form_name}}` - Form name
- `{{page_number}}` - Current page (step notifications only)
- `{{submission_uuid}}` - Unique submission ID
- `{{date}}` - Current date/time
- `{{site_name}}` - WordPress site name
- `{{field_name}}` - Any form field value

See `EMAIL_NOTIFICATIONS.md` for detailed configuration guide.

## Database Tables

The plugin creates three database tables:

- `wp_form_builder_forms` - Stores form definitions
- `wp_form_builder_submissions` - Stores form submissions
- `wp_form_builder_webhook_logs` - Logs webhook delivery attempts

## Security

- Nonce verification for admin actions
- Capability checks (`manage_options` required)
- Input sanitization and validation
- Payload size limits (1MB)
- Form existence validation before webhook processing

## File Structure

```
form-builder-plugin/
├── form-builder-plugin.php      # Main plugin file
├── includes/
│   ├── class-form-storage.php   # Database operations
│   ├── class-form-builder.php   # Builder logic
│   ├── class-form-renderer.php  # Frontend rendering
│   └── class-webhook-handler.php # Webhook processing
├── admin/
│   ├── builder.php              # Admin builder UI
│   ├── builder.js               # Builder JavaScript
│   └── builder.css              # Builder styles
└── frontend/
    ├── form.js                  # Form runtime JavaScript
    └── form.css                 # Form styles
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## License

GPL v2 or later
