<?php

function blacklistContractFail($message)
{
    fwrite(STDERR, "FAIL blacklist_persistence_contract_test: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$files = array(
    'admin add' => $root . '/application/admin/controller/Black.php',
    'monitor move' => $root . '/application/admin/controller/Monitor.php',
    'auto blacklist' => $root . '/application/index/controller/App.php',
);

foreach ($files as $label => $file) {
    if (!is_file($file)) {
        blacklistContractFail('missing ' . $label . ' source');
    }
    $source = file_get_contents($file);
    if (strpos($source, 'BlacklistPolicy::insertData') === false) {
        blacklistContractFail($label . ' does not use complete blacklist insert data');
    }
}

$blackController = file_get_contents($files['admin add']);
if (strpos($blackController, '$result !== 1') === false) {
    blacklistContractFail('admin add does not verify insert result');
}

$model = file_get_contents($root . '/application/admin/model/Black.php');
if (strpos($model, "setUsetimeAttr") === false || strpos($model, "setEndtimeAttr") === false) {
    blacklistContractFail('black model missing usage/expiry setters');
}
if (substr_count($model, "return $value === '' ? 0") < 2) {
    blacklistContractFail('empty usage/expiry values must persist as zero for NOT NULL columns');
}

$js = file_get_contents($root . '/public/assets/js/backend/black.js');
foreach (array("field: 'usetime'", "field: 'endtime'", '未使用', '永久') as $needle) {
    if (strpos($js, $needle) === false) {
        blacklistContractFail('black list UI missing ' . $needle);
    }
}

$addView = file_get_contents($root . '/application/admin/view/black/add.html');
if (strpos($addView, 'row[endtime]') === false || strpos($addView, '留空表示永久') === false) {
    blacklistContractFail('black add form missing optional expiration field');
}

$editView = file_get_contents($root . '/application/admin/view/black/edit.html');
if (strpos($editView, 'row[usetime]') === false || strpos($editView, 'row[endtime]') === false) {
    blacklistContractFail('black edit form missing usage/expiration fields');
}

echo "OK blacklist_persistence_contract_test\n";
