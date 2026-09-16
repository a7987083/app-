<?php

namespace app\common\library\update;

/**
 * Phase 15.2 whole-site storage inventory and safe cleanup planner.
 *
 * The scanner accounts for every regular file under ROOT_PATH without
 * following symlinks. Files are classified into protected/persistent,
 * regenerable, logs, backups, temporary and unknown buckets. Only files
 * that are explicitly classified as regenerable/temporary are eligible for
 * automatic deletion. Backups and unknown files are review-only.
 */
class SiteStorageManager
{
    const LIST_LIMIT = 200;
    const SAFE_MIN_AGE_SECONDS = 3600;

    protected $root;

    public function __construct($root)
    {
        $this->root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
    }

    public function snapshot()
    {
        $totals = $this->emptyBuckets();
        $largest = [];
        $review = [];
        $safe = [];
        $errors = [];

        foreach ($this->walkFiles($errors) as $row) {
            $class = $this->classify($row['path']);
            $row['category'] = $class['category'];
            $row['reason'] = $class['reason'];
            $row['auto_safe'] = $class['auto_safe'];
            $row['reviewable'] = $class['reviewable'];

            $totals['total']['count']++;
            $totals['total']['bytes'] += $row['bytes'];
            if (!isset($totals[$class['category']])) {
                $totals[$class['category']] = ['count' => 0, 'bytes' => 0];
            }
            $totals[$class['category']]['count']++;
            $totals[$class['category']]['bytes'] += $row['bytes'];

            $largest[] = $row;
            if ($class['reviewable'] && count($review) < self::LIST_LIMIT) {
                $review[] = $row;
            }
            if ($class['auto_safe'] && count($safe) < self::LIST_LIMIT) {
                $safe[] = $row;
            }
        }

        usort($largest, function ($a, $b) {
            if ($a['bytes'] === $b['bytes']) {
                return strcmp($a['path'], $b['path']);
            }
            return $a['bytes'] < $b['bytes'] ? 1 : -1;
        });
        $largest = array_slice($largest, 0, 50);

        return [
            'root' => str_replace('\\', '/', rtrim($this->root, '/\\')),
            'buckets' => $totals,
            'largest' => $largest,
            'review_candidates' => $review,
            'safe_candidates' => $safe,
            'errors' => array_slice($errors, 0, 50),
            'policy' => [
                'auto_delete' => ['regenerable', 'temporary'],
                'review_only' => ['backup', 'unknown'],
                'protected' => ['protected', 'persistent', 'log'],
                'safe_min_age_seconds' => self::SAFE_MIN_AGE_SECONDS,
            ],
        ];
    }

    public function cleanupSafe($dryRun = true)
    {
        $errors = [];
        $candidates = [];
        $deleted = ['count' => 0, 'bytes' => 0];
        $now = time();

        foreach ($this->walkFiles($errors) as $row) {
            $class = $this->classify($row['path']);
            if (!$class['auto_safe']) {
                continue;
            }
            if ($row['mtime'] > 0 && ($now - $row['mtime']) < self::SAFE_MIN_AGE_SECONDS) {
                continue;
            }
            $row['category'] = $class['category'];
            $row['reason'] = $class['reason'];
            if (count($candidates) < self::LIST_LIMIT) {
                $candidates[] = $row;
            }
            if (!$dryRun) {
                $full = $this->absolute($row['path']);
                if ($this->isInsideRoot($full) && is_file($full) && !is_link($full) && @unlink($full)) {
                    $deleted['count']++;
                    $deleted['bytes'] += $row['bytes'];
                }
            }
        }

        if (!$dryRun) {
            $this->removeEmptySafeDirectories();
        }

        return [
            'dry_run' => (bool)$dryRun,
            'candidates' => $candidates,
            'deleted' => $deleted,
            'errors' => array_slice($errors, 0, 50),
        ];
    }

    /**
     * Deletes explicitly selected review-only files (backup/unknown).
     * Paths are reclassified server-side so protected data cannot be deleted
     * by forging a request.
     */
    public function deleteSelected(array $paths, $dryRun = false)
    {
        $result = ['dry_run' => (bool)$dryRun, 'deleted' => ['count' => 0, 'bytes' => 0], 'skipped' => []];
        $seen = [];
        foreach ($paths as $path) {
            $path = $this->normalizeRelative($path);
            if ($path === '' || isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $class = $this->classify($path);
            if (!$class['reviewable']) {
                $result['skipped'][] = ['path' => $path, 'reason' => '该文件不属于人工可删除类别'];
                continue;
            }
            $full = $this->absolute($path);
            if (!$this->isInsideRoot($full) || !is_file($full) || is_link($full)) {
                $result['skipped'][] = ['path' => $path, 'reason' => '文件不存在或路径不安全'];
                continue;
            }
            $bytes = (int)@filesize($full);
            if (!$dryRun && !@unlink($full)) {
                $result['skipped'][] = ['path' => $path, 'reason' => '删除失败'];
                continue;
            }
            if (!$dryRun) {
                $result['deleted']['count']++;
                $result['deleted']['bytes'] += $bytes;
            }
        }
        return $result;
    }

    protected function emptyBuckets()
    {
        $keys = ['total', 'protected', 'persistent', 'regenerable', 'log', 'backup', 'temporary', 'unknown'];
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = ['count' => 0, 'bytes' => 0];
        }
        return $out;
    }

    protected function walkFiles(array &$errors)
    {
        $rows = [];
        $stack = [''];
        while ($stack) {
            $relativeDir = array_pop($stack);
            $fullDir = $relativeDir === '' ? $this->root : $this->absolute($relativeDir);
            $items = @scandir($fullDir);
            if (!is_array($items)) {
                $errors[] = ['path' => $relativeDir, 'reason' => '目录不可读'];
                continue;
            }
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $relative = ltrim(($relativeDir === '' ? '' : $relativeDir . '/') . $item, '/');
                $full = $this->absolute($relative);
                if (is_link($full)) {
                    // Symlinks are counted as protected metadata but never followed.
                    $rows[] = ['path' => $relative, 'bytes' => 0, 'mtime' => (int)@filemtime($full), 'symlink' => true];
                    continue;
                }
                if (is_dir($full)) {
                    $stack[] = $relative;
                    continue;
                }
                if (!is_file($full)) {
                    continue;
                }
                $rows[] = [
                    'path' => $relative,
                    'bytes' => (int)@filesize($full),
                    'mtime' => (int)@filemtime($full),
                    'symlink' => false,
                ];
            }
        }
        return $rows;
    }

    protected function classify($relative)
    {
        $path = strtolower(str_replace('\\', '/', ltrim($relative, '/')));
        $base = basename($path);
        $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));

        if ($this->startsWith($path, 'public/uploads/')) {
            return $this->rule('persistent', '用户上传/业务持久文件', false, false);
        }
        if ($this->startsWith($path, 'runtime/update_backup/')) {
            return $this->rule('backup', '在线更新/回滚备份', false, true);
        }
        if ($this->startsWith($path, 'runtime/log/') || $ext === 'log') {
            return $this->rule('log', '运行日志，纳入统计但默认保护', false, false);
        }
        if ($this->startsWith($path, 'runtime/cache/') || $this->startsWith($path, 'runtime/temp/') || $this->startsWith($path, 'runtime/tmp/')) {
            return $this->rule('regenerable', '可重建运行缓存/临时目录', true, false);
        }
        if (in_array($ext, ['tmp', 'temp', 'part', 'download', 'swp'], true) || substr($base, -1) === '~' || $base === '.ds_store') {
            return $this->rule('temporary', '临时/未完成下载文件', true, false);
        }
        if (in_array($ext, ['zip', 'tar', 'gz', 'tgz', 'bz2', '7z', 'rar', 'sql', 'bak', 'backup'], true)) {
            return $this->rule('backup', '备份/归档文件，需人工确认', false, true);
        }

        $protectedPrefixes = [
            'application/', 'vendor/', 'thinkphp/', 'extend/', 'addons/',
            'public/assets/', 'public/static/', 'public/update/',
            'release/', 'tools/', 'tests/', '.github/'
        ];
        foreach ($protectedPrefixes as $prefix) {
            if ($this->startsWith($path, $prefix)) {
                return $this->rule('protected', '站点程序/发布资产', false, false);
            }
        }

        $protectedExact = [
            'public/index.php', 'public/api.php', 'public/encrypt.php',
            'index.php', 'think', 'composer.json', 'composer.lock',
            'ver.json', 'auto_install.json', 'nginx.rewrite', '.htaccess', '.user.ini',
            'license', 'readme.md', 'version', 'phase13_release.txt'
        ];
        if (in_array($path, $protectedExact, true)) {
            return $this->rule('protected', '站点入口/配置/版本文件', false, false);
        }

        if ($this->startsWith($path, 'runtime/update/')) {
            return $this->rule('protected', '更新任务状态/历史/锁文件', false, false);
        }
        if ($this->startsWith($path, 'runtime/')) {
            return $this->rule('unknown', '未识别 runtime 文件，需人工确认', false, true);
        }

        return $this->rule('unknown', '不属于已知站点资产规则，需人工确认', false, true);
    }

    protected function rule($category, $reason, $autoSafe, $reviewable)
    {
        return [
            'category' => $category,
            'reason' => $reason,
            'auto_safe' => (bool)$autoSafe,
            'reviewable' => (bool)$reviewable,
        ];
    }

    protected function removeEmptySafeDirectories()
    {
        foreach (['runtime/cache', 'runtime/temp', 'runtime/tmp'] as $relative) {
            $dir = $this->absolute($relative);
            if (!is_dir($dir)) {
                continue;
            }
            $this->removeEmptyChildren($dir, false);
        }
    }

    protected function removeEmptyChildren($dir, $removeSelf)
    {
        $items = @scandir($dir);
        if (!is_array($items)) {
            return false;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) {
                $this->removeEmptyChildren($path, true);
            }
        }
        if ($removeSelf) {
            $after = @scandir($dir);
            if (is_array($after) && count($after) === 2) {
                @rmdir($dir);
            }
        }
        return true;
    }

    protected function normalizeRelative($path)
    {
        $path = str_replace('\\', '/', trim((string)$path));
        $path = ltrim($path, '/');
        if ($path === '' || strpos($path, "\0") !== false) {
            return '';
        }
        $parts = explode('/', $path);
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return '';
            }
        }
        return implode('/', $parts);
    }

    protected function absolute($relative)
    {
        return $this->root . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relative, '/'));
    }

    protected function isInsideRoot($path)
    {
        $root = str_replace('\\', '/', $this->root);
        $candidate = str_replace('\\', '/', $path);
        return strpos($candidate, $root) === 0;
    }

    protected function startsWith($haystack, $needle)
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
