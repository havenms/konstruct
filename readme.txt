=== Konstruct Form Builder ===
Contributors: havenmediasolutions
Tags: forms, form-builder, contact-form, survey, webhook
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A standalone form builder tool that creates paginated forms with configurable per-page webhooks. All data stored in WordPress database.

== Description ==

Konstruct Form Builder is a powerful WordPress plugin that allows you to create beautiful, multi-page forms with ease. Build custom forms with various field types, configure webhooks for each page, and receive email notifications for form submissions.

= Key Features =

* **Simple Form Builder**: Clean admin interface for creating forms
* **All Input Types**: Supports text, email, tel, number, textarea, select, radio, checkbox, file, date
* **Paginated Forms**: Multi-page forms with Next/Back navigation
* **Per-Page Webhooks**: Configure webhook URL for each page
* **Email Notifications**: Automatic notifications for step completion and final submission
* **Form Persistence**: Auto-saves form data to localStorage
* **Shortcode Embedding**: Easy form embedding via `[form_builder id="form-slug"]`
* **WordPress Mail Integration**: Uses WordPress native mail system
* **WordPress Database**: All form definitions and submissions stored in WordPress database
* **Import/Export**: Export forms as JSON and import them on other sites
* **File Uploads**: Secure file upload handling with protected storage

= Use Cases =

* Contact forms
* Survey forms
* Multi-step registration forms
* Lead generation forms
* Feedback forms
* Application forms
* Order forms

== Installation ==

= Automatic Installation =

1. Go to Plugins → Add New in your WordPress admin
2. Search for "Konstruct Form Builder"
3. Click "Install Now"
4. Click "Activate"

= Manual Installation =

1. Upload the `form-builder-plugin` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Konstruct Form Builder** in the WordPress admin menu

== Frequently Asked Questions ==

= How do I create a form? =

Go to **Konstruct Form Builder** → **Add New** in your WordPress admin. Enter a form name and slug, then click field types in the sidebar to add fields. Configure each field and add more pages as needed.

= How do I embed a form on my site? =

Use the shortcode `[form_builder id="your-form-slug"]` in any post or page. You can also use the form ID: `[form_builder id="1"]`.

= Can I configure webhooks for each page? =

Yes! Each page can have its own webhook URL configured. When a user clicks "Next" on a page with a webhook enabled, all accumulated form data is sent to that webhook URL.

= How do email notifications work? =

The plugin supports two types of email notifications:
* **Step Completion**: Sent when users complete any form page
* **Final Submission**: Sent when the entire form is submitted

You can configure recipients, customize subject and message with placeholders, and use dynamic field values.

= Can I export and import forms? =

Yes! You can export any form as a JSON file and import it on another WordPress site. This is useful for migrating forms between sites or backing up your forms.

= What file types are supported for uploads? =

The plugin supports common file types including PDF, images (PNG, JPG, GIF), text files, CSV, ZIP, and Office documents (DOC, DOCX, PPT, PPTX). Maximum file size is 10MB.

= Is my form data secure? =

Yes! All form data is stored securely in your WordPress database. File uploads are stored in a protected directory with server-side access restrictions. The plugin follows WordPress security best practices including input sanitization, output escaping, and nonce verification.

= Does the plugin work with caching plugins? =

Yes, the plugin is compatible with most caching plugins including LiteSpeed Cache. The plugin includes cache-busting mechanisms for admin pages.

== Screenshots ==

1. Form Builder Interface - Create and edit forms with an intuitive drag-and-drop interface
2. Form Rendering - Beautiful, responsive forms on the frontend
3. Submissions Management - View and manage all form submissions
4. Email Notifications - Configure email notifications with placeholders
5. Webhook Configuration - Set up webhooks for each form page

== Changelog ==

= 1.2.0 =
* Initial release
* Form builder interface
* Multi-page form support
* Webhook integration
* Email notifications
* File upload support
* Import/Export functionality
* WordPress database storage
* Shortcode embedding

== Upgrade Notice ==

= 1.2.0 =
Initial release of Konstruct Form Builder.

== Development ==

= Contributing =

Contributions are welcome! Please ensure your code follows WordPress Coding Standards.

= Support =

For support, feature requests, and bug reports, please visit the [support forums](https://wordpress.org/support/plugin/konstruct-form-builder).

== Credits ==

Built with WordPress best practices and following WordPress Coding Standards.

