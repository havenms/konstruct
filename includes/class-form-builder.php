<?php
/**
 * Form Builder Class
 * Handles admin form builder logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class Form_Builder_Builder {
    
    private $storage;
    
    public function __construct() {
        $this->storage = new Form_Builder_Storage();
    }
    
    /**
     * Get default form structure
     */
    public function get_default_form() {
        return array(
            'name' => '',
            'pages' => array(
                array(
                    'pageNumber' => 1,
                    'fields' => array(),
                    'webhook' => array(
                        'enabled' => false,
                        'url' => '',
                        'method' => 'POST'
                    ),
                    'customJS' => ''
                )
            ),
            'notifications' => array(
                'step_notifications' => array(
                    'enabled' => true,
                    'recipients' => '',
                    'recipient_field' => '',
                    'include_admin' => true,
                    'subject' => 'Form Step Completed - {{form_name}}',
                    'message' => 'Hello,

A step has been completed in the form "{{form_name}}".

Step {{page_number}} was completed on {{date}}.

Submission ID: {{submission_uuid}}

{{dynamic_fields}}

Best regards,
{{site_name}}'
                ),
                'submission_notifications' => array(
                    'enabled' => true,
                    'recipients' => '',
                    'recipient_field' => '',
                    'include_admin' => true,
                    'subject' => 'New Form Submission - {{form_name}}',
                    'message' => 'Hello,

A new form submission has been received for "{{form_name}}".

Submitted on: {{date}}.

Submission ID: {{submission_uuid}}

{{dynamic_fields}}

Best regards,
{{site_name}}'
                )
            )
        );
    }
    
    /**
     * Get default field structure
     */
    public function get_default_field() {
        return array(
            'id' => 'field_' . uniqid(),
            'name' => '',
            'label' => '',
            'type' => 'text',
            'required' => false,
            'placeholder' => '',
            'options' => array() // For select, radio, checkbox
        );
    }
    
    /**
     * Validate form config
     */
    public function validate_form_config($config) {
        $errors = array();
        
        if (empty($config['name'])) {
            $errors[] = 'Form name is required';
        }
        
        if (empty($config['pages']) || !is_array($config['pages'])) {
            $errors[] = 'At least one page is required';
        }
        
        foreach ($config['pages'] as $index => $page) {
            $page_num = $index + 1;
            
            if (empty($page['fields']) || !is_array($page['fields'])) {
                $errors[] = "Page {$page_num} must have at least one field";
            }
            
            // Validate webhook URL if enabled
            if (isset($page['webhook']['enabled']) && $page['webhook']['enabled']) {
                if (empty($page['webhook']['url']) || !filter_var($page['webhook']['url'], FILTER_VALIDATE_URL)) {
                    $errors[] = "Page {$page_num} has an invalid webhook URL";
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Get available field types
     */
    public function get_field_types() {
        return array(
            'label' => 'Label/Heading',
            'text' => 'Text Input',
            'email' => 'Email',
            'tel' => 'Phone',
            'number' => 'Number',
            'textarea' => 'Textarea',
            'select' => 'Dropdown',
            'radio' => 'Radio Buttons',
            'checkbox' => 'Checkboxes',
            'file' => 'File Upload',
            'date' => 'Date',
            'link' => 'Link Button',
        );
    }
    
    /**
     * Export form to JSON format
     */
    public function export_form($form_id) {
        $form = $this->storage->get_form_by_id($form_id);
        
        if (!$form) {
            return new WP_Error('form_not_found', 'Form not found');
        }
        
        // Create export data structure
        $export_data = array(
            'export_version' => '1.0',
            'export_date' => current_time('Y-m-d H:i:s'),
            'plugin_version' => defined('FORM_BUILDER_VERSION') ? FORM_BUILDER_VERSION : '1.0.0',
            'form' => array(
                'name' => $form['form_name'],
                'slug' => $form['form_slug'],
                'config' => $form['form_config'],
                'created_at' => $form['created_at']
            )
        );
        
        return $export_data;
    }
    
    /**
     * Import form from JSON data
     */
    public function import_form($json_data, $options = array()) {
        // Parse JSON if it's a string
        if (is_string($json_data)) {
            $data = json_decode($json_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('invalid_json', 'Invalid JSON format');
            }
        } else {
            $data = $json_data;
        }
        
        // Validate import data structure
        if (!isset($data['form']) || !is_array($data['form'])) {
            return new WP_Error('invalid_format', 'Invalid import format - missing form data');
        }
        
        $form_data = $data['form'];
        
        // Validate required fields
        if (empty($form_data['name']) || empty($form_data['config'])) {
            return new WP_Error('missing_data', 'Form name and config are required');
        }
        
        // Prepare form data for import
        $import_form_data = array(
            'form_name' => sanitize_text_field($form_data['name']),
            'form_slug' => isset($form_data['slug']) ? sanitize_title($form_data['slug']) : '',
            'form_config' => $form_data['config']
        );
        
        // Handle naming conflicts
        $original_name = $import_form_data['form_name'];
        $counter = 1;
        
        // Check if form name already exists
        while ($this->form_name_exists($import_form_data['form_name'])) {
            $import_form_data['form_name'] = $original_name . ' (Copy ' . $counter . ')';
            $counter++;
        }
        
        // Clear slug to force regeneration with new name
        $import_form_data['form_slug'] = '';
        
        // Save the imported form
        $result = $this->storage->save_form($import_form_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Check if form name exists
     */
    private function form_name_exists($name) {
        global $wpdb;
        $storage = new Form_Builder_Storage();
        $forms_table = $wpdb->prefix . 'form_builder_forms';
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$forms_table} WHERE form_name = %s",
                $name
            )
        );
        
        return $count > 0;
    }
}

