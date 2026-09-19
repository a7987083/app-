<?php
require_once dirname(__DIR__) . '/application/common/library/IpaGovernanceLifecycleService.php';

use app\common\library\IpaGovernanceLifecycleService;

function phase207lcAssert($condition,$message){if(!$condition){fwrite(STDERR,"FAIL phase207_lifecycle_contract_test: {$message}\n");exit(1);}}

phase207lcAssert(IpaGovernanceLifecycleService::MAX_BATCH===100,'lifecycle batch limit');
phase207lcAssert(IpaGovernanceLifecycleService::normalizeIds([3,1,3,2])===[1,2,3],'lifecycle normalize ids');
$service=file_get_contents(dirname(__DIR__).'/application/common/library/IpaGovernanceLifecycleService.php');
foreach(['ignore_until','governance_ignore_batch','governance_unignore_batch','governance_ignore_expiry_sweep','state\',\'ignored'] as $needle)phase207lcAssert(strpos($service,$needle)!==false,'lifecycle service '.$needle);
$controller=file_get_contents(dirname(__DIR__).'/application/admin/controller/IpaLifecycle.php');
foreach(['function ignoredList(','function ignoreBatch(','function unignoreBatch(','function sweepExpired('] as $needle)phase207lcAssert(strpos($controller,$needle)!==false,'lifecycle controller '.$needle);
$sql=file_get_contents(dirname(__DIR__).'/application/admin/command/Install/phase20_ipa_lifecycle.sql');
foreach(['ipa_lifecycle/ignored_list','ipa_lifecycle/ignore_batch','ipa_lifecycle/unignore_batch','ipa_lifecycle/sweep_expired'] as $needle)phase207lcAssert(strpos($sql,$needle)!==false,'lifecycle auth '.$needle);
$view=file_get_contents(dirname(__DIR__).'/application/admin/view/ipa_center/governance.html');
foreach(['ipa-phase207-capabilities','ipa_lifecycle/ignore_batch','ipa_lifecycle/unignore_batch','ipa_lifecycle/sweep_expired','ipa_production/retention_apply'] as $needle)phase207lcAssert(strpos($view,$needle)!==false,'lifecycle permission UI '.$needle);
$js=file_get_contents(dirname(__DIR__).'/public/assets/js/backend/ipa_governance_production.js');
foreach(['loadIgnored','ipa_lifecycle/ignored_list','ipa_lifecycle/ignore_batch','ipa_lifecycle/unignore_batch','ipa_lifecycle/sweep_expired','can-retry'] as $needle)phase207lcAssert(strpos($js,$needle)!==false,'lifecycle JS '.$needle);

echo "OK phase207_lifecycle_contract_test\n";
