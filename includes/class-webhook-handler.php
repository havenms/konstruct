<?php
/**
 * Webhook Handler Class
 * Handles server-side webhook POST requests and logging
 */

if (!defined('ABSPATH')) {
    exit;
}

class Form_Builder_Webhook_Handler {
    
    private $storage;
    private $email_handler;
    
    public function __construct() {
        $this->storage = new Form_Builder_Storage();
        $this->email_handler = new Form_Builder_Email_Handler();
    }
    
    /**
     * Process webhook request from REST API
     */
    public function process_webhook($request) {
        $params = $request->get_json_params();
        
        // Validate required parameters
        if (empty($params['form_id']) || empty($params['webhook_url']) || !isset($params['formData'])) {
            return new WP_Error(
                'missing_params',
                'form_id, webhook_url, and formData are required',
                array('status' => 400)
            );
        }
        
        $form_id = intval($params['form_id']);
        $page_number = isset($params['page_number']) ? intval($params['page_number']) : 1;
        $webhook_url = esc_url_raw($params['webhook_url']);
        $form_data = $params['formData'];
        $submission_uuid = isset($params['submission_uuid']) ? sanitize_text_field($params['submission_uuid']) : $this->generate_uuid();
        
        // Validate form exists
        $form = $this->storage->get_form_by_id($form_id);
        if (!$form) {
            return new WP_Error(
                'form_not_found',
                'Form not found',
                array('status' => 404)
            );
        }
        
        // Validate page number is within bounds
        if (empty($form['form_config']['pages']) || $page_number < 1 || $page_number > count($form['form_config']['pages'])) {
            return new WP_Error(
                'invalid_page',
                'Invalid page number',
                array('status' => 400)
            );
        }
        
        // Validate webhook URL
        if (!filter_var($webhook_url, FILTER_VALIDATE_URL)) {
            return new WP_Error(
                'invalid_url',
                'Invalid webhook URL',
                array('status' => 400)
            );
        }
        
        // Store submission (if first time for this UUID)
        $submission_id = null;
        if (!empty($submission_uuid)) {
            // Check if submission exists
            global $wpdb;
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}form_builder_submissions WHERE submission_uuid = %s",
                $submission_uuid
            ));
            
            if (!$existing) {
                $submission_id = $this->storage->insert_submission(
                    $form_id,
                    $submission_uuid,
                    $page_number,
                    $form_data,
                    false
                );
            } else {
                // Update existing submission
                $submission_id = $existing;
                $this->update_submission_data($submission_uuid, $form_data, $page_number);
            }
        }
        
        // Sanitize form data (ensure it's an array)
        if (!is_array($form_data)) {
            $form_data = array();
        }
        
        // Limit form data size (prevent abuse)
        $form_data_json = json_encode($form_data);
        if (strlen($form_data_json) > 1000000) { // 1MB limit
            return new WP_Error(
                'payload_too_large',
                'Form data payload too large',
                array('status' => 413)
            );
        }
        
        // Prepare payload
        $payload = array(
            'formData' => $form_data
        );
        
        // Send webhook
        $start_time = microtime(true);
        $response = $this->send_webhook($webhook_url, $payload);
        $response_time = round((microtime(true) - $start_time) * 1000);
        
        // Log webhook call
        $this->storage->log_webhook(
            $submission_id,
            $form_id,
            $page_number,
            $webhook_url,
            isset($response['status_code']) ? $response['status_code'] : null,
            $response_time,
            isset($response['error']) ? $response['error'] : null
        );
        
        // Send email notification for step completion (always send, regardless of webhook status)
        // Add debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Form Builder: Attempting to send step notification for form ' . $form_id . ', page ' . $page_number);
        }
        
        $email_result = $this->email_handler->send_step_notification($form_id, $page_number, $form_data, $submission_uuid);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Form Builder: Email notification result: ' . ($email_result ? 'success' : 'failed'));
        }
        
        // Return response
        if (isset($response['error'])) {
            return new WP_Error(
                'webhook_failed',
                $response['error'],
                array(
                    'status' => isset($response['status_code']) ? $response['status_code'] : 500,
                    'response' => $response
                )
            );
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'submission_uuid' => $submission_uuid,
            'response' => $response
        ), 200);
    }
    
    /**
     * Send webhook POST request
     */
    private function send_webhook($url, $payload) {
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($payload),
            'timeout' => 30,
            'sslverify' => true,
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'error' => $response->get_error_message(),
                'status_code' => null,
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        return array(
            'status_code' => $status_code,
            'body' => $body,
            'success' => $status_code >= 200 && $status_code < 300,
        );
    }
    
    /**
     * Update submission data
     */
    private function update_submission_data($submission_uuid, $form_data, $page_number) {
        global $wpdb;
        
        if (is_array($form_data)) {
            $form_data_json = json_encode($form_data);
        } else {
            $form_data_json = $form_data;
        }
        
        $wpdb->update(
            $wpdb->prefix . 'form_builder_submissions',
            array(
                'form_data' => $form_data_json,
                'page_number' => intval($page_number),
            ),
            array('submission_uuid' => sanitize_text_field($submission_uuid)),
            array('%s', '%d'),
            array('%s')
        );
    }
    
    /**
     * Mark submission as delivered
     */
    public function mark_delivered($submission_uuid) {
        return $this->storage->mark_submission_delivered($submission_uuid);
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

