<?php
// Quick Email Handler Test
// Upload to WordPress root, access via browser, delete after use

define('WP_USE_THEMES', false);
require_once('./wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo "<h1>Quick Email Test</h1>";

// Test 1: Basic wp_mail
echo "<h2>Test 1: Basic WordPress Email</h2>";
$result1 = wp_mail(get_option('admin_email'), 'Test Email', 'This is a test email');
echo "Result: " . ($result1 ? "SUCCESS" : "FAILED") . "<br>";

// Test 2: Check if classes exist
echo "<h2>Test 2: Plugin Classes</h2>";
echo "Form_Builder_Email_Handler exists: " . (class_exists('Form_Builder_Email_Handler') ? "YES" : "NO") . "<br>";
echo "Form_Builder_Webhook_Handler exists: " . (class_exists('Form_Builder_Webhook_Handler') ? "YES" : "NO") . "<br>";

// Test 3: Create email handler instance
if (class_exists('Form_Builder_Email_Handler')) {
    echo "<h2>Test 3: Email Handler Instance</h2>";
    try {
        $email_handler = new Form_Builder_Email_Handler();
        echo "Email handler created: SUCCESS<br>";
        
        // Get a form from database to test with
        global $wpdb;
        $form = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}form_builder_forms LIMIT 1", ARRAY_A);
        
        if ($form) {
            echo "Test form found: " . $form['form_name'] . "<br>";
            
            // Test step notification
            $test_data = ['name' => 'Test', 'email' => get_option('admin_email')];
            $result = $email_handler->send_step_notification($form['id'], 1, $test_data, 'test-uuid');
            echo "Step notification result: " . ($result ? "SUCCESS" : "FAILED") . "<br>";
        } else {
            echo "No forms found in database<br>";
        }
        
    } catch (Exception $e) {
        echo "Email handler error: " . $e->getMessage() . "<br>";
    }
}

echo "<p><strong>Delete this file after testing!</strong></p>";
?>