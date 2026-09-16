<?php

namespace think {
    class Db
    {
        public static $mode = '';

        public static function query($sql, $bind = [])
        {
            if (self::$mode === 'backup_fail' && stripos($sql, 'SHOW TABLES') !== false) {
                throw new \RuntimeException('synthetic backup failure');
            }
            return [];
        }

        public static function execute($sql, $bind = [])
        {
            if (self::$mode === 'sql_fail' && strpos($sql, 'FAIL_ME') !== false) {
                throw new \RuntimeException('synthetic SQL failure');
            }
            return 0;
        }
    }
}

namespace app\common\library {
    class SiteConfigSync
    {
        public static function mergeMissingFromDb() { return true; }
    }

    class UpdateIntegrity
    {
        public static function signFromRoot($root) { return 'phase142-sign'; }
    }
}

namespace {
    $repo = dirname(__DIR__);
    require_once $repo . '/application/common/library/update/UpdateHttpClient.php';
    require_once $repo . '/application/common/library/update/UpdateSqlRunner.php';
    require_once $repo . '/application/common/library/update/UpdateBackup.php';
    require_once $repo . '/application/common/library/update/UpdateRuntimeStore.php';
    require_once $repo . '/application/common/library/update/UpdateInstaller.php';
    require_once $repo . '/application/common/library/update/UpdateManager.php';

    use app\common\library\update\UpdateHttpClient;
    use app\common\library\update\UpdateInstaller;
    use app\common\library\update\UpdateManager;
    use think\Db;

    function p142_assert($condition, $message)
    {
        if (!$condition) {
            fwrite(STDERR, "FAIL phase14_2_failure_injection_test: {$message}\n");
            exit(1);
        }
    }

    function p142_rm($path)
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

    function p142_site($label)
    {
        $site = sys_get_temp_dir() . '/zonoe_phase142_' . $label . '_' . uniqid('', true);
        mkdir($site . '/runtime', 0755, true);
        mkdir($site . '/public/update', 0755, true);
        file_put_contents($site . '/ver.json', json_encode(['version' => '2026091602', 'file_sign' => '']));
        file_put_contents($site . '/public/update/ver.txt', '2026091602');
        return $site;
    }

    function p142_zip(array $entries)
    {
        $file = tempnam(sys_get_temp_dir(), 'p142_zip_');
        @unlink($file);
        $file .= '.zip';
        $zip = new \ZipArchive();
        p142_assert($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true, 'fixture ZIP create failed');
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $bytes = file_get_contents($file);
        @unlink($file);
        return $bytes;
    }

    class Phase142FixtureHttp extends UpdateHttpClient
    {
        public $payload;
        public function __construct($payload) { $this->payload = $payload; }
        public function download($url, $destination, $maxBytes = 104857600)
        {
            $dir = dirname($destination);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            return file_put_contents($destination, $this->payload) !== false;
        }
    }

    class Phase142LowDiskInstaller extends UpdateInstaller
    {
        protected function availableDiskBytes($path) { return 1; }
    }

    class Phase142CopyFailInstaller extends UpdateInstaller
    {
        protected function copyProgram($source, $target)
        {
            throw new \RuntimeException('文件覆盖失败: synthetic-unwritable-target');
        }
    }

    $validZip = p142_zip(['program/demo.txt' => 'new']);

    // 1. SHA256 mismatch must fail before extraction/backup.
    $site = p142_site('sha');
    $installer = new UpdateInstaller($site, new Phase142FixtureHttp($validZip));
    $failed = false;
    try {
        $installer->install([
            'version' => '2026091603',
            'download' => 'https://example.invalid/update.zip',
            'sha256' => str_repeat('0', 64),
        ], true);
    } catch (\RuntimeException $e) {
        $failed = strpos($e->getMessage(), 'SHA256 校验失败') !== false;
    }
    p142_assert($failed, 'SHA mismatch must be rejected');
    p142_assert(trim(file_get_contents($site . '/public/update/ver.txt')) === '2026091602', 'SHA mismatch must not change version');
    p142_rm($site);

    // 2. Corrupt ZIP must fail safe extraction.
    $site = p142_site('corrupt');
    $installer = new UpdateInstaller($site, new Phase142FixtureHttp('not-a-zip'));
    $failed = false;
    try {
        $installer->install(['version' => '2026091603', 'download' => 'https://example.invalid/update.zip'], false);
    } catch (\RuntimeException $e) {
        $failed = strpos($e->getMessage(), '解压或路径安全校验失败') !== false;
    }
    p142_assert($failed, 'corrupt ZIP must be rejected');
    p142_rm($site);

    // 3. Low disk must fail before extraction/backup.
    $site = p142_site('disk');
    $installer = new Phase142LowDiskInstaller($site, new Phase142FixtureHttp($validZip));
    $failed = false;
    try {
        $installer->install(['version' => '2026091603', 'download' => 'https://example.invalid/update.zip'], false);
    } catch (\RuntimeException $e) {
        $failed = strpos($e->getMessage(), '磁盘可用空间不足') !== false;
    }
    p142_assert($failed, 'low-disk preflight must reject update');
    p142_rm($site);

    // 4. SQL failure after backup must trigger rollback and preserve old files/version.
    $site = p142_site('sql');
    file_put_contents($site . '/demo.txt', 'old');
    $zip = p142_zip([
        'program/demo.txt' => 'new',
        'mysql/001.sql' => 'FAIL_ME;',
    ]);
    Db::$mode = 'sql_fail';
    $installer = new UpdateInstaller($site, new Phase142FixtureHttp($zip));
    $failed = false;
    try {
        $installer->install(['version' => '2026091603', 'download' => 'https://example.invalid/update.zip'], false);
    } catch (\RuntimeException $e) {
        $failed = strpos($e->getMessage(), 'synthetic SQL failure') !== false;
    }
    Db::$mode = '';
    p142_assert($failed, 'SQL migration failure must surface');
    p142_assert(file_get_contents($site . '/demo.txt') === 'old', 'SQL failure rollback must preserve original file');
    p142_assert(trim(file_get_contents($site . '/public/update/ver.txt')) === '2026091602', 'SQL failure must preserve version');
    p142_rm($site);

    // 5. Unwritable-target equivalent: injected copy failure after backup must rollback.
    $site = p142_site('copy');
    file_put_contents($site . '/demo.txt', 'old');
    $installer = new Phase142CopyFailInstaller($site, new Phase142FixtureHttp($validZip));
    $failed = false;
    try {
        $installer->install(['version' => '2026091603', 'download' => 'https://example.invalid/update.zip'], false);
    } catch (\RuntimeException $e) {
        $failed = strpos($e->getMessage(), 'synthetic-unwritable-target') !== false;
    }
    p142_assert($failed, 'target write failure must surface');
    p142_assert(file_get_contents($site . '/demo.txt') === 'old', 'target write failure rollback must preserve original file');
    p142_rm($site);

    // 6. Backup failure must abort before program/database mutation.
    $site = p142_site('backup');
    file_put_contents($site . '/demo.txt', 'old');
    Db::$mode = 'backup_fail';
    $installer = new UpdateInstaller($site, new Phase142FixtureHttp($validZip));
    $failed = false;
    try {
        $installer->install(['version' => '2026091603', 'download' => 'https://example.invalid/update.zip'], false);
    } catch (\RuntimeException $e) {
        $failed = strpos($e->getMessage(), 'synthetic backup failure') !== false;
    }
    Db::$mode = '';
    p142_assert($failed, 'backup failure must abort update');
    p142_assert(file_get_contents($site . '/demo.txt') === 'old', 'backup failure must not modify program file');
    p142_assert(trim(file_get_contents($site . '/public/update/ver.txt')) === '2026091602', 'backup failure must preserve version');
    p142_rm($site);

    // 7. Real flock conflict must return HTTP-style 409 without entering source/install path.
    $site = p142_site('lock');
    $lockDir = $site . '/runtime/update';
    mkdir($lockDir, 0755, true);
    $held = fopen($lockDir . '/update.lock', 'c+');
    p142_assert($held !== false && flock($held, LOCK_EX | LOCK_NB), 'test must acquire real update lock');
    $manager = new UpdateManager($site);
    $ret = $manager->install('nuosike', false, 'phase142_lock_case');
    p142_assert(isset($ret['code']) && (int)$ret['code'] === 409, 'lock conflict must return 409');
    flock($held, LOCK_UN);
    fclose($held);
    p142_rm($site);

    fwrite(STDOUT, "OK phase14_2_failure_injection_test sha=passed corrupt_zip=passed sql=passed target=passed backup=passed lock=passed low_disk=passed\n");
}
