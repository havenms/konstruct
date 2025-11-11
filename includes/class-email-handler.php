<?php
/**
 * Email Handler Class
 * Handles email notifications for form steps and final submissions
 */

if (!defined('ABSPATH')) {
    exit;
}

class Form_Builder_Email_Handler {
    
    private $storage;
    
    public function __construct() {
        $this->storage = new Form_Builder_Storage();
    }
    
    /**
     * Send step completion email notification
     */
    public function send_step_notification($form_id, $page_number, $form_data, $submission_uuid) {
        $form = $this->storage->get_form_by_id($form_id);
        if (!$form) {
            return false;
        }
        
        // Get notification settings from form config
        $form_config = json_decode($form['form_config'], true);
        if (!$form_config || empty($form_config['notifications'])) {
            return false;
        }
        
        $notifications = $form_config['notifications'];
        
        // Check if step notifications are enabled
        if (empty($notifications['step_notifications']) || !$notifications['step_notifications']['enabled']) {
            return false;
        }
        
        $step_config = $notifications['step_notifications'];
        $recipients = $this->get_recipients($step_config, $form_data);
        
        if (empty($recipients)) {
            return false;
        }
        
        // Prepare email content
        $subject = $this->replace_placeholders(
            $step_config['subject'] ?? 'Form Step Completed - {{form_name}}',
            $form,
            $form_data,
            $page_number,
            $submission_uuid
        );
        
        $message = $this->replace_placeholders(
            $step_config['message'] ?? $this->get_default_step_message(),
            $form,
            $form_data,
            $page_number,
            $submission_uuid
        );
        
        // Send emails
        $success = true;
        foreach ($recipients as $recipient) {
            if (!$this->send_email($recipient, $subject, $message, $form_data)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Send final submission email notification
     */
    public function send_submission_notification($form_id, $form_data, $submission_uuid) {
        $form = $this->storage->get_form_by_id($form_id);
        if (!$form) {
            return false;
        }
        
        // Get notification settings from form config
        $form_config = json_decode($form['form_config'], true);
        if (!$form_config || empty($form_config['notifications'])) {
            return false;
        }
        
        $notifications = $form_config['notifications'];
        
        // Check if submission notifications are enabled
        if (empty($notifications['submission_notifications']) || !$notifications['submission_notifications']['enabled']) {
            return false;
        }
        
        $submission_config = $notifications['submission_notifications'];
        $recipients = $this->get_recipients($submission_config, $form_data);
        
        if (empty($recipients)) {
            return false;
        }
        
        // Prepare email content
        $subject = $this->replace_placeholders(
            $submission_config['subject'] ?? 'New Form Submission - {{form_name}}',
            $form,
            $form_data,
            null,
            $submission_uuid
        );
        
        $message = $this->replace_placeholders(
            $submission_config['message'] ?? $this->get_default_submission_message(),
            $form,
            $form_data,
            null,
            $submission_uuid
        );
        
        // Send emails
        $success = true;
        foreach ($recipients as $recipient) {
            if (!$this->send_email($recipient, $subject, $message, $form_data)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Get email recipients from configuration
     */
    private function get_recipients($config, $form_data) {
        $recipients = array();
        
        // Static recipients
        if (!empty($config['recipients'])) {
            if (is_string($config['recipients'])) {
                $recipients[] = $config['recipients'];
            } elseif (is_array($config['recipients'])) {
                $recipients = array_merge($recipients, $config['recipients']);
            }
        }
        
        // Dynamic recipient from form field
        if (!empty($config['recipient_field']) && !empty($form_data[$config['recipient_field']])) {
            $recipients[] = $form_data[$config['recipient_field']];
        }
        
        // Admin email as fallback
        if (empty($recipients) && (!isset($config['include_admin']) || $config['include_admin'])) {
            $recipients[] = get_option('admin_email');
        }
        
        // Validate and sanitize email addresses
        $valid_recipients = array();
        foreach ($recipients as $email) {
            $email = sanitize_email(trim($email));
            if (is_email($email)) {
                $valid_recipients[] = $email;
            }
        }
        
        return array_unique($valid_recipients);
    }
    
    /**
     * Replace placeholders in email content
     */
    private function replace_placeholders($content, $form, $form_data, $page_number = null, $submission_uuid = null) {
        $placeholders = array(
            '{{form_name}}' => $form['form_name'],
            '{{form_slug}}' => $form['form_slug'],
            '{{submission_uuid}}' => $submission_uuid ?? '',
            '{{page_number}}' => $page_number ?? '',
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_url}}' => get_site_url(),
            '{{admin_email}}' => get_option('admin_email'),
            '{{date}}' => current_time('Y-m-d H:i:s'),
        );
        
        // Add form data placeholders
        if (is_array($form_data)) {
            foreach ($form_data as $field_name => $field_value) {
                if (is_string($field_value) || is_numeric($field_value)) {
                    $placeholders['{{' . $field_name . '}}'] = $field_value;
                } elseif (is_array($field_value)) {
                    $placeholders['{{' . $field_name . '}}'] = implode(', ', $field_value);
                }
            }
        }
        
        // Replace placeholders
        $content = str_replace(array_keys($placeholders), array_values($placeholders), $content);
        
        return $content;
    }
    
    /**
     * Send email using WordPress wp_mail
     */
    private function send_email($to, $subject, $message, $form_data = array()) {
        // Set content type to HTML
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
        
        // Set from name and email
        add_filter('wp_mail_from', array($this, 'get_from_email'));
        add_filter('wp_mail_from_name', array($this, 'get_from_name'));
        
        // Convert line breaks to HTML
        $html_message = nl2br($message);
        
        // Add basic HTML structure
        $html_message = $this->wrap_in_html_template($html_message, $subject, $form_data);
        
        // Send email
        $result = wp_mail($to, $subject, $html_message);
        
        // Remove filters
        remove_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
        remove_filter('wp_mail_from', array($this, 'get_from_email'));
        remove_filter('wp_mail_from_name', array($this, 'get_from_name'));
        
        return $result;
    }
    
    /**
     * Set email content type to HTML
     */
    public function set_html_content_type() {
        return 'text/html';
    }
    
    /**
     * Get from email address
     */
    public function get_from_email() {
        return get_option('admin_email');
    }
    
    /**
     * Get from name
     */
    public function get_from_name() {
        return get_bloginfo('name');
    }
    
    /**
     * Wrap message in HTML template
     */
    private function wrap_in_html_template($message, $subject, $form_data = array()) {
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        
        $html = '<!DOCTYPE html>';
        $html .= '<html><head><meta charset="UTF-8"><title>' . esc_html($subject) . '</title></head>';
        $html .= '<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">';
        $html .= '<div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px;">';
        $html .= '<h2 style="color: #007cba; margin-top: 0;">' . esc_html($subject) . '</h2>';
        $html .= '</div>';
        $html .= '<div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';
        $html .= $message;
        
        // Add form data summary if available
        if (!empty($form_data) && is_array($form_data)) {
            $html .= '<hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">';
            $html .= '<h3 style="color: #007cba;">Form Data Summary:</h3>';
            $html .= '<table style="width: 100%; border-collapse: collapse;">';
            foreach ($form_data as $field_name => $field_value) {
                if (is_string($field_value) || is_numeric($field_value)) {
                    $display_value = esc_html($field_value);
                } elseif (is_array($field_value)) {
                    $display_value = esc_html(implode(', ', $field_value));
                } else {
                    continue;
                }
                
                $html .= '<tr>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; width: 30%;">' . esc_html(ucfirst(str_replace('_', ' ', $field_name))) . ':</td>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . $display_value . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
        }
        
        $html .= '</div>';
        $html .= '<div style="text-align: center; margin-top: 20px; color: #666; font-size: 12px;">';
        $html .= '<p>This email was sent from <a href="' . esc_url($site_url) . '">' . esc_html($site_name) . '</a></p>';
        $html .= '</div>';
        $html .= '</body></html>';
        
        return $html;
    }
    
    /**
     * Get default step completion message template
     */
    private function get_default_step_message() {
        return "Hello,\n\nA step has been completed in the form \"{{form_name}}\".\n\nStep {{page_number}} was completed on {{date}}.\n\nSubmission ID: {{submission_uuid}}\n\nBest regards,\n{{site_name}}";
    }
    
    /**
     * Get default submission message template
     */
    private function get_default_submission_message() {
        return "Hello,\n\nA new form submission has been received for \"{{form_name}}\".\n\nSubmitted on: {{date}}\nSubmission ID: {{submission_uuid}}\n\nPlease review the form data below.\n\nBest regards,\n{{site_name}}";
    }
    
    /**
     * Test email configuration
     */
    public function test_email_config($to_email) {
        $subject = 'Form Builder Email Test - ' . get_bloginfo('name');
        $message = "This is a test email from the Form Builder plugin.\n\nIf you received this email, your email configuration is working correctly.\n\nSent on: " . current_time('Y-m-d H:i:s');
        
        return $this->send_email($to_email, $subject, $message);
    }
}