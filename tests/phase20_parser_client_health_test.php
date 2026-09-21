<?php
$root=dirname(__DIR__);
require_once $root.'/application/common/library/IpaParserRunner.php';
use app\common\library\IpaParserRunner;
function p20ClientAssert($condition,$message){if(!$condition){fwrite(STDERR,"FAIL phase20_parser_client_health_test: {$message}\n");exit(1);}}
p20ClientAssert(!function_exists('proc_open'),'proc_open must be disabled for this production-compat test');
$health=IpaParserRunner::health(1.0);
p20ClientAssert(!empty($health['ok']),'persistent parser health failed: '.(isset($health['error'])?$health['error']:''));
p20ClientAssert(isset($health['service'])&&$health['service']==='zonoe-ipa-parser','unexpected parser service identity');
echo "OK phase20_parser_client_health_test proc_open_disabled=passed persistent_rpc=passed\n";
