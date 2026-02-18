# Debug Tools Directory

⚠️  **SECURITY NOTICE**: This directory contains debug and testing tools for the Konstruct Form Builder plugin.

## Important Security Information

- **Production Safety**: These files should be **removed or disabled** in production environments
- **Access Control**: Tools only work when `WP_DEBUG` is enabled and user is logged in as WordPress administrator
- **File Protection**: Direct access to debug files is blocked by `.htaccess` rules

## Available Debug Tools

### 1. Email Notification Debug (`form-builder-debug.php`)
- Diagnoses email notification configuration issues
- Tests WordPress email functionality
- Checks plugin class loading
- Validates form configurations

### 2. Quick Email Test (`quick-email-test.php`)
- Basic email functionality testing
- Plugin class existence verification
- Simple email handler testing

### 3. Form Configuration Fix (`fix-existing-forms.php`)
- One-time script to update existing forms
- Adds missing notification configurations
- Should be run once then removed

## Usage Instructions

1. Ensure `WP_DEBUG` is enabled in `wp-config.php`
2. Log in as WordPress administrator
3. Access via: `yoursite.com/wp-content/plugins/konstruct/debug/`
4. Select the appropriate debug tool
5. Follow on-screen instructions
6. **Remove or disable after debugging**

## Security Best Practices

- Only use in development/staging environments
- Remove entire `/debug/` directory before deploying to production  
- Never leave debug tools accessible on live sites
- Monitor access logs for unauthorized access attempts

## Imunify360 Compatibility

This structure isolates debug functionality to avoid false positive detection of "fake plugin backdoors" while maintaining security best practices.

---

**Last Updated**: February 2026
**Plugin Version**: 1.2.0