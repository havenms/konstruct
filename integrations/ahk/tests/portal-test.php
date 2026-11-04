<?php
function check($ok,$label){if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
define('ABSPATH',__DIR__);
function add_filter(){} function add_action(){}
function sanitize_text_field($v){return trim(strip_tags($v));}
function sanitize_textarea_field($v){return trim(strip_tags($v));}
$f=__DIR__.'/../ahk-client-portal.php';
check(is_file($f),'portal integration exists');require $f;
$p=ahk_portal_payload(array('fname'=>'Test','lname'=>'Portal','email'=>'test@example.com','phone'=>'+12025550199','property_address'=>'123 Test Lane','property_type'=>'Single Family','selling_timeline'=>'Soon','property_details'=>'TEST ONLY'),'step-2');
check($p['firstName']==='Test' && $p['lastName']==='Portal' && $p['phone']==='+12025550199','contact mapping');
check(strpos($p['message'],'Property address: 123 Test Lane')!==false && strpos($p['message'],'Property type: Single Family')!==false && strpos($p['message'],'Selling timeline: Soon')!==false && strpos($p['message'],'TEST ONLY')!==false,'property mapping');
check(!isset($p['status']),'repeat does not reset lead status');
$p=ahk_portal_payload(array('email'=>'test@example.com'),'step-1');
check(!isset($p['message'])&&!isset($p['phone'])&&!isset($p['firstName']),'partial does not erase absent fields');
echo "All mapping tests passed\n";
class WP_Error {}
function is_wp_error($v){return $v instanceof WP_Error;}
function wp_json_encode($v){return json_encode($v);}
$GLOBALS['calls']=array();$GLOBALS['opts']=array();$GLOBALS['cron']=array();$GLOBALS['http']=201;
function wp_remote_post($url,$args){$GLOBALS['calls'][]=array($url,$args);return array('response'=>array('code'=>$GLOBALS['http']));}
function wp_remote_retrieve_response_code($r){return $r['response']['code'];}
function get_option($key,$default=false){return isset($GLOBALS['opts'][$key])?$GLOBALS['opts'][$key]:$default;}
function update_option($key,$value,$autoload=false){$GLOBALS['opts'][$key]=$value;return true;}
function wp_schedule_single_event($time,$hook,$args){$GLOBALS['cron'][]=array($time,$hook,$args);return true;}
define('AHK_PORTAL_CONFIG_FILE',__DIR__.'/fixture-config.php');
check(function_exists('ahk_portal_observe'),'portal observer exists');
$original=array('response'=>array('code'=>200),'body'=>'GHL response unchanged');
$args=array('method'=>'POST','body'=>json_encode(array('formData'=>array('fname'=>'Test','email'=>'test@example.com'))));
foreach(array('step-1','step-2','elementor-offer','elementor-contact') as $step){
 $before=count($GLOBALS['calls']);$result=ahk_portal_observe($original,$args,'https://affordablehomeking.com/wp-json/ahk-ghl/v1/'.$step);
 check($result===$original && count($GLOBALS['calls'])===$before+1,'dual delivery preserves GHL response '.$step);
}
check($GLOBALS['calls'][0][0]==='https://clients.havenmediasolutions.com/api/leads','fixed portal endpoint');
check($GLOBALS['calls'][0][1]['headers']['x-api-key']==='test-key-not-real','server-side key');
$before=count($GLOBALS['calls']);
ahk_portal_observe(false,$args,'https://other.example/wp-json/ahk-ghl/v1/step-1');
ahk_portal_observe(false,array('method'=>'GET'),'https://affordablehomeking.com/wp-json/ahk-ghl/v1/step-1');
ahk_portal_observe(false,array('method'=>'POST','body'=>'{}'),'https://affordablehomeking.com/wp-json/ahk-ghl/v1/step-1');
check(count($GLOBALS['calls'])===$before,'unrelated or invalid requests ignored');
$GLOBALS['http']=503;
check(ahk_portal_observe($original,$args,'https://affordablehomeking.com/wp-json/ahk-ghl/v1/step-1')===$original,'portal failure never replaces GHL response');
check(count($GLOBALS['cron'])===1 && $GLOBALS['cron'][0][1]==='ahk_portal_retry','failed delivery scheduled for retry');
$GLOBALS['http']=200;$retry=$GLOBALS['cron'][0][2];
call_user_func_array('ahk_portal_deliver',$retry);
check(count($GLOBALS['cron'])===1,'successful retry is not rescheduled');
$log=$GLOBALS['opts']['ahk_portal_delivery'];
check(end($log)['status']==='accepted' && strpos(json_encode($log),'test@example.com')===false,'bounded delivery log excludes contact values');
echo "All delivery tests passed\n";
