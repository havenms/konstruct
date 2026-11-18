<?php
const ABSPATH = '/fixture/';
function esc_attr($s) {return htmlspecialchars($s,ENT_QUOTES);}
require __DIR__.'/../includes/class-form-renderer.php';
$r=new ReflectionClass('Form_Builder_Renderer');$o=$r->newInstanceWithoutConstructor();$m=$r->getMethod('render_input');$m->setAccessible(true);
foreach(['fname'=>'given-name','lname'=>'family-name','first_name'=>'given-name','last_name'=>'family-name','name'=>'name','username'=>'username','email'=>'email','phone'=>'tel'] as $name=>$expected){ob_start();$m->invoke($o,'test',$name,'text',false,'');$html=ob_get_clean();if(strpos($html,'autocomplete="'.$expected.'"')===false){fwrite(STDERR,"FAIL $name should use $expected\n");exit(1);}}
echo "Name autocomplete regression passed\n";
