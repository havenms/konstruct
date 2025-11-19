<?php
/**
 * Form Storage Class
 * Handles all database operations for forms, submissions, and webhook logs
 */

if (!defined('ABSPATH')) {
    exit;
}

class Form_Builder_Storage {
    
    private $forms_table;
    private $submissions_table;
    private $logs_table;
    
    public function __construct() {
        global $wpdb;
        $this->forms_table = $wpdb->prefix . 'form_builder_forms';
        $this->submissions_table = $wpdb->prefix . 'form_builder_submissions';
        $this->logs_table = $wpdb->prefix . 'form_builder_webhook_logs';
    }
    
    /**
     * Save form (insert or update)
     */
    public function save_form($data) {
        global $wpdb;
        
        // Validate required fields
        if (empty($data['form_name']) || empty($data['form_config'])) {
            return new WP_Error('invalid_data', 'Form name and config are required');
        }
        
        // Generate slug if not provided
        if (empty($data['form_slug'])) {
            $data['form_slug'] = $this->generate_slug($data['form_name']);
        } else {
            $data['form_slug'] = sanitize_title($data['form_slug']);
        }
        
        // Ensure slug is unique
        $data['form_slug'] = $this->ensure_unique_slug($data['form_slug'], isset($data['id']) ? $data['id'] : null);
        
        // Validate JSON config
        if (is_array($data['form_config'])) {
            $form_config = json_encode($data['form_config']);
        } else {
            $form_config = $data['form_config'];
            // Validate it's valid JSON
            json_decode($form_config);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('invalid_json', 'Invalid JSON in form config');
            }
        }
        
        $form_data = array(
            'form_name' => sanitize_text_field($data['form_name']),
            'form_slug' => $data['form_slug'],
            'form_config' => $form_config,
            'updated_at' => current_time('mysql'),
        );
        
        // Update existing form
        if (isset($data['id']) && !empty($data['id'])) {
            $form_id = intval($data['id']);

            // IMPORTANT: Do not include primary key `id` in the data to update
            $result = $wpdb->update(
                $this->forms_table,
                $form_data,
                array('id' => $form_id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );

            if ($result === false) {
                return new WP_Error('update_failed', 'Failed to update form');
            }

            return $this->get_form_by_id($form_id);
        }
        
        // Insert new form
        $form_data['created_at'] = current_time('mysql');
        $result = $wpdb->insert(
            $this->forms_table,
            $form_data,
            array('%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('insert_failed', 'Failed to create form');
        }
        
        return $this->get_form_by_id($wpdb->insert_id);
    }
    
    /**
     * Get form by ID
     */
    public function get_form_by_id($id) {
        global $wpdb;
        
        $form = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->forms_table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
        
        if ($form) {
            $form['form_config'] = json_decode($form['form_config'], true);
        }
        
        return $form;
    }
    
    /**
     * Get form by slug
     */
    public function get_form_by_slug($slug) {
        global $wpdb;
        
        $form = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->forms_table} WHERE form_slug = %s",
                $slug
            ),
            ARRAY_A
        );
        
        if ($form) {
            $form['form_config'] = json_decode($form['form_config'], true);
        }
        
        return $form;
    }
    
    /**
     * Get all forms
     */
    public function get_all_forms($pagination = array()) {
        global $wpdb;
        
        $page = isset($pagination['page']) ? intval($pagination['page']) : 1;
        $per_page = isset($pagination['per_page']) ? intval($pagination['per_page']) : 20;
        $offset = ($page - 1) * $per_page;
        
        $forms = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->forms_table} ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );
        
        foreach ($forms as &$form) {
            $form['form_config'] = json_decode($form['form_config'], true);
        }
        
        // Note: This query doesn't use user input, so it's safe without prepare()
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->forms_table}");
        
        return array(
            'forms' => $forms,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
        );
    }
    
    /**
     * Delete form
     */
    public function delete_form($id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->forms_table,
            array('id' => intval($id)),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Insert submission
     */
    public function insert_submission($form_id, $submission_uuid, $page_number, $form_data, $delivered = false) {
        global $wpdb;
        
        if (is_array($form_data)) {
            $form_data_json = json_encode($form_data);
        } else {
            $form_data_json = $form_data;
        }
        
        $result = $wpdb->insert(
            $this->submissions_table,
            array(
                'form_id' => intval($form_id),
                'submission_uuid' => sanitize_text_field($submission_uuid),
                'page_number' => intval($page_number),
                'form_data' => $form_data_json,
                'delivered' => $delivered ? 1 : 0,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%d', '%s', '%d', '%s')
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update submission delivery status
     */
    public function mark_submission_delivered($submission_uuid) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->submissions_table,
            array('delivered' => 1),
            array('submission_uuid' => sanitize_text_field($submission_uuid)),
            array('%d'),
            array('%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Log webhook call
     */
    public function log_webhook($submission_id, $form_id, $page_number, $webhook_url, $status_code = null, $response_ms = null, $error_message = null) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->logs_table,
            array(
                'submission_id' => $submission_id ? intval($submission_id) : null,
                'form_id' => intval($form_id),
                'page_number' => intval($page_number),
                'webhook_url' => esc_url_raw($webhook_url),
                'status_code' => $status_code ? intval($status_code) : null,
                'response_ms' => $response_ms ? intval($response_ms) : null,
                'error_message' => $error_message ? sanitize_text_field($error_message) : null,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Generate unique slug from name
     */
    private function generate_slug($name) {
        $slug = sanitize_title($name);
        return $this->ensure_unique_slug($slug);
    }
    
    /**
     * Ensure slug is unique
     */
    private function ensure_unique_slug($slug, $exclude_id = null) {
        global $wpdb;
        
        $original_slug = $slug;
        $counter = 1;
        
        while (true) {
            if ($exclude_id) {
                $query = $wpdb->prepare(
                    "SELECT id FROM {$this->forms_table} WHERE form_slug = %s AND id != %d",
                    $slug,
                    $exclude_id
                );
            } else {
                $query = $wpdb->prepare(
                    "SELECT id FROM {$this->forms_table} WHERE form_slug = %s",
                    $slug
                );
            }
            
            $existing = $wpdb->get_var($query);
            
            if (!$existing) {
                return $slug;
            }
            
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
    }
}

