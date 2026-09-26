<?php

function p154_assert($ok, $msg)
{
    if (!$ok) {
        fwrite(STDERR, "FAIL phase15_4_ops_slim_contract_test: {$msg}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/application/admin/controller/general/Updatemaintenance.php');
$view = file_get_contents($root . '/application/admin/view/general/updatemaintenance/panel.html');
$js = file_get_contents($root . '/public/assets/js/backend/general/updatemaintenance.js');

p154_assert(strpos($controller, 'FastStorageManager') === false, 'whole-site storage manager must be removed from controller');
p154_assert(strpos($controller, 'public function scan()') === false, 'whole-site scan endpoint must be removed');
p154_assert(strpos($controller, 'cleanupSelected') === false, 'whole-site arbitrary file delete endpoint must be removed');
p154_assert(strpos($controller, 'new UpdateOps(ROOT_PATH)') !== false, 'update operations snapshot missing');
p154_assert(strpos($controller, 'cleanup($apply ? false : true)') !== false, 'update-only safe cleanup missing');

foreach (['重新扫描全站', '全站储存分类', '最大文件 TOP 50', '备份 / 未识别文件', 'scan-progress-bar', 'site-review-list'] as $needle) {
    p154_assert(strpos($view, $needle) === false, 'removed whole-site UI survived: ' . $needle);
}
p154_assert(strpos($view, '更新文件储存') !== false, 'update-only storage panel missing');
p154_assert(strpos($view, '更新历史') !== false, 'update history panel missing');
p154_assert(strpos($view, '安全清理更新数据') !== false, 'update-only cleanup panel missing');

p154_assert(strpos($js, 'updatemaintenance/scan') === false, 'whole-site scan client survived');
p154_assert(strpos($js, 'cleanupSelected') === false, 'whole-site delete client survived');
p154_assert(strpos($js, 'status_only=1') === false, 'storage polling client survived');
p154_assert(strpos($js, 'cleanup(false)') !== false || strpos($js, 'retentionCleanup(false)') !== false, 'update cleanup preview missing');
p154_assert(strpos($js, 'cleanup(true)') !== false || strpos($js, 'retentionCleanup(true)') !== false, 'update cleanup apply missing');
p154_assert(strpos($js, 'apply:apply ? 1 : 0') !== false || strpos($js, 'apply: apply ? 1 : 0') !== false, 'cleanup apply flag mapping missing');

fwrite(STDOUT, "OK phase15_4_ops_slim_contract_test\n");
