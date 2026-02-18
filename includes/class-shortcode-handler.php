<?php
/**
 * Shortcode Handler Class
 * Manages the [form_builder] shortcode
 */

if (!defined('ABSPATH')) {
    exit;
}

class Form_Builder_Shortcode_Handler {
    
    /**
     * Render form shortcode
     */
    public function render_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => '',
        ), $atts, 'form_builder');
        
        if (empty($atts['id'])) {
            return '<p>' . __('Form ID is required', 'form-builder-microsaas') . '</p>';
        }
        
        // Validate that the form exists
        $storage = new Form_Builder_Storage();
        $form = $storage->get_form_by_id($atts['id']);
        
        if (!$form) {
            return '<p>' . __('Form not found', 'form-builder-microsaas') . '</p>';
        }
        
        $renderer = new Form_Builder_Renderer();
        return $renderer->render_form($atts['id']);
    }
}