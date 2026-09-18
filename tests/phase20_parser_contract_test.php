<?php
function p20ParserContractAssert($condition,$message){if(!$condition){fwrite(STDERR,"FAIL phase20_parser_contract_test: {$message}\n");exit(1);}}
$root=dirname(__DIR__);$script=file_get_contents($root.'/scripts/ipa-range-info.py');$service=file_get_contents($root.'/application/common/library/IpaParserService.php');$runner=file_get_contents($root.'/application/common/library/IpaParserRunner.php');$client=file_get_contents($root.'/application/common/library/IpaOpenListClient.php');
p20ParserContractAssert(strpos($script,"'Range':")!==false,'python parser sends Range header');
p20ParserContractAssert(strpos($script,'DEFAULT_MAX_FETCH')!==false,'range safety cap exists');
p20ParserContractAssert(strpos($script,"Payload/[^/]+\\.app/Info\\.plist")!==false,'main Info.plist discovery');
p20ParserContractAssert(strpos($script,'declared_icon_names')!==false && strpos($script,'primary_icon')!==false,'icon candidate logic');
p20ParserContractAssert(strpos($script,'mach_arches')!==false,'Mach-O architecture probe');
p20ParserContractAssert(strpos($script,'embedded.mobileprovision')!==false,'provisioning extraction');
p20ParserContractAssert(strpos($service,'findReusableMetadata')!==false,'MD5 metadata reuse');
p20ParserContractAssert(strpos($service,'RETRY_DELAY = 1800')!==false,'30 minute failure cooldown');
p20ParserContractAssert(strpos($service,"Db::name('category')")===false,'parser must not write category');
p20ParserContractAssert(strpos($runner,'proc_open')!==false,'parser worker is isolated process');
p20ParserContractAssert(strpos($client,"'/fs/get'")!==false,'raw_url obtained privately via fs/get');
echo "OK phase20_parser_contract_test\n";
