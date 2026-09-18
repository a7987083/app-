<?php
require_once dirname(__DIR__) . '/application/common/library/IpaFoundation.php';
require_once dirname(__DIR__) . '/application/common/library/IpaMetadataNormalizer.php';
use app\common\library\IpaMetadataNormalizer;
function p20ParserAssert($condition,$message){if(!$condition){fwrite(STDERR,"FAIL phase20_metadata_normalizer_test: {$message}\n");exit(1);}}
$parsed=['name'=>'Demo App','bundle_id'=>'com.example.demo','version'=>'1.2.3','build'=>'123','minimum_ios'=>'15.0','executable'=>'Demo','architectures'=>['arm64'],'url_schemes'=>['demo'],'icons'=>[['name'=>'AppIcon.png','width'=>180,'height'=>180]],'primary_icon'=>['name'=>'AppIcon.png','width'=>180,'height'=>180],'range_bytes'=>1048576,'range_requests'=>4];
$out=IpaMetadataNormalizer::normalize($parsed);
p20ParserAssert($out['columns']['bundle_id']==='com.example.demo','bundle id column');
p20ParserAssert($out['columns']['package_version']==='1.2.3','version column');
p20ParserAssert($out['normalized']['architectures']===['arm64'],'architecture normalized');
p20ParserAssert($out['normalized']['_parser']['range_bytes']===1048576,'range bytes kept');
p20ParserAssert($out['confidence']['bundle_id']==='exact','bundle id exact');
p20ParserAssert($out['confidence']['name']==='derived','display name derived');
echo "OK phase20_metadata_normalizer_test\n";
