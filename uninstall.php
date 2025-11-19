<?php
/**
 * Uninstall script for Konstruct Form Builder
 * 
 * This file is executed when the plugin is uninstalled.
 * It removes all plugin data from the database and filesystem.
 * 
 * @package Form_Builder_Microsaas
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Remove database tables
$forms_table = $wpdb->prefix . 'form_builder_forms';
$submissions_table = $wpdb->prefix . 'form_builder_submissions';
$logs_table = $wpdb->prefix . 'form_builder_webhook_logs';

$wpdb->query("DROP TABLE IF EXISTS {$forms_table}");
$wpdb->query("DROP TABLE IF EXISTS {$submissions_table}");
$wpdb->query("DROP TABLE IF EXISTS {$logs_table}");

// Remove plugin options
delete_option('form_builder_version');

// Remove uploaded form data files
$uploads = wp_upload_dir();
$form_data_dir = trailingslashit($uploads['basedir']) . 'form_data';

if (file_exists($form_data_dir) && is_dir($form_data_dir)) {
    // Remove all files in the directory
    $files = glob(trailingslashit($form_data_dir) . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    
    // Remove the directory itself
    @rmdir($form_data_dir);
}

// Clear any cached data
wp_cache_flush();

