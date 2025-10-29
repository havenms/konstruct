<?php
/**
 * Plugin Name: Form Builder Microsaas
 * Plugin URI: https://example.com/form-builder
 * Description: A standalone form builder tool that creates paginated forms with configurable per-page webhooks. All data stored in WordPress database.
 * Version: 1.0.0
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
define('FORM_BUILDER_VERSION', '1.0.0');
define('FORM_BUILDER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FORM_BUILDER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class Form_Builder_Microsaas {
    
    private static $instance = null;
    
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
     * Initialize the plugin
     */
    private function init() {
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Load required files
        $this->load_dependencies();
        
        // Initialize components
        add_action('plugins_loaded', array($this, 'load_components'));
        
        // Register admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Register shortcode
        add_shortcode('form_builder', array($this, 'render_form_shortcode'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Enqueue frontend scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-form-storage.php';
        require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-form-builder.php';
        require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-form-renderer.php';
        require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-webhook-handler.php';
    }
    
    /**
     * Load plugin components
     */
    public function load_components() {
        // Components will be initialized as needed
    }
    
    /**
     * Plugin activation hook
     */
    public function activate() {
        global $wpdb;
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset_collate = $wpdb->get_charset_collate();
        $forms_table = $wpdb->prefix . 'form_builder_forms';
        $submissions_table = $wpdb->prefix . 'form_builder_submissions';
        $logs_table = $wpdb->prefix . 'form_builder_webhook_logs';
        
        $sql = "CREATE TABLE $forms_table (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_name VARCHAR(255) NOT NULL,
            form_slug VARCHAR(255) NOT NULL,
            form_config LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY form_slug (form_slug),
            KEY updated_at (updated_at)
        ) $charset_collate;
        
        CREATE TABLE $submissions_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id INT UNSIGNED NOT NULL,
            submission_uuid CHAR(36) NOT NULL,
            page_number INT UNSIGNED NOT NULL,
            form_data LONGTEXT NOT NULL,
            delivered TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY submission_uuid (submission_uuid),
            KEY form_id (form_id),
            KEY page_number (page_number)
        ) $charset_collate;
        
        CREATE TABLE $logs_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            submission_id BIGINT UNSIGNED NULL,
            form_id INT UNSIGNED NOT NULL,
            page_number INT UNSIGNED NOT NULL,
            webhook_url TEXT NOT NULL,
            status_code SMALLINT NULL,
            response_ms INT NULL,
            error_message TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY form_id_page (form_id, page_number),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Set version option
        update_option('form_builder_version', FORM_BUILDER_VERSION);
    }
    
    /**
     * Plugin deactivation hook
     */
    public function deactivate() {
        // Cleanup if needed
        flush_rewrite_rules();
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Form Builder', 'form-builder-microsaas'),
            __('Form Builder', 'form-builder-microsaas'),
            'manage_options',
            'form-builder',
            array($this, 'render_builder_page'),
            'dashicons-feedback',
            30
        );
        
        add_submenu_page(
            'form-builder',
            __('All Forms', 'form-builder-microsaas'),
            __('All Forms', 'form-builder-microsaas'),
            'manage_options',
            'form-builder',
            array($this, 'render_builder_page')
        );
        
        add_submenu_page(
            'form-builder',
            __('Add New Form', 'form-builder-microsaas'),
            __('Add New', 'form-builder-microsaas'),
            'manage_options',
            'form-builder-new',
            array($this, 'render_builder_page')
        );
    }
    
    /**
     * Render builder admin page
     */
    public function render_builder_page() {
        require_once FORM_BUILDER_PLUGIN_DIR . 'admin/builder.php';
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('form-builder/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook_request'),
            'permission_callback' => '__return_true',
        ));
        
        register_rest_route('form-builder/v1', '/forms', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_forms'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));
        
        register_rest_route('form-builder/v1', '/forms', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_form'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));
        
        register_rest_route('form-builder/v1', '/forms/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_form'),
            'permission_callback' => '__return_true',
        ));
        
        register_rest_route('form-builder/v1', '/forms/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_form'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));
    }
    
    /**
     * Check admin permission
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }
    
    /**
     * Handle webhook request
     */
    public function handle_webhook_request($request) {
        $webhook_handler = new Form_Builder_Webhook_Handler();
        return $webhook_handler->process_webhook($request);
    }
    
    /**
     * Get all forms
     */
    public function get_forms($request) {
        $storage = new Form_Builder_Storage();
        $forms = $storage->get_all_forms();
        return new WP_REST_Response($forms, 200);
    }
    
    /**
     * Save form
     */
    public function save_form($request) {
        $data = $request->get_json_params();
        
        // Verify nonce
        if (!isset($data['nonce']) || !wp_verify_nonce($data['nonce'], 'form_builder_save')) {
            return new WP_Error('invalid_nonce', 'Invalid nonce', array('status' => 403));
        }
        
        $storage = new Form_Builder_Storage();
        $result = $storage->save_form($data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new WP_REST_Response($result, 200);
    }
    
    /**
     * Get single form
     */
    public function get_form($request) {
        $id = $request->get_param('id');
        $storage = new Form_Builder_Storage();
        $form = $storage->get_form_by_id($id);
        
        if (!$form) {
            return new WP_Error('form_not_found', 'Form not found', array('status' => 404));
        }
        
        return new WP_REST_Response($form, 200);
    }
    
    /**
     * Delete form
     */
    public function delete_form($request) {
        $id = $request->get_param('id');
        $storage = new Form_Builder_Storage();
        $result = $storage->delete_form($id);
        
        if (!$result) {
            return new WP_Error('delete_failed', 'Failed to delete form', array('status' => 500));
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    /**
     * Render form shortcode
     */
    public function render_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => '',
        ), $atts);
        
        if (empty($atts['id'])) {
            return '<p>' . __('Form ID is required', 'form-builder-microsaas') . '</p>';
        }
        
        $renderer = new Form_Builder_Renderer();
        return $renderer->render_form($atts['id']);
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'form-builder') === false) {
            return;
        }
        
        wp_enqueue_style(
            'form-builder-admin',
            FORM_BUILDER_PLUGIN_URL . 'admin/builder.css',
            array(),
            FORM_BUILDER_VERSION
        );
        
        wp_enqueue_script(
            'form-builder-admin',
            FORM_BUILDER_PLUGIN_URL . 'admin/builder.js',
            array('jquery'),
            FORM_BUILDER_VERSION,
            true
        );
        
        wp_localize_script('form-builder-admin', 'formBuilderAdmin', array(
            'apiUrl' => rest_url('form-builder/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'saveNonce' => wp_create_nonce('form_builder_save'),
            'adminUrl' => admin_url('admin.php'),
        ));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue if a form shortcode is present on the page
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'form_builder')) {
            wp_enqueue_style(
                'form-builder-frontend',
                FORM_BUILDER_PLUGIN_URL . 'frontend/form.css',
                array(),
                FORM_BUILDER_VERSION
            );
            
            wp_enqueue_script(
                'form-builder-frontend',
                FORM_BUILDER_PLUGIN_URL . 'frontend/form.js',
                array(),
                FORM_BUILDER_VERSION,
                true
            );
            
            wp_localize_script('form-builder-frontend', 'formBuilderFrontend', array(
                'apiUrl' => rest_url('form-builder/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
            ));
        }
    }
}

// Initialize the plugin
Form_Builder_Microsaas::get_instance();

