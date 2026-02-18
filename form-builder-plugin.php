<?php
/**
 * Plugin Name: Konstruct Form Builder
 * Plugin URI: https://example.com/form-builder
 * Description: A standalone form builder tool that creates paginated forms with configurable per-page webhooks. All data stored in WordPress database.
 * Version: 1.2.0
 * Author: Your Name
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: form-builder-microsaas
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FORM_BUILDER_VERSION', '1.2.0');
define('FORM_BUILDER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FORM_BUILDER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FORM_BUILDER_DEV_MODE', defined('WP_DEBUG') && WP_DEBUG);

// Load required dependencies
require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-form-storage.php';
require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-form-builder.php';
require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-form-renderer.php';
require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-webhook-handler.php';
require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-email-handler.php';
require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-file-handler.php';
require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-admin-interface.php';
require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-shortcode-handler.php';
require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-asset-manager.php';
require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-plugin-activator.php';
require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-main-controller.php';

/**
 * Main Plugin Bootstrap
 */
class Form_Builder_Microsaas {
    
    private static $instance = null;
    private $controller;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the plugin - minimal bootstrap only
     */
    private function init() {
        // Set up activation/deactivation hooks
        register_activation_hook(__FILE__, array('Form_Builder_Plugin_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('Form_Builder_Plugin_Activator', 'deactivate'));
        
        // Initialize main controller on WordPress init
        add_action('plugins_loaded', array($this, 'load_controller'));
    }
    
    /**
     * Load the main controller
     */
    public function load_controller() {
        $this->controller = new Form_Builder_Main_Controller();
    }
}

// Initialize plugin
Form_Builder_Microsaas::get_instance();

