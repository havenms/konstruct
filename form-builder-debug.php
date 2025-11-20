<?php
/**
 * Form Builder Debug Script
 * Run this to diagnose email notification issues
 * 
 * Instructions:
 * 1. Upload this file to your WordPress root directory
 * 2. Access it via: yoursite.com/form-builder-debug.php
 * 3. Review the output to identify issues
 * 4. DELETE this file after debugging for security
 */

// Load WordPress
define('WP_USE_THEMES', false);
require_once('./wp-load.php');

// Security check - only allow admin users
if (!current_user_can('manage_options')) {
    die('Access denied. You must be logged in as an administrator.');
}

echo "<h1>Form Builder Email Notification Debug</h1>";
echo "<style>body{font-family:monospace;} .success{color:green;} .error{color:red;} .warning{color:orange;} pre{background:#f5f5f5;padding:10px;margin:10px 0;}</style>";

echo "<h2>1. WordPress Environment Check</h2>";

// Check WordPress basics
echo "<p><strong>WordPress Version:</strong> " . get_bloginfo('version') . "</p>";
echo "<p><strong>Site URL:</strong> " . get_site_url() . "</p>";
echo "<p><strong>Admin Email:</strong> " . get_option('admin_email') . "</p>";
echo "<p><strong>WP_DEBUG:</strong> " . (defined('WP_DEBUG') && WP_DEBUG ? '<span class="warning">Enabled</span>' : '<span class="success">Disabled</span>') . "</p>";

echo "<h2>2. Plugin File Check</h2>";

$plugin_files = [
    'form-builder-plugin.php',
    'includes/class-webhook-handler.php',
    'includes/class-email-handler.php',
    'includes/class-form-storage.php'
];

foreach ($plugin_files as $file) {
    $full_path = ABSPATH . 'wp-content/plugins/konstruct/' . $file;
    if (file_exists($full_path)) {
        echo "<p class='success'>✓ {$file} exists</p>";
    } else {
        echo "<p class='error'>✗ {$file} missing</p>";
    }
}

echo "<h2>3. Class Availability Check</h2>";

$classes = [
    'Form_Builder_Webhook_Handler',
    'Form_Builder_Email_Handler',
    'Form_Builder_Storage'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "<p class='success'>✓ {$class} loaded</p>";
    } else {
        echo "<p class='error'>✗ {$class} not found</p>";
    }
}

echo "<h2>4. WordPress Mail Function Test</h2>";

// Test basic WordPress mail
$test_subject = "Form Builder Debug Test - " . date('Y-m-d H:i:s');
$test_message = "This is a test email from the Form Builder debug script.";
$admin_email = get_option('admin_email');

echo "<p>Attempting to send test email to: {$admin_email}</p>";

$mail_result = wp_mail($admin_email, $test_subject, $test_message);

if ($mail_result) {
    echo "<p class='success'>✓ WordPress wp_mail() test successful</p>";
} else {
    echo "<p class='error'>✗ WordPress wp_mail() test failed</p>";
    
    global $phpmailer;
    if (isset($phpmailer)) {
        echo "<p class='error'>PHPMailer Error: " . $phpmailer->ErrorInfo . "</p>";
    }
}

echo "<h2>5. Database Check</h2>";

global $wpdb;

// Check if forms exist
$forms_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}form_builder_forms");
echo "<p><strong>Forms in database:</strong> " . ($forms_count ?? 0) . "</p>";

// Check if submissions exist
$submissions_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}form_builder_submissions");
echo "<p><strong>Submissions in database:</strong> " . ($submissions_count ?? 0) . "</p>";

// Get a sample form for testing
$sample_form = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}form_builder_forms LIMIT 1", ARRAY_A);

if ($sample_form) {
    echo "<h2>6. Form Configuration Test</h2>";
    echo "<p><strong>Sample Form ID:</strong> " . $sample_form['id'] . "</p>";
    echo "<p><strong>Sample Form Name:</strong> " . $sample_form['form_name'] . "</p>";
    
    // Parse form config
    $form_config = json_decode($sample_form['form_config'], true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<p class='success'>✓ Form config JSON is valid</p>";
        
        if (isset($form_config['notifications'])) {
            echo "<p class='success'>✓ Notifications config exists</p>";
            
            if (isset($form_config['notifications']['step_notifications'])) {
                $step_config = $form_config['notifications']['step_notifications'];
                echo "<p><strong>Step notifications enabled:</strong> " . ($step_config['enabled'] ? '<span class="success">Yes</span>' : '<span class="error">No</span>') . "</p>";
                
                if ($step_config['enabled']) {
                    echo "<p><strong>Recipients:</strong> " . ($step_config['recipients'] ?? 'None') . "</p>";
                    echo "<p><strong>Include Admin:</strong> " . ($step_config['include_admin'] ? 'Yes' : 'No') . "</p>";
                    echo "<p><strong>Subject:</strong> " . ($step_config['subject'] ?? 'Not set') . "</p>";
                }
            } else {
                echo "<p class='error'>✗ Step notifications config missing</p>";
            }
        } else {
            echo "<p class='error'>✗ Notifications config missing from form</p>";
        }
        
        echo "<h3>Full Form Config:</h3>";
        echo "<pre>" . json_encode($form_config, JSON_PRETTY_PRINT) . "</pre>";
    } else {
        echo "<p class='error'>✗ Form config JSON is invalid: " . json_last_error_msg() . "</p>";
    }
} else {
    echo "<p class='warning'>No forms found in database. Create a form first.</p>";
}

echo "<h2>7. Email Handler Test</h2>";

if (class_exists('Form_Builder_Email_Handler') && $sample_form) {
    try {
        $email_handler = new Form_Builder_Email_Handler();
        echo "<p class='success'>✓ Email handler instantiated</p>";
        
        // Test email notification with sample data
        $test_form_data = [
            'first_name' => 'Debug',
            'last_name' => 'Test',
            'email' => $admin_email
        ];
        
        echo "<p>Testing step notification with sample data...</p>";
        
        // Enable debug output
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo "<p class='success'>Debug mode enabled - check error logs</p>";
        }
        
        $notification_result = $email_handler->send_step_notification(
            $sample_form['id'],
            1, // Page number
            $test_form_data,
            'debug-test-' . time()
        );
        
        if ($notification_result) {
            echo "<p class='success'>✓ Step notification test successful</p>";
        } else {
            echo "<p class='error'>✗ Step notification test failed</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>✗ Email handler error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='error'>✗ Cannot test email handler</p>";
}

echo "<h2>8. Server Configuration</h2>";

echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>PHP Mail Function:</strong> " . (function_exists('mail') ? '<span class="success">Available</span>' : '<span class="error">Not available</span>') . "</p>";
echo "<p><strong>Sendmail Path:</strong> " . (ini_get('sendmail_path') ?: 'Not set') . "</p>";
echo "<p><strong>SMTP:</strong> " . (ini_get('SMTP') ?: 'Not configured') . "</p>";

echo "<h2>9. Recent Error Logs</h2>";
echo "<p><em>Check your WordPress debug.log file for recent entries containing 'Form Builder'</em></p>";

$debug_log = WP_CONTENT_DIR . '/debug.log';
if (file_exists($debug_log)) {
    echo "<p>Debug log exists at: {$debug_log}</p>";
    
    // Get last 50 lines that contain 'Form Builder'
    $log_content = file_get_contents($debug_log);
    $lines = explode("\n", $log_content);
    $form_builder_lines = array_filter($lines, function($line) {
        return stripos($line, 'form builder') !== false;
    });
    
    $recent_lines = array_slice($form_builder_lines, -10);
    
    if (!empty($recent_lines)) {
        echo "<h3>Recent Form Builder Log Entries:</h3>";
        echo "<pre>" . implode("\n", $recent_lines) . "</pre>";
    } else {
        echo "<p class='warning'>No recent Form Builder entries in debug log</p>";
    }
} else {
    echo "<p class='warning'>Debug log file not found</p>";
}

echo "<h2>10. Recommendations</h2>";

echo "<div style='background:#f0f8ff;padding:15px;border-left:4px solid #0073aa;'>";
echo "<h3>Next Steps:</h3>";
echo "<ul>";
echo "<li>If wp_mail() test failed: Configure SMTP or check server mail settings</li>";
echo "<li>If classes are missing: Ensure plugin is properly activated</li>";
echo "<li>If form config is invalid: Re-save your form in the admin</li>";
echo "<li>If step notifications are disabled: Enable them in the form builder</li>";
echo "<li>Check error logs for specific PHP errors</li>";
echo "<li>Test with a simple form step completion</li>";
echo "</ul>";
echo "</div>";

echo "<hr><p><strong>Debug completed at:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Remember:</strong> Delete this debug file after use for security!</p>";
?>