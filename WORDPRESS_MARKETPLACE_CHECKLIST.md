# WordPress.org Marketplace Submission Checklist

Use this checklist to ensure your plugin is ready for WordPress.org submission.

## Code Quality

- [x] Plugin header includes all required fields (Name, URI, Description, Version, Author, License, Text Domain)
- [x] Plugin header includes: Requires at least, Requires PHP, Tested up to, Network, Update URI
- [x] All code follows WordPress Coding Standards
- [x] Proper indentation (tabs for PHP, spaces for JS/CSS)
- [x] Consistent naming conventions
- [x] PHPDoc blocks for all classes and methods
- [x] No debug code (error_log, console.log, print_r) in production
- [x] Debug code only in dev-tools directory or wrapped in WP_DEBUG checks

## Security

- [x] All user inputs are sanitized (sanitize_text_field, sanitize_email, etc.)
- [x] All outputs are escaped (esc_html, esc_attr, esc_url, etc.)
- [x] SQL queries use $wpdb->prepare() when using user input
- [x] Nonce verification for all admin actions
- [x] Capability checks (current_user_can) for admin functions
- [x] File uploads are validated (type, size, sanitized filenames)
- [x] REST API endpoints have proper permission callbacks
- [x] No direct database queries with user input
- [x] No eval() or similar dangerous functions

## Internationalization (i18n)

- [x] All user-facing strings use translation functions (__(), _e(), _n(), _x())
- [x] Consistent text domain throughout: `form-builder-microsaas`
- [x] Text domain specified in all translation function calls
- [x] JavaScript strings use wp.i18n (if applicable)
- [x] No hardcoded English text in user-facing areas

## Documentation

- [x] readme.txt file in WordPress.org format
- [x] readme.txt includes: description, installation, FAQ, screenshots, changelog
- [x] README.md for GitHub/development (optional but recommended)
- [x] Code comments explain complex logic
- [x] PHPDoc blocks document parameters and return types

## Plugin Structure

- [x] uninstall.php file exists and handles cleanup
- [x] uninstall.php removes database tables (optional, user preference)
- [x] uninstall.php removes options and transients
- [x] uninstall.php removes uploaded files (optional)
- [x] Proper file organization
- [x] Debug/temporary files moved to dev-tools/ directory

## Assets (Optional but Recommended)

- [ ] banner-772x250.png created (772x250 pixels)
- [ ] icon-256x256.png created (256x256 pixels, square)
- [ ] screenshot-1.png through screenshot-5.png (1200x900 recommended)
- [ ] Assets uploaded to SVN assets/ directory

## Functionality

- [x] Plugin activates without errors
- [x] Plugin deactivates cleanly
- [x] Plugin uninstalls cleanly
- [x] All features work as documented
- [x] No PHP errors or warnings
- [x] No JavaScript console errors
- [x] Compatible with latest WordPress version
- [x] Compatible with PHP 7.4+

## Testing

- [x] Tested on fresh WordPress installation
- [x] Tested with default themes (Twenty Twenty-Three, etc.)
- [x] Tested with common plugins (WooCommerce, Yoast, etc.)
- [x] Tested form creation and editing
- [x] Tested form rendering on frontend
- [x] Tested form submissions
- [x] Tested webhook functionality
- [x] Tested email notifications
- [x] Tested file uploads
- [x] Tested import/export
- [x] Tested with different PHP versions (7.4, 8.0, 8.1, 8.2)

## Publishing

- [ ] WordPress.org account created
- [ ] Plugin submission request submitted
- [ ] SVN repository access received
- [ ] SVN repository checked out
- [ ] Files committed to trunk/
- [ ] First release tag created (tags/1.2.0/)
- [ ] Assets uploaded to assets/
- [ ] readme.txt stable tag updated
- [ ] Submission form completed
- [ ] Review feedback addressed (if any)

## Post-Approval

- [ ] Support forums monitored
- [ ] User questions answered
- [ ] Bug reports addressed
- [ ] Version update process established
- [ ] Changelog maintained

## Notes

- All items marked with [x] are completed
- Items marked with [ ] need to be completed before/during submission
- Review this checklist before each submission
- Keep this checklist updated as you make changes

## Quick Reference

**Plugin Header Template:**
```php
/**
 * Plugin Name: Your Plugin Name
 * Plugin URI: https://wordpress.org/plugins/your-plugin-slug
 * Description: Your plugin description
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Tested up to: 6.4
 * Author: Your Name
 * Author URI: https://profiles.wordpress.org/your-username
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: your-text-domain
 * Network: false
 * Update URI: false
 */
```

**Security Checklist:**
- Sanitize inputs: `sanitize_text_field()`, `sanitize_email()`, `sanitize_file_name()`
- Escape outputs: `esc_html()`, `esc_attr()`, `esc_url()`, `esc_js()`
- Use prepare(): `$wpdb->prepare("SELECT * FROM table WHERE id = %d", $id)`
- Verify nonces: `wp_verify_nonce($_POST['nonce'], 'action_name')`
- Check capabilities: `current_user_can('manage_options')`

**Translation Checklist:**
- Use `__('Text', 'text-domain')` for return values
- Use `_e('Text', 'text-domain')` for direct output
- Use `_n('Singular', 'Plural', $count, 'text-domain')` for plural forms
- Use `_x('Text', 'context', 'text-domain')` for context-specific translations

