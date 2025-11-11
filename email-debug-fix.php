<?php
/**
 * Form Builder Email Fix & Test
 * Upload this to your WordPress root, run once, then delete
 */

define('WP_USE_THEMES', false);
require_once('./wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied - must be admin');
}

echo "<h1>Form Builder Email Notification Fix</h1>";
echo "<style>body{font-family:Arial;} .success{color:green;} .error{color:red;} .fix{background:#e7f3ff;padding:10px;border-left:3px solid #2196F3;margin:10px 0;}</style>";

// Step 1: Check current plugin status
echo "<h2>Step 1: Plugin Status Check</h2>";
$active_plugins = get_option('active_plugins');
$plugin_found = false;
foreach ($active_plugins as $plugin) {
    if (strpos($plugin, 'form-builder') !== false || strpos($plugin, 'konstruct') !== false) {
        echo "<p class='success'>✓ Form Builder plugin is active: $plugin</p>";
        $plugin_found = true;
        break;
    }
}
if (!$plugin_found) {
    echo "<p class='error'>✗ Form Builder plugin not found in active plugins</p>";
}

// Step 2: Check if classes exist
echo "<h2>Step 2: Class Loading Check</h2>";
$classes = ['Form_Builder_Email_Handler', 'Form_Builder_Webhook_Handler', 'Form_Builder_Storage'];
foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "<p class='success'>✓ $class loaded</p>";
    } else {
        echo "<p class='error'>✗ $class missing</p>";
    }
}

// Step 3: Test WordPress email
echo "<h2>Step 3: WordPress Email Test</h2>";
$admin_email = get_option('admin_email');
$test_result = wp_mail($admin_email, 'Form Builder Test Email', 'This is a test email from Form Builder diagnostic script. If you receive this, WordPress email is working.');

if ($test_result) {
    echo "<p class='success'>✓ WordPress email test sent successfully to $admin_email</p>";
} else {
    echo "<p class='error'>✗ WordPress email test failed</p>";
    
    // Check for common email issues
    if (ini_get('sendmail_path')) {
        echo "<p>Sendmail path: " . ini_get('sendmail_path') . "</p>";
    } else {
        echo "<p class='error'>No sendmail path configured</p>";
    }
}

// Step 4: Check for forms and test email handler
echo "<h2>Step 4: Form & Email Handler Test</h2>";
global $wpdb;
$forms = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}form_builder_forms LIMIT 3");

if (empty($forms)) {
    echo "<p class='error'>✗ No forms found in database</p>";
} else {
    echo "<p class='success'>✓ Found " . count($forms) . " forms</p>";
    
    foreach ($forms as $form) {
        echo "<h3>Testing Form: " . esc_html($form->form_name) . "</h3>";
        
        // Check form config
        $config = json_decode($form->form_config, true);
        if (!$config) {
            echo "<p class='error'>✗ Invalid JSON config</p>";
            continue;
        }
        
        // Check notifications config
        if (!isset($config['notifications'])) {
            echo "<div class='fix'>";
            echo "<p><strong>FIX NEEDED:</strong> This form doesn't have email notifications configured.</p>";
            echo "<p>1. Go to WordPress Admin → Forms → Edit this form</p>";
            echo "<p>2. Click 'Email Notifications' tab</p>";
            echo "<p>3. Enable step notifications and configure recipients</p>";
            echo "</div>";
            continue;
        }
        
        $notifications = $config['notifications'];
        $step_notifications = $notifications['step_notifications'] ?? null;
        
        if (!$step_notifications || !$step_notifications['enabled']) {
            echo "<div class='fix'>";
            echo "<p><strong>FIX NEEDED:</strong> Step notifications are disabled for this form.</p>";
            echo "<p>Go to WordPress Admin → Forms → Edit → Email Notifications tab → Enable step notifications</p>";
            echo "</div>";
            continue;
        }
        
        echo "<p class='success'>✓ Step notifications are enabled</p>";
        echo "<p>Recipients: " . ($step_notifications['recipients'] ?: 'None set') . "</p>";
        echo "<p>Include Admin: " . ($step_notifications['include_admin'] ? 'Yes' : 'No') . "</p>";
        
        // Test email handler with this form
        if (class_exists('Form_Builder_Email_Handler')) {
            try {
                $email_handler = new Form_Builder_Email_Handler();
                
                // Test with sample data
                $test_data = array(
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'email' => $admin_email,
                    'test_field' => 'Sample data from diagnostic'
                );
                
                echo "<p>Testing email notification with sample data...</p>";
                $email_result = $email_handler->send_step_notification($form->id, 1, $test_data, 'test-' . time());
                
                if ($email_result) {
                    echo "<p class='success'>✓ Email notification test SUCCESSFUL! Check your email.</p>";
                } else {
                    echo "<p class='error'>✗ Email notification test failed</p>";
                }
                
            } catch (Exception $e) {
                echo "<p class='error'>✗ Email handler error: " . $e->getMessage() . "</p>";
            }
        }
        
        break; // Only test first form
    }
}

// Step 5: Quick fixes
echo "<h2>Step 5: Apply Quick Fixes</h2>";

// Try to reactivate plugin
if ($plugin_found) {
    echo "<p>Attempting to refresh plugin...</p>";
    
    // Deactivate and reactivate plugin
    $plugin_file = null;
    foreach ($active_plugins as $plugin) {
        if (strpos($plugin, 'form-builder') !== false || strpos($plugin, 'konstruct') !== false) {
            $plugin_file = $plugin;
            break;
        }
    }
    
    if ($plugin_file) {
        deactivate_plugins($plugin_file);
        activate_plugin($plugin_file);
        echo "<p class='success'>✓ Plugin reactivated</p>";
    }
}

// Step 6: Manual debugging steps
echo "<h2>Step 6: If Still Not Working</h2>";
echo "<div class='fix'>";
echo "<h3>Manual Steps to Fix Email Notifications:</h3>";
echo "<ol>";
echo "<li><strong>Enable WordPress Debug:</strong> Add to wp-config.php:<br><code>define('WP_DEBUG', true);<br>define('WP_DEBUG_LOG', true);</code></li>";
echo "<li><strong>Check Debug Log:</strong> Look in /wp-content/debug.log for 'Form Builder' errors</li>";
echo "<li><strong>Test Form Step:</strong> Complete a step in your form and check debug log immediately</li>";
echo "<li><strong>Check Form Settings:</strong> Admin → Forms → Edit → Email Notifications tab → Enable step notifications</li>";
echo "<li><strong>Verify Recipients:</strong> Make sure email addresses are correct or 'Include Admin' is checked</li>";
echo "</ol>";

echo "<h3>Common Issues & Solutions:</h3>";
echo "<ul>";
echo "<li><strong>No emails at all:</strong> Server mail not configured - install SMTP plugin</li>";
echo "<li><strong>Emails to admin work, step emails don't:</strong> Step notifications not enabled in form settings</li>";
echo "<li><strong>Class not found errors:</strong> Upload latest plugin files and reactivate</li>";
echo "<li><strong>JSON decode errors:</strong> Re-save your form in admin to fix config format</li>";
echo "</ul>";
echo "</div>";

// Step 7: Test webhook endpoint
echo "<h2>Step 7: Webhook Endpoint Test</h2>";
$webhook_url = rest_url('form-builder/v1/webhook');
echo "<p>Webhook URL: $webhook_url</p>";

// Check if REST API is working
$rest_test = wp_remote_get(rest_url());
if (is_wp_error($rest_test)) {
    echo "<p class='error'>✗ WordPress REST API not accessible</p>";
} else {
    echo "<p class='success'>✓ WordPress REST API is working</p>";
}

echo "<hr>";
echo "<p><strong style='color:red;'>IMPORTANT: Delete this file after testing!</strong></p>";
echo "<p>File to delete: " . __FILE__ . "</p>";
?>