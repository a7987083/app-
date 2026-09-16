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

p153_assert(strpos($controller, "status_only") !== false, 'fast status-only polling path missing');
p153_assert(strpos($controller, 'public function scan()') !== false, 'scan start endpoint missing');
p153_assert(strpos($controller, 'FastStorageManager') !== false, 'controller still uses slow scanner');
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
