<?php
// Synthetic Elementor records. No WordPress or real network is loaded.
define('ABSPATH', __DIR__.'/');
$GLOBALS['actions']=array();
function add_action($name,$callback,$priority=10,$count=1){$GLOBALS['actions'][$name]=array($callback,$priority,$count);}
function sanitize_text_field($v){return trim(strip_tags($v));}
function sanitize_textarea_field($v){return trim(strip_tags($v));}
function sanitize_email($v){return filter_var($v,FILTER_SANITIZE_EMAIL);}
function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL);}
function home_url(){return 'https://affordablehomeking.com';}
function check($ok,$message){if(!$ok){fwrite(STDERR,'FAIL: '.$message."\n");exit(1);}}
class Record {private $fields;function __construct($f){$this->fields=$f;}function get($key){return $key==='fields'?$this->fields:null;}}
$file=__DIR__.'/../deploy/ahk-elementor-ghl.php';if(is_file($file))require $file;
check(function_exists('ahk_elementor_ghl_payload'),'Elementor adapter maps actual fields');
$fields=array(
 'name'=>array('type'=>'text','title'=>'Name','value'=>'AHKTEST Elementor Fixture'),
 'email'=>array('type'=>'number','title'=>'Phone Number','value'=>'2025550147'),
 'field_0da9406'=>array('type'=>'email','title'=>'Email','value'=>'elementor-fixture@example.invalid'),
 'message'=>array('type'=>'select','title'=>'Property Type','value'=>'Raw Land'),
 'field_2bbcc3b'=>array('type'=>'textarea','title'=>'Message','value'=>'TEST ONLY - property inquiry')
);
$p=ahk_elementor_ghl_payload(new Record($fields));
check($p['email']==='elementor-fixture@example.invalid','Email uses semantic field, not misleading phone custom ID');
check($p['phone']==='+12025550147','Phone is normalized');
check($p['fname']==='AHKTEST'&&$p['lname']==='Elementor Fixture','Full name maps first and last');
check($p['property_type']==='Raw Land','Property select is not treated as message');
check($p['property_details']==='TEST ONLY - property inquiry','Message is retained');
echo "Elementor field mapping test passed\n";
$GLOBALS['requests']=array();$GLOBALS['delivery_log']=array();$GLOBALS['response']=array('response'=>array('code'=>200));
function wp_remote_post($url,$args){$GLOBALS['requests'][]=array($url,$args);return $GLOBALS['response'];}
function wp_json_encode($v){return json_encode($v);}
function wp_remote_retrieve_response_code($r){return $r['response']['code'];}
class WP_Error {}
function is_wp_error($r){return $r instanceof WP_Error;}
function get_option($k,$default=array()){return isset($GLOBALS['delivery_log'][$k])?$GLOBALS['delivery_log'][$k]:$default;}
function update_option($k,$v,$autoload=null){$GLOBALS['delivery_log'][$k]=$v;}
class Handler {public $errors=array();function add_error_message($m){$this->errors[]=$m;}}
check(function_exists('ahk_elementor_ghl_forward'),'Validated Elementor records are forwarded');
$h=new Handler;ahk_elementor_ghl_forward(new Record($fields),$h);
check(count($GLOBALS['requests'])===1&&empty($h->errors),'One validated submission makes one delivery');
check($GLOBALS['requests'][0][0]==='https://affordablehomeking.com/wp-json/ahk-ghl/v1/elementor-offer','Property inquiry uses a mapping limited to Elementor fields');
check(json_decode($GLOBALS['requests'][0][1]['body'],true)['formData']===$p,'Mapped fields are forwarded intact');
$emailOnly=array('name'=>array('type'=>'text','title'=>'Name','value'=>'AHKTEST Contact'), 'email'=>array('type'=>'email','title'=>'Email','value'=>'contact-fixture@example.invalid'),'message'=>array('type'=>'textarea','title'=>'Message','value'=>'TEST ONLY contact message'));
$h=new Handler;ahk_elementor_ghl_forward(new Record($emailOnly),$h);
check(count($GLOBALS['requests'])===2&&empty($h->errors),'Contact form without phone is supported');
check(!isset(json_decode($GLOBALS['requests'][1][1]['body'],true)['formData']['phone']),'Missing phone is never fabricated');
check($GLOBALS['requests'][1][0]==='https://affordablehomeking.com/wp-json/ahk-ghl/v1/elementor-contact','Email-only message never invokes property-field mappings');
$GLOBALS['response']=new WP_Error;$h=new Handler;ahk_elementor_ghl_forward(new Record($fields),$h);
check(count($h->errors)===1,'Transport failure does not report form success');
$GLOBALS['response']=array('response'=>array('code'=>500));$h=new Handler;ahk_elementor_ghl_forward(new Record($fields),$h);
check(count($h->errors)===1,'Non-2xx response does not report success');
$log=get_option('ahk_elementor_ghl_delivery',array());check(count($log)===4&&$log[3]['status']==='failed','Delivery outcome is recorded');
check(strpos(json_encode($log),'@')===false,'Delivery telemetry contains no contact PII');
$count=count($GLOBALS['requests']);$h=new Handler;ahk_elementor_ghl_forward(new Record(array()),$h);check(count($GLOBALS['requests'])===$count&&count($h->errors)===1,'Missing identity is blocked');
check(isset($GLOBALS['actions']['elementor_pro/forms/new_record']),'Native Elementor integration hook is registered');
echo "Elementor forwarding and failure tests passed\n";

