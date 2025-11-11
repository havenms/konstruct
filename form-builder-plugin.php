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
define('FORM_BUILDER_VERSION', '1.1.0');
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
        
        // Add admin notice for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('admin_notices', array($this, 'debug_admin_notice'));
        }
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-form-storage.php';
        require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-form-builder.php';
        require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-form-renderer.php';
        require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-webhook-handler.php';
        require_once FORM_BUILDER_PLUGIN_DIR . 'includes/class-email-handler.php';
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

        // Ensure protected uploads directory exists with server-side protection
        $uploads = wp_upload_dir();
        $form_data_dir = trailingslashit($uploads['basedir']) . 'form_data';
        if (!file_exists($form_data_dir)) {
            wp_mkdir_p($form_data_dir);
        }

        // Add .htaccess to block direct access (for Apache environments)
        $htaccess_path = trailingslashit($form_data_dir) . '.htaccess';
        if (!file_exists($htaccess_path)) {
            $rules = "# Deny direct access to uploaded form files\nDeny from all\n";
            @file_put_contents($htaccess_path, $rules);
        }

        // Add index.php to prevent directory listing
        $index_path = trailingslashit($form_data_dir) . 'index.php';
        if (!file_exists($index_path)) {
            @file_put_contents($index_path, "<?php\n// Silence is golden.\n");
        }
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

        add_submenu_page(
            'form-builder',
            __('All Submissions', 'form-builder-microsaas'),
            __('Submissions', 'form-builder-microsaas'),
            'manage_options',
            'form-builder-submissions',
            array($this, 'render_submissions_page')
        );
    }
    
    /**
     * Render builder admin page
     */
    public function render_builder_page() {
        require_once FORM_BUILDER_PLUGIN_DIR . 'admin/builder.php';
    }

    /**
     * Render submissions admin page
     */
    public function render_submissions_page() {
        require_once FORM_BUILDER_PLUGIN_DIR . 'admin/submissions.php';
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

        register_rest_route('form-builder/v1', '/submissions', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_submission'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('form-builder/v1', '/submissions', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_submissions'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));

        register_rest_route('form-builder/v1', '/test-email', array(
            'methods' => 'POST',
            'callback' => array($this, 'test_email'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));

        // Protected file download route (admins only)
        register_rest_route('form-builder/v1', '/file', array(
            'methods' => 'GET',
            'callback' => array($this, 'download_file'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'submission_uuid' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'field' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
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

        // Debug logging
        error_log('Form save request received: ' . json_encode($data));

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
     * Save submission
     */
    public function save_submission($request) {
        // Support multipart (files) and JSON bodies
        $is_multipart = strpos($request->get_header('content-type'), 'multipart/form-data') !== false;
        if ($is_multipart) {
            // For multipart, parameters come from $request->get_param and files from $_FILES
            $form_id = intval($request->get_param('form_id'));
            $submission_uuid = $request->get_param('submission_uuid');
            $submission_uuid = $submission_uuid ? sanitize_text_field($submission_uuid) : $this->generate_uuid();
            // formData fields are sent as flattened key/value pairs
            $form_data = array();
            $raw = $request->get_params();
            if (isset($raw['formData']) && is_string($raw['formData'])) {
                // If frontend sent JSON blob for formData
                $decoded = json_decode($raw['formData'], true);
                if (is_array($decoded)) {
                    $form_data = $decoded;
                }
            } else {
                // Collect non-file params except internal REST params
                foreach ($raw as $key => $val) {
                    if (in_array($key, array('form_id', 'submission_uuid', 'rest_route'))) {
                        continue;
                    }
                    if (!isset($_FILES[$key])) {
                        $form_data[$key] = is_array($val) ? array_map('sanitize_text_field', $val) : sanitize_text_field($val);
                    }
                }
            }
        } else {
            $params = $request->get_json_params();
            // Debug logging
            error_log('Submission save request received: ' . json_encode($params));
            if (empty($params['form_id']) || !isset($params['formData'])) {
                return new WP_Error(
                    'missing_params',
                    'form_id and formData are required',
                    array('status' => 400)
                );
            }
            $form_id = intval($params['form_id']);
            $form_data = $params['formData'];
            $submission_uuid = isset($params['submission_uuid']) ? sanitize_text_field($params['submission_uuid']) : $this->generate_uuid();
        }

        // Validate required parameters
        if (empty($form_id)) {
            return new WP_Error(
                'missing_params',
                'form_id is required',
                array('status' => 400)
            );
        }

        // Validate form exists
        $storage = new Form_Builder_Storage();
        $form = $storage->get_form_by_id($form_id);
        if (!$form) {
            return new WP_Error(
                'form_not_found',
                'Form not found',
                array('status' => 404)
            );
        }

        // Handle file uploads if multipart
        if ($is_multipart && !empty($_FILES)) {
            $form_data = $this->process_uploads_and_enrich_form_data($submission_uuid, $form_data, $_FILES);
            if (is_wp_error($form_data)) {
                return $form_data;
            }
        }

        // Save submission with enriched form_data (with file links)
        $submission_id = $storage->insert_submission(
            $form_id,
            $submission_uuid,
            count($form['form_config']['pages']),
            $form_data,
            true
        );

        if (!$submission_id) {
            return new WP_Error(
                'save_failed',
                'Failed to save submission',
                array('status' => 500)
            );
        }

        // Send final submission email notification
        $email_handler = new Form_Builder_Email_Handler();
        $email_handler->send_submission_notification($form_id, $form_data, $submission_uuid);

        return new WP_REST_Response(array(
            'success' => true,
            'submission_id' => $submission_id,
            'submission_uuid' => $submission_uuid
        ), 200);
    }

    /**
     * Protected download
     */
    public function download_file($request) {
        $submission_uuid = sanitize_text_field($request->get_param('submission_uuid'));
        $field = sanitize_text_field($request->get_param('field'));

        if (empty($submission_uuid) || empty($field)) {
            return new WP_Error('bad_request', 'Missing parameters', array('status' => 400));
        }

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT form_data FROM {$wpdb->prefix}form_builder_submissions WHERE submission_uuid = %s",
            $submission_uuid
        ), ARRAY_A);

        if (!$row) {
            return new WP_Error('not_found', 'Submission not found', array('status' => 404));
        }

        $data = json_decode($row['form_data'], true);
        if (!isset($data[$field]) || !is_array($data[$field]) || empty($data[$field]['path'])) {
            return new WP_Error('not_found', 'File not found for field', array('status' => 404));
        }

        $file_meta = $data[$field];
        $path = $file_meta['path'];

        // Ensure path is inside uploads/form_data
        $uploads = wp_upload_dir();
        $base = realpath(trailingslashit($uploads['basedir']) . 'form_data');
        $real = realpath($path);
        if ($base === false || $real === false || strpos($real, $base) !== 0 || !file_exists($real)) {
            return new WP_Error('forbidden', 'Access denied', array('status' => 403));
        }

        // Serve file
        $mime = isset($file_meta['mime']) ? $file_meta['mime'] : 'application/octet-stream';
        $filename = isset($file_meta['name']) ? $file_meta['name'] : basename($real);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($real));
        readfile($real);
        exit;
    }

    /**
     * Process uploads: validate, move to uploads/form_data, and replace fields with metadata and protected URLs.
     */
    private function process_uploads_and_enrich_form_data($submission_uuid, $form_data, $files) {
        $uploads = wp_upload_dir();
        $target_dir = trailingslashit($uploads['basedir']) . 'form_data';
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }

        // Security: allowed mimes and size cap (10MB)
        $max_bytes = 10 * 1024 * 1024;
        $allowed = array(
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'zip' => 'application/zip',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        );

        foreach ($files as $field => $file) {
            if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return new WP_Error('upload_error', 'Failed to upload file for ' . $field, array('status' => 400));
            }
            if ($file['size'] > $max_bytes) {
                return new WP_Error('file_too_large', 'File too large for ' . $field, array('status' => 413));
            }

            $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
            $ext = isset($check['ext']) ? $check['ext'] : '';
            $type = isset($check['type']) ? $check['type'] : '';
            if (empty($ext) || empty($type) || !isset($allowed[$ext]) || $allowed[$ext] !== $type) {
                return new WP_Error('invalid_type', 'Invalid file type for ' . $field, array('status' => 415));
            }

            // Sanitize and generate unique file name
            $safe_name = sanitize_file_name($file['name']);
            $unique = $submission_uuid . '-' . wp_generate_password(8, false, false) . '-' . $safe_name;
            $dest = trailingslashit($target_dir) . $unique;

            if (!@move_uploaded_file($file['tmp_name'], $dest)) {
                // Fallback to WP handle upload
                $overrides = array('test_form' => false);
                $handled = wp_handle_upload($file, $overrides);
                if (isset($handled['error'])) {
                    return new WP_Error('upload_move_failed', $handled['error'], array('status' => 500));
                }
                // Move from default uploads to our protected dir
                $moved = @rename($handled['file'], $dest);
                if (!$moved) {
                    return new WP_Error('upload_move_failed', 'Could not secure file location', array('status' => 500));
                }
            }

            // Build protected URL via our REST route (admin-only)
            $protected_url = add_query_arg(array(
                'submission_uuid' => rawurlencode($submission_uuid),
                'field' => rawurlencode($field),
            ), rest_url('form-builder/v1/file'));

            $form_data[$field] = array(
                'name' => $safe_name,
                'mime' => $type,
                'size' => filesize($dest),
                'path' => $dest,
                'url' => $protected_url,
            );
        }

        return $form_data;
    }

    /**
     * Get submissions
     */
    public function get_submissions($request) {
        $storage = new Form_Builder_Storage();

        // Get all submissions with form info
        global $wpdb;
        $submissions = $wpdb->get_results("
            SELECT s.*, f.form_name, f.form_slug
            FROM {$wpdb->prefix}form_builder_submissions s
            LEFT JOIN {$wpdb->prefix}form_builder_forms f ON s.form_id = f.id
            ORDER BY s.created_at DESC
        ", ARRAY_A);

        // Decode form data
        foreach ($submissions as &$submission) {
            if ($submission['form_data']) {
                $submission['form_data'] = json_decode($submission['form_data'], true);
            }
        }

        return new WP_REST_Response(array('submissions' => $submissions), 200);
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

    /**
     * Debug admin notice (only shown when WP_DEBUG is true)
     */
    public function debug_admin_notice() {
        $screen = get_current_screen();
        if (strpos($screen->id, 'form-builder') !== false) {
            $email_handler_exists = class_exists('Form_Builder_Email_Handler');
            echo '<div class="notice notice-info"><p>';
            echo '<strong>Form Builder Debug:</strong> Version ' . FORM_BUILDER_VERSION;
            echo ' | Email Handler: ' . ($email_handler_exists ? '✓ Loaded' : '✗ Not Found');
            echo '</p></div>';
        }
    }

    /**
     * Test email functionality
     */
    public function test_email($request) {
        $params = $request->get_json_params();
        
        if (empty($params['email'])) {
            return new WP_Error('missing_email', 'Email address is required', array('status' => 400));
        }
        
        $email = sanitize_email($params['email']);
        if (!is_email($email)) {
            return new WP_Error('invalid_email', 'Invalid email address', array('status' => 400));
        }
        
        $email_handler = new Form_Builder_Email_Handler();
        $result = $email_handler->test_email_config($email);
        
        if ($result) {
            return new WP_REST_Response(array('success' => true, 'message' => 'Test email sent successfully'), 200);
        } else {
            return new WP_Error('email_failed', 'Failed to send test email', array('status' => 500));
        }
    }

    /**
     * Generate UUID
     */
    private function generate_uuid() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}

// Initialize the plugin
Form_Builder_Microsaas::get_instance();

