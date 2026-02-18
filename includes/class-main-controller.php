<?php
/**
 * Main Controller Class
 * Coordinates all plugin components and handles WordPress hooks
 */

if (!defined('ABSPATH')) {
    exit;
}

class Form_Builder_Main_Controller {
    
    private $rest_api;
    private $admin_interface;
    private $shortcode_handler;
    private $asset_manager;
    private $file_handler;
    
    public function __construct() {
        $this->init_components();
        $this->register_hooks();
    }
    
    /**
     * Initialize all components
     */
    private function init_components() {
        $this->rest_api = new Form_Builder_REST_API();
        $this->admin_interface = new Form_Builder_Admin_Interface();
        $this->shortcode_handler = new Form_Builder_Shortcode_Handler();
        $this->asset_manager = new Form_Builder_Asset_Manager();
        $this->file_handler = new Form_Builder_File_Handler();
    }
    
    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // REST API routes
        add_action('rest_api_init', array($this->rest_api, 'register_routes'));
        
        // Admin interface
        add_action('admin_menu', array($this->admin_interface, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this->asset_manager, 'enqueue_admin_assets'));
        
        // Frontend
        add_shortcode('form_builder', array($this->shortcode_handler, 'render_form_shortcode'));
        add_action('wp_enqueue_scripts', array($this->asset_manager, 'enqueue_frontend_assets'));
        add_action('template_redirect', array($this, 'prevent_form_page_caching'));
        
        // Debug notice (only in WP_DEBUG mode)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('admin_notices', array($this, 'debug_admin_notice'));
        }
    }
    
    /**
     * Prevent caching of pages with forms
     * This ensures field name updates are immediately reflected
     */
    public function prevent_form_page_caching() {
        global $post;
        
        // Check if the current post/page contains the form_builder shortcode
        if (is_singular() && isset($post->post_content) && has_shortcode($post->post_content, 'form_builder')) {
            // Send no-cache headers
            if (!headers_sent()) {
                header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
                header('X-Accel-Expires: 0'); // For Nginx/LiteSpeed
            }
            
            // Set WordPress constants to prevent object caching
            if (!defined('DONOTCACHEPAGE')) {
                define('DONOTCACHEPAGE', true);
            }
            if (!defined('DONOTCACHEDB')) {
                define('DONOTCACHEDB', true);
            }
            if (!defined('DONOTCACHEOBJECT')) {
                define('DONOTCACHEOBJECT', true);
            }
        }
    }
    
    /**
     * Debug admin notice (only shown when WP_DEBUG is true)
     */
    public function debug_admin_notice() {
        $screen = get_current_screen();
        if (strpos($screen->id, 'form-builder') !== false) {
            $email_handler_exists = class_exists('Form_Builder_Email_Handler');
            echo '<div class="notice notice-info"><p>';
            echo '<strong>Konstruct Form Builder Debug:</strong> Version ' . FORM_BUILDER_VERSION;
            echo ' | Email Handler: ' . ($email_handler_exists ? '✓ Loaded' : '✗ Not Found');
            echo '</p></div>';
        }
    }
}