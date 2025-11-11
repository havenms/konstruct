#!/bin/bash

# Form Builder Email Notification Quick Fix
# Run this in your WordPress directory via SSH

echo "Form Builder Email Notification Fix"
echo "==================================="

# Check if we're in WordPress directory
if [ ! -f "wp-config.php" ]; then
    echo "Error: Not in WordPress directory. Please cd to your WordPress root first."
    exit 1
fi

echo "✓ Found WordPress installation"

# Find plugin directory
PLUGIN_DIR=""
for dir in wp-content/plugins/*/; do
    if [ -f "${dir}form-builder-plugin.php" ]; then
        PLUGIN_DIR="$dir"
        break
    fi
done

if [ -z "$PLUGIN_DIR" ]; then
    echo "✗ Form Builder plugin not found"
    exit 1
fi

echo "✓ Found plugin in: $PLUGIN_DIR"

# Check if WP-CLI is available
if command -v wp &> /dev/null; then
    echo "✓ WP-CLI found - using advanced diagnostics"
    
    # Test basic WordPress email
    echo "Testing WordPress email..."
    wp eval "echo wp_mail(get_option('admin_email'), 'Test', 'Test email from Form Builder fix') ? 'Email test: SUCCESS' : 'Email test: FAILED';"
    
    # Check plugin status
    echo "Checking plugin status..."
    wp plugin list | grep -i form
    
    # Reactivate plugin
    echo "Reactivating plugin..."
    wp plugin deactivate form-builder-microsaas 2>/dev/null || true
    wp plugin activate form-builder-microsaas
    
    # Check if classes are loaded
    echo "Checking classes..."
    wp eval "echo class_exists('Form_Builder_Email_Handler') ? 'Email Handler: OK' : 'Email Handler: MISSING';"
    wp eval "echo class_exists('Form_Builder_Webhook_Handler') ? 'Webhook Handler: OK' : 'Webhook Handler: MISSING';"
    
    # Test email notification
    echo "Testing email notification with sample form..."
    wp eval "
    global \$wpdb;
    \$form = \$wpdb->get_row(\"SELECT * FROM {\$wpdb->prefix}form_builder_forms LIMIT 1\", ARRAY_A);
    if (\$form && class_exists('Form_Builder_Email_Handler')) {
        \$handler = new Form_Builder_Email_Handler();
        \$test_data = array('name' => 'Test User', 'email' => get_option('admin_email'));
        \$result = \$handler->send_step_notification(\$form['id'], 1, \$test_data, 'test-' . time());
        echo \$result ? 'Step notification test: SUCCESS' : 'Step notification test: FAILED';
    } else {
        echo 'No forms found or email handler missing';
    }
    "
    
else
    echo "WP-CLI not found - using basic fixes"
    
    # Check file permissions
    echo "Checking file permissions..."
    find "$PLUGIN_DIR" -name "*.php" -type f ! -readable && echo "✗ Some plugin files not readable" || echo "✓ Plugin files are readable"
    
    # Check if classes exist
    if [ -f "${PLUGIN_DIR}includes/class-email-handler.php" ]; then
        echo "✓ Email handler file exists"
    else
        echo "✗ Email handler file missing"
    fi
    
    if [ -f "${PLUGIN_DIR}includes/class-webhook-handler.php" ]; then
        echo "✓ Webhook handler file exists"
    else
        echo "✗ Webhook handler file missing"
    fi
fi

# Check debug log for errors
if [ -f "wp-content/debug.log" ]; then
    echo "Checking recent errors in debug log..."
    echo "Recent Form Builder errors:"
    tail -50 wp-content/debug.log | grep -i "form builder" | tail -5
else
    echo "No debug log found"
fi

echo ""
echo "Manual Steps to Complete the Fix:"
echo "================================="
echo "1. Upload the diagnostic script to WordPress root:"
echo "   - Upload: email-debug-fix.php"
echo "   - Access: https://yoursite.com/email-debug-fix.php"
echo "   - Delete after use"
echo ""
echo "2. Check form settings in WordPress admin:"
echo "   - Go to: WordPress Admin → Forms → Edit your form"
echo "   - Click: 'Email Notifications' tab"
echo "   - Enable: 'Enable step notifications'"
echo "   - Set recipients or check 'Include administrator'"
echo ""
echo "3. Test a form step completion and check your email"
echo ""
echo "4. If still not working, enable debug mode in wp-config.php:"
echo "   define('WP_DEBUG', true);"
echo "   define('WP_DEBUG_LOG', true);"
echo ""
echo "Fix complete! Check the steps above if emails still not working."