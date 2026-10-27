<?php
// Native PHP 7.4 unit test. All network replies below are explicit fixtures.
define('ABSPATH', __DIR__ . '/');
$mode=getenv('AHK_GHL_TEST_MODE');
$config=$mode==='missing' ? __DIR__.'/fixtures/does-not-exist.php' : ($mode==='invalid' ? __DIR__.'/fixtures/ahk-ghl-invalid-endpoints.php' : __DIR__.'/fixtures/ahk-ghl-endpoints.php');
define('AHK_GHL_ENDPOINTS_FILE', $config);
$GLOBALS['ahk_test_filters'] = array();
$GLOBALS['ahk_test_requests'] = array();
function add_filter($name, $callback, $priority, $accepted) { $GLOBALS['ahk_test_filters'][$name] = array($callback, $priority, $accepted); }
class WP_Error { public $code; public $message; public function __construct($code, $message) { $this->code=$code; $this->message=$message; } }
function wp_remote_post($url, $args) { $GLOBALS['ahk_test_requests'][] = array($url, $args); return array('response'=>array('code'=>200),'body'=>'{"status":"Success"}'); }
function check($value, $message) { if (!$value) { fwrite(STDERR, "FAIL: " . $message . "\n"); exit(1); } }
$file=__DIR__ . '/../deploy/ahk-ghl-webhook-relay.php';
if (is_file($file)) require $file;
check(function_exists('ahk_ghl_relay_request'), 'native Konstruct relay is available');
$args=array('method'=>'POST','headers'=>array('Content-Type'=>'application/json'),'timeout'=>30,'body'=>json_encode(array('formData'=>array('fname'=>'AHKTEST','lname'=>'Unit Fixture','email'=>'fixture@example.invalid','phone'=>'+12025550147'))));
$response=ahk_ghl_relay_request(false,$args,'https://affordablehomeking.com/wp-json/ahk-ghl/v1/step-1');
if ($mode==='missing' || $mode==='invalid') {
    check($response instanceof WP_Error && $response->code==='ahk_ghl_unavailable','missing or unsafe destinations fail closed');
    check(count($GLOBALS['ahk_test_requests'])===0,'invalid configuration never sends a lead');
    check(strpos($response->message,'http')===false,'configuration errors do not reveal destinations');
    echo "AHK GHL relay fail-closed test passed\n";
    exit(0);
}
check($response['response']['code']===200,'contact webhook response is preserved');
check(count($GLOBALS['ahk_test_requests'])===1,'one remote request is made');
check($GLOBALS['ahk_test_requests'][0][0]==='https://services.leadconnectorhq.com/hooks/fixtureLocation12345/webhook-trigger/11111111-1111-4111-8111-111111111111','step 1 uses the server-only destination');
check($GLOBALS['ahk_test_requests'][0][1]===$args,'the original form payload and HTTP options are preserved');
$second=ahk_ghl_relay_request(false,$args,'https://affordablehomeking.com/wp-json/ahk-ghl/v1/step-2');
check($second['response']['code']===200 && count($GLOBALS['ahk_test_requests'])===2,'property step sends one additional request');
check(strpos($GLOBALS['ahk_test_requests'][1][0],'22222222-2222-4222-8222-222222222222')!==false,'step 2 uses its distinct property workflow');
$already=array('response'=>array('code'=>202));
check(ahk_ghl_relay_request($already,$args,'https://affordablehomeking.com/wp-json/ahk-ghl/v1/step-1')===$already,'existing HTTP preemption is respected');
foreach (array('https://other.example/wp-json/ahk-ghl/v1/step-1','https://affordablehomeking.com/wp-json/ahk-ghl/v1/step-1?x=1','https://affordablehomeking.com/wp-json/ahk-ghl/v1/step-3') as $unrelated) {
    check(ahk_ghl_relay_request(false,$args,$unrelated)===false,'unrelated requests are untouched');
}
$bad=$args;$bad['method']='GET';check(ahk_ghl_relay_request(false,$bad,'https://affordablehomeking.com/wp-json/ahk-ghl/v1/step-1') instanceof WP_Error,'non-POST delivery is refused');
foreach (array('{bad json',json_encode(array('formData'=>array('email'=>'not-email','phone'=>'123'))),json_encode(array('formData'=>array('phone'=>'2025550147')))) as $badBody) {
    $bad=$args;$bad['body']=$badBody;check(ahk_ghl_relay_request(false,$bad,'https://affordablehomeking.com/wp-json/ahk-ghl/v1/step-1') instanceof WP_Error,'invalid contact payloads are refused');
}
check(count($GLOBALS['ahk_test_requests'])===2,'guards never create extra deliveries');
check($GLOBALS['ahk_test_filters']['pre_http_request']===array('ahk_ghl_relay_request',10,3),'native WordPress HTTP hook is registered');
$emailOnly=$args;$emailOnly['body']=json_encode(array('formData'=>array('email'=>'contact-fixture@example.invalid')));
$contactResponse=ahk_ghl_relay_request(false,$emailOnly,'https://affordablehomeking.com/wp-json/ahk-ghl/v1/step-2');
check(is_array($contactResponse)&&$contactResponse['response']['code']===200,'Email-only Elementor contact is accepted without fabricating phone');
foreach (array('elementor-offer','elementor-contact') as $variant) {
    $r=ahk_ghl_relay_request(false,$emailOnly,'https://affordablehomeking.com/wp-json/ahk-ghl/v1/'.$variant);
    check(is_array($r)&&$r['response']['code']===200,'Elementor variant has an exact private route');
}
echo "AHK GHL relay routing and guard tests passed\n";
