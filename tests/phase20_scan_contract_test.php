<?php

function p20ScanContractAssert($condition,$message){if(!$condition){fwrite(STDERR,"FAIL phase20_scan_contract_test: {$message}\n");exit(1);}}
$root=dirname(__DIR__);
$service=file_get_contents($root.'/application/common/library/IpaScanService.php');
$discovery=file_get_contents($root.'/application/common/library/IpaReferenceDiscoveryService.php');
$client=file_get_contents($root.'/application/common/library/IpaOpenListClient.php');
$dirCache=file_get_contents($root.'/application/common/library/IpaDirectoryCache.php');
$sql=file_get_contents($root.'/application/admin/command/Install/phase20_ipa_scan.sql');
$worksetSql=file_get_contents($root.'/application/admin/command/Install/phase20_ipa_workset.sql');
$command=file_get_contents($root.'/application/admin/command/IpaScan.php');
p20ScanContractAssert(strpos($service,"Db::name('category')")===false,'Phase 20.2 must not mutate/read category');
p20ScanContractAssert(strpos($service,'IpaReferenceDiscoveryService::listReferencedRemoteFiles')!==false,'scan service uses MySQL-reference discovery');
p20ScanContractAssert(strpos($service,'listIpaFiles')===false,'scan service must not fall back to full-root OpenList discovery');
p20ScanContractAssert(strpos($service,'IpaScanPlanner::plan')!==false,'scan service uses pure planner');
p20ScanContractAssert(strpos($discovery,'IpaDirectoryCache::load')!==false,'reference discovery reuses directory cache');
p20ScanContractAssert(strpos($discovery,'listDirectory')!==false,'reference discovery lists only referenced directories');
p20ScanContractAssert(strpos($service,'没有启用的 MySQL 软件源')!==false,'scan refuses to invent a full-root workset without MySQL sources');
p20ScanContractAssert(strpos($service,"'parse_state'=>'pending'")!==false || strpos($service,"'parse_state' => 'pending'")!==false,'new/changed files queue parser state');
p20ScanContractAssert(strpos($service,"'referenced'=>1")!==false || strpos($service,"'referenced' => 1")!==false,'scan marks active workset as referenced');
p20ScanContractAssert(strpos($client,'/fs/list')!==false,'OpenList client uses fs/list');
p20ScanContractAssert(strpos($client,'CURLOPT_POST')!==false,'OpenList client uses JSON POST');
p20ScanContractAssert(strpos($dirCache,'token_ciphertext')===false && strpos($dirCache,'Authorization:')===false,'directory cache does not persist OpenList token');
p20ScanContractAssert(strpos($sql,'fa_ipa_source')!==false,'source table migration');
p20ScanContractAssert(strpos($sql,'ipa_center/scan_start')!==false,'scan start permission');
p20ScanContractAssert(strpos($worksetSql,'fa_ipa_parse_cache')!==false,'workset migration creates durable MD5 parse cache');
p20ScanContractAssert(strpos($command,"setName('ipa:scan')")!==false,'CLI worker registered by class');
echo "OK phase20_scan_contract_test\n";