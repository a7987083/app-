<?php

namespace think {
    class Db
    {
        public static function query($sql, $bind = [])
        {
            if (stripos((string)$sql, 'SHOW TABLES') === 0) {
                return [];
            }
            return [];
        }

        public static function execute($sql, $bind = [])
        {
            return 0;
        }
    }
}

namespace app\common\library {
    class SiteConfigSync
    {
        public static function mergeMissingFromDb()
        {
            return true;
        }
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

    use app\common\library\UpdateIntegrity;
    use app\common\library\update\UpdateManager;

    function p13fail($message)
    {
        fwrite(STDERR, "FAIL phase13_github_online_update_e2e: {$message}\n");
        exit(1);
    }

    function p13assert($condition, $message)
    {
        if (!$condition) {
            p13fail($message);
        }
    }

    function p13copy($source, $target)
    {
        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            p13fail('cannot create target directory: ' . $dir);
        }
        if (!copy($source, $target)) {
            p13fail('cannot copy: ' . $source);
        }
    }

    function p13rm($path)
    {
        if (!is_dir($path)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($path);
    }

    $targetVersion = trim((string)file_get_contents($rootRepo . '/public/update/ver.txt'));
    $oldVersion = getenv('ZONOE_E2E_OLD_VERSION');
    if ($oldVersion === false || $oldVersion === '') {
        $oldVersion = '0';
    }
    $site = sys_get_temp_dir() . '/zonoe_phase13_e2e_' . uniqid('', true);
    mkdir($site, 0755, true);

    foreach (UpdateIntegrity::files() as $relative) {
        p13copy($rootRepo . '/' . $relative, $site . '/' . $relative);
    }

    $oldSelfFiles = [
        'application/common/library/update/UpdateManager.php',
        'application/common/library/update/UpdateInstaller.php',
        'application/admin/controller/general/Config.php',
        'public/assets/js/backend/general/config.js',
    ];
    foreach ($oldSelfFiles as $relative) {
        p13copy($rootRepo . '/' . $relative, $site . '/' . $relative);
        file_put_contents($site . '/' . $relative, "\n// PHASE13_OLD_SELF_UPDATE_MARKER\n", FILE_APPEND);
    }

    mkdir($site . '/public/update', 0755, true);
    mkdir($site . '/runtime', 0755, true);

    $oldSign = UpdateIntegrity::signFromRoot($site);
    file_put_contents($site . '/ver.json', json_encode(['version' => $oldVersion, 'file_sign' => $oldSign]));
    file_put_contents($site . '/public/update/ver.txt', $oldVersion);

    $manager = new UpdateManager($site);
    $check = null;
    for ($i = 0; $i < 20; $i++) {
        $check = $manager->check('github');
        if (isset($check['data']['last_version']) && (string)$check['data']['last_version'] === $targetVersion) {
            break;
        }
        sleep(3);
    }

    p13assert(is_array($check), 'GitHub update check did not return an array');
    p13assert(isset($check['code']) && (int)$check['code'] === 200, 'GitHub update check did not report an update: ' . json_encode($check));
    p13assert(isset($check['data']['last_version']) && (string)$check['data']['last_version'] === $targetVersion, 'unexpected latest version');
    p13assert(!empty($check['data']['sha256']) && preg_match('/^[a-f0-9]{64}$/', $check['data']['sha256']), 'release SHA256 missing');
    p13assert(strpos((string)$check['data']['changelog'], '更新内容') !== false, 'GitHub release notes must be Chinese');

    $jobId = 'e2e_' . date('YmdHis') . '_phase13';
    $result = $manager->install('github', false, $jobId);
    p13assert(isset($result['code']) && (int)$result['code'] === 200, 'real GitHub install failed: ' . json_encode($result));
    p13assert(is_file($site . '/PHASE13_RELEASE.txt'), 'release marker was not installed');
    p13assert(trim((string)file_get_contents($site . '/public/update/ver.txt')) === $targetVersion, 'ver.txt was not advanced');

    $manifest = json_decode((string)file_get_contents($site . '/ver.json'), true);
    p13assert(is_array($manifest) && isset($manifest['version']) && (string)$manifest['version'] === $targetVersion, 'ver.json was not advanced');
    p13assert(isset($manifest['file_sign']) && $manifest['file_sign'] === UpdateIntegrity::signFromRoot($site), 'local file_sign does not match installed files');

    $selfUpdatedFiles = [
        'application/common/library/update/UpdateManager.php',
        'application/common/library/update/UpdateInstaller.php',
        'application/common/library/update/UpdateRuntimeStore.php',
        'application/admin/controller/general/Config.php',
        'public/assets/js/backend/general/config.js',
    ];
    foreach ($selfUpdatedFiles as $relative) {
        p13assert(is_file($site . '/' . $relative), 'self-update target missing: ' . $relative);
        p13assert(hash_file('sha256', $site . '/' . $relative) === hash_file('sha256', $rootRepo . '/' . $relative), 'self-update file mismatch: ' . $relative);
        p13assert(strpos((string)file_get_contents($site . '/' . $relative), 'PHASE13_OLD_SELF_UPDATE_MARKER') === false, 'old self-update marker survived: ' . $relative);
    }

    $status = $manager->status($jobId);
    p13assert(isset($status['code']) && (int)$status['code'] === 200, 'progress status missing');
    p13assert(isset($status['data']['status']) && $status['data']['status'] === 'success', 'progress status did not finish successfully');
    p13assert(isset($status['data']['progress']) && (int)$status['data']['progress'] === 100, 'progress did not reach 100');
    p13assert(!empty($status['data']['integrity_verified']), 'final integrity status missing');

    $installed = isset($result['data']['installed']) && is_array($result['data']['installed']) ? $result['data']['installed'] : [];
    p13assert(count($installed) >= 1, 'expected at least one installed release package');
    $lastInstalled = $installed[count($installed) - 1];
    p13assert(!empty($lastInstalled['sha256_verified']), 'installed release SHA256 was not verified');
    p13assert(!empty($lastInstalled['files_verified']), 'installed release files were not verified');
    p13assert(empty($lastInstalled['database_migrated']), 'Phase 13.3-13.6 must not require DB migration');
    $backup = isset($lastInstalled['backup']) ? $lastInstalled['backup'] : '';
    p13assert($backup !== '' && is_dir($backup), 'updater backup directory missing');
    p13assert(is_file(rtrim($backup, '/\\') . '/database.sql'), 'database backup file missing');
    p13assert(is_file(rtrim($backup, '/\\') . '/created.json'), 'file backup manifest missing');
    p13assert(is_file(rtrim($backup, '/\\') . '/files/application/common/library/update/UpdateManager.php'), 'self-updater old file was not backed up');
    p13assert(strpos((string)file_get_contents(rtrim($backup, '/\\') . '/files/application/common/library/update/UpdateManager.php'), 'PHASE13_OLD_SELF_UPDATE_MARKER') !== false, 'backup did not preserve old updater file');

    $history = $manager->history(10);
    p13assert(isset($history['data']['list'][0]['status']) && $history['data']['list'][0]['status'] === 'success', 'successful update history missing');
    p13assert(isset($history['data']['list'][0]['from_version']) && $history['data']['list'][0]['from_version'] === $oldVersion, 'history old version mismatch');
    p13assert(isset($history['data']['list'][0]['to_version']) && $history['data']['list'][0]['to_version'] === $targetVersion, 'history target version mismatch');

    $checkAfter = $manager->check('github');
    p13assert(isset($checkAfter['code']) && (int)$checkAfter['code'] === 204, 'post-install check should report latest: ' . json_encode($checkAfter));
    p13assert(isset($checkAfter['data']['local_version']) && (string)$checkAfter['data']['local_version'] === $targetVersion, 'post-install local version mismatch');

    p13rm($site);
    fwrite(STDOUT, "OK phase13_github_online_update_e2e real_release={$targetVersion} base={$oldVersion} self_update=passed progress=passed history=passed\n");
}
