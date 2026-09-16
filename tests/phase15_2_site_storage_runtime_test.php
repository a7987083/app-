<?php

require_once dirname(__DIR__) . '/application/common/library/update/SiteStorageManager.php';

use app\common\library\update\SiteStorageManager;

function p152rAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase15_2_site_storage_runtime_test: {$message}\n");
        exit(1);
    }
}

$root = sys_get_temp_dir() . '/zonoe-p152-' . uniqid('', true);
@mkdir($root . '/application', 0777, true);
@mkdir($root . '/public/uploads', 0777, true);
@mkdir($root . '/runtime/cache', 0777, true);
@mkdir($root . '/runtime/log', 0777, true);
@mkdir($root . '/runtime/update_backup', 0777, true);
file_put_contents($root . '/application/core.php', '<?php');
file_put_contents($root . '/public/uploads/user.ipa', 'user');
file_put_contents($root . '/runtime/cache/a.tmpcache', str_repeat('c', 12));
file_put_contents($root . '/runtime/log/app.log', str_repeat('l', 8));
file_put_contents($root . '/runtime/update_backup/old.zip', str_repeat('b', 20));
file_put_contents($root . '/manual.sql', str_repeat('s', 24));
file_put_contents($root . '/mystery.bin', str_repeat('u', 16));
@touch($root . '/runtime/cache/a.tmpcache', time() - 7200);

$manager = new SiteStorageManager($root);
$snapshot = $manager->snapshot();
p152rAssert($snapshot['buckets']['total']['count'] === 7, 'all files must be counted');
p152rAssert($snapshot['buckets']['protected']['count'] === 1, 'application file should be protected');
p152rAssert($snapshot['buckets']['persistent']['count'] === 1, 'upload should be persistent');
p152rAssert($snapshot['buckets']['regenerable']['count'] === 1, 'cache should be regenerable');
p152rAssert($snapshot['buckets']['log']['count'] === 1, 'log should be classified');
p152rAssert($snapshot['buckets']['backup']['count'] === 2, 'backup/sql should be classified');
p152rAssert($snapshot['buckets']['unknown']['count'] === 1, 'unknown file should be visible');

$preview = $manager->cleanupSafe(true);
p152rAssert(count($preview['candidates']) === 1, 'only old cache should be auto-safe candidate');
p152rAssert(is_file($root . '/runtime/cache/a.tmpcache'), 'dry run must not delete');
$apply = $manager->cleanupSafe(false);
p152rAssert($apply['deleted']['count'] === 1, 'safe cleanup should delete cache');
p152rAssert(!is_file($root . '/runtime/cache/a.tmpcache'), 'cache should be deleted');
p152rAssert(is_file($root . '/public/uploads/user.ipa'), 'user upload must survive');
p152rAssert(is_file($root . '/application/core.php'), 'core file must survive');

$selected = $manager->deleteSelected(['manual.sql', 'application/core.php'], false);
p152rAssert($selected['deleted']['count'] === 1, 'only reviewable file may be manually deleted');
p152rAssert(!is_file($root . '/manual.sql'), 'manual backup should be deleted');
p152rAssert(is_file($root . '/application/core.php'), 'protected file must reject forged deletion');

function p152Remove($path)
{
    if (!is_dir($path)) { @unlink($path); return; }
    $items = scandir($path);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        p152Remove($path . '/' . $item);
    }
    @rmdir($path);
}
p152Remove($root);

echo "OK phase15_2_site_storage_runtime_test\n";
