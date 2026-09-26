<?php

namespace app\common\library\update;

/**
 * Read/delete updater-owned rollback backups without exposing filesystem paths
 * to the browser. Backups referenced by retained update history are protected.
 */
class UpdateBackupManager
{
    protected $root;
    protected $backupDir;
    protected $historyDir;

    public function __construct($root)
    {
        $this->root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        $this->backupDir = $this->root . 'runtime' . DIRECTORY_SEPARATOR . 'update_backup' . DIRECTORY_SEPARATOR;
        $this->historyDir = $this->root . 'runtime' . DIRECTORY_SEPARATOR . 'update' . DIRECTORY_SEPARATOR . 'history' . DIRECTORY_SEPARATOR;
    }

    public function snapshot()
    {
        $protected = $this->protectedBackupIds();
        $items = [];
        $totalBytes = 0;
        $deletableBytes = 0;
        $deletableCount = 0;

        if (is_dir($this->backupDir)) {
            $names = @scandir($this->backupDir, SCANDIR_SORT_DESCENDING);
            if (is_array($names)) {
                foreach ($names as $name) {
                    if ($name === '.' || $name === '..' || !$this->isSafeId($name)) {
                        continue;
                    }
                    $path = $this->backupDir . $name;
                    if (!is_dir($path) || is_link($path)) {
                        continue;
                    }
                    $stats = $this->pathStats($path);
                    $mtime = (int)@filemtime($path);
                    $isProtected = isset($protected[$name]);
                    $row = [
                        'id' => $name,
                        'created_at' => $mtime > 0 ? date('Y-m-d H:i:s', $mtime) : '',
                        'timestamp' => $mtime,
                        'bytes' => $stats['bytes'],
                        'files' => $stats['files'],
                        'database_bytes' => is_file($path . DIRECTORY_SEPARATOR . 'database.sql')
                            ? (int)@filesize($path . DIRECTORY_SEPARATOR . 'database.sql') : 0,
                        'protected' => $isProtected,
                        'protected_reason' => $isProtected ? '仍被更新历史引用，可用于回滚' : '',
                    ];
                    $items[] = $row;
                    $totalBytes += $row['bytes'];
                    if (!$isProtected) {
                        $deletableCount++;
                        $deletableBytes += $row['bytes'];
                    }
                }
            }
        }

        usort($items, function ($a, $b) {
            if ($a['timestamp'] === $b['timestamp']) {
                return strcmp($b['id'], $a['id']);
            }
            return $b['timestamp'] - $a['timestamp'];
        });

        $diskFree = @disk_free_space($this->root);
        $diskTotal = @disk_total_space($this->root);

        return [
            'items' => $items,
            'count' => count($items),
            'bytes' => $totalBytes,
            'deletable_count' => $deletableCount,
            'deletable_bytes' => $deletableBytes,
            'disk_free_bytes' => $diskFree === false ? null : (float)$diskFree,
            'disk_total_bytes' => $diskTotal === false ? null : (float)$diskTotal,
        ];
    }

    /**
     * Delete selected backup IDs. dryRun performs the same validation/protection
     * checks and reports what would be removed.
     */
    public function deleteSelected(array $ids, $dryRun = false)
    {
        $ids = array_values(array_unique(array_map('strval', $ids)));
        if (!$ids) {
            throw new \InvalidArgumentException('请至少选择一个备份');
        }
        if (count($ids) > 500) {
            throw new \InvalidArgumentException('单次最多处理 500 个备份');
        }

        $protected = $this->protectedBackupIds();
        $deleted = [];
        $skipped = [];
        $bytes = 0;

        foreach ($ids as $id) {
            $id = trim($id);
            if (!$this->isSafeId($id)) {
                $skipped[] = ['id' => $id, 'reason' => '非法备份标识'];
                continue;
            }
            if (isset($protected[$id])) {
                $skipped[] = ['id' => $id, 'reason' => '仍被更新历史引用，禁止删除'];
                continue;
            }

            $path = $this->safeBackupPath($id);
            if ($path === null || !is_dir($path)) {
                $skipped[] = ['id' => $id, 'reason' => '备份不存在或路径校验失败'];
                continue;
            }
            if (is_link($path)) {
                $skipped[] = ['id' => $id, 'reason' => '拒绝删除符号链接'];
                continue;
            }

            $size = $this->pathStats($path)['bytes'];
            if (!$dryRun && !$this->removeTree($path)) {
                $skipped[] = ['id' => $id, 'reason' => '删除失败，请检查目录权限'];
                continue;
            }
            $deleted[] = $id;
            $bytes += $size;
        }

        return [
            'dry_run' => (bool)$dryRun,
            'requested' => count($ids),
            'deleted_count' => count($deleted),
            'deleted' => $deleted,
            'skipped' => $skipped,
            'bytes' => $bytes,
        ];
    }

    protected function protectedBackupIds()
    {
        $result = [];
        if (!is_dir($this->historyDir)) {
            return $result;
        }
        $files = @glob($this->historyDir . '*.json');
        if (!is_array($files)) {
            return $result;
        }
        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            if ($raw === false) {
                continue;
            }
            $row = json_decode($raw, true);
            if (!is_array($row) || empty($row['backups']) || !is_array($row['backups'])) {
                continue;
            }
            foreach ($row['backups'] as $backup) {
                $id = basename(rtrim(str_replace('\\', '/', (string)$backup), '/'));
                if ($this->isSafeId($id)) {
                    $result[$id] = true;
                }
            }
        }
        return $result;
    }

    protected function isSafeId($id)
    {
        $id = (string)$id;
        return $id !== ''
            && $id !== '.'
            && $id !== '..'
            && strlen($id) <= 128
            && preg_match('/^[A-Za-z0-9._-]+$/', $id) === 1
            && basename($id) === $id;
    }

    protected function safeBackupPath($id)
    {
        if (!$this->isSafeId($id) || !is_dir($this->backupDir)) {
            return null;
        }
        $base = realpath($this->backupDir);
        $target = realpath($this->backupDir . $id);
        if ($base === false || $target === false) {
            return null;
        }
        $base = rtrim($base, '/\\') . DIRECTORY_SEPARATOR;
        $targetNormalized = rtrim($target, '/\\') . DIRECTORY_SEPARATOR;
        if (strpos($targetNormalized, $base) !== 0) {
            return null;
        }
        return rtrim($target, '/\\');
    }

    protected function pathStats($path)
    {
        $bytes = 0;
        $files = 0;
        if (!is_dir($path)) {
            return ['bytes' => 0, 'files' => 0];
        }
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $item) {
                if ($item->isLink()) {
                    continue;
                }
                if ($item->isFile()) {
                    $files++;
                    $bytes += (int)$item->getSize();
                }
            }
        } catch (\UnexpectedValueException $e) {
            return ['bytes' => $bytes, 'files' => $files];
        }
        return ['bytes' => $bytes, 'files' => $files];
    }

    protected function removeTree($path)
    {
        if (!is_dir($path) || is_link($path)) {
            return false;
        }
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isLink()) {
                    if (!@unlink($item->getPathname())) {
                        return false;
                    }
                    continue;
                }
                $ok = $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
                if (!$ok) {
                    return false;
                }
            }
            return @rmdir($path);
        } catch (\UnexpectedValueException $e) {
            return false;
        }
    }
}
