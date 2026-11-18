<?php
require __DIR__.'/ahk-ghl-relay-test.php';
$_SERVER['HTTP_REFERER']='https://affordablehomeking.com/?utm_source=fb&utm_medium=paid&utm_campaign=fixture';
ahk_ghl_relay_request(false,$args,'https://affordablehomeking.com/wp-json/ahk-ghl/v1/step-1');
$last=end($GLOBALS['ahk_test_requests']);$body=json_decode($last[1]['body'],true);
check(($body['formData']['lead_source']??'')==='Facebook / paid | AHK Website - Konstruct Cash Offer','Facebook campaign and form source reach the webhook');
foreach(['step-1'=>'Konstruct Cash Offer','step-2'=>'Konstruct Cash Offer','elementor-offer'=>'Elementor Property Inquiry','elementor-contact'=>'Elementor Contact Message'] as $route=>$label){
 unset($_SERVER['HTTP_REFERER']);ahk_ghl_relay_request(false,$args,'https://affordablehomeking.com/wp-json/ahk-ghl/v1/'.$route);$last=end($GLOBALS['ahk_test_requests']);$body=json_decode($last[1]['body'],true);check($body['formData']['lead_source']==='AHK Website - '.$label,'fallback distinguishes provider without inventing acquisition source');
}
$_SERVER['HTTP_REFERER']='https://evil.invalid/?utm_source=fb';ahk_ghl_relay_request(false,$args,'https://affordablehomeking.com/wp-json/ahk-ghl/v1/step-1');$last=end($GLOBALS['ahk_test_requests']);$body=json_decode($last[1]['body'],true);check($body['formData']['lead_source']==='AHK Website - Konstruct Cash Offer','foreign referring URLs are ignored');
echo "AHK source regression passed\n";
