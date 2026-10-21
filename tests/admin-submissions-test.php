<?php
// Render the actual dashboard and assert every stored record appears once.
define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');
class Form_Builder_Storage {
    public function get_all_forms() { return array('forms' => array(array('id'=>1,'form_name'=>'QA','form_slug'=>'qa'))); }
}
class Test_Database {
    public $prefix='wp_';
    public function prepare($query, ...$args) {return $query;}
    public function get_var($query) {return 3;}
    public function get_results($query, $format) {
        $rows=array();
        foreach(array(3,2,1) as $id) {
            $rows[]=array('id'=>$id,'form_id'=>1,'form_name'=>'QA','form_slug'=>'qa','submission_uuid'=>'qa-'.$id,'page_number'=>1,'delivered'=>1,'created_at'=>'2026-10-08 21:10:17','form_data'=>json_encode(array('fname'=>'Record '.$id)));
        }
        return $rows;
    }
}
function sanitize_text_field($s){return $s;}
function _e($s, $domain){echo $s;}
function esc_attr($s){return htmlspecialchars((string)$s, ENT_QUOTES);}
function esc_html($s){return htmlspecialchars((string)$s, ENT_QUOTES);}
function selected($a,$b){if($a==$b)echo 'selected';}
function admin_url($s){return '/wp-admin/'.$s;}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
function paginate_links($args){return '';}
$wpdb=new Test_Database();
ob_start();
require dirname(__DIR__).'/admin/submissions.php';
$html=ob_get_clean();
preg_match_all("/data-submission='([^']+)'/", $html, $matches);
$ids=array();
foreach($matches[1] as $json){$row=json_decode(html_entity_decode($json,ENT_QUOTES),true);$ids[]=$row['id'];}
if($ids!==array(3,2,1)){
    fwrite(STDERR,'FAIL: dashboard must render every original record exactly once; got '.json_encode($ids)."\n");exit(1);
}
echo "PASS: dashboard renders IDs 3,2,1 exactly once without changing stored rows\n";
