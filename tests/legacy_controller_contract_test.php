<?php

function legacyContractFail($message)
{
    fwrite(STDERR, "FAIL legacy_controller_contract_test: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$route = file_get_contents($root . '/application/route.php');
$audit = file_get_contents($root . '/tools/legacy_controller_access_audit.sh');

if (!is_file($root . '/application/index/controller/App-mb.php') ||
    !is_file($root . '/application/index/controller/Index2.php')) {
    legacyContractFail('legacy files were deleted before production access-log verification');
}

foreach (array('App-mb', 'Index2') as $name) {
    if (stripos($route, $name) !== false) {
        legacyContractFail('route.php references legacy controller: ' . $name);
    }
    if (strpos($audit, $name) === false) {
        legacyContractFail('audit script does not cover: ' . $name);
    }
}

foreach (array('--delete', '不会删除任何文件', 'exit 2', 'exit 3') as $needle) {
    if (strpos($audit, $needle) === false) {
        legacyContractFail('audit guard missing: ' . $needle);
    }
}

echo "OK legacy_controller_contract_test\n";
