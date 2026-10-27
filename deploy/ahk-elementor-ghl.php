<?php
/**
 * Plugin Name: AHK Elementor GHL Adapter
 * Description: Maps validated Elementor submissions into AHK's private GHL webhook relay.
 */
if (!defined('ABSPATH')) { exit; }

function ahk_elementor_ghl_payload($record) {
    $data = array();
    $full_name = '';
    foreach ((array) $record->get('fields') as $id => $field) {
        $value = isset($field['value']) && is_scalar($field['value']) ? (string) $field['value'] : '';
        $type = isset($field['type']) ? strtolower($field['type']) : '';
        $label = isset($field['title']) ? strtolower(trim($field['title'])) : '';
        if ($type === 'email') {
            $data['email'] = sanitize_email($value);
        } elseif ($type === 'tel' || strpos($label, 'phone') !== false || $label === 'mobile') {
            $phone = preg_replace('/\D/', '', $value);
            if (strlen($phone) === 10) { $phone = '1' . $phone; }
            if (strlen($phone) === 11 && substr($phone, 0, 1) === '1') { $data['phone'] = '+' . $phone; }
        } elseif ($id === 'name' || $label === 'name' || $label === 'full name') {
            $full_name = sanitize_text_field($value);
        } elseif ($label === 'first name' || $id === 'fname') {
            $data['fname'] = sanitize_text_field($value);
        } elseif ($label === 'last name' || $id === 'lname') {
            $data['lname'] = sanitize_text_field($value);
        } elseif ($label === 'property type' || $id === 'property_type') {
            $data['property_type'] = sanitize_text_field($value);
        } elseif ($label === 'property address' || $id === 'property_address') {
            $data['property_address'] = sanitize_text_field($value);
        } elseif ($id === 'selling_timeline' || strpos($label, 'timeline') !== false) {
            $data['selling_timeline'] = sanitize_text_field($value);
        } elseif ($type === 'textarea' || $label === 'message') {
            $data['property_details'] = sanitize_textarea_field($value);
        }
    }
    if ($full_name !== '' && empty($data['fname'])) {
        $parts = preg_split('/\s+/', $full_name, 2);
        $data['fname'] = $parts[0];
        if (isset($parts[1])) { $data['lname'] = $parts[1]; }
    }
    return $data;
}

function ahk_elementor_ghl_forward($record, $handler) {
    if (parse_url(home_url(), PHP_URL_HOST) !== 'affordablehomeking.com') { return; }
    $data = ahk_elementor_ghl_payload($record);
    if (empty($data['email']) || !is_email($data['email'])) {
        $handler->add_error_message('Please provide a valid email address.');
        return;
    }
    $step = !empty($data['property_type']) ? 'elementor-offer' : 'elementor-contact';
    $response = wp_remote_post('https://affordablehomeking.com/wp-json/ahk-ghl/v1/' . $step, array(
        'method' => 'POST',
        'headers' => array('Content-Type' => 'application/json'),
        'body' => wp_json_encode(array('formData' => $data)),
        'timeout' => 30,
        'sslverify' => true,
    ));
    $code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
    $accepted = $code >= 200 && $code < 300;
    $log = get_option('ahk_elementor_ghl_delivery', array());
    if (!is_array($log)) { $log = array(); }
    $log[] = array('time' => gmdate('c'), 'identity_sha256' => hash('sha256', strtolower($data['email'])), 'step' => $step, 'http_status' => $code, 'status' => $accepted ? 'accepted' : 'failed');
    update_option('ahk_elementor_ghl_delivery', array_slice($log, -20), false);
    if (!$accepted) {
        $handler->add_error_message('We could not finish sending your request. Please try again or contact us directly.');
    }
}
add_action('elementor_pro/forms/new_record', 'ahk_elementor_ghl_forward', 10, 2);
