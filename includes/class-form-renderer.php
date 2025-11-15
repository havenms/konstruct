<?php
/**
 * Form Renderer Class
 * Handles rendering forms on the frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

class Form_Builder_Renderer {
    
    private $storage;
    
    public function __construct() {
        $this->storage = new Form_Builder_Storage();
    }
    
    /**
     * Render form by slug or ID
     */
    public function render_form($identifier) {
        // Try to get by slug first, then by ID
        if (is_numeric($identifier)) {
            $form = $this->storage->get_form_by_id($identifier);
        } else {
            $form = $this->storage->get_form_by_slug($identifier);
        }
        
        if (!$form) {
            return '<p>' . __('Form not found', 'form-builder-microsaas') . '</p>';
        }
        
        // Generate unique form instance ID
        $form_instance_id = 'form-builder-' . $form['id'] . '-' . uniqid();
        
        // Enqueue scripts and styles
        $this->enqueue_form_assets($form_instance_id, $form);
        
        // Render form HTML
        ob_start();
        ?>
        <div class="form-builder-container" id="<?php echo esc_attr($form_instance_id); ?>" data-form-id="<?php echo esc_attr($form['id']); ?>">
            <form class="form-builder-form" data-instance-id="<?php echo esc_attr($form_instance_id); ?>">
                <div class="form-builder-pages">
                    <?php $this->render_pages($form['form_config']); ?>
                </div>
                
                <div class="form-builder-navigation">
                    <button type="button" class="form-builder-btn form-builder-btn-back" style="display: none;">
                        <?php _e('Back', 'form-builder-microsaas'); ?>
                    </button>
                    <button type="button" class="form-builder-btn form-builder-btn-next">
                        <?php _e('Next', 'form-builder-microsaas'); ?>
                    </button>
                    <button type="submit" class="form-builder-btn form-builder-btn-submit" style="display: none;">
                        <?php _e('Submit', 'form-builder-microsaas'); ?>
                    </button>
                </div>
                
                <div class="form-builder-progress">
                    <span class="form-builder-page-current">1</span>
                    <span class="form-builder-page-separator">/</span>
                    <span class="form-builder-page-total"><?php echo count($form['form_config']['pages']); ?></span>
                </div>
            </form>
            
            <div class="form-builder-success" style="display: none;">
                <h3><?php _e('Form Submitted Successfully!', 'form-builder-microsaas'); ?></h3>
                <p><?php _e('Thank you for your submission.', 'form-builder-microsaas'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render form pages
     */
    private function render_pages($config) {
        if (empty($config['pages']) || !is_array($config['pages'])) {
            return;
        }
        
        foreach ($config['pages'] as $index => $page) {
            $page_number = $index + 1;
            $is_first = $page_number === 1;
            ?>
            <div class="form-builder-page" data-page="<?php echo esc_attr($page_number); ?>" <?php echo $is_first ? '' : 'style="display: none;"'; ?>>
                <?php $this->render_fields($page['fields']); ?>
            </div>
            <?php
        }
    }
    
    /**
     * Render form fields
     */
    private function render_fields($fields) {
        if (empty($fields) || !is_array($fields)) {
            return;
        }
        
        foreach ($fields as $field) {
            $this->render_field($field);
        }
    }
    
    /**
     * Render individual field
     */
    private function render_field($field) {
        $field_id = isset($field['id']) ? $field['id'] : 'field_' . uniqid();
        $field_name = isset($field['name']) && !empty($field['name']) ? esc_attr($field['name']) : $field_id;
        $field_label = isset($field['label']) ? esc_html($field['label']) : '';
        $field_type = isset($field['type']) ? esc_attr($field['type']) : 'text';
        $required = isset($field['required']) && $field['required'] ? 'required' : '';
        $placeholder = isset($field['placeholder']) ? esc_attr($field['placeholder']) : '';
        
        ?>
        <div class="form-builder-field form-builder-field-<?php echo esc_attr($field_type); ?>" data-field-id="<?php echo esc_attr($field_id); ?>">
            <label for="<?php echo esc_attr($field_id); ?>">
                <?php echo $field_label; ?>
                <?php if ($required): ?>
                    <span class="form-builder-required">*</span>
                <?php endif; ?>
            </label>
            
            <?php
            switch ($field_type) {
                case 'textarea':
                    $this->render_textarea($field_id, $field_name, $required, $placeholder);
                    break;
                
                case 'select':
                    $this->render_select($field_id, $field_name, $field, $required);
                    break;
                
                case 'radio':
                    $this->render_radio($field_id, $field_name, $field, $required);
                    break;
                
                case 'checkbox':
                    $this->render_checkbox($field_id, $field_name, $field, $required);
                    break;
                
                case 'file':
                    $this->render_file($field_id, $field_name, $required);
                    break;
                
                case 'link':
                    $this->render_link($field_id, $field_name, $field);
                    break;
                
                default:
                    $this->render_input($field_id, $field_name, $field_type, $required, $placeholder);
                    break;
            }
            ?>
            
            <div class="form-builder-error-message" style="display: none;"></div>
        </div>
        <?php
    }
    
    /**
     * Render text input
     */
    private function render_input($id, $name, $type, $required, $placeholder) {
        // Map field names to autocomplete attributes
        $autocomplete_map = array(
            'email' => 'email',
            'phone' => 'tel',
            'name' => 'name',
            'first' => 'given-name',
            'last' => 'family-name',
            'address' => 'street-address',
            'city' => 'address-level2',
            'state' => 'address-level1',
            'zip' => 'postal-code',
            'country' => 'country-name',
            'company' => 'organization',
            'password' => 'current-password',
            'url' => 'url',
            'username' => 'username',
        );

        $autocomplete = 'off';
        $input_type = $type;
        
        // Detect autocomplete based on field name
        $name_lower = strtolower($name);
        foreach ($autocomplete_map as $key => $attr) {
            if (strpos($name_lower, $key) !== false) {
                $autocomplete = $attr;
                break;
            }
        }

        // Map field type to autocomplete
        if ($type === 'email') {
            $autocomplete = 'email';
        } elseif ($type === 'tel') {
            $autocomplete = 'tel';
        } elseif ($type === 'url') {
            $autocomplete = 'url';
        }
        ?>
        <input 
            type="<?php echo esc_attr($input_type); ?>" 
            id="<?php echo esc_attr($id); ?>" 
            name="<?php echo esc_attr($name); ?>" 
            class="form-builder-input"
            autocomplete="<?php echo esc_attr($autocomplete); ?>"
            <?php echo $required ? 'required' : ''; ?>
            <?php echo $placeholder ? 'placeholder="' . esc_attr($placeholder) . '"' : ''; ?>
        />
        <?php
    }
    
    /**
     * Render textarea
     */
    private function render_textarea($id, $name, $required, $placeholder) {
        ?>
        <textarea 
            id="<?php echo esc_attr($id); ?>" 
            name="<?php echo esc_attr($name); ?>" 
            class="form-builder-textarea"
            <?php echo $required ? 'required' : ''; ?>
            <?php echo $placeholder ? 'placeholder="' . esc_attr($placeholder) . '"' : ''; ?>
        ></textarea>
        <?php
    }
    
    /**
     * Render select dropdown
     */
    private function render_select($id, $name, $field, $required) {
        $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : array();
        ?>
        <select 
            id="<?php echo esc_attr($id); ?>" 
            name="<?php echo esc_attr($name); ?>" 
            class="form-builder-select"
            <?php echo $required ? 'required' : ''; ?>
        >
            <option value=""><?php _e('Select...', 'form-builder-microsaas'); ?></option>
            <?php foreach ($options as $option): ?>
                <option value="<?php echo esc_attr($option); ?>"><?php echo esc_html($option); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }
    
    /**
     * Render radio buttons
     */
    private function render_radio($id, $name, $field, $required) {
        $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : array();
        ?>
        <div class="form-builder-radio-group">
            <?php foreach ($options as $index => $option): ?>
                <label class="form-builder-radio-option">
                    <input 
                        type="radio" 
                        name="<?php echo esc_attr($name); ?>" 
                        value="<?php echo esc_attr($option); ?>"
                        class="form-builder-radio"
                        <?php echo $required ? 'required' : ''; ?>
                    />
                    <span><?php echo esc_html($option); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render checkboxes
     */
    private function render_checkbox($id, $name, $field, $required) {
        $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : array();
        ?>
        <div class="form-builder-checkbox-group">
            <?php foreach ($options as $index => $option): ?>
                <label class="form-builder-checkbox-option">
                    <input 
                        type="checkbox" 
                        name="<?php echo esc_attr($name); ?>[]" 
                        value="<?php echo esc_attr($option); ?>"
                        class="form-builder-checkbox"
                    />
                    <span><?php echo esc_html($option); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render file input
     */
    private function render_file($id, $name, $required) {
        ?>
        <input 
            type="file" 
            id="<?php echo esc_attr($id); ?>" 
            name="<?php echo esc_attr($name); ?>" 
            class="form-builder-file"
            <?php echo $required ? 'required' : ''; ?>
        />
        <?php
    }
    
    /**
     * Render link button
     */
    private function render_link($id, $name, $field) {
        $url = isset($field['url']) && !empty($field['url']) ? esc_url($field['url']) : '#';
        $target = isset($field['target']) && $field['target'] === 'new' ? '_blank' : '_self';
        $button_text = isset($field['button_text']) && !empty($field['button_text']) ? esc_html($field['button_text']) : esc_html($field['label']);
        $button_style = isset($field['button_style']) ? esc_attr($field['button_style']) : 'primary';
        
        ?>
        <a 
            href="<?php echo $url; ?>" 
            target="<?php echo esc_attr($target); ?>"
            class="form-builder-link-button form-builder-link-<?php echo $button_style; ?>"
            id="<?php echo esc_attr($id); ?>"
            <?php if ($target === '_blank'): ?>
                rel="noopener noreferrer"
            <?php endif; ?>
        >
            <?php echo $button_text; ?>
        </a>
        <?php
    }
    
    /**
     * Enqueue form assets
     */
    private function enqueue_form_assets($form_instance_id, $form) {
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
        
        // Localize script with form data using instance ID
        wp_add_inline_script('form-builder-frontend',
            'window.formBuilderData = window.formBuilderData || {}; ' .
            'window.formBuilderData["' . esc_js($form_instance_id) . '"] = ' . json_encode(array(
                'formId' => $form['id'],
                'formSlug' => $form['form_slug'],
                'formConfig' => $form['form_config'],
                'submissionUuid' => $this->generate_uuid(),
            )) . ';',
            'after'
        );
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

