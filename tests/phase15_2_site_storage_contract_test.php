<?php

function p152Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase15_2_site_storage_contract_test: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$manager = file_get_contents($root . '/application/common/library/update/SiteStorageManager.php');
$fastManager = file_get_contents($root . '/application/common/library/update/FastStorageManager.php');
$controller = file_get_contents($root . '/application/admin/controller/general/Updatemaintenance.php');
$view = file_get_contents($root . '/application/admin/view/general/updatemaintenance/panel.html');
$js = file_get_contents($root . '/public/assets/js/backend/general/updatemaintenance.js');

foreach (['protected', 'persistent', 'regenerable', 'log', 'backup', 'temporary', 'unknown'] as $bucket) {
    p152Assert(strpos($manager, "'{$bucket}'") !== false || strpos($fastManager, "'{$bucket}'") !== false, 'missing bucket ' . $bucket);
}
p152Assert(strpos($fastManager, "'public/uploads/'") !== false, 'uploads must be persistent/protected');
p152Assert(strpos($fastManager, "'runtime/update_backup/'") !== false, 'update backups must be classified');
p152Assert(strpos($fastManager, 'reviewable') !== false, 'review-only classification missing');
p152Assert(strpos($fastManager, 'auto_safe') !== false, 'auto-safe classification missing');
p152Assert(strpos($fastManager, 'is_link') !== false, 'symlink protection missing');
p152Assert(strpos($fastManager, "'..'") !== false, 'path traversal validation missing');
p152Assert(strpos($controller, 'FastStorageManager') !== false || strpos($controller, 'SiteStorageManager') !== false, 'controller does not expose site storage');
p152Assert(strpos($controller, 'cleanupSelected') !== false, 'manual review cleanup endpoint missing');
p152Assert(strpos($view, '网站总占用') !== false, 'whole-site total missing in UI');
p152Assert(strpos($view, '备份 / 未识别文件') !== false, 'review list missing in UI');
p152Assert(strpos($js, 'site-review-check') !== false, 'review checkbox UI missing');
p152Assert(strpos($js, 'cleanupSelected') !== false, 'manual delete client missing');

echo "OK phase15_2_site_storage_contract_test\n";
