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
        public static function mergeMissingFromDb()
        {
            throw new \RuntimeException('forced config failure');
        }
    }
}

namespace {
    $rootRepo = dirname(__DIR__);
    require_once $rootRepo . '/application/common/library/UpdateIntegrity.php';
    require_once $rootRepo . '/application/common/library/update/UpdateHttpClient.php';
    require_once $rootRepo . '/application/common/library/update/UpdateSqlRunner.php';
    require_once $rootRepo . '/application/common/library/update/UpdateBackup.php';
    require_once $rootRepo . '/application/common/library/update/UpdateInstaller.php';

    use app\common\library\update\UpdateHttpClient;
    use app\common\library\update\UpdateInstaller;

    class Phase20LocalPackageHttp extends UpdateHttpClient
    {
        private $source;
        public function __construct($source) { $this->source = $source; }
        public function download($url, $destination, $maxBytes = 104857600)
        {
            $dir = dirname($destination);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            return copy($this->source, $destination);
        }
    }

    function phase20RollbackAssert($condition, $message)
    {
        if (!$condition) {
            fwrite(STDERR, "FAIL phase20_update_rollback_e2e: {$message}\n");
            exit(1);
        }
    }

    function phase20RollbackRm($path)
    {
        if (!is_dir($path)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        @rmdir($path);
    }

    $tmp = sys_get_temp_dir() . '/phase20_rollback_' . uniqid('', true);
    $site = $tmp . '/site';
    mkdir($site . '/public/update', 0755, true);
    file_put_contents($site . '/demo.txt', 'old-content');
    file_put_contents($site . '/public/update/ver.txt', '2026091809');
    file_put_contents($site . '/ver.json', json_encode(['version'=>'2026091809','file_sign'=>'']));

    $zipPath = $tmp . '/update.zip';
    $zip = new \ZipArchive();
    phase20RollbackAssert($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true, 'create zip');
    $zip->addFromString('program/demo.txt', 'new-content');
    $zip->addFromString('program/new-file.txt', 'created-by-update');
    $zip->addFromString('mysql/001_noop.sql', 'SET @phase20_noop := 1;');
    $zip->close();

    $events = [];
    $installer = new UpdateInstaller($site, new Phase20LocalPackageHttp($zipPath), function ($stage, $progress, $message, $extra) use (&$events) {
        $events[] = ['stage'=>$stage,'progress'=>$progress,'extra'=>$extra];
    });

    $failed = false;
    try {
        $installer->install([
            'version'=>'2026091999',
            'download'=>'https://example.invalid/phase20.zip',
            'sha256'=>hash_file('sha256', $zipPath),
        ], true);
    } catch (\RuntimeException $e) {
        $failed = strpos($e->getMessage(), 'forced config failure') !== false;
    }

    phase20RollbackAssert($failed, 'forced post-copy failure observed');
    phase20RollbackAssert(file_get_contents($site . '/demo.txt') === 'old-content', 'overwritten file restored');
    phase20RollbackAssert(!is_file($site . '/new-file.txt'), 'new file removed by rollback');
    phase20RollbackAssert(trim(file_get_contents($site . '/public/update/ver.txt')) === '2026091809', 'version remained old');
    $backup = $installer->lastBackup();
    phase20RollbackAssert($backup !== '' && is_dir($backup), 'backup directory retained');
    phase20RollbackAssert(is_file(rtrim($backup, '/\\') . '/files/demo.txt'), 'old file exists in backup');
    phase20RollbackAssert(is_file(rtrim($backup, '/\\') . '/database.sql'), 'database backup exists');

    $sawRollback = false;
    $sawRollbackSuccess = false;
    foreach ($events as $event) {
        if ($event['stage'] === 'rollback') {
            $sawRollback = true;
            if (isset($event['extra']['rollback']) && $event['extra']['rollback'] === true) $sawRollbackSuccess = true;
        }
    }
    phase20RollbackAssert($sawRollback && $sawRollbackSuccess, 'rollback progress reported success');

    phase20RollbackRm($tmp);
    fwrite(STDOUT, "OK phase20_update_rollback_e2e backup=passed file_restore=passed created_file_cleanup=passed rollback_signal=passed\n");
}
