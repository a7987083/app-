<?php

function statsContractFail($message)
{
    fwrite(STDERR, "FAIL category_statistics_contract_test: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$admin = file_get_contents($root . '/application/admin/controller/Category.php');
$index = file_get_contents($root . '/application/index/controller/Index.php');
$helper = file_get_contents($root . '/application/common/library/CategoryDailyStat.php');

$indexStart = strpos($admin, 'public function index()');
$parentStart = strpos($admin, 'protected function buildParentList()');
$adminIndex = substr($admin, $indexStart, $parentStart - $indexStart);

foreach (array("where('cstime'", '"cstime"', "'cstime' =>", "date('d'") as $legacy) {
    if (strpos($adminIndex, $legacy) !== false) {
        statsContractFail('admin list still mutates daily statistics: ' . $legacy);
    }
}

foreach (array(
    'use app\\common\\library\\CategoryDailyStat;',
    "CategoryDailyStat::record((int)\$_POST['uid'])",
) as $needle) {
    if (strpos($index, $needle) === false) {
        statsContractFail('Index.php missing: ' . $needle);
    }
}

foreach (array(
    "date('Ymd'",
    "->field('id,cs,cstime')",
    '->lock(true)',
    "'cs' => 1",
) as $needle) {
    if (strpos($helper, $needle) === false) {
        statsContractFail('CategoryDailyStat missing: ' . $needle);
    }
}

if (strpos($index, "date('d'") !== false) {
    statsContractFail('day-of-month counter key remains in runtime controller');
}

foreach (array('App-mb.php', 'Index2.php') as $file) {
    if (is_file($root . '/application/index/controller/' . $file)) {
        statsContractFail('retired controller still exists: ' . $file);
    }
}

echo "OK category_statistics_contract_test\n";
