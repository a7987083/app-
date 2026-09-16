<?php

require_once dirname(__DIR__) . '/application/common/library/update/FastStorageManager.php';

use app\common\library\update\FastStorageManager;

function p153r_assert($ok, $msg)
{
    if (!$ok) {
        fwrite(STDERR, "FAIL phase15_3_fast_storage_runtime_test: {$msg}\n");
        exit(1);
    }
}

$base = sys_get_temp_dir() . '/zonoe-storage-' . getmypid() . '-' . mt_rand(1000, 9999);
@mkdir($base . '/application', 0777, true);
@mkdir($base . '/runtime/cache', 0777, true);
@mkdir($base . '/runtime/update', 0777, true);
@mkdir($base . '/public/uploads', 0777, true);
@mkdir($base . '/tools', 0777, true);
file_put_contents($base . '/ver.json', json_encode(['version' => '2026091607']));
file_put_contents($base . '/application/core.php', str_repeat('A', 64));
file_put_contents($base . '/public/uploads/user.bin', str_repeat('U', 32));
file_put_contents($base . '/backup.zip', str_repeat('B', 48));
file_put_contents($base . '/runtime/cache/old.tmp', str_repeat('C', 24));
@touch($base . '/runtime/cache/old.tmp', time() - 7200);

$m = new FastStorageManager($base);
$ok = $m->runWorker('scan');
p153r_assert($ok === true, 'first system scan failed');
$s = $m->snapshot();
p153r_assert(!empty($s['index_ready']), 'index not ready after scan');
p153r_assert(isset($s['buckets']['total']['count']) && $s['buckets']['total']['count'] >= 4, 'whole-site files not counted');
p153r_assert($s['buckets']['persistent']['count'] === 1, 'upload file not protected as persistent');
p153r_assert($s['buckets']['backup']['count'] === 1, 'backup not classified');
p153r_assert($s['buckets']['regenerable']['count'] >= 1, 'cache not classified as regenerable');
$st = $m->statuses();
p153r_assert($st['scan']['status'] === 'success' && (int)$st['scan']['progress'] === 100, 'scan progress did not finish at 100');

$ok2 = $m->runWorker('scan');
p153r_assert($ok2 === true, 'second system scan failed');
$s2 = $m->snapshot();
p153r_assert(in_array('application', isset($s2['meta']['skipped_source_dirs']) ? $s2['meta']['skipped_source_dirs'] : [], true), 'unchanged source directory fingerprint was not reused');

$preview = $m->previewSafe();
p153r_assert($preview['count'] >= 1, 'safe cleanup index missing old cache candidate');

function p153r_rm($dir)
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}
p153r_rm($base);

fwrite(STDOUT, "OK phase15_3_fast_storage_runtime_test\n");
