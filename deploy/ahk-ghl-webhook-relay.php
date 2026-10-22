<?php
/**
 * Plugin Name: AHK Konstruct GHL Relay
 * Description: Keeps AHK's GHL webhook destinations server-side. No public REST route is registered.
 */
if (!defined('ABSPATH')) { exit; }
if (!defined('AHK_GHL_ENDPOINTS_FILE')) {
    define('AHK_GHL_ENDPOINTS_FILE', '/home/ahkllc/.ahk-ghl-webhooks.php');
}

/** Intercept only Konstruct's two exact, non-secret local destination aliases. */
function ahk_ghl_relay_request($preempt, $args, $url) {
    $aliases = array(
        'https://affordablehomeking.com/wp-json/ahk-ghl/v1/step-1' => 'step-1',
        'https://affordablehomeking.com/wp-json/ahk-ghl/v1/step-2' => 'step-2',
    );
    if ($preempt !== false || !isset($aliases[$url])) { return $preempt; }
    if (strtoupper(isset($args['method']) ? $args['method'] : '') !== 'POST') {
        return new WP_Error('ahk_ghl_invalid_request', 'Lead delivery requires a POST request.');
    }
    $body = isset($args['body']) && is_string($args['body']) ? json_decode($args['body'], true) : null;
    $data = is_array($body) && isset($body['formData']) ? $body['formData'] : null;
    if (!is_array($data) || empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) || empty($data['phone'])) {
        return new WP_Error('ahk_ghl_invalid_payload', 'Lead contact details are incomplete.');
    }
    if (!is_readable(AHK_GHL_ENDPOINTS_FILE)) {
        return new WP_Error('ahk_ghl_unavailable', 'Lead delivery is temporarily unavailable.');
    }
    $destinations = require AHK_GHL_ENDPOINTS_FILE;
    $key = $aliases[$url];
    $target = is_array($destinations) && isset($destinations[$key]) ? $destinations[$key] : '';
    if (!is_string($target) || !preg_match('~^https://services\.leadconnectorhq\.com/hooks/[A-Za-z0-9]+/webhook-trigger/[A-Za-z0-9-]+$~D', $target)) {
        return new WP_Error('ahk_ghl_unavailable', 'Lead delivery is temporarily unavailable.');
    }
    return wp_remote_post($target, $args);
}
add_filter('pre_http_request', 'ahk_ghl_relay_request', 10, 3);
