<?php
require_once dirname(__DIR__) . '/application/common/library/IpaRangeMetricsService.php';
require_once dirname(__DIR__) . '/application/common/library/IpaRetentionService.php';

use app\common\library\IpaRangeMetricsService;
use app\common\library\IpaRetentionService;

function phase207mrAssert($condition,$message){if(!$condition){fwrite(STDERR,"FAIL phase207_metrics_retention_contract_test: {$message}\n");exit(1);}}

phase207mrAssert(IpaRangeMetricsService::normalizeDays(0)===1,'metrics minimum window');
phase207mrAssert(IpaRangeMetricsService::normalizeDays(999)===90,'metrics maximum window');
phase207mrAssert(IpaRetentionService::normalizeDays(1)===7,'retention minimum days');
phase207mrAssert(IpaRetentionService::normalizeDays(99999)===3650,'retention maximum days');
phase207mrAssert(IpaRetentionService::MAX_DELETE_PER_TABLE===1000,'retention bounded delete');

$retention=file_get_contents(dirname(__DIR__).'/application/common/library/IpaRetentionService.php');
foreach(["['success','superseded']","['success','cancelled']",'Retention 计划已变化，请重新预览','protected_states','plan_hash'] as $needle)phase207mrAssert(strpos($retention,$needle)!==false,'retention safety '.$needle);
$metrics=file_get_contents(dirname(__DIR__).'/application/common/library/IpaRangeMetricsService.php');
foreach(['range_bytes','range_requests','reused','MAX_SAMPLE_ROWS','truncated'] as $needle)phase207mrAssert(strpos($metrics,$needle)!==false,'range metrics '.$needle);
$controller=file_get_contents(dirname(__DIR__).'/application/admin/controller/IpaProduction.php');
foreach(['function metrics(','function retentionPreview(','function retentionApply('] as $needle)phase207mrAssert(strpos($controller,$needle)!==false,'production controller '.$needle);
$sql=file_get_contents(dirname(__DIR__).'/application/admin/command/Install/phase20_ipa_production.sql');
foreach(['ipa_production/metrics','ipa_production/retention_preview','ipa_production/retention_apply'] as $needle)phase207mrAssert(strpos($sql,$needle)!==false,'production auth '.$needle);
$view=file_get_contents(dirname(__DIR__).'/application/admin/view/ipa_center/governance.html');
foreach(['Phase 20.7.3 生产指标与 Retention','ipa-range-window','ipa-retention-days','预览清理'] as $needle)phase207mrAssert(strpos($view,$needle)!==false,'production UI '.$needle);
$js=file_get_contents(dirname(__DIR__).'/public/assets/js/backend/ipa_center.js');
foreach(['ipa_production/metrics','ipa_production/retention_preview','ipa_production/retention_apply','loadMetrics'] as $needle)phase207mrAssert(strpos($js,$needle)!==false,'production FastAdmin JS '.$needle);

echo "OK phase207_metrics_retention_contract_test\n";
