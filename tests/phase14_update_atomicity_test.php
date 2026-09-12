<?php

namespace think {
    class Db
    {
        public static function query($sql, $bind = []) { return []; }
        public static function execute($sql, $bind = []) { return 0; }
    }
}

namespace {
    $rootRepo = dirname(__DIR__);
    require_once $rootRepo . '/application/common/library/update/UpdateHttpClient.php';
    require_once $rootRepo . '/application/common/library/update/UpdateSqlRunner.php';
    require_once $rootRepo . '/application/common/library/update/UpdateBackup.php';
    require_once $rootRepo . '/application/common/library/update/UpdateRuntimeStore.php';
    require_once $rootRepo . '/application/common/library/update/UpdateManager.php';

    use app\common\library\update\UpdateBackup;
    use app\common\library\update\UpdateManager;

    function p14_assert($condition, $message)
    {
        if (!$condition) {
            fwrite(STDERR, "FAIL phase14_update_atomicity_test: {$message}\n");
            exit(1);
        }
    }

    function p14_rm($path)
    {
        if (!is_dir($path)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }

    class Phase14FakeSource
    {
        public function name() { return 'nuosike'; }
        public function requiresSha256() { return false; }
        public function packagesAfter($localVersion, $force = false)
        {
            return [
                ['version' => '2026091204', 'download' => 'https://example.invalid/one.zip'],
                ['version' => '2026091205', 'download' => 'https://example.invalid/two.zip'],
            ];
        }
    }

    class Phase14FakeInstaller
    {
        public $calls = 0;
        protected $lastBackup = '';

        public function install(array $package, $requiresSha256)
        {
            $this->calls++;
            if ($this->calls === 1) {
                $this->lastBackup = '/tmp/backup_one/';
                return [
                    'version' => $package['version'],
                    'backup' => '/tmp/backup_one/',
                    'sha256' => '',
                    'sha256_verified' => false,
                    'files_verified' => true,
                    'database_migrated' => false,
                ];
            }
            $this->lastBackup = '/tmp/backup_two/';
            throw new \RuntimeException('synthetic second package failure');
        }

        public function lastBackup()
        {
            return $this->lastBackup;
        }
    }

    class Phase14Manager extends UpdateManager
    {
        protected $fakeSource;
        protected $fakeInstaller;
        public $restored = [];

        public function __construct($root, $source, $installer)
        {
            $this->fakeSource = $source;
            $this->fakeInstaller = $installer;
            parent::__construct($root);
        }

        protected function source($name)
        {
            return $this->fakeSource;
        }

        protected function createInstaller($progress)
        {
            return $this->fakeInstaller;
        }

        protected function restoreBackup($backupId)
        {
            $this->restored[] = $backupId;
        }
    }

    $installerSource = file_get_contents($rootRepo . '/application/common/library/update/UpdateInstaller.php');
    p14_assert(strpos($installerSource, "'runtime' . DIRECTORY_SEPARATOR . 'update' . DIRECTORY_SEPARATOR . 'cache'") !== false, 'update cache must live under runtime');
    p14_assert(strpos($installerSource, "'public' . DIRECTORY_SEPARATOR . 'update' . DIRECTORY_SEPARATOR . 'cache'") === false, 'update cache must not live under public web root');
    p14_assert(strpos($installerSource, 'public function lastBackup()') !== false, 'installer must expose the current backup for manager-level rollback');

    $site = sys_get_temp_dir() . '/zonoe_phase14_atomic_' . uniqid('', true);
    mkdir($site . '/runtime', 0755, true);
    file_put_contents($site . '/ver.json', json_encode(['version' => '2026091203', 'file_sign' => '']));

    $fakeInstaller = new Phase14FakeInstaller();
    $manager = new Phase14Manager($site, new Phase14FakeSource(), $fakeInstaller);
    $result = $manager->install('nuosike', false, 'phase14_atomic_case');
    p14_assert(isset($result['code']) && (int)$result['code'] === 406, 'synthetic second package failure must fail the update');
    p14_assert(isset($result['data']['rollback']) && $result['data']['rollback'] === true, 'manager must report successful full-chain rollback');
    p14_assert($manager->restored === ['backup_two', 'backup_one'], 'manager must rollback current and previous packages in reverse order');

    $backupSite = sys_get_temp_dir() . '/zonoe_phase14_backup_' . uniqid('', true);
    mkdir($backupSite . '/runtime/update_backup/case/files', 0755, true);
    file_put_contents($backupSite . '/demo.txt', 'new');
    file_put_contents($backupSite . '/created.txt', 'created-by-update');
    file_put_contents($backupSite . '/runtime/update_backup/case/files/demo.txt', 'old');
    file_put_contents($backupSite . '/runtime/update_backup/case/created.json', json_encode(['created.txt']));
    file_put_contents($backupSite . '/runtime/update_backup/case/database.sql', "SET FOREIGN_KEY_CHECKS=0;\nSET FOREIGN_KEY_CHECKS=1;\n");

    (new UpdateBackup($backupSite, $backupSite . '/runtime/update_backup/case'))->rollback();
    p14_assert(file_get_contents($backupSite . '/demo.txt') === 'old', 'rollback must restore overwritten program files');
    p14_assert(!file_exists($backupSite . '/created.txt'), 'rollback must remove files created by the failed update');

    $brokenSite = sys_get_temp_dir() . '/zonoe_phase14_broken_' . uniqid('', true);
    mkdir($brokenSite . '/demo.txt', 0755, true);
    mkdir($brokenSite . '/runtime/update_backup/case/files', 0755, true);
    file_put_contents($brokenSite . '/runtime/update_backup/case/files/demo.txt', 'old');
    file_put_contents($brokenSite . '/runtime/update_backup/case/created.json', '[]');
    file_put_contents($brokenSite . '/runtime/update_backup/case/database.sql', "SET FOREIGN_KEY_CHECKS=0;\nSET FOREIGN_KEY_CHECKS=1;\n");

    $rollbackFailed = false;
    try {
        (new UpdateBackup($brokenSite, $brokenSite . '/runtime/update_backup/case'))->rollback();
    } catch (\RuntimeException $e) {
        $rollbackFailed = strpos($e->getMessage(), '文件恢复失败') !== false;
    }
    p14_assert($rollbackFailed, 'rollback I/O failure must be reported instead of silently succeeding');

    p14_rm($site);
    p14_rm($backupSite);
    p14_rm($brokenSite);
    fwrite(STDOUT, "OK phase14_update_atomicity_test chain_rollback=passed backup_io=passed private_cache=passed\n");
}
