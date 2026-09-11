<?php

namespace think {
    class Db
    {
        public static function query($sql, $bind = []) { return []; }
        public static function execute($sql, $bind = []) { return 0; }
    }
}

namespace app\common\library {
    class SiteConfigSync
    {
        public static function mergeMissingFromDb() { return true; }
    }
}

namespace {
    $rootRepo = dirname(__DIR__);
    require_once $rootRepo . '/application/common/library/UpdateIntegrity.php';
    require_once $rootRepo . '/application/common/library/update/UpdateSourceInterface.php';
    require_once $rootRepo . '/application/common/library/update/UpdateHttpClient.php';
    require_once $rootRepo . '/application/common/library/update/GitHubUpdateSource.php';
    require_once $rootRepo . '/application/common/library/update/NuosikeUpdateSource.php';
    require_once $rootRepo . '/application/common/library/update/UpdateSqlRunner.php';
    require_once $rootRepo . '/application/common/library/update/UpdateBackup.php';
    require_once $rootRepo . '/application/common/library/update/UpdateRuntimeStore.php';
    require_once $rootRepo . '/application/common/library/update/UpdateInstaller.php';
    require_once $rootRepo . '/application/common/library/update/UpdateManager.php';

    use app\common\library\update\UpdateManager;
    use app\common\library\update\UpdateRuntimeStore;

    function p13runtime_assert($condition, $message)
    {
        if (!$condition) {
            fwrite(STDERR, "FAIL phase13_update_runtime_test: {$message}\n");
            exit(1);
        }
    }

    function p13runtime_rm($path)
    {
        if (!is_dir($path)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }

    $site = sys_get_temp_dir() . '/zonoe_phase13_runtime_' . uniqid('', true);
    mkdir($site . '/public/update', 0755, true);
    mkdir($site . '/runtime/update_backup/backup_case/files/public/update', 0755, true);
    mkdir($site . '/runtime/update_backup/backup_case/files', 0755, true);

    file_put_contents($site . '/demo.txt', 'new');
    file_put_contents($site . '/ver.json', json_encode(['version' => '2026091203', 'file_sign' => '']));
    file_put_contents($site . '/public/update/ver.txt', '2026091203');

    $backup = $site . '/runtime/update_backup/backup_case/';
    file_put_contents($backup . 'files/demo.txt', 'old');
    file_put_contents($backup . 'files/ver.json', json_encode(['version' => '2026091202', 'file_sign' => '']));
    file_put_contents($backup . 'files/public/update/ver.txt', '2026091202');
    file_put_contents($backup . 'created.json', '[]');
    file_put_contents($backup . 'database.sql', "SET FOREIGN_KEY_CHECKS=0;\nSET FOREIGN_KEY_CHECKS=1;\n");

    $store = new UpdateRuntimeStore($site);
    $historyId = $store->recordHistory([
        'type' => 'update',
        'status' => 'success',
        'source' => 'github',
        'from_version' => '2026091202',
        'to_version' => '2026091203',
        'backups' => ['backup_case'],
    ]);
    p13runtime_assert(is_string($historyId) && $historyId !== '', 'history record was not created');

    $manager = new UpdateManager($site);
    $result = $manager->rollback($historyId, 'rollback_runtime_case');
    p13runtime_assert(isset($result['code']) && (int)$result['code'] === 200, 'rollback failed: ' . json_encode($result));
    p13runtime_assert(file_get_contents($site . '/demo.txt') === 'old', 'program file was not restored');
    p13runtime_assert(trim(file_get_contents($site . '/public/update/ver.txt')) === '2026091202', 'ver.txt was not restored');
    $manifest = json_decode(file_get_contents($site . '/ver.json'), true);
    p13runtime_assert(isset($manifest['version']) && $manifest['version'] === '2026091202', 'ver.json was not restored');

    $status = $manager->status('rollback_runtime_case');
    p13runtime_assert(isset($status['data']['status']) && $status['data']['status'] === 'success', 'rollback progress did not finish successfully');
    p13runtime_assert(isset($status['data']['progress']) && (int)$status['data']['progress'] === 100, 'rollback progress did not reach 100');

    $history = $manager->history(10);
    p13runtime_assert(isset($history['data']['list'][0]['type']) && $history['data']['list'][0]['type'] === 'rollback', 'rollback history was not recorded');

    p13runtime_rm($site);
    fwrite(STDOUT, "OK phase13_update_runtime_test rollback=passed status=passed history=passed\n");
}
