<?php

require_once dirname(__DIR__) . '/application/common/library/update/UpdateOps.php';

use app\common\library\update\UpdateOps;

function p143_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase14_update_ops_test: {$message}\n");
        exit(1);
    }
}

function p143_rm($path)
{
    if (!is_dir($path)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    @rmdir($path);
}

$site = sys_get_temp_dir() . '/zonoe_phase143_' . uniqid('', true);
$statusDir = $site . '/runtime/update/status';
$historyDir = $site . '/runtime/update/history';
$backupDir = $site . '/runtime/update_backup';
mkdir($statusDir, 0755, true); mkdir($historyDir, 0755, true);
mkdir($backupDir . '/referenced/files', 0755, true); mkdir($backupDir . '/orphan/files', 0755, true);

$old = time() - (120 * 86400);
$stale = date('Y-m-d H:i:s', time() - 7200);
file_put_contents($statusDir . '/running_stale.json', json_encode(['job_id'=>'running_stale','status'=>'running','updated_at'=>$stale]));
file_put_contents($statusDir . '/old_done.json', json_encode(['job_id'=>'old_done_01','status'=>'success','updated_at'=>date('Y-m-d H:i:s',$old)]));
@touch($statusDir . '/old_done.json', $old);
file_put_contents($historyDir . '/20200101_000000_z000001_success.json', json_encode(['id'=>'history_success_01','type'=>'update','status'=>'success','backups'=>['referenced'],'created_at'=>date('Y-m-d H:i:s',$old)]));
@touch($historyDir . '/20200101_000000_z000001_success.json', $old);
file_put_contents($historyDir . '/20200101_000000_z000002_failed.json', json_encode(['id'=>'history_failed_02','type'=>'update','status'=>'failed','created_at'=>date('Y-m-d H:i:s',$old)]));
@touch($historyDir . '/20200101_000000_z000002_failed.json', $old);
file_put_contents($backupDir . '/referenced/files/remove-too.txt', str_repeat('a',32));
file_put_contents($backupDir . '/orphan/files/remove.txt', str_repeat('b',64));
@touch($backupDir . '/referenced', $old); @touch($backupDir . '/orphan', $old);

$ops = new UpdateOps($site);
$snapshot = $ops->snapshot();
p143_assert($snapshot['jobs']['stale_count'] === 1, 'stale running job must be detected');
p143_assert($snapshot['storage']['backups']['count'] === 2, 'backup directory count must be reported');
p143_assert($snapshot['history']['latest_update']['id'] === 'history_failed_02', 'latest update history must be exposed');

$preview = $ops->cleanup(true);
p143_assert($preview['dry_run'] === true, 'cleanup preview must be dry-run');
p143_assert(in_array('old_done.json',$preview['candidates']['status'],true), 'old terminal status must be cleanup candidate');
p143_assert(in_array('20200101_000000_z000002_failed.json',$preview['candidates']['history'],true), 'old failed history must be cleanup candidate');
p143_assert(in_array('20200101_000000_z000001_success.json',$preview['candidates']['history'],true), 'old successful update history must no longer be protected');
p143_assert(in_array('orphan',$preview['candidates']['backups'],true), 'old orphan backup must be cleanup candidate');
p143_assert(in_array('referenced',$preview['candidates']['backups'],true), 'history-referenced backup must no longer be protected');
p143_assert(is_file($statusDir . '/old_done.json'), 'dry-run must not delete files');

$applied = $ops->cleanup(false);
p143_assert($applied['dry_run'] === false, 'cleanup apply must not be dry-run');
p143_assert(!is_file($statusDir . '/old_done.json'), 'old terminal status must be deleted');
p143_assert(is_file($statusDir . '/running_stale.json'), 'running status must never be deleted');
p143_assert(!is_file($historyDir . '/20200101_000000_z000002_failed.json'), 'old failed history must be deleted');
p143_assert(!is_file($historyDir . '/20200101_000000_z000001_success.json'), 'old successful history must be deleted by retention');
p143_assert(!is_dir($backupDir . '/orphan'), 'orphan old backup must be deleted');
p143_assert(!is_dir($backupDir . '/referenced'), 'referenced old backup must be deleted after protection removal');
p143_assert($applied['deleted']['bytes'] >= 96, 'cleanup must report freed bytes for both backups');

p143_rm($site);
fwrite(STDOUT, "OK phase14_update_ops_test diagnostics=passed retention=passed rollback_protection=removed\n");
