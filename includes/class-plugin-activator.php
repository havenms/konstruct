<?php
/**
 * Plugin Activator Class
 * Handles plugin activation, deactivation, and database setup
 */

if (!defined('ABSPATH')) {
    exit;
}

class Form_Builder_Plugin_Activator {
    
    /**
     * Plugin activation hook
     */
    public static function activate() {
        global $wpdb;
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset_collate = $wpdb->get_charset_collate();
        $forms_table = $wpdb->prefix . 'form_builder_forms';
        $submissions_table = $wpdb->prefix . 'form_builder_submissions';
        $logs_table = $wpdb->prefix . 'form_builder_webhook_logs';
        
        $sql = "CREATE TABLE $forms_table (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_name VARCHAR(255) NOT NULL,
            form_slug VARCHAR(255) NOT NULL,
            form_config LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY form_slug (form_slug),
            KEY updated_at (updated_at)
        ) $charset_collate;
        
        CREATE TABLE $submissions_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id INT UNSIGNED NOT NULL,
            submission_uuid CHAR(36) NOT NULL,
            page_number INT UNSIGNED NOT NULL,
            form_data LONGTEXT NOT NULL,
            delivered TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY submission_uuid (submission_uuid),
            KEY form_id (form_id),
            KEY page_number (page_number)
        ) $charset_collate;
        
        CREATE TABLE $logs_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            submission_id BIGINT UNSIGNED NULL,
            form_id INT UNSIGNED NOT NULL,
            page_number INT UNSIGNED NOT NULL,
            webhook_url TEXT NOT NULL,
            status_code SMALLINT NULL,
            response_ms INT NULL,
            error_message TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY form_id_page (form_id, page_number),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Set version option
        update_option('form_builder_version', FORM_BUILDER_VERSION);

        // Setup protected uploads directory
        self::setup_uploads_directory();
    }
    
    /**
     * Plugin deactivation hook
     */
    public static function deactivate() {
        // Cleanup if needed
        flush_rewrite_rules();
    }
    
    /**
     * Setup protected uploads directory with security measures
     */
    private static function setup_uploads_directory() {
        $uploads = wp_upload_dir();
        $form_data_dir = trailingslashit($uploads['basedir']) . 'form_data';
        
        if (!file_exists($form_data_dir)) {
            wp_mkdir_p($form_data_dir);
        }

        // Add .htaccess to block direct access (for Apache environments)
        $htaccess_path = trailingslashit($form_data_dir) . '.htaccess';
        if (!file_exists($htaccess_path)) {
            $rules = "# Deny direct access to uploaded form files\nDeny from all\n";
            @file_put_contents($htaccess_path, $rules);
        }

        // Add index.php to prevent directory listing
        $index_path = trailingslashit($form_data_dir) . 'index.php';
        if (!file_exists($index_path)) {
            @file_put_contents($index_path, "<?php\n// Silence is golden.\n");
        }
    }
}