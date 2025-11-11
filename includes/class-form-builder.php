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
                    'enabled' => false,
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
                    'enabled' => false,
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
        );
    }
}

