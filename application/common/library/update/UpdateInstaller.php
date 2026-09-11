<?php

namespace app\common\library\update;

use app\common\library\SiteConfigSync;
use app\common\library\UpdateIntegrity;

class UpdateInstaller
{
    protected $root;
    protected $http;
    protected $sqlRunner;

    public function __construct($root, UpdateHttpClient $http = null)
    {
        $this->root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        $this->http = $http ?: new UpdateHttpClient();
        $this->sqlRunner = new UpdateSqlRunner();
    }

    public function install(array $package, $requiresSha256)
    {
        $version = isset($package['version']) ? trim((string)$package['version']) : '';
        $url = isset($package['download']) ? trim((string)$package['download']) : '';
        $sha256 = isset($package['sha256']) ? strtolower(trim((string)$package['sha256'])) : '';
        if ($version === '' || $url === '') {
            throw new \RuntimeException('更新包信息不完整');
        }
        if ($requiresSha256 && !preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            throw new \RuntimeException('GitHub 更新包缺少有效 SHA256');
        }

        $workBase = $this->root . 'public' . DIRECTORY_SEPARATOR . 'update' . DIRECTORY_SEPARATOR;
        $runId = date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8);
        $workDir = $workBase . 'cache' . DIRECTORY_SEPARATOR . $runId . DIRECTORY_SEPARATOR;
        $backupDir = $this->root . 'runtime' . DIRECTORY_SEPARATOR . 'update_backup' . DIRECTORY_SEPARATOR . $runId . DIRECTORY_SEPARATOR;
        if (!is_dir($workDir) && !@mkdir($workDir, 0755, true)) {
            throw new \RuntimeException('无法创建更新缓存目录');
        }
        $zipFile = $workDir . 'update.zip';
        if (!$this->http->download($url, $zipFile)) {
            $this->removeTree($workDir);
            throw new \RuntimeException('升级程序包下载失败');
        }
        if ($sha256 !== '' && hash_file('sha256', $zipFile) !== $sha256) {
            $this->removeTree($workDir);
            throw new \RuntimeException('更新包 SHA256 校验失败');
        }

        $extractDir = $workDir . 'extract' . DIRECTORY_SEPARATOR;
        if (!$this->safeExtract($zipFile, $extractDir)) {
            $this->removeTree($workDir);
            throw new \RuntimeException('更新包解压或路径安全校验失败');
        }
        $programDir = $extractDir . 'program' . DIRECTORY_SEPARATOR;
        $mysqlDir = $extractDir . 'mysql' . DIRECTORY_SEPARATOR;
        if (!is_dir($programDir) && !is_dir($mysqlDir)) {
            $this->removeTree($workDir);
            throw new \RuntimeException('更新包结构无效');
        }
        if (is_dir($programDir)) {
            $this->validateProgramTree($programDir);
        }

        $backup = new UpdateBackup($this->root, $backupDir);
        $backupCreated = false;
        try {
            $backup->create(is_dir($programDir) ? $programDir : $this->emptyProgramDir($workDir));
            $backupCreated = true;
            if (is_dir($mysqlDir)) {
                $this->sqlRunner->runDirectory($mysqlDir);
            }
            if (is_dir($programDir)) {
                $this->copyProgram($programDir, $this->root);
                if (!$this->verifyProgram($programDir, $this->root)) {
                    throw new \RuntimeException('文件覆盖完整性校验失败');
                }
            }
            try {
                SiteConfigSync::mergeMissingFromDb();
            } catch (\Exception $e) {
                throw new \RuntimeException('站点配置同步失败: ' . $e->getMessage());
            }
            $this->writeVersion($version);
            if (function_exists('rmdirs') && defined('CACHE_PATH')) {
                @rmdirs(CACHE_PATH, false);
            }
            $this->removeTree($workDir);
            return [
                'version' => $version,
                'backup' => str_replace('\\', '/', $backupDir),
            ];
        } catch (\Exception $e) {
            $rollbackError = '';
            if ($backupCreated) {
                try {
                    $backup->rollback();
                } catch (\Exception $rollbackException) {
                    $rollbackError = '; 自动回滚失败: ' . $rollbackException->getMessage();
                }
            }
            $this->removeTree($workDir);
            throw new \RuntimeException($e->getMessage() . $rollbackError);
        }
    }

    protected function emptyProgramDir($workDir)
    {
        $dir = $workDir . 'empty_program' . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public static function isSafeArchivePath($name)
    {
        if ($name === '' || strpos($name, "\0") !== false) {
            return false;
        }
        $name = str_replace('\\', '/', $name);
        if ($name[0] === '/' || preg_match('/^[A-Za-z]:\//', $name)) {
            return false;
        }
        foreach (explode('/', $name) as $part) {
            if ($part === '..') {
                return false;
            }
        }
        return true;
    }

    public static function isProtectedRelativePath($relative)
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $protected = [
            'application/database.php',
            '.env',
            'uploads',
            'public/uploads',
            'runtime',
        ];
        foreach ($protected as $path) {
            if ($relative === $path || strpos($relative, $path . '/') === 0) {
                return true;
            }
        }
        return false;
    }

    protected function safeExtract($zipFile, $target)
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return false;
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!self::isSafeArchivePath($name)) {
                $zip->close();
                return false;
            }
            if (method_exists($zip, 'getExternalAttributesIndex')) {
                $opsys = 0;
                $attr = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attr)) {
                    $mode = ($attr >> 16) & 0170000;
                    if ($mode === 0120000) {
                        $zip->close();
                        return false;
                    }
                }
            }
        }
        if (!is_dir($target) && !@mkdir($target, 0755, true)) {
            $zip->close();
            return false;
        }
        $ok = $zip->extractTo($target);
        $zip->close();
        return $ok;
    }

    protected function validateProgramTree($programDir)
    {
        $base = rtrim($programDir, '/\\') . DIRECTORY_SEPARATOR;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base)));
            if (self::isProtectedRelativePath($relative)) {
                throw new \RuntimeException('更新包包含受保护路径: ' . $relative);
            }
        }
    }

    protected function copyProgram($source, $target)
    {
        $source = rtrim($source, '/\\') . DIRECTORY_SEPARATOR;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source));
            $destination = rtrim($target, '/\\') . DIRECTORY_SEPARATOR . $relative;
            if ($item->isDir()) {
                if (!is_dir($destination) && !@mkdir($destination, 0755, true)) {
                    throw new \RuntimeException('目录创建失败: ' . $relative);
                }
            } elseif ($item->isFile()) {
                $parent = dirname($destination);
                if (!is_dir($parent) && !@mkdir($parent, 0755, true)) {
                    throw new \RuntimeException('目录创建失败: ' . $relative);
                }
                if (!@copy($item->getPathname(), $destination)) {
                    throw new \RuntimeException('文件覆盖失败: ' . $relative);
                }
            }
        }
    }

    protected function verifyProgram($source, $target)
    {
        $source = rtrim($source, '/\\') . DIRECTORY_SEPARATOR;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        $checked = 0;
        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $relative = substr($item->getPathname(), strlen($source));
            $destination = rtrim($target, '/\\') . DIRECTORY_SEPARATOR . $relative;
            if (!is_file($destination) || hash_file('sha256', $item->getPathname()) !== hash_file('sha256', $destination)) {
                return false;
            }
            $checked++;
        }
        return $checked > 0 || is_dir($source);
    }

    protected function writeVersion($version)
    {
        $updateDir = $this->root . 'public' . DIRECTORY_SEPARATOR . 'update' . DIRECTORY_SEPARATOR;
        if (!is_dir($updateDir) && !@mkdir($updateDir, 0755, true)) {
            throw new \RuntimeException('版本目录创建失败');
        }
        $json = [
            'version' => (string)$version,
            'file_sign' => UpdateIntegrity::signFromRoot($this->root),
        ];
        if (file_put_contents($updateDir . 'ver.txt', (string)$version) === false ||
            file_put_contents($this->root . 'ver.json', json_encode($json)) === false) {
            throw new \RuntimeException('本地版本号写入失败');
        }
    }

    protected function removeTree($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }
}
