<?php
/**
 * Debug Access Manager
 * Provides secure access to debug tools for authorized users only
 * 
 * SECURITY NOTICE: This file should only be accessed by WordPress administrators
 * and should be removed or disabled in production environments.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Load WordPress if accessed directly
    define('WP_USE_THEMES', false);
    $wp_load_paths = [
        '../wp-load.php',
        '../../wp-load.php', 
        '../../../wp-load.php',
        '../../../../wp-load.php'
    ];
    
    foreach ($wp_load_paths as $wp_load) {
        if (file_exists($wp_load)) {
            require_once($wp_load);
            break;
        }
    }
    
    if (!defined('ABSPATH')) {
        die('WordPress not found. Please access through WordPress admin.');
    }
}

// Security check - only allow admin users and WP_DEBUG mode
if (!current_user_can('manage_options')) {
    wp_die('Access denied. You must be logged in as an administrator.');
}

if (!defined('WP_DEBUG') || !WP_DEBUG) {
    wp_die('Debug mode is disabled. Enable WP_DEBUG to use debug tools.');
}

/**
 * Debug Access Manager Class
 */
class Form_Builder_Debug_Access {
    
    private $available_tools = array(
        'email-debug' => array(
            'file' => 'form-builder-debug.php',
            'title' => 'Email Notification Debug',
            'description' => 'Diagnose email notification issues and test email configuration.',
        ),
        'email-test' => array(
            'file' => 'quick-email-test.php', 
            'title' => 'Quick Email Test',
            'description' => 'Test basic email functionality and plugin classes.',
        ),
        'form-fix' => array(
            'file' => 'fix-existing-forms.php',
            'title' => 'Form Configuration Fix',
            'description' => 'One-time script to update existing forms with missing configurations.',
        ),
    );
    
    public function __construct() {
        $this->handle_request();
    }
    
    /**
     * Handle debug tool requests
     */
    private function handle_request() {
        $tool = isset($_GET['tool']) ? sanitize_key($_GET['tool']) : '';
        
        if (empty($tool)) {
            $this->show_tool_selection();
            return;
        }
        
        if (!isset($this->available_tools[$tool])) {
            wp_die('Invalid debug tool specified.');
        }
        
        $this->load_debug_tool($tool);
    }
    
    /**
     * Show available debug tools
     */
    private function show_tool_selection() {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Konstruct Form Builder - Debug Tools</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 20px; }
                .tool { border: 1px solid #ddd; padding: 20px; margin: 10px 0; border-radius: 5px; }
                .tool h3 { margin-top: 0; }
                .button { background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <h1>Konstruct Form Builder Debug Tools</h1>
            
            <div class="warning">
                <strong>⚠️ Security Notice:</strong> These debug tools are only available when WP_DEBUG is enabled and should be removed or disabled in production environments.
            </div>
            
            <?php foreach ($this->available_tools as $key => $tool): ?>
                <div class="tool">
                    <h3><?php echo esc_html($tool['title']); ?></h3>
                    <p><?php echo esc_html($tool['description']); ?></p>
                    <a href="?tool=<?php echo esc_attr($key); ?>" class="button">Run Tool</a>
                </div>
            <?php endforeach; ?>
            
            <p><strong>Usage Instructions:</strong></p>
            <ol>
                <li>Select a debug tool from the list above</li>
                <li>Follow the on-screen instructions for each tool</li>
                <li>Remove or disable debug access when debugging is complete</li>
            </ol>
            
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Load specific debug tool
     */
    private function load_debug_tool($tool) {
        $tool_info = $this->available_tools[$tool];
        $file_path = __DIR__ . '/' . $tool_info['file'];
        
        if (!file_exists($file_path)) {
            wp_die('Debug tool file not found: ' . esc_html($tool_info['file']));
        }
        
        // Security header
        echo '<div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; margin-bottom: 20px;">';
        echo '<strong>Debug Tool:</strong> ' . esc_html($tool_info['title']) . ' | ';
        echo '<a href="' . remove_query_arg('tool') . '">← Back to Debug Tools</a>';
        echo '</div>';
        
        // Load the debug tool
        require_once($file_path);
    }
}

// Initialize debug access manager
new Form_Builder_Debug_Access();