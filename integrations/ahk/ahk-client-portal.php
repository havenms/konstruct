<?php
/**
 * Plugin Name: AHK Client Portal Delivery
 * Description: Adds client-portal delivery to the existing private AHK GHL aliases without changing GHL responses.
 */
if (!defined('ABSPATH')) { exit; }

function ahk_portal_payload($data, $step) {
    $payload = array('source' => 'affordablehomeking.com');
    foreach (array('fname'=>'firstName', 'lname'=>'lastName', 'email'=>'email', 'phone'=>'phone') as $from=>$to) {
        if (isset($data[$from]) && is_scalar($data[$from]) && trim((string)$data[$from]) !== '') {
            $payload[$to] = sanitize_text_field((string)$data[$from]);
        }
    }
    $details = array();
    foreach (array('property_address'=>'Property address', 'property_type'=>'Property type', 'selling_timeline'=>'Selling timeline', 'property_details'=>'Message') as $field=>$label) {
        if (isset($data[$field]) && is_scalar($data[$field]) && trim((string)$data[$field]) !== '') {
            $details[] = $label . ': ' . sanitize_textarea_field((string)$data[$field]);
        }
    }
    if ($details) { $payload['message'] = implode("\n", $details); }
    return $payload;
}

/** POST only to the fixed HTTPS portal, with its key kept outside the web root. */
function ahk_portal_deliver($payload, $attempt = 0) {
    $file = defined('AHK_PORTAL_CONFIG_FILE') ? AHK_PORTAL_CONFIG_FILE : '/home/ahkllc/.ahk-portal.php';
    $code = 0;
    try {
        $config = is_readable($file) ? require $file : array();
        if (is_array($config) && !empty($config['key'])) {
            $response = wp_remote_post('https://clients.havenmediasolutions.com/api/leads', array(
                'method' => 'POST',
                'headers' => array('Content-Type'=>'application/json', 'x-api-key'=>$config['key']),
                'body' => wp_json_encode($payload),
                'timeout' => 10,
                'redirection' => 0,
                'sslverify' => true,
            ));
            $code = is_wp_error($response) ? 0 : (int)wp_remote_retrieve_response_code($response);
        }
    } catch (Throwable $error) {
        // Do not expose keys, bodies, or upstream error text to visitors/logs.
        $code = 0;
    }
    $accepted = $code >= 200 && $code < 300;
    $log = get_option('ahk_portal_delivery', array());
    if (!is_array($log)) { $log = array(); }
    $log[] = array('time'=>gmdate('c'), 'identity_sha256'=>hash('sha256',strtolower(isset($payload['email'])?$payload['email']:'')), 'http_status'=>$code, 'attempt'=>(int)$attempt, 'status'=>$accepted?'accepted':'failed');
    update_option('ahk_portal_delivery', array_slice($log,-50), false);
    if (!$accepted && $attempt < 8) {
        wp_schedule_single_event(time()+min(3600,60*pow(2,$attempt)), 'ahk_portal_retry', array($payload,$attempt+1));
    }
    return $accepted;
}

/** Runs AFTER the existing GHL relay; always returns its original result. */
function ahk_portal_observe($preempt, $args, $url) {
    $base = 'https://affordablehomeking.com/wp-json/ahk-ghl/v1/';
    $aliases = array($base.'step-1'=>'step-1', $base.'step-2'=>'step-2', $base.'elementor-offer'=>'elementor-offer', $base.'elementor-contact'=>'elementor-contact');
    if (!isset($aliases[$url]) || strtoupper(isset($args['method'])?$args['method']:'') !== 'POST') { return $preempt; }
    $body = isset($args['body']) && is_string($args['body']) ? json_decode($args['body'],true) : null;
    $data = is_array($body) && isset($body['formData']) ? $body['formData'] : null;
    if (!is_array($data) || empty($data['email']) || !is_string($data['email']) || !filter_var($data['email'],FILTER_VALIDATE_EMAIL)) { return $preempt; }
    ahk_portal_deliver(ahk_portal_payload($data,$aliases[$url]));
    return $preempt;
}
add_filter('pre_http_request','ahk_portal_observe',20,3);
add_action('ahk_portal_retry','ahk_portal_deliver',10,2);
