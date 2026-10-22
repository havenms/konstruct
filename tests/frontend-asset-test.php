<?php
define('ABSPATH',__DIR__);
define('FORM_BUILDER_PLUGIN_DIR',dirname(__DIR__).'/');
define('FORM_BUILDER_PLUGIN_URL','https://example.invalid/konstruct/');
define('FORM_BUILDER_VERSION','1.3.1');
define('FORM_BUILDER_DEV_MODE',false);
class WP_Post { public $post_content='[form_builder id="1"]'; }
function has_shortcode($s,$tag){return true;}
function wp_enqueue_style(...$args){}
function wp_enqueue_script($handle,$src,$deps,$version,$footer){$GLOBALS['version']=$version;}
function wp_localize_script(...$args){}
function rest_url($s){return 'https://example.invalid/wp-json/'.$s;}
function wp_create_nonce($s){return 'test';}
require dirname(__DIR__).'/includes/class-asset-manager.php';
$post=new WP_Post();
(new Form_Builder_Asset_Manager())->enqueue_frontend_assets();
$expected=FORM_BUILDER_VERSION.'.'.filemtime(FORM_BUILDER_PLUGIN_DIR.'frontend/form.js');
if($GLOBALS['version']!==$expected){fwrite(STDERR,"FAIL: modified frontend must have content-deployment cache busting\n");exit(1);}
echo "PASS: frontend URL includes deployed script modification time\n";
function wp_add_inline_script(...$args){}
function esc_js($s){return $s;}
require dirname(__DIR__).'/includes/class-form-renderer.php';
$renderer=(new ReflectionClass('Form_Builder_Renderer'))->newInstanceWithoutConstructor();
$enqueue=new ReflectionMethod('Form_Builder_Renderer','enqueue_form_assets');
$enqueue->setAccessible(true);
$GLOBALS['version']=null;
$enqueue->invoke($renderer,'test',array('id'=>1,'form_slug'=>'test','form_config'=>array('pages'=>array())));
if($GLOBALS['version']!==$expected){fwrite(STDERR,"FAIL: shortcode renderer must also cache-bust deployed frontend\n");exit(1);}
echo "PASS: shortcode renderer URL includes deployed script modification time\n";
