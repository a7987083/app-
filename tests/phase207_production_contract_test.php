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
foreach(['批量治理计划已变化，请重新预览','MAX_BATCH','failedOperations','governance_%','IpaGovernanceService::apply'] as $needle)phase207Assert(strpos($service,$needle)!==false,'production governance '.$needle);
$sql=file_get_contents(dirname(__DIR__).'/application/admin/command/Install/phase20_ipa_governance.sql');
foreach(['governance_batch_preview','governance_batch_apply','governance_failures'] as $rule)phase207Assert(strpos($sql,$rule)!==false,'phase20.7 auth '.$rule);

echo "OK phase207_production_contract_test\n";
