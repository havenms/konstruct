<?php
/**
 * Admin Interface Handler Class
 * Manages WordPress admin interface pages and menus
 */

if (!defined('ABSPATH')) {
    exit;
}

class Form_Builder_Admin_Interface {
    
    /**
     * Add admin menu pages
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
     * Render builder admin page with cache-busting headers
     */
    public function render_builder_page() {
        $this->send_no_cache_headers();
        require_once FORM_BUILDER_PLUGIN_DIR . 'admin/builder.php';
    }

    /**
     * Render submissions admin page with cache-busting headers
     */
    public function render_submissions_page() {
        $this->send_no_cache_headers();
        require_once FORM_BUILDER_PLUGIN_DIR . 'admin/submissions.php';
    }
    
    /**
     * Send no-cache headers for admin pages to help with LiteSpeed Cache and other caching systems
     */
    private function send_no_cache_headers() {
        if (!headers_sent()) {
            header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        }
    }
}