# Konstruct Form Builder WordPress Plugin

A standalone HTML-CSS-JS form builder that creates paginated forms with configurable per-page webhooks. All data is stored in the WordPress database.

## Features

- **Simple Form Builder**: Clean admin interface for creating forms
- **All Input Types**: Supports text, email, tel, number, textarea, select, radio, checkbox, file, date
- **Paginated Forms**: Multi-page forms with Next/Back navigation
- **Per-Page Webhooks**: Configure webhook URL for each page
- **Facebook Pixel**: Enter a Pixel ID and pick an event; no JavaScript required
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
3. Add fields by dragging a type from the sidebar onto the page, or by clicking it to append one. Once added, drag a field by its row to reorder it, or drop it onto a page in the sidebar to move it there. The Up/Down arrows reorder from the keyboard
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

### Facebook Pixel

Enter a Pixel ID, choose an event, save. No custom JavaScript.

1. Open your form and expand the **Facebook Pixel** panel
2. Tick **Track submissions with Facebook Pixel**
3. Paste your **Pixel ID** — the number from Meta **Events Manager → Data Sources**. Pasting extra text is fine; everything but the digits is stripped as you type
4. Choose the **event to fire on submit**

Available events: `Lead`, `CompleteRegistration`, `Contact`, `Schedule`, `SubmitApplication`, `Subscribe`, `InitiateCheckout`, `AddToCart`, `ViewContent`, `Purchase`.

**Choosing when the conversion fires**

By default the conversion fires when the form is submitted. The **Fire it on** dropdown can move it to any earlier page instead.

This matters when the last page only confirms and collects nothing: a visitor who fills everything in and then closes the tab on that screen is a real lead who would otherwise never be counted. Setting the conversion to fire on the page that captures the details fixes that.

It always fires **once** per visitor, whichever page is chosen. The last page is not offered, since it has no Next button and is the same as firing on submit. If pages are later deleted and the chosen one no longer exists, it falls back to firing on submit rather than never firing at all.

**How it works**

The pixel base code is printed in the page `<head>` when the page loads, before the form renders. `PageView` fires immediately; the conversion fires once, at the moment configured above.

This ordering matters. Installing a pixel through Custom JS does not work reliably:

- Custom JS runs inside `new Function(...)` at submit time, so at page load there is no pixel for **Meta Pixel Helper** to detect
- `fbevents.js` may not have finished loading when the event fires, so the event is lost

Loading the base code with the page fixes both. Pixel Helper detects it normally, and `fbq` queues any event sent before the script finishes.

**Tracking drop-offs (optional)**

Tick **Also track each step** to send a custom event every time someone completes a page. These are *not* conversions — they exist so you can build a Facebook audience of people who started the form and never finished, including people who typed nothing and so left no record in your CRM.

Event names are generated from the form slug and shown in the panel, for example:

```
Konstruct_ContactUs_Step1
Konstruct_ContactUs_Step2
```

To retarget drop-offs, create a Custom Audience in Ads Manager that **includes** a step event and **excludes** your conversion event.

These use `trackCustom` rather than `track`, so they never interfere with what a standard event means elsewhere in your ad account. They appear in the audience builder automatically; adding them to Ads Manager reporting columns requires defining a Custom Conversion.

**Notes**

- Two forms on one page sharing a Pixel ID are initialised once, not twice
- The submission UUID is sent as the event's `eventID`, so the event can be de-duplicated if the same conversion is later sent server-side through the Conversions API
- Only standard Meta events are offered — a custom event name would not appear in Ads Manager reporting without extra setup
- A `<noscript>` tracking pixel is included for visitors without JavaScript
- Webhook behaviour is unchanged

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
