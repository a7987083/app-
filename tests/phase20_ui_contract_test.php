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
$backendJs = file_get_contents($root . '/public/assets/js/backend/ipa_center.js');
phase20UiAssert(strpos($setting, 'OpenList 令牌') !== false, 'settings use provider token terminology');
phase20UiAssert(strpos($setting, 'OpenList 设置 → 其他 → 令牌') !== false, 'settings show provider token location');
phase20UiAssert(strpos($setting, 'name="api_base"') === false, 'API prefix is not user-configurable');
phase20UiAssert(strpos($setting, 'name="token"') !== false, 'token is submitted to existing sourceSave contract');
phase20UiAssert(strpos($setting, 'autocomplete="new-password"') !== false, 'token input discourages browser credential autofill');
phase20UiAssert(strpos($setting, 'data-lpignore="true"') !== false, 'token input discourages password-manager autofill');
phase20UiAssert(strpos($setting, 'btn-ipa-source-save') !== false, 'save button uses FastAdmin controller binding');
phase20UiAssert(strpos($setting, 'btn-ipa-source-test') !== false, 'test button uses FastAdmin controller binding');
phase20UiAssert(strpos($setting, '<script>') === false, 'settings do not depend on inline script execution');
phase20UiAssert(strpos($backendJs, "$('.btn-ipa-source-save').on('click'") !== false, 'FastAdmin controller binds save button');
phase20UiAssert(strpos($backendJs, "$('.btn-ipa-source-test').on('click'") !== false, 'FastAdmin controller binds test button');
phase20UiAssert(strpos($sourceConfig, "const API_BASE = '/api';") !== false, 'OpenList API prefix fixed in application code');
phase20UiAssert(strpos($sourceConfig, "'api_base'=>self::API_BASE") !== false, 'saved source always uses provider API prefix');
phase20UiAssert(strpos($sourceConfig, 'isset($input[\'api_base\'])') === false, 'user input cannot override provider API prefix');

echo "OK phase20_ui_contract_test\n";
