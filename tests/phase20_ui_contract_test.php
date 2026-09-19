<?php

function phase20UiAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase20_ui_contract_test: {$message}\n");
        exit(1);
    }
}

function phase20AssertNoSuccessInsideTry($source, $label)
{
    $pattern = '/try\s*\{(?:(?!catch\s*\().)*\$this->success\s*\(/s';
    phase20UiAssert(!preg_match($pattern, $source), "{$label} keeps FastAdmin success responses outside business try/catch");
}

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/application/admin/controller/IpaCenter.php');
$recoveryController = file_get_contents($root . '/application/admin/controller/IpaRecovery.php');
$lifecycleController = file_get_contents($root . '/application/admin/controller/IpaLifecycle.php');
$productionController = file_get_contents($root . '/application/admin/controller/IpaProduction.php');
foreach (['index','metadata','binding','governance','task','writeback','setting'] as $action) {
    phase20UiAssert(strpos($controller, 'function ' . $action . '(') !== false, "controller action {$action}");
    phase20UiAssert(is_file($root . '/application/admin/view/ipa_center/' . $action . '.html'), "view {$action}");
}

$menuSql = file_get_contents($root . '/application/admin/command/Install/phase20_ipa_center.sql');
foreach (['ipa_center/index','ipa_center/metadata','ipa_center/binding','ipa_center/governance','ipa_center/task','ipa_center/writeback','ipa_center/setting'] as $rule) {
    phase20UiAssert(strpos($menuSql, "'{$rule}'") !== false, "menu rule {$rule}");
}

$views = [];
foreach (['index','metadata','binding','governance','task','writeback','setting'] as $name) {
    $views[$name] = file_get_contents($root . '/application/admin/view/ipa_center/' . $name . '.html');
}
$gov = $views['governance'];
foreach (['重复 Bundle ID','元数据不一致','IPA 缺失 / 路径异常','版本异常'] as $label) {
    phase20UiAssert(strpos($gov, $label) !== false, "governance card {$label}");
}

$writeback = $views['writeback'];
foreach (['category.name','category.nickname','category.image','category.bt1a','category.bt2a','category.keywords'] as $field) {
    phase20UiAssert(strpos($writeback, $field) !== false, "writeback target {$field}");
}

$setting = $views['setting'];
$sourceConfig = file_get_contents($root . '/application/common/library/IpaSourceConfig.php');
$payloadStore = file_get_contents($root . '/application/common/library/IpaMetadataPayloadStore.php');
$backendJs = file_get_contents($root . '/public/assets/js/backend/ipa_center.js');
$fastJs = file_get_contents($root . '/public/assets/js/fast.js');
$commonBehavior = file_get_contents($root . '/application/common/behavior/Common.php');
$scanService = file_get_contents($root . '/application/common/library/IpaScanService.php');
$parserService = file_get_contents($root . '/application/common/library/IpaParserService.php');
$version = trim(file_get_contents($root . '/VERSION'));

phase20UiAssert($version === '2026091911', 'phase20 FastAdmin response lifecycle recovery targets the 2026091911 candidate');
phase20UiAssert(strpos($setting, 'OpenList 令牌') !== false, 'settings use provider token terminology');
phase20UiAssert(strpos($setting, 'OpenList 设置 → 其他 → 令牌') !== false, 'settings show provider token location');
phase20UiAssert(strpos($setting, 'name="api_base"') === false, 'API prefix is not user-configurable');
phase20UiAssert(strpos($setting, 'name="token"') !== false, 'token is submitted to sourceSave contract');
phase20UiAssert(strpos($setting, 'autocomplete="new-password"') !== false, 'token input discourages browser credential autofill');
phase20UiAssert(strpos($setting, 'data-lpignore="true"') !== false, 'token input discourages password-manager autofill');
phase20UiAssert(strpos($setting, 'role="form"') !== false, 'settings use FastAdmin form role');
phase20UiAssert(strpos($setting, 'data-toggle="validator"') !== false, 'settings use FastAdmin validator');
phase20UiAssert(strpos($setting, "action=\"{:url('ipa_center/source_save')}\"") !== false, 'settings keep framework-generated source_save form action');
phase20UiAssert(strpos($setting, '{:token()}') !== false, 'settings include CSRF token');
phase20UiAssert(strpos($setting, 'type="submit"') !== false, 'settings use native submit button');
phase20UiAssert(strpos($setting, 'btn-ipa-source-save') === false, 'legacy custom save button removed');
phase20UiAssert(strpos($setting, 'btn-ipa-source-test') !== false, 'test connection remains an explicit action');
phase20UiAssert(strpos($setting, '<script>') === false, 'settings do not depend on inline script execution');
phase20UiAssert(strpos($setting, '请先点击“确定”保存') !== false, 'settings explain saved-config connection test flow');
phase20UiAssert(strpos($setting, '直接使用当前表单内容') === false, 'settings no longer advertise unsaved-input connection testing');
phase20UiAssert(strpos($setting, '已保存令牌读取失败') !== false, 'settings surface persisted token decode failures');
phase20UiAssert(strpos($backendJs, 'Form.api.bindevent') !== false, 'FastAdmin Form lifecycle is bound');
phase20UiAssert(strpos($backendJs, '$.ajaxPrefilter') === false, 'IPA module does not override FastAdmin AJAX routing');
phase20UiAssert(strpos($backendJs, "'s=/'") === false, 'IPA module does not force ThinkPHP query-route workarounds');
phase20UiAssert(strpos($backendJs, "url:'ipa_center/source_test'") !== false, 'IPA source test keeps native FastAdmin controller/action URL');

$centerActions = [
    'task_list','scan_start','metadata_list','parse_start','binding_list','binding_candidates','binding_apply','binding_remove',
    'governance_refresh','governance_list','governance_preview','governance_apply','governance_ignore','governance_verify',
    'governance_batch_preview','governance_batch_apply','governance_failures','writeback_rules','writeback_seed','writeback_save',
    'writeback_random_preview','source_save','source_test'
];
foreach ($centerActions as $action) {
    phase20UiAssert(strpos($controller, 'function ' . $action . '(') !== false, "IpaCenter exposes native FastAdmin action {$action}");
}
foreach (['scan_interrupted','retry'] as $action) {
    phase20UiAssert(strpos($recoveryController, 'function ' . $action . '(') !== false, "IpaRecovery exposes native FastAdmin action {$action}");
}
foreach (['ignored_list','ignore_batch','unignore_batch','sweep_expired'] as $action) {
    phase20UiAssert(strpos($lifecycleController, 'function ' . $action . '(') !== false, "IpaLifecycle exposes native FastAdmin action {$action}");
}
foreach (['metrics','retention_preview','retention_apply'] as $action) {
    phase20UiAssert(strpos($productionController, 'function ' . $action . '(') !== false, "IpaProduction exposes native FastAdmin action {$action}");
}
foreach (['IpaCenter'=>$controller,'IpaRecovery'=>$recoveryController,'IpaLifecycle'=>$lifecycleController,'IpaProduction'=>$productionController] as $label=>$source) {
    phase20AssertNoSuccessInsideTry($source, $label);
}
phase20UiAssert(strpos($controller, "\$this->success('IPA 网络源已保存'") !== false, 'source save preserves FastAdmin success response');
phase20UiAssert(strpos($controller, "\$this->success('OpenList 连接正常'") !== false, 'source test preserves FastAdmin success response');
phase20UiAssert(strpos($controller, "throw new RuntimeException('当前没有已绑定 IPA')") !== false, 'writeback validation uses domain exception inside business try block');

phase20UiAssert(strpos($sourceConfig, 'public static function testSaved()') !== false, 'OpenList test has explicit saved-config entry point');
phase20UiAssert(strpos($sourceConfig, 'return self::testSaved();') !== false, 'legacy testInput delegates to saved configuration contract');
phase20UiAssert(strpos($sourceConfig, "self::publicRow(\$row,true)") !== false, 'saved test decrypts persisted token before connection test');
phase20UiAssert(strpos($sourceConfig, "\$saved=self::readConfig()") !== false, 'save verifies persisted configuration can be read back');
phase20UiAssert(strpos($sourceConfig, 'hash_equals($token,$roundTrip)') !== false, 'save verifies persisted token encryption round trip');
phase20UiAssert(strpos($sourceConfig, "\$out['token_error']=\$tokenError") !== false, 'public config exposes safe token decode status without exposing token');

foreach (['metadata'=>'ipa-meta-table','binding'=>'ipa-binding-table','task'=>'ipa-task-table','writeback'=>'ipa-writeback-rule-table','governance'=>'ipa-governance-table'] as $page=>$tableId) {
    phase20UiAssert(strpos($views[$page], 'id="' . $tableId . '"') !== false, "{$page} has FastAdmin table");
    phase20UiAssert(strpos($views[$page], '<tbody>') === false, "{$page} no longer hand-renders tbody");
}
phase20UiAssert(strpos($gov, 'ipa_governance_production.js') === false, 'governance no longer loads a second page lifecycle');
phase20UiAssert(strpos($gov, '<script>') === false, 'governance does not use inline require');
phase20UiAssert(substr_count($backendJs, 'bootstrapTable') >= 10, 'IPA pages use BootstrapTable lifecycle');

phase20UiAssert(strpos($sourceConfig, "const API_BASE = '/api';") !== false, 'OpenList API prefix fixed in application code');
phase20UiAssert(strpos($sourceConfig, "'api_base'=>self::API_BASE") !== false, 'runtime source always uses provider API prefix');
phase20UiAssert(strpos($sourceConfig, 'isset($input[\'api_base\'])') === false, 'user input cannot override provider API prefix');
phase20UiAssert(strpos($sourceConfig, "'token_ciphertext'=>self::sealToken") !== false, 'token is encrypted in runtime config');
phase20UiAssert(strpos($sourceConfig, "runtimeDir().'openlist.json'") !== false, 'OpenList config is stored under runtime/ipa');
phase20UiAssert(strpos($sourceConfig, "Db::name('ipa_source')->where") !== false, 'legacy DB config is import-only for upgrade compatibility');
phase20UiAssert(substr_count($sourceConfig, "Db::name('ipa_source')") === 1, 'new OpenList configuration no longer writes to source table');
phase20UiAssert((bool)preg_match('/protected\s+\$noNeedRight\s*=\s*\[[^\]]*source_save[^\]]*source_test[^\]]*\]/', $controller), 'save/test bypass names match underscore actions');
phase20UiAssert(strpos($controller, "check('ipa_center/setting')") !== false, 'save/test inherit visible setting permission');
phase20UiAssert(substr_count($controller, 'catch(\\Throwable $e)') >= 2, 'save/test catch PHP 7 Throwable failures');
phase20UiAssert(strpos($fastJs, 'xhr.responseJSON') !== false && strpos($fastJs, 'xhr.responseText') !== false, 'HTTP failures surface backend error details instead of generic error');
foreach (['fast.js','require-backend.js','backend.js','backend-init.js'] as $coreJs) {
    phase20UiAssert(strpos($commonBehavior, "'{$coreJs}'") !== false, "asset cache version tracks {$coreJs}");
}
phase20UiAssert(strpos($payloadStore, 'metadata-detail') !== false, 'large IPA parser payloads live outside MySQL');
phase20UiAssert(strpos($parserService, "'confidence_json'=>'','raw_metadata_json'=>'','normalized_metadata_json'=>''") !== false, 'new parser results keep legacy blob columns empty');
phase20UiAssert(strpos($scanService, 'TASK_ITEM_RETENTION_DAYS = 90') !== false, 'task item history is bounded to metrics window');

echo "OK phase20_ui_contract_test\n";
