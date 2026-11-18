<?php
/**
 * Plugin Name: AHK Konstruct GHL Relay
 * Description: Keeps AHK's GHL webhook destinations server-side. No public REST route is registered.
 */
if (!defined('ABSPATH')) { exit; }
if (!defined('AHK_GHL_ENDPOINTS_FILE')) {
    define('AHK_GHL_ENDPOINTS_FILE', '/home/ahkllc/.ahk-ghl-webhooks.php');
}

/** Use current-page campaign tags, never guess an untracked channel. */
function ahk_ghl_lead_source($route) {
    $labels = array('step-1'=>'Konstruct Cash Offer', 'step-2'=>'Konstruct Cash Offer', 'elementor-offer'=>'Elementor Property Inquiry', 'elementor-contact'=>'Elementor Contact Message');
    $source = 'AHK Website - ' . $labels[$route];
    $ref = isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER']) : array();
    if (!is_array($ref) || !isset($ref['host']) || strtolower($ref['host']) !== 'affordablehomeking.com') { return $source; }
    $query = array(); parse_str(isset($ref['query']) ? $ref['query'] : '', $query);
    $tag = isset($query['utm_source']) && is_string($query['utm_source']) ? trim($query['utm_source']) : '';
    if ($tag === '' || !preg_match('/^[a-zA-Z0-9_. -]{1,64}$/D', $tag)) { return $source; }
    $known = array('fb'=>'Facebook', 'facebook'=>'Facebook', 'ig'=>'Instagram', 'instagram'=>'Instagram', 'google'=>'Google', 'bing'=>'Bing');
    $channel = isset($known[strtolower($tag)]) ? $known[strtolower($tag)] : $tag;
    $medium = isset($query['utm_medium']) && is_string($query['utm_medium']) ? trim($query['utm_medium']) : '';
    if ($medium !== '' && preg_match('/^[a-zA-Z0-9_. -]{1,32}$/D', $medium)) { $channel .= ' / ' . $medium; }
    return $channel . ' | ' . $source;
}

/** Current-page campaign tags only; never infer an untracked acquisition channel. */
function ahk_ghl_lead_source($route) {
    $labels = array('step-1'=>'Konstruct Cash Offer', 'step-2'=>'Konstruct Cash Offer', 'elementor-offer'=>'Elementor Property Inquiry', 'elementor-contact'=>'Elementor Contact Message');
    $source = 'AHK Website - ' . $labels[$route];
    $ref = isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER']) : array();
    if (!is_array($ref) || !isset($ref['host']) || strtolower($ref['host']) !== 'affordablehomeking.com') { return $source; }
    $query = array(); parse_str(isset($ref['query']) ? $ref['query'] : '', $query);
    $tag = isset($query['utm_source']) && is_string($query['utm_source']) ? trim($query['utm_source']) : '';
    if ($tag === '' || !preg_match('/^[a-zA-Z0-9_. -]{1,64}$/D', $tag)) { return $source; }
    $known = array('fb'=>'Facebook', 'facebook'=>'Facebook', 'ig'=>'Instagram', 'instagram'=>'Instagram', 'google'=>'Google', 'bing'=>'Bing');
    $channel = isset($known[strtolower($tag)]) ? $known[strtolower($tag)] : $tag;
    $medium = isset($query['utm_medium']) && is_string($query['utm_medium']) ? trim($query['utm_medium']) : '';
    if ($medium !== '' && preg_match('/^[a-zA-Z0-9_. -]{1,32}$/D', $medium)) { $channel .= ' / ' . $medium; }
    return $channel . ' | ' . $source;
}

/** Intercept only AHK's exact, non-secret local destination aliases. */
function ahk_ghl_relay_request($preempt, $args, $url) {
    $aliases = array(
        'https://affordablehomeking.com/wp-json/ahk-ghl/v1/step-1' => 'step-1',
        'https://affordablehomeking.com/wp-json/ahk-ghl/v1/step-2' => 'step-2',
        'https://affordablehomeking.com/wp-json/ahk-ghl/v1/elementor-offer' => 'elementor-offer',
        'https://affordablehomeking.com/wp-json/ahk-ghl/v1/elementor-contact' => 'elementor-contact',
    );
    if ($preempt !== false || !isset($aliases[$url])) { return $preempt; }
    if (strtoupper(isset($args['method']) ? $args['method'] : '') !== 'POST') {
        return new WP_Error('ahk_ghl_invalid_request', 'Lead delivery requires a POST request.');
    }
    $body = isset($args['body']) && is_string($args['body']) ? json_decode($args['body'], true) : null;
    $data = is_array($body) && isset($body['formData']) ? $body['formData'] : null;
    if (!is_array($data) || empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
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
    $body['formData']['lead_source'] = ahk_ghl_lead_source($key);
    $args['body'] = json_encode($body);
    return wp_remote_post($target, $args);
}
add_filter('pre_http_request', 'ahk_ghl_relay_request', 10, 3);
