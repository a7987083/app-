<?php
require_once dirname(__DIR__) . '/application/common/library/IpaRemoteFile.php';
require_once dirname(__DIR__) . '/application/common/library/IpaFoundation.php';
require_once dirname(__DIR__) . '/application/common/library/IpaGovernanceService.php';

use app\common\library\IpaGovernanceService;
use app\common\library\IpaFoundation;

function phase206Assert($condition,$message){if(!$condition){fwrite(STDERR,"FAIL phase20_governance_contract_test: {$message}\n");exit(1);}}

phase206Assert(IpaGovernanceService::pathFromPublicUrl('http://yun.zonoeios.xyz/d/a/app/Demo.ipa','http://yun.zonoeios.xyz/d/{path}')==='/a/app/Demo.ipa','template path extraction');
phase206Assert(IpaGovernanceService::pathFromPublicUrl('http://yun.zonoeios.xyz/d/a/app/Demo.ipa','http://yun.zonoeios.xyz/d')==='/a/app/Demo.ipa','prefix path extraction');
phase206Assert(IpaGovernanceService::pathFromPublicUrl('https://other/Demo.ipa','http://yun.zonoeios.xyz/d/{path}')==='','foreign URL rejected');

$controller=file_get_contents(dirname(__DIR__).'/application/admin/controller/IpaCenter.php');
foreach(['governanceRefresh','governanceList','governancePreview','governanceApply','governanceIgnore','governanceVerify'] as $action)phase206Assert(strpos($controller,'function '.$action.'(')!==false,'controller action '.$action);
$service=file_get_contents(dirname(__DIR__).'/application/common/library/IpaGovernanceService.php');
foreach(['plan_hash','治理计划已变化，请重新预览','OpenList 目标文件已存在，禁止自动覆盖','idempotencyKey','verifyPlan'] as $needle)phase206Assert(strpos($service,$needle)!==false,'governance safety '.$needle);
$client=file_get_contents(dirname(__DIR__).'/application/common/library/IpaOpenListClient.php');
phase206Assert(strpos($client,"'/fs/rename'")!==false,'OpenList rename endpoint');
phase206Assert(strpos($client,"'/fs/move'")!==false,'OpenList move endpoint');
$view=file_get_contents(dirname(__DIR__).'/application/admin/view/ipa_center/governance.html');
$backendJs=file_get_contents(dirname(__DIR__).'/public/assets/js/backend/ipa_center.js');
phase206Assert(strpos($view,'重新检测全部')!==false,'governance UI refresh');
phase206Assert(strpos($view,'ipa-governance-table')!==false,'governance FastAdmin table');
phase206Assert(strpos($view,'<tbody>')===false,'governance does not hand-render tbody');
phase206Assert(strpos($backendJs,"data-mode=\"db_to_openlist\"")!==false,'governance UI database to OpenList action');
phase206Assert(strpos($backendJs,"data-mode=\"openlist_to_db\"")!==false,'governance UI OpenList to database action');
phase206Assert(strpos($backendJs,'修复预览')!==false,'governance UI repair preview');
$sql=file_get_contents(dirname(__DIR__).'/application/admin/command/Install/phase20_ipa_governance.sql');
foreach(['governance_refresh','governance_preview','governance_apply','governance_verify'] as $rule)phase206Assert(strpos($sql,$rule)!==false,'governance auth '.$rule);

$planA=['issue_id'=>1,'action'=>'category_update','payload'=>['field'=>'nickname','new'=>'1.2.3']];
$planB=['payload'=>['new'=>'1.2.3','field'=>'nickname'],'action'=>'category_update','issue_id'=>1];
phase206Assert(IpaFoundation::planHash($planA)===IpaFoundation::planHash($planB),'governance plan hash canonical');

echo "OK phase20_governance_contract_test\n";
