<?php

function p20ScanContractAssert($condition,$message){if(!$condition){fwrite(STDERR,"FAIL phase20_scan_contract_test: {$message}\n");exit(1);}}
$root=dirname(__DIR__);
$service=file_get_contents($root.'/application/common/library/IpaScanService.php');
$client=file_get_contents($root.'/application/common/library/IpaOpenListClient.php');
$cache=file_get_contents($root.'/application/common/library/IpaInventoryCache.php');
$sql=file_get_contents($root.'/application/admin/command/Install/phase20_ipa_scan.sql');
$command=file_get_contents($root.'/application/admin/command/IpaScan.php');
p20ScanContractAssert(strpos($service,"Db::name('category')")===false,'Phase 20.2 must not mutate/read category');
p20ScanContractAssert(strpos($service,'listIpaFiles')!==false,'scan service calls remote listing');
p20ScanContractAssert(strpos($service,'IpaScanPlanner::plan')!==false,'scan service uses pure planner');
p20ScanContractAssert(strpos($service,'IpaInventoryCache::load')!==false,'scan service reuses directory inventory cache');
p20ScanContractAssert(strpos($service,"'parse_state'=>'pending'")!==false || strpos($service,"'parse_state' => 'pending'")!==false,'new/changed files queue parser state');
p20ScanContractAssert(strpos($client,'/fs/list')!==false,'OpenList client uses fs/list');
p20ScanContractAssert(strpos($client,'CURLOPT_POST')!==false,'OpenList client uses JSON POST');
p20ScanContractAssert(strpos($cache,'token_ciphertext')===false && strpos($cache,'Authorization:')===false,'inventory cache does not persist OpenList token');
p20ScanContractAssert(strpos($sql,'fa_ipa_source')!==false,'source table migration');
p20ScanContractAssert(strpos($sql,'ipa_center/scan_start')!==false,'scan start permission');
p20ScanContractAssert(strpos($command,"setName('ipa:scan')")!==false,'CLI worker registered by class');
echo "OK phase20_scan_contract_test\n";
