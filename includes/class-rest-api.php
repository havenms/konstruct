<?php
/**
 * REST API Handler Class
 * Manages all REST API endpoints for the form builder
 */

if (!defined('ABSPATH')) {
    exit;
}

class Form_Builder_REST_API {
    
    private $file_handler;
    
    public function __construct() {
        $this->file_handler = new Form_Builder_File_Handler();
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
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

        // Only register debug endpoint in WP_DEBUG mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            register_rest_route('form-builder/v1', '/debug-form/(?P<id>\d+)', array(
                'methods' => 'GET',
                'callback' => array($this, 'debug_form'),
                'permission_callback' => array($this, 'check_admin_permission'),
            ));
        }

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

        // Protected file download route (admins only) - using secure handler
        register_rest_route('form-builder/v1', '/file', array(
            'methods' => 'GET',
            'callback' => array($this->file_handler, 'serve_protected_file'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'submission_uuid' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'field' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
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
            
            // Collect form data
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
            $form_data = $this->file_handler->process_uploads_and_enrich_form_data($submission_uuid, $form_data, $_FILES);
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
     * Get submissions
     */
    public function get_submissions($request) {
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
     * Test email functionality (debug endpoint)
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
     * Debug form configuration (only available in WP_DEBUG mode)
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
     * Send step notification (independent of webhooks)
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