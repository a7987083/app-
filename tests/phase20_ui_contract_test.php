<?php

function phase20UiAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase20_ui_contract_test: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/application/admin/controller/IpaCenter.php');
foreach (['index','metadata','binding','governance','task','writeback','setting'] as $action) {
    phase20UiAssert(strpos($controller, 'function ' . $action . '(') !== false, "controller action {$action}");
    phase20UiAssert(is_file($root . '/application/admin/view/ipa_center/' . $action . '.html'), "view {$action}");
}

$menuSql = file_get_contents($root . '/application/admin/command/Install/phase20_ipa_center.sql');
foreach (['ipa_center/index','ipa_center/metadata','ipa_center/binding','ipa_center/governance','ipa_center/task','ipa_center/writeback','ipa_center/setting'] as $rule) {
    phase20UiAssert(strpos($menuSql, "'{$rule}'") !== false, "menu rule {$rule}");
}

$gov = file_get_contents($root . '/application/admin/view/ipa_center/governance.html');
foreach (['重复 Bundle ID','元数据不一致','IPA 缺失 / 路径异常','版本异常'] as $label) {
    phase20UiAssert(strpos($gov, $label) !== false, "governance card {$label}");
}

$writeback = file_get_contents($root . '/application/admin/view/ipa_center/writeback.html');
foreach (['category.name','category.nickname','category.image','category.bt1a','category.bt2a','category.keywords'] as $field) {
    phase20UiAssert(strpos($writeback, $field) !== false, "writeback target {$field}");
}

$setting = file_get_contents($root . '/application/admin/view/ipa_center/setting.html');
$sourceConfig = file_get_contents($root . '/application/common/library/IpaSourceConfig.php');
$payloadStore = file_get_contents($root . '/application/common/library/IpaMetadataPayloadStore.php');
$backendJs = file_get_contents($root . '/public/assets/js/backend/ipa_center.js');
$fastJs = file_get_contents($root . '/public/assets/js/fast.js');
$commonBehavior = file_get_contents($root . '/application/common/behavior/Common.php');
$scanService = file_get_contents($root . '/application/common/library/IpaScanService.php');
$parserService = file_get_contents($root . '/application/common/library/IpaParserService.php');
$version = trim(file_get_contents($root . '/VERSION'));

phase20UiAssert($version === '2026091906', 'appstore memory hotfix targets the 2026091906 candidate');
phase20UiAssert(strpos($setting, 'OpenList 令牌') !== false, 'settings use provider token terminology');
phase20UiAssert(strpos($setting, 'OpenList 设置 → 其他 → 令牌') !== false, 'settings show provider token location');
phase20UiAssert(strpos($setting, 'name="api_base"') === false, 'API prefix is not user-configurable');
phase20UiAssert(strpos($setting, 'name="token"') !== false, 'token is submitted to sourceSave contract');
phase20UiAssert(strpos($setting, 'autocomplete="new-password"') !== false, 'token input discourages browser credential autofill');
phase20UiAssert(strpos($setting, 'data-lpignore="true"') !== false, 'token input discourages password-manager autofill');
phase20UiAssert(strpos($setting, 'btn-ipa-source-save') !== false, 'save button uses FastAdmin controller binding');
phase20UiAssert(strpos($setting, 'btn-ipa-source-test') !== false, 'test button uses FastAdmin controller binding');
phase20UiAssert(strpos($setting, '<script>') === false, 'settings do not depend on inline script execution');
phase20UiAssert(strpos($backendJs, "$('.btn-ipa-source-save').on('click'") !== false, 'FastAdmin controller binds save button');
phase20UiAssert(strpos($backendJs, "$('.btn-ipa-source-test').on('click'") !== false, 'FastAdmin controller binds test button');
phase20UiAssert(strpos($sourceConfig, "const API_BASE = '/api';") !== false, 'OpenList API prefix fixed in application code');
phase20UiAssert(strpos($sourceConfig, "'api_base'=>self::API_BASE") !== false, 'runtime source always uses provider API prefix');
phase20UiAssert(strpos($sourceConfig, 'isset($input[\'api_base\'])') === false, 'user input cannot override provider API prefix');
phase20UiAssert(strpos($sourceConfig, "'token_ciphertext'=>self::sealToken") !== false, 'token is encrypted in runtime config');
phase20UiAssert(strpos($sourceConfig, "runtimeDir().'openlist.json'") !== false, 'OpenList config is stored under runtime/ipa');
phase20UiAssert(strpos($sourceConfig, "Db::name('ipa_source')->where") !== false, 'legacy DB config is import-only for upgrade compatibility');
phase20UiAssert(substr_count($sourceConfig, "Db::name('ipa_source')") === 1, 'new OpenList configuration no longer writes to source table');
phase20UiAssert(strpos($controller, "protected \$noNeedRight=['source_save','source_test']") !== false, 'save/test bypass names exactly match underscore actions');
phase20UiAssert(strpos($controller, "['sourcesave','sourcetest']") === false, 'camelized noNeedRight regression is forbidden');
phase20UiAssert(strpos($controller, "check('ipa_center/setting')") !== false, 'save/test inherit visible setting permission');
phase20UiAssert(substr_count($controller, 'catch(\\Throwable $e)') >= 2, 'save/test catch PHP 7 Throwable failures');
phase20UiAssert(strpos($fastJs, 'xhr.responseJSON') !== false && strpos($fastJs, 'xhr.responseText') !== false, 'HTTP failures surface backend error details instead of generic error');
foreach (['fast.js','require-backend.js','backend.js','backend-init.js'] as $coreJs) {
    phase20UiAssert(strpos($commonBehavior, "'{$coreJs}'") !== false, "asset cache version tracks {$coreJs}");
}
phase20UiAssert(strpos($payloadStore, "metadata-detail") !== false, 'large IPA parser payloads live outside MySQL');
phase20UiAssert(strpos($parserService, "'confidence_json'=>'','raw_metadata_json'=>'','normalized_metadata_json'=>''") !== false, 'new parser results keep legacy blob columns empty');
phase20UiAssert(strpos($scanService, 'TASK_ITEM_RETENTION_DAYS = 90') !== false, 'task item history is bounded to metrics window');

echo "OK phase20_ui_contract_test\n";
