<?php
/**
 * Asset Manager Class
 * Handles enqueuing of scripts and styles with cache busting
 */

if (!defined('ABSPATH')) {
    exit;
}

class Form_Builder_Asset_Manager {
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'form-builder') === false) {
            return;
        }
        
        // Add cache-busting headers for admin pages
        $this->send_no_cache_headers();
        
        // Generate dynamic version for cache busting
        $css_version = $this->get_asset_version('admin/builder.css');
        $js_version = $this->get_asset_version('admin/builder.js');
        
        wp_enqueue_style(
            'form-builder-admin',
            FORM_BUILDER_PLUGIN_URL . 'admin/builder.css',
            array(),
            $css_version
        );
        
        wp_enqueue_script(
            'form-builder-admin',
            FORM_BUILDER_PLUGIN_URL . 'admin/builder.js',
            array('jquery'),
            $js_version,
            true
        );
        
        wp_localize_script('form-builder-admin', 'formBuilderAdmin', array(
            'apiUrl' => rest_url('form-builder/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'saveNonce' => wp_create_nonce('form_builder_save'),
            'adminUrl' => admin_url('admin.php'),
            'cacheKey' => time(), // Additional cache buster
            'isDev' => defined('WP_DEBUG') && WP_DEBUG,
        ));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue if a form shortcode is present on the page
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'form_builder')) {
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
        }
    }
    
    /**
     * Get asset version for cache busting
     */
    private function get_asset_version($asset_path) {
        // In development mode, use file modification time for aggressive cache busting
        if (FORM_BUILDER_DEV_MODE) {
            $file_path = FORM_BUILDER_PLUGIN_DIR . $asset_path;
            if (file_exists($file_path)) {
                return filemtime($file_path);
            }
            // If file doesn't exist, use current timestamp
            return time();
        }
        
        // In production, use version + timestamp for cache busting with LiteSpeed
        return FORM_BUILDER_VERSION . '.' . time();
    }
    
    /**
     * Send no-cache headers for admin pages
     */
    private function send_no_cache_headers() {
        if (!headers_sent()) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }
}