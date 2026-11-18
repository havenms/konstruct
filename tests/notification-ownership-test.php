<?php
// Run the real REST, webhook and email handlers with synthetic storage/HTTP/mail boundaries.
define('ABSPATH', __DIR__);
define('FORM_BUILDER_PLUGIN_DIR', dirname(__DIR__) . '/');
class WP_Error { public $code; function __construct($code, $message='', $data=array()) { $this->code=$code; } }
class WP_REST_Response { public $data; function __construct($data, $status) { $this->data=$data; } }
class Form_Builder_File_Handler {}
class Form_Builder_Storage {
    static $records=array();
    function get_form_by_id($id) {
        return array('form_name'=>'Fixture', 'form_slug'=>'fixture', 'form_config'=>array(
            'pages'=>array(array('fields'=>array(array('name'=>'fname'),array('name'=>'lname'),array('name'=>'phone'),array('name'=>'email'))),array('fields'=>array(array('name'=>'property_address')))),
            'notifications'=>array(
                'step_notifications'=>array('enabled'=>true,'include_admin'=>false,'recipients'=>'staff@example.test','subject'=>'Step {{page_number}}','message'=>'{{fname}} {{lname}} {{phone}} {{email}}'),
                'submission_notifications'=>array('enabled'=>true,'include_admin'=>false,'recipients'=>'staff@example.test','subject'=>'Final','message'=>'{{fname}} {{lname}} {{phone}} {{email}} {{property_address}}')
            )
        ));
    }
    function save_submission_record($id,$uuid,$page,$data,$final) { self::$records[$uuid]=array_merge(isset(self::$records[$uuid])?self::$records[$uuid]:array(),$data); return 1; }
    function log_webhook(...$args) {}
}
function is_wp_error($v) { return $v instanceof WP_Error; }
function sanitize_text_field($v) { return trim($v); }
function esc_url_raw($v) { return $v; }
function sanitize_email($v) { return $v; }
function is_email($v) { return filter_var($v,FILTER_VALIDATE_EMAIL); }
function get_option($key) { return 'admin@example.test'; }
function get_bloginfo($key) { return 'Fixture site'; }
function get_site_url() { return 'https://example.test'; }
function current_time($type) { return '2026-11-18 12:00:00'; }
function esc_html($v) { return htmlspecialchars((string)$v,ENT_QUOTES); }
function esc_url($v) { return $v; }
function add_filter(...$args) {}
function remove_filter(...$args) {}
$mails=array(); $http_calls=0; $http_status=200; $failures=array(); $checks=0;
function wp_mail($to,$subject,$message) { $GLOBALS['mails'][]=array($to,$subject,$message); return true; }
function wp_remote_request($url,$args) { $GLOBALS['http_calls']++; return array('status'=>$GLOBALS['http_status'],'body'=>'fixture'); }
function wp_remote_retrieve_response_code($r) { return $r['status']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
class RequestStub {
    private $params;
    function __construct($params) { $this->params=$params; }
    function get_json_params() { return $this->params; }
    function get_header($key) { return 'application/json'; }
}
function check($ok,$message) { $GLOBALS['checks']++; if (!$ok) $GLOBALS['failures'][]=$message; }
require dirname(__DIR__).'/includes/class-email-handler.php';
require dirname(__DIR__).'/includes/class-rest-api.php';
require dirname(__DIR__).'/includes/class-webhook-handler.php';
$api=new Form_Builder_REST_API(); $handler=new Form_Builder_Webhook_Handler();
$contact=array('fname'=>'Test','lname'=>'Fixture','phone'=>'+12025550123','email'=>'fixture@example.test');
$full=array_merge($contact,array('property_address'=>'123 Fixture St'));
$base=array('form_id'=>1,'submission_uuid'=>'11111111-1111-4111-8111-111111111111');
$step=array_merge($base,array('page_number'=>1,'form_data'=>$contact));
$hook=array_merge($base,array('page_number'=>1,'webhook_url'=>'https://example.test/hook','formData'=>$contact));
// Next owns its notification independently of webhook presence or delivery status.
$r=$api->send_step_notification(new RequestStub($step));
check($r->data['email_sent']===true && count($mails)===1,'Next sends one dedicated step email');
$r=$handler->process_webhook(new RequestStub($hook));
check($r instanceof WP_REST_Response,'step webhook delivery succeeds');
check(count($mails)===1,'step webhook must not duplicate dedicated step email');
$handler->process_webhook(new RequestStub($hook));
check(count($mails)===1,'step webhook retry must not resend staff email');
// Final persistence owns the complete notification, not its following webhook.
$api->save_submission(new RequestStub(array_merge($base,array('formData'=>$full))));
check(count($mails)===2 && $mails[1][1]==='Final','final save sends the complete notification');
check(strpos($mails[1][2],'123 Fixture St')!==false && strpos($mails[1][2],'fixture@example.test')!==false,'final email contains contact and property fields');
$hook['page_number']=2; $hook['formData']=$full;
$r=$handler->process_webhook(new RequestStub($hook));
check($r instanceof WP_REST_Response && count($mails)===2,'final webhook must not send a property-only step email');
$handler->process_webhook(new RequestStub($hook));
check(count($mails)===2,'final webhook retry must not resend staff email');
$http_status=503;
check(is_wp_error($handler->process_webhook(new RequestStub($hook))),'failed webhook still returns delivery error');
check(count($mails)===2,'failed webhook must not send staff email');
$http_status=200;
check($handler->process_webhook(new RequestStub($hook)) instanceof WP_REST_Response,'retry after delivery failure succeeds');
check(count($mails)===2,'retry after failure must not resend staff email');
check($http_calls===6,'every webhook attempt still reaches its transport');
// A form without a webhook keeps independent step notifications.
$step['submission_uuid']='22222222-2222-4222-8222-222222222222';
$api->send_step_notification(new RequestStub($step));
check(count($mails)===3,'non-webhook form step notifications are preserved');
foreach ($mails as $mail) check(strpos($mail[2],'{{')===false,'staff emails have no unresolved fixture placeholders');
if ($failures) { foreach ($failures as $failure) fwrite(STDERR,"FAIL: $failure\n"); exit(1); }
echo "notification ownership regressions passed ($checks checks)\n";
