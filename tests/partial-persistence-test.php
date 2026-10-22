<?php
// Exercise the real storage and REST classes without loading WordPress.
define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');
class WP_Error {
    public $code; public $message; public $data;
    function __construct($code, $message, $data = array()) { $this->code=$code; $this->message=$message; $this->data=$data; }
}
class WP_REST_Response { public $data; function __construct($data, $status) { $this->data=$data; } }
function is_wp_error($v) { return $v instanceof WP_Error; }
function sanitize_text_field($v) { return trim((string)$v); }
function current_time($type) { return $GLOBALS['clock']; }
class Form_Builder_File_Handler {}
class Form_Builder_Email_Handler {
    static $sent = array();
    function send_submission_notification($id, $data, $uuid) { self::$sent[]=array($id,$data,$uuid); }
}
class MemoryDB {
    public $prefix='wp_'; public $rows=array(); public $insert_id=0; public $last_error='';
    public $fail=false; public $lock=true; public $locks=0;
    function prepare($sql, ...$args) {
        foreach ($args as $arg) $sql=preg_replace_callback('/%[ds]/', function($m) use($arg) { return $m[0]==='%d' ? (string)(int)$arg : "'".addslashes($arg)."'"; }, $sql, 1);
        return $sql;
    }
    function get_var($sql) {
        if (strpos($sql,'GET_LOCK')!==false) { if ($this->lock) $this->locks++; return $this->lock ? '1' : '0'; }
        if (strpos($sql,'RELEASE_LOCK')!==false) { $this->locks--; return '1'; }
        throw new Exception('Unhandled SQL: '.$sql);
    }
    function get_row($sql, $format) {
        if (strpos($sql,'form_builder_forms')!==false) return array('id'=>1,'form_config'=>json_encode(array('pages'=>array(array(),array(),array()))));
        preg_match("/submission_uuid = '([^']*)'/",$sql,$m);
        foreach($this->rows as $row) if(isset($m[1]) && $row['submission_uuid']===$m[1]) return $row;
        return null;
    }
    function insert($table,$data,$format) {
        if($this->fail) return false;
        foreach($this->rows as $row) if($row['submission_uuid']===$data['submission_uuid']) return false;
        $this->insert_id++;$data['id']=$this->insert_id;$this->rows[$this->insert_id]=$data;return 1;
    }
    function update($table,$data,$where,$format=null,$where_format=null) {
        if($this->fail) return false;
        $n=0;foreach($this->rows as &$row) { $match=true;foreach($where as $k=>$v) if($row[$k]!=$v) $match=false;if($match){$row=array_merge($row,$data);$n++;} } unset($row);return $n;
    }
}
function check($value,$message) { if(!$value) throw new Exception($message); }
require dirname(__DIR__).'/includes/class-form-storage.php';
require dirname(__DIR__).'/includes/class-rest-api.php';
$wpdb=new MemoryDB();$clock='2026-10-21 10:00:00';$storage=new Form_Builder_Storage();
$uuid='11111111-1111-4111-8111-111111111111';
$id=$storage->save_submission_record(1,$uuid,1,array('fname'=>'Alice'),false);
check($id===1,'partial must insert');
$clock='2026-10-21 11:00:00';
check($storage->save_submission_record(1,$uuid,2,array('phone'=>'555'),false)===$id,'partial must update same row');
check(count($wpdb->rows)===1,'one row');
check(json_decode($wpdb->rows[1]['form_data'],true)===array('fname'=>'Alice','phone'=>'555'),'cumulative merge');
check($wpdb->rows[1]['created_at']==='2026-10-21 10:00:00','creation timestamp preserved');
$storage->mark_submission_delivered($uuid);
check($storage->save_submission_record(1,$uuid,2,array('email'=>'alice@example.test'),false)===$id,'webhook delivery flag must not prevent partial merging');
check(isset(json_decode($wpdb->rows[1]['form_data'],true)['email']),'delivered partial still merges');
check($storage->save_submission_record(1,$uuid,3,array('address'=>'Main'),true)===$id,'final upgrades same row');
$final=$wpdb->rows[1];
check($final['delivered']===1 && $final['page_number']===3,'final status');
check($storage->save_submission_record(1,$uuid,1,array('fname'=>'STALE'),false)===$id,'stale partial is harmless success');
check($wpdb->rows[1]===$final,'stale partial cannot overwrite final');
check(is_wp_error($storage->save_submission_record(2,$uuid,1,array(),false)),'UUID/form mismatch rejected');
check($wpdb->rows[1]===$final,'mismatch cannot overwrite');
$wpdb->fail=true;check(is_wp_error($storage->save_submission_record(1,'new',1,array(),false)),'DB failure propagated');$wpdb->fail=false;
check($wpdb->locks===0,'all acquired locks released');
$wpdb->lock=false;$before=$wpdb->rows;
check(is_wp_error($storage->save_submission_record(1,'blocked',1,array(),false)),'lock timeout is retryable failure');
check($wpdb->rows===$before && $wpdb->locks===0,'lock failure does not write or release unowned lock');$wpdb->lock=true;
echo "storage partial persistence tests passed\n";
class RequestStub {
    private $params; private $multipart;
    function __construct($params,$multipart=false){$this->params=$params;$this->multipart=$multipart;}
    function get_header($name){return $this->multipart ? 'multipart/form-data; boundary=test' : 'application/json';}
    function get_param($name){return isset($this->params[$name]) ? $this->params[$name] : null;}
    function get_params(){return $this->params;}
    function get_json_params(){return $this->params;}
}
$api=new Form_Builder_REST_API();$wpdb=new MemoryDB();
$params=array('form_id'=>1,'submission_uuid'=>$uuid,'formData'=>array('fname'=>'Alice'),'partial'=>true,'page_number'=>1);
$response=$api->save_submission(new RequestStub($params));
check($response instanceof WP_REST_Response,'partial API success');
check($wpdb->rows[1]['page_number']===1 && $wpdb->rows[1]['delivered']===0,'partial API records partial state');
check(count(Form_Builder_Email_Handler::$sent)===0,'no partial email');
foreach(array(null,0,-1,3,4,'1.5','bogus') as $invalid){
    $bad=$params;$bad['page_number']=$invalid;$before=$wpdb->rows;
    check(is_wp_error($api->save_submission(new RequestStub($bad))),'invalid partial page rejected: '.json_encode($invalid));
    check($before===$wpdb->rows,'invalid partial never writes');
}
$bad=$params;$bad['form_id']=2;check(is_wp_error($api->save_submission(new RequestStub($bad))),'API rejects UUID/form mismatch');
$multipart=$params;$multipart['partial']='1';$multipart['page_number']='2';$multipart['formData']=json_encode(array('phone'=>'555'));
check($api->save_submission(new RequestStub($multipart,true)) instanceof WP_REST_Response,'multipart partial success');
check($wpdb->rows[1]['page_number']===2 && count(Form_Builder_Email_Handler::$sent)===0,'multipart partial state/no email');
$finalParams=$params;unset($finalParams['partial']);$finalParams['formData']=array('address'=>'Main');
check($api->save_submission(new RequestStub($finalParams)) instanceof WP_REST_Response,'legacy final success');
check(count($wpdb->rows)===1 && $wpdb->rows[1]['page_number']===3,'API final upgrades');
check(count(Form_Builder_Email_Handler::$sent)===1,'final email preserved');
check(json_decode($wpdb->rows[1]['form_data'],true)===array('fname'=>'Alice','phone'=>'555','address'=>'Main'),'API cumulative final merge');
$before=$wpdb->rows;$api->save_submission(new RequestStub($params));check($before===$wpdb->rows,'late API partial cannot overwrite final');
check(count(Form_Builder_Email_Handler::$sent)===1,'late partial no email');
echo "REST partial persistence tests passed\n";
