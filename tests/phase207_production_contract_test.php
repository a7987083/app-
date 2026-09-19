<?php
require_once dirname(__DIR__) . '/application/common/library/IpaGovernanceBatchService.php';

use app\common\library\IpaGovernanceBatchService;

function phase207Assert($condition,$message){if(!$condition){fwrite(STDERR,"FAIL phase207_production_contract_test: {$message}\n");exit(1);}}

phase207Assert(IpaGovernanceBatchService::MAX_BATCH===100,'batch limit');
phase207Assert(IpaGovernanceBatchService::normalizeIssueIds([3,1,3,2])===[1,2,3],'batch ids unique and sorted');
phase207Assert(IpaGovernanceBatchService::defaultModeForIssue(['issue_type'=>'metadata_mismatch'])==='ipa_to_db','metadata safe batch mode');
phase207Assert(IpaGovernanceBatchService::defaultModeForIssue(['issue_type'=>'version_mismatch'])==='ipa_to_db','version safe batch mode');
phase207Assert(IpaGovernanceBatchService::defaultModeForIssue(['issue_type'=>'path_mismatch'])==='openlist_to_db','path safe batch mode');
phase207Assert(IpaGovernanceBatchService::defaultModeForIssue(['issue_type'=>'ipa_missing'])==='','missing ipa not auto repaired');
phase207Assert(IpaGovernanceBatchService::defaultModeForIssue(['issue_type'=>'duplicate_bundle'])==='','duplicate bundle not auto repaired');

$controller=file_get_contents(dirname(__DIR__).'/application/admin/controller/IpaCenter.php');
foreach(['governanceBatchPreview','governanceBatchApply','governanceFailures'] as $action)phase207Assert(strpos($controller,'function '.$action.'(')!==false,'controller action '.$action);
$service=file_get_contents(dirname(__DIR__).'/application/common/library/IpaGovernanceBatchService.php');
foreach(['批量治理计划已变化，请重新预览','MAX_BATCH','failedOperations','interrupted','IpaGovernanceService::apply'] as $needle)phase207Assert(strpos($service,$needle)!==false,'production governance '.$needle);
$recovery=file_get_contents(dirname(__DIR__).'/application/common/library/IpaGovernanceRecoveryService.php');
foreach(['markInterrupted','DEFAULT_STALE_SECONDS','governance_retry','superseded','Never replay the stale plan','只有 failed / interrupted 操作允许重试'] as $needle)phase207Assert(strpos($recovery,$needle)!==false,'recovery safety '.$needle);
$recoveryController=file_get_contents(dirname(__DIR__).'/application/admin/controller/IpaRecovery.php');
foreach(['scanInterrupted','retry','stale_seconds','operation_id'] as $needle)phase207Assert(strpos($recoveryController,$needle)!==false,'recovery controller '.$needle);
$sql=file_get_contents(dirname(__DIR__).'/application/admin/command/Install/phase20_ipa_governance.sql');
foreach(['governance_batch_preview','governance_batch_apply','governance_failures','ipa_recovery/scan_interrupted','ipa_recovery/retry'] as $rule)phase207Assert(strpos($sql,$rule)!==false,'phase20.7 auth '.$rule);
$view=file_get_contents(dirname(__DIR__).'/application/admin/view/ipa_center/governance.html');
foreach(['批量预览并修复','治理恢复队列','ipa-governance-table','扫描 5 分钟无更新的 running'] as $needle)phase207Assert(strpos($view,$needle)!==false,'phase20.7 governance UI '.$needle);
phase207Assert(strpos($view,'ipa_governance_production.js')===false,'phase20.7 legacy governance lifecycle removed from view');
$js=file_get_contents(dirname(__DIR__).'/public/assets/js/backend/ipa_center.js');
foreach(['governance_batch_preview','governance_batch_apply','governance_failures','getSelections','ipa_recovery/scan_interrupted','ipa_recovery/retry'] as $needle)phase207Assert(strpos($js,$needle)!==false,'phase20.7 FastAdmin governance JS '.$needle);

echo "OK phase207_production_contract_test\n";
