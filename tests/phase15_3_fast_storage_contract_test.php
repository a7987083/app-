<?php

function p153_assert($ok, $msg)
{
    if (!$ok) {
        fwrite(STDERR, "FAIL phase15_3_fast_storage_contract_test: {$msg}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$manager = file_get_contents($root . '/application/common/library/update/FastStorageManager.php');
$compat = file_get_contents($root . '/application/common/library/update/FastStorageManagerCompat.php');
$controller = file_get_contents($root . '/application/admin/controller/general/Updatemaintenance.php');
$view = file_get_contents($root . '/application/admin/view/general/updatemaintenance/panel.html');
$js = file_get_contents($root . '/public/assets/js/backend/general/updatemaintenance.js');
$worker = file_get_contents($root . '/tools/storage_worker.php');

p153_assert(strpos($manager, "find {$root}") === false, 'manager must not hard-code CI root');
p153_assert(strpos($manager, "-xdev") !== false, 'system scan must stay on the site filesystem');
p153_assert(strpos($manager, "-printf '%P\\\\t%s") !== false || strpos($manager, "-printf '%P\\t%s") !== false, 'GNU find metadata scan missing');
p153_assert(strpos($manager, 'quickFingerprint') !== false, 'source directory fingerprint shortcut missing');
p153_assert(strpos($manager, "sha256sum") !== false, 'directory fingerprint hashing missing');
p153_assert(strpos($manager, "runtime' . DIRECTORY_SEPARATOR . 'storage") !== false, 'persistent storage index directory missing');
p153_assert(strpos($manager, 'startScan') !== false && strpos($manager, 'startCleanup') !== false, 'async job starters missing');
p153_assert(strpos($manager, "nohup") !== false, 'background worker spawn missing');
p153_assert(strpos($manager, "progress") !== false && strpos($manager, "processed") !== false && strpos($manager, "total") !== false, 'progress fields missing');

p153_assert(strpos($compat, 'class FastStorageManagerCompat extends FastStorageManager') !== false, 'open_basedir compatibility wrapper missing');
p153_assert(strpos($compat, 'PHP_BINDIR') !== false, 'compat wrapper must probe the Baota PHP CLI candidate');
p153_assert(strpos($compat, 'is_file($php)') === false && strpos($compat, 'is_executable($php)') === false, 'compat wrapper must not filesystem-probe PHP_BINDIR outside open_basedir');
p153_assert(strpos($compat, " -r ") !== false, 'compat wrapper must validate PHP CLI by execution instead of filesystem stat');
p153_assert(strpos($controller, 'FastStorageManagerCompat') !== false, 'controller must use open_basedir-safe manager');
p153_assert(strpos($controller, "status_only") !== false, 'fast status-only polling path missing');
p153_assert(strpos($controller, 'public function scan()') !== false, 'scan start endpoint missing');
p153_assert(strpos($controller, 'snapshot()') !== false, 'cached snapshot endpoint missing');

foreach (['scan-progress-box','scan-progress-bar','scan-progress-text','cleanup-progress-box','cleanup-progress-bar','cleanup-progress-text'] as $id) {
    p153_assert(strpos($view, 'id="' . $id . '"') !== false, 'progress UI missing: ' . $id);
}
p153_assert(strpos($js, 'setInterval(pollStatus, 1000)') !== false, '1-second progress polling missing');
p153_assert(strpos($js, "index?status_only=1") !== false, 'status-only polling route not wired');
p153_assert(strpos($js, "general/updatemaintenance/scan") !== false, 'scan route not wired');
p153_assert(strpos($js, "apply:1") !== false, 'async cleanup start not wired');
p153_assert(strpos($worker, "PHP_SAPI !== 'cli'") !== false, 'worker must be CLI-only');

fwrite(STDOUT, "OK phase15_3_fast_storage_contract_test\n");
