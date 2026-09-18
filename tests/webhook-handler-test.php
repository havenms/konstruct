<?php

define('ABSPATH', __DIR__);
define('FORM_BUILDER_PLUGIN_DIR', dirname(__DIR__) . '/');

class Form_Builder_Storage {}
class Form_Builder_Email_Handler {}
class WP_Error {}

function wp_remote_request($url, $args) {
    return array('status' => 400, 'body' => '{"ok":false,"error":"invalid"}');
}
function is_wp_error($value) { return false; }
function wp_remote_retrieve_response_code($response) { return $response['status']; }
function wp_remote_retrieve_body($response) { return $response['body']; }

require dirname(__DIR__) . '/includes/class-webhook-handler.php';

$handler = new Form_Builder_Webhook_Handler();
$method = new ReflectionMethod($handler, 'send_webhook');
$method->setAccessible(true);
$result = $method->invoke($handler, 'https://example.test/webhook', array('formData' => array()));

if (!isset($result['error'])) {
    fwrite(STDERR, "Expected non-2xx webhook response to return an error\n");
    exit(1);
}
if ($result['status_code'] !== 400) {
    fwrite(STDERR, "Expected status code 400\n");
    exit(1);
}

echo "webhook-handler test passed\n";
