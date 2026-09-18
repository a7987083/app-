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

echo "OK phase20_ui_contract_test\n";
