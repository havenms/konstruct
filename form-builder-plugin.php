<?php
/**
 * Plugin Name: Konstruct Form Builder
 * Plugin URI: https://wordpress.org/plugins/konstruct-form-builder
 * Description: A standalone form builder tool that creates paginated forms with configurable per-page webhooks. All data stored in WordPress database.
 * Version: 1.2.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Tested up to: 6.4
 * Author: Haven Media Solutions
 * Author URI: https://profiles.wordpress.org/havenmediasolutions
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: form-builder-microsaas
 * Network: false
 * Update URI: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FORM_BUILDER_VERSION', '1.2.0');
define('FORM_BUILDER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FORM_BUILDER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Development mode - set to true during development to enable aggressive cache busting
define('FORM_BUILDER_DEV_MODE', defined('WP_DEBUG') && WP_DEBUG);

/**
 * Main Plugin Class
 * 
 * Handles plugin initialization, activation, deactivation, and core functionality.
 * 
 * @package Form_Builder_Microsaas
 * @since 1.2.0
 */
class Form_Builder_Microsaas {
    
    /**
     * Plugin instance
     * 
     * @var Form_Builder_Microsaas
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     * 
     * @return Form_Builder_Microsaas Plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     * 
     * @access private
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the plugin
     * 
     * Sets up hooks, loads dependencies, and registers components.
     * 
     * @return void
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
     * 
     * Includes all necessary class files for the plugin.
     * 
     * @return void
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
     * 
     * Placeholder for component initialization.
     * 
     * @return void
     */
    public function load_components() {
        // Components will be initialized as needed
    }
    
    /**
     * Plugin activation hook
     * 
     * Creates database tables and sets up initial plugin data.
     * 
     * @return void
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
     * 
     * Performs cleanup tasks when plugin is deactivated.
     * 
     * @return void
     */
    public function deactivate() {
        // Cleanup if needed
        flush_rewrite_rules();
    }
    
    /**
     * Add admin menu
     * 
     * Registers admin menu pages for the plugin.
     * 
     * @return void
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Konstruct Form Builder', 'form-builder-microsaas'),
            __('Konstruct Form Builder', 'form-builder-microsaas'),
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
     * 
     * Outputs the form builder interface.
     * 
     * @return void
     */
    public function render_builder_page() {
        // Add cache-busting headers for admin pages to help with LiteSpeed Cache
        if (!headers_sent()) {
            header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        }
        
        require_once FORM_BUILDER_PLUGIN_DIR . 'admin/builder.php';
    }

    /**
     * Render submissions admin page
     * 
     * Outputs the submissions management interface.
     * 
     * @return void
     */
    public function render_submissions_page() {
        // Add cache-busting headers for admin pages to help with LiteSpeed Cache
        if (!headers_sent()) {
            header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        }
        
        require_once FORM_BUILDER_PLUGIN_DIR . 'admin/submissions.php';
    }
    
    /**
     * Register REST API routes
     * 
     * Registers all REST API endpoints for the plugin.
     * 
     * @return void
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

        register_rest_route('form-builder/v1', '/debug-form/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'debug_form'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));

        register_rest_route('form-builder/v1', '/step-notification', array(
            'methods' => 'POST',
            'callback' => array($this, 'send_step_notification'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('form-builder/v1', '/forms/(?P<id>\d+)/export', array(
            'methods' => 'GET',
            'callback' => array($this, 'export_form'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));

        register_rest_route('form-builder/v1', '/forms/import', array(
            'methods' => 'POST',
            'callback' => array($this, 'import_form'),
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
     * 
     * Verifies that the current user has admin capabilities.
     * 
     * @return bool True if user has manage_options capability
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }
    
    /**
     * Handle webhook request
     * 
     * Processes webhook requests from the REST API.
     * 
     * @param WP_REST_Request $request REST API request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function handle_webhook_request($request) {
        $webhook_handler = new Form_Builder_Webhook_Handler();
        return $webhook_handler->process_webhook($request);
    }
    
    /**
     * Get all forms
     * 
     * Retrieves all forms from the database.
     * 
     * @param WP_REST_Request $request REST API request object
     * @return WP_REST_Response Response containing forms data
     */
    public function get_forms($request) {
        $storage = new Form_Builder_Storage();
        $forms = $storage->get_all_forms();
        return new WP_REST_Response($forms, 200);
    }
    
    /**
     * Save form
     * 
     * Creates or updates a form in the database.
     * 
     * @param WP_REST_Request $request REST API request object
     * @return WP_REST_Response|WP_Error Response object or error
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
     * 
     * Retrieves a single form by ID.
     * 
     * @param WP_REST_Request $request REST API request object
     * @return WP_REST_Response|WP_Error Response object or error
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
     * 
     * Deletes a form from the database.
     * 
     * @param WP_REST_Request $request REST API request object
     * @return WP_REST_Response|WP_Error Response object or error
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
     * 
     * Saves a form submission to the database.
     * 
     * @param WP_REST_Request $request REST API request object
     * @return WP_REST_Response|WP_Error Response object or error
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
     * 
     * Handles secure file downloads for form submissions.
     * 
     * @param WP_REST_Request $request REST API request object
     * @return void|WP_Error Exits on success or returns error
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
     * 
     * @param string $submission_uuid Unique submission identifier
     * @param array  $form_data Form data array
     * @param array  $files Uploaded files array
     * @return array|WP_Error Enriched form data or error
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
     * 
     * Retrieves all form submissions from the database.
     * 
     * @param WP_REST_Request $request REST API request object
     * @return WP_REST_Response Response containing submissions data
     */
    public function get_submissions($request) {
        $storage = new Form_Builder_Storage();

        // Get all submissions with form info
        // Note: This query doesn't use user input, so it's safe without prepare()
        global $wpdb;
        $submissions = $wpdb->get_results(
            "SELECT s.*, f.form_name, f.form_slug
            FROM {$wpdb->prefix}form_builder_submissions s
            LEFT JOIN {$wpdb->prefix}form_builder_forms f ON s.form_id = f.id
            ORDER BY s.created_at DESC",
            ARRAY_A
        );

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
     * 
     * Handles the [form_builder] shortcode.
     * 
     * @param array $atts Shortcode attributes
     * @return string Form HTML or error message
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
     * 
     * Loads CSS and JavaScript files for admin pages.
     * 
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'form-builder') === false) {
            return;
        }
        
        // Add cache-busting headers for admin pages
        if (!headers_sent()) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        
        // Generate dynamic version for cache busting
        $css_version = $this->get_asset_version('admin/builder.css');
        $js_version = $this->get_asset_version('admin/builder.js');
        
        wp_enqueue_style(
            'form-builder-admin',
            FORM_BUILDER_PLUGIN_URL . 'admin/builder.css',
            array(),
            $css_version
        );
        
        wp_enqueue_script(
            'form-builder-admin',
            FORM_BUILDER_PLUGIN_URL . 'admin/builder.js',
            array('jquery'),
            $js_version,
            true
        );
        
        wp_localize_script('form-builder-admin', 'formBuilderAdmin', array(
            'apiUrl' => rest_url('form-builder/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'saveNonce' => wp_create_nonce('form_builder_save'),
            'adminUrl' => admin_url('admin.php'),
            'cacheKey' => time(), // Additional cache buster
            'isDev' => defined('WP_DEBUG') && WP_DEBUG,
        ));
    }
    
    /**
     * Enqueue frontend assets
     * 
     * Loads CSS and JavaScript files for frontend forms.
     * 
     * @return void
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
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'form-builder') === false) {
            return;
        }
        
        $email_handler_exists = class_exists('Form_Builder_Email_Handler');
        echo '<div class="notice notice-info"><p>';
        echo '<strong>Konstruct Form Builder Debug:</strong> Version ' . esc_html(FORM_BUILDER_VERSION);
        echo ' | Email Handler: ' . ($email_handler_exists ? '✓ Loaded' : '✗ Not Found');
        echo '</p></div>';
    }

    /**
     * Debug form configuration
     * 
     * Returns form configuration for debugging purposes.
     * 
     * @param WP_REST_Request $request REST API request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function debug_form($request) {
        $id = $request->get_param('id');
        $storage = new Form_Builder_Storage();
        $form = $storage->get_form_by_id($id);
        
        if (!$form) {
            return new WP_Error('form_not_found', 'Form not found', array('status' => 404));
        }
        
        $form_config = json_decode($form['form_config'], true);
        
        return new WP_REST_Response(array(
            'form_id' => $id,
            'form_name' => $form['form_name'],
            'notifications' => isset($form_config['notifications']) ? $form_config['notifications'] : 'Not set',
            'raw_config' => $form_config
        ), 200);
    }

    /**
     * Test email functionality
     * 
     * Sends a test email to verify email configuration.
     * 
     * @param WP_REST_Request $request REST API request object
     * @return WP_REST_Response|WP_Error Response object or error
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
     * 
     * Generates a unique identifier for form submissions.
     * 
     * @return string UUID string
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

    /**
     * Send step notification (independent of webhooks)
     * 
     * Sends email notification for form step completion.
     * 
     * @param WP_REST_Request $request REST API request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function send_step_notification($request) {
        $params = $request->get_json_params();
        
        // Validate required parameters
        if (empty($params['form_id']) || empty($params['page_number']) || !isset($params['form_data'])) {
            return new WP_Error(
                'missing_params',
                'form_id, page_number, and form_data are required',
                array('status' => 400)
            );
        }
        
        $form_id = intval($params['form_id']);
        $page_number = intval($params['page_number']);
        $form_data = $params['form_data'];
        $submission_uuid = isset($params['submission_uuid']) ? sanitize_text_field($params['submission_uuid']) : $this->generate_uuid();
        
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
        
        // Send email notification
        $email_handler = new Form_Builder_Email_Handler();
        $email_result = $email_handler->send_step_notification($form_id, $page_number, $form_data, $submission_uuid);
        
        return new WP_REST_Response(array(
            'success' => true,
            'email_sent' => $email_result,
            'submission_uuid' => $submission_uuid
        ), 200);
    }
    
    /**
     * Export form
     * 
     * Exports a form configuration as JSON.
     * 
     * @param WP_REST_Request $request REST API request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function export_form($request) {
        $id = $request->get_param('id');
        $builder = new Form_Builder_Builder();
        
        $export_data = $builder->export_form($id);
        
        if (is_wp_error($export_data)) {
            return $export_data;
        }
        
        // Set headers for file download
        $filename = 'form-' . $export_data['form']['slug'] . '-' . date('Y-m-d') . '.json';
        
        return new WP_REST_Response($export_data, 200, array(
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ));
    }
    
    /**
     * Import form
     * 
     * Imports a form configuration from JSON.
     * 
     * @param WP_REST_Request $request REST API request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function import_form($request) {
        $files = $request->get_file_params();
        
        if (empty($files['import_file'])) {
            return new WP_Error('no_file', 'No import file provided', array('status' => 400));
        }
        
        $file = $files['import_file'];
        
        // Validate file type
        if ($file['type'] !== 'application/json' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'json') {
            return new WP_Error('invalid_file_type', 'Only JSON files are allowed', array('status' => 400));
        }
        
        // Read file contents
        $json_content = file_get_contents($file['tmp_name']);
        
        if ($json_content === false) {
            return new WP_Error('file_read_error', 'Could not read file', array('status' => 500));
        }
        
        // Import the form
        $builder = new Form_Builder_Builder();
        $result = $builder->import_form($json_content);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'form' => $result,
            'message' => 'Form imported successfully'
        ), 200);
    }
    
    /**
     * Get asset version for cache busting
     * 
     * Generates version string for CSS/JS assets to prevent caching issues.
     * 
     * @param string $asset_path Path to asset file
     * @return string Version string
     */
    private function get_asset_version($asset_path) {
        // In development mode, use file modification time for aggressive cache busting
        if (FORM_BUILDER_DEV_MODE) {
            $file_path = FORM_BUILDER_PLUGIN_DIR . $asset_path;
            if (file_exists($file_path)) {
                return filemtime($file_path);
            }
            // If file doesn't exist, use current timestamp
            return time();
        }
        
        // In production, use version + timestamp for cache busting with LiteSpeed
        return FORM_BUILDER_VERSION . '.' . time();
    }
}

// Initialize plugin
Form_Builder_Microsaas::get_instance();

