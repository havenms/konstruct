<?php
/**
 * File Handler Class
 * Manages file uploads and secure downloads without direct readfile() + exit patterns
 */

if (!defined('ABSPATH')) {
    exit;
}

class Form_Builder_File_Handler {
    
    private $allowed_mime_types;
    private $max_file_size;
    
    public function __construct() {
        $this->max_file_size = 10 * 1024 * 1024; // 10MB
        $this->allowed_mime_types = array(
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'zip' => 'application/zip',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        );
    }
    
    /**
     * Process uploads: validate, move to uploads/form_data, and replace fields with metadata and protected URLs.
     */
    public function process_uploads_and_enrich_form_data($submission_uuid, $form_data, $files) {
        $uploads = wp_upload_dir();
        $target_dir = trailingslashit($uploads['basedir']) . 'form_data';
        
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }

        foreach ($files as $field => $file) {
            if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            
            // Validate upload
            $validation_result = $this->validate_upload($file, $field);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Process the file
            $file_result = $this->process_single_upload($file, $field, $submission_uuid, $target_dir);
            if (is_wp_error($file_result)) {
                return $file_result;
            }
            
            $form_data[$field] = $file_result;
        }

        return $form_data;
    }
    
    /**
     * Validate a single file upload
     */
    private function validate_upload($file, $field) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'Failed to upload file for ' . $field, array('status' => 400));
        }
        
        if ($file['size'] > $this->max_file_size) {
            return new WP_Error('file_too_large', 'File too large for ' . $field, array('status' => 413));
        }

        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        $ext = isset($check['ext']) ? $check['ext'] : '';
        $type = isset($check['type']) ? $check['type'] : '';
        
        if (empty($ext) || empty($type) || !isset($this->allowed_mime_types[$ext]) || $this->allowed_mime_types[$ext] !== $type) {
            return new WP_Error('invalid_type', 'Invalid file type for ' . $field, array('status' => 415));
        }
        
        return true;
    }
    
    /**
     * Process a single file upload
     */
    private function process_single_upload($file, $field, $submission_uuid, $target_dir) {
        // Sanitize and generate unique file name
        $safe_name = sanitize_file_name($file['name']);
        $unique = $submission_uuid . '-' . wp_generate_password(8, false, false) . '-' . $safe_name;
        $dest = trailingslashit($target_dir) . $unique;

        // Try to move uploaded file
        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            // Fallback to WP handle upload
            $overrides = array('test_form' => false);
            $handled = wp_handle_upload($file, $overrides);
            
            if (isset($handled['error'])) {
                return new WP_Error('upload_move_failed', $handled['error'], array('status' => 500));
            }
            
            // Move from default uploads to our protected dir
            $moved = @rename($handled['file'], $dest);
            if (!$moved) {
                return new WP_Error('upload_move_failed', 'Could not secure file location', array('status' => 500));
            }
        }

        // Build protected URL via our REST route (admin-only)
        $protected_url = add_query_arg(array(
            'submission_uuid' => rawurlencode($submission_uuid),
            'field' => rawurlencode($field),
        ), rest_url('form-builder/v1/file'));

        return array(
            'name' => $safe_name,
            'mime' => wp_check_filetype($dest)['type'],
            'size' => filesize($dest),
            'path' => $dest,
            'url' => $protected_url,
        );
    }
    
    /**
     * Serve protected file using WordPress response system instead of direct readfile() + exit
     */
    public function serve_protected_file($request) {
        $submission_uuid = $request->get_param('submission_uuid');
        $field = $request->get_param('field');

        if (empty($submission_uuid) || empty($field)) {
            return new WP_Error('bad_request', 'Missing parameters', array('status' => 400));
        }

        // Get file metadata from database
        $file_data = $this->get_file_metadata($submission_uuid, $field);
        if (is_wp_error($file_data)) {
            return $file_data;
        }

        // Validate file path security
        $security_check = $this->validate_file_path($file_data['path']);
        if (is_wp_error($security_check)) {
            return $security_check;
        }

        // Use WordPress response streaming instead of direct readfile()
        return $this->create_file_response($file_data);
    }
    
    /**
     * Get file metadata from database
     */
    private function get_file_metadata($submission_uuid, $field) {
        global $wpdb;
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT form_data FROM {$wpdb->prefix}form_builder_submissions WHERE submission_uuid = %s",
            $submission_uuid
        ), ARRAY_A);

        if (!$row) {
            return new WP_Error('not_found', 'Submission not found', array('status' => 404));
        }

        $data = json_decode($row['form_data'], true);
        if (!isset($data[$field]) || !is_array($data[$field]) || empty($data[$field]['path'])) {
            return new WP_Error('not_found', 'File not found for field', array('status' => 404));
        }
        
        return $data[$field];
    }
    
    /**
     * Validate file path for security
     */
    private function validate_file_path($path) {
        // Ensure path is inside uploads/form_data
        $uploads = wp_upload_dir();
        $base = realpath(trailingslashit($uploads['basedir']) . 'form_data');
        $real = realpath($path);
        
        if ($base === false || $real === false || strpos($real, $base) !== 0 || !file_exists($real)) {
            return new WP_Error('forbidden', 'Access denied', array('status' => 403));
        }
        
        return true;
    }
    
    /**
     * Create file response using WordPress response system
     */
    private function create_file_response($file_data) {
        $path = $file_data['path'];
        $mime = isset($file_data['mime']) ? $file_data['mime'] : 'application/octet-stream';
        $filename = isset($file_data['name']) ? $file_data['name'] : basename($path);
        
        // Read file contents safely
        $contents = file_get_contents($path);
        if ($contents === false) {
            return new WP_Error('read_error', 'Could not read file', array('status' => 500));
        }
        
        // Create response with proper headers
        $response = new WP_REST_Response($contents, 200);
        $response->header('Content-Type', $mime);
        $response->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->header('Content-Length', strlen($contents));
        $response->header('Cache-Control', 'private, max-age=0, no-cache');
        
        return $response;
    }
}