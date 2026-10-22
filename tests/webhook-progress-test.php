<?php
// Regression: optional legacy webhook callbacks must use the locked, cumulative persistence path.
require __DIR__.'/partial-persistence-test.php';
define('FORM_BUILDER_PLUGIN_DIR',dirname(__DIR__).'/');
function esc_url_raw($value){return trim($value);}
$GLOBALS['webhook_calls']=0;
function wp_remote_request($url,$args){$GLOBALS['webhook_calls']++;return array('status'=>200,'body'=>'{"success":true}');}
function wp_remote_retrieve_response_code($r){return $r['status'];}
function wp_remote_retrieve_body($r){return $r['body'];}
class WebhookMemoryDB extends MemoryDB {
    public $logs=array();
    function get_var($sql){
        if(strpos($sql,'SELECT id FROM')!==false){preg_match("/submission_uuid = '([^']*)'/",$sql,$m);foreach($this->rows as $r)if(isset($m[1])&&$r['submission_uuid']===$m[1])return $r['id'];return null;}
        return parent::get_var($sql);
    }
    function insert($table,$data,$format){if(strpos($table,'webhook_logs')!==false){$this->logs[]=$data;return 1;}return parent::insert($table,$data,$format);}
}
require dirname(__DIR__).'/includes/class-webhook-handler.php';
$wpdb=new WebhookMemoryDB();$storage=new Form_Builder_Storage();$handler=new Form_Builder_Webhook_Handler();
$uuid='99999999-9999-4999-8999-999999999999';
$storage->save_submission_record(1,$uuid,1,array('fname'=>'Current','email'=>'fixture@example.test'),false);
$storage->save_submission_record(1,$uuid,3,array('property_address'=>'Final address','property_details'=>'Keep this'),true);
$completed=$wpdb->rows[1];
$base=array('form_id'=>1,'submission_uuid'=>$uuid,'page_number'=>1,'webhook_url'=>'https://example.test/hook','formData'=>array('fname'=>'STALE'));
$response=$handler->process_webhook(new RequestStub($base));
check($response instanceof WP_REST_Response,'delayed optional webhook remains a successful request');
check($wpdb->rows[1]===$completed,'delayed page-one webhook must not erase final property fields or regress completed page');
check(count($wpdb->rows)===1,'optional webhook cannot duplicate a submission');
$bad=$base;$bad['form_id']=2;$before=$wpdb->rows;$calls=$GLOBALS['webhook_calls'];
check(is_wp_error($handler->process_webhook(new RequestStub($bad))),'webhook rejects cross-form UUID reuse');
check($wpdb->rows===$before&&$GLOBALS['webhook_calls']===$calls,'cross-form rejection has no delivery or persistence side effects');
$wpdb->lock=false;$before=$wpdb->rows;$calls=$GLOBALS['webhook_calls'];
check(is_wp_error($handler->process_webhook(new RequestStub($base))),'lock timeout is propagated');
check($wpdb->rows===$before&&$GLOBALS['webhook_calls']===$calls,'lock failure does not send or change progress');$wpdb->lock=true;
$wpdb->fail=true;$bad=$base;$bad['submission_uuid']='new-failing-record';$calls=$GLOBALS['webhook_calls'];
check(is_wp_error($handler->process_webhook(new RequestStub($bad))),'webhook cannot report success after DB save failure');
check($GLOBALS['webhook_calls']===$calls,'failed persistence never dispatches a webhook');$wpdb->fail=false;
$new=$base;$new['submission_uuid']='fresh-webhook';$new['formData']=array('fname'=>'New');
check($handler->process_webhook(new RequestStub($new)) instanceof WP_REST_Response,'legacy standalone first-step webhook still inserts');
$new['page_number']=3;$new['formData']=array('property_address'=>'Merged');
check($handler->process_webhook(new RequestStub($new)) instanceof WP_REST_Response,'legacy standalone final webhook still updates');
check(count($wpdb->rows)===2&&$wpdb->rows[2]['page_number']===3,'standalone final uses the same row');
check(json_decode($wpdb->rows[2]['form_data'],true)===array('fname'=>'New','property_address'=>'Merged'),'standalone webhook progress is cumulative');
check($wpdb->locks===0,'webhook locks are released');
echo "webhook progress persistence regressions passed\n";
