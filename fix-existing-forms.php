<?php
/**
 * One-time fix to enable email notifications on existing forms
 * Add this to functions.php temporarily, run once, then remove
 */

function form_builder_fix_existing_forms() {
    global $wpdb;
    
    // Get all forms
    $forms = $wpdb->get_results("SELECT id, form_config FROM {$wpdb->prefix}form_builder_forms");
    
    $updated_count = 0;
    
    foreach ($forms as $form) {
        $config = json_decode($form->form_config, true);
        
        if (!$config) continue;
        
        $needs_update = false;
        
        // Add notifications config if missing
        if (!isset($config['notifications'])) {
            $config['notifications'] = array(
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
            );
            $needs_update = true;
        } else {
            // Enable notifications if they exist but are disabled
            if (isset($config['notifications']['step_notifications']) && !$config['notifications']['step_notifications']['enabled']) {
                $config['notifications']['step_notifications']['enabled'] = true;
                $needs_update = true;
            }
            if (isset($config['notifications']['submission_notifications']) && !$config['notifications']['submission_notifications']['enabled']) {
                $config['notifications']['submission_notifications']['enabled'] = true;
                $needs_update = true;
            }
        }
        
        if ($needs_update) {
            $updated_config = json_encode($config);
            $wpdb->update(
                "{$wpdb->prefix}form_builder_forms",
                array('form_config' => $updated_config),
                array('id' => $form->id)
            );
            $updated_count++;
        }
    }
    
    return "Updated $updated_count forms with email notifications enabled.";
}

// Uncomment to run the fix:
// echo form_builder_fix_existing_forms();
?>