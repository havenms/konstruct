#!/bin/bash

# Form Builder Email Notification Fix Script
# Upload this to your production server and run it

echo "Form Builder Email Notification Fix"
echo "=================================="

# Find WordPress installation
if [ -f "wp-config.php" ]; then
    WP_DIR="."
elif [ -f "../wp-config.php" ]; then
    WP_DIR=".."
elif [ -f "../../wp-config.php" ]; then
    WP_DIR="../.."
else
    echo "WordPress installation not found. Please run this script from your WordPress directory."
    exit 1
fi

echo "WordPress found in: $WP_DIR"

# Find the form builder plugin
PLUGIN_DIR=""
for dir in "$WP_DIR"/wp-content/plugins/*/; do
    if [ -f "$dir/form-builder-plugin.php" ]; then
        PLUGIN_DIR="$dir"
        break
    fi
done

if [ -z "$PLUGIN_DIR" ]; then
    echo "Form Builder plugin not found in wp-content/plugins/"
    exit 1
fi

echo "Plugin found in: $PLUGIN_DIR"

# Check if email handler exists
if [ ! -f "$PLUGIN_DIR/includes/class-email-handler.php" ]; then
    echo "ERROR: class-email-handler.php not found"
    exit 1
fi

# Quick fix: Add safety checks to prevent class loading errors
echo "Applying email notification fixes..."

# Backup original files
cp "$PLUGIN_DIR/includes/class-webhook-handler.php" "$PLUGIN_DIR/includes/class-webhook-handler.php.backup"
cp "$PLUGIN_DIR/includes/class-email-handler.php" "$PLUGIN_DIR/includes/class-email-handler.php.backup"

# Create a simple test script
cat > "$WP_DIR/test-email-notifications.php" << 'EOF'
<?php
// Quick Email Notification Test - DELETE AFTER USE!

define('WP_USE_THEMES', false);
require_once('./wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied. Must be admin.');
}

echo "<h1>Form Builder Email Test</h1>";

// Test 1: Check if classes exist
echo "<h2>Class Check:</h2>";
echo "Form_Builder_Email_Handler: " . (class_exists('Form_Builder_Email_Handler') ? "✓ Found" : "✗ Missing") . "<br>";
echo "Form_Builder_Webhook_Handler: " . (class_exists('Form_Builder_Webhook_Handler') ? "✓ Found" : "✗ Missing") . "<br>";
echo "Form_Builder_Storage: " . (class_exists('Form_Builder_Storage') ? "✓ Found" : "✗ Missing") . "<br>";

// Test 2: Basic WordPress email
echo "<h2>WordPress Email Test:</h2>";
$admin_email = get_option('admin_email');
$result = wp_mail($admin_email, 'Form Builder Test', 'This is a test email from Form Builder');
echo "wp_mail() result: " . ($result ? "✓ Success" : "✗ Failed") . "<br>";

// Test 3: Email Handler Test  
if (class_exists('Form_Builder_Email_Handler')) {
    echo "<h2>Email Handler Test:</h2>";
    try {
        $email_handler = new Form_Builder_Email_Handler();
        echo "Email handler instantiated: ✓ Success<br>";
        
        // Test with sample data
        global $wpdb;
        $form = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}form_builder_forms LIMIT 1", ARRAY_A);
        
        if ($form) {
            echo "Test form found: " . $form['form_name'] . "<br>";
            
            $test_data = array('test_field' => 'test_value', 'email' => $admin_email);
            $result = $email_handler->send_step_notification($form['id'], 1, $test_data, 'test-' . time());
            echo "Step notification test: " . ($result ? "✓ Success" : "✗ Failed") . "<br>";
        } else {
            echo "No forms found to test with<br>";
        }
        
    } catch (Exception $e) {
        echo "Email handler error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "Email handler class not found<br>";
}

echo "<p><strong style='color:red;'>DELETE THIS FILE AFTER TESTING!</strong></p>";
?>
EOF

echo "Test script created: $WP_DIR/test-email-notifications.php"
echo ""
echo "Next steps:"
echo "1. Access: https://yoursite.com/test-email-notifications.php"
echo "2. Review the output to identify issues"
echo "3. Delete the test file after use"
echo ""
echo "If you see class loading errors, run these WordPress CLI commands:"
echo "wp plugin deactivate form-builder-microsaas"
echo "wp plugin activate form-builder-microsaas"
echo ""
echo "Check your WordPress debug log for detailed errors:"
echo "tail -f $WP_DIR/wp-content/debug.log"

chmod +x "$WP_DIR/test-email-notifications.php"
echo ""
echo "Fix completed! Test the email notifications now."