<?php

namespace app\common\library\update;

/**
 * Phase 14.3 update-operations diagnostics and conservative cleanup.
 *
 * Cleanup rules are intentionally defensive:
 * - never delete a running/stale-running job status;
 * - never delete successful update history that still references backups;
 * - never delete a backup referenced by any retained history entry;
 * - only delete terminal status/history files after the configured age.
 */
class UpdateOps
{
    const DEFAULT_STATUS_DAYS = 14;
    const DEFAULT_HISTORY_DAYS = 90;
    const DEFAULT_BACKUP_DAYS = 30;
    const STALE_JOB_SECONDS = 1800;

    protected $root;
    protected $runtimeDir;
    protected $statusDir;
    protected $historyDir;
    protected $backupDir;

    public function __construct($root)
    {
        $this->root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        $this->runtimeDir = $this->root . 'runtime' . DIRECTORY_SEPARATOR . 'update' . DIRECTORY_SEPARATOR;
        $this->statusDir = $this->runtimeDir . 'status' . DIRECTORY_SEPARATOR;
        $this->historyDir = $this->runtimeDir . 'history' . DIRECTORY_SEPARATOR;
        $this->backupDir = $this->root . 'runtime' . DIRECTORY_SEPARATOR . 'update_backup' . DIRECTORY_SEPARATOR;
    }

    public function snapshot()
    {
        $statuses = $this->statusRows();
        $running = [];
        $stale = [];
        $now = time();
        foreach ($statuses as $row) {
            if (!isset($row['status']) || $row['status'] !== 'running') {
                continue;
            }
            $running[] = $row;
            $stamp = $this->rowTimestamp($row);
            if ($stamp > 0 && ($now - $stamp) >= self::STALE_JOB_SECONDS) {
                $stale[] = $row;
            }
        }

        $historyRows = $this->historyRows();
        $latestUpdate = null;
        $latestRollback = null;
        foreach ($historyRows as $row) {
            if ($latestUpdate === null && isset($row['type']) && $row['type'] === 'update') {
                $latestUpdate = $row;
            }
            if ($latestRollback === null && isset($row['type']) && $row['type'] === 'rollback') {
                $latestRollback = $row;
            }
            if ($latestUpdate !== null && $latestRollback !== null) {
                break;
            }
        }

        return [
            'storage' => [
                'backups' => $this->dirStats($this->backupDir, true),
                'history' => $this->dirStats($this->historyDir, false),
                'status' => $this->dirStats($this->statusDir, false),
                'cache' => $this->dirStats($this->runtimeDir . 'cache' . DIRECTORY_SEPARATOR, false),
            ],
            'jobs' => [
                'running_count' => count($running),
                'stale_count' => count($stale),
                'running' => array_slice($running, 0, 10),
                'stale' => array_slice($stale, 0, 10),
            ],
            'lock' => $this->lockState(),
            'history' => [
                'count' => count($historyRows),
                'latest_update' => $latestUpdate,
                'latest_rollback' => $latestRollback,
            ],
            'retention' => [
                'status_days' => self::DEFAULT_STATUS_DAYS,
                'history_days' => self::DEFAULT_HISTORY_DAYS,
                'backup_days' => self::DEFAULT_BACKUP_DAYS,
                'stale_job_seconds' => self::STALE_JOB_SECONDS,
            ],
        ];
    }

    public function cleanup($dryRun = true)
    {
        $now = time();
        $statusCutoff = $now - (self::DEFAULT_STATUS_DAYS * 86400);
        $historyCutoff = $now - (self::DEFAULT_HISTORY_DAYS * 86400);
        $backupCutoff = $now - (self::DEFAULT_BACKUP_DAYS * 86400);
        $deleted = ['status' => 0, 'history' => 0, 'backups' => 0, 'bytes' => 0];
        $candidates = ['status' => [], 'history' => [], 'backups' => []];

        $historyFiles = $this->jsonFiles($this->historyDir);
        $referencedBackups = [];
        foreach ($historyFiles as $file) {
            $row = $this->readJson($file);
            if (!is_array($row)) {
                continue;
            }
            if (isset($row['backups']) && is_array($row['backups'])) {
                foreach ($row['backups'] as $backup) {
                    $id = basename(rtrim(str_replace('\\', '/', (string)$backup), '/'));
                    if ($id !== '') {
                        $referencedBackups[$id] = true;
                    }
                }
            }
        }

        foreach ($this->jsonFiles($this->statusDir) as $file) {
            $row = $this->readJson($file);
            if (!is_array($row) || (isset($row['status']) && $row['status'] === 'running')) {
                continue;
            }
            if (@filemtime($file) > 0 && @filemtime($file) < $statusCutoff) {
                $candidates['status'][] = basename($file);
                $this->deleteFile($file, $dryRun, $deleted, 'status');
            }
        }

        foreach ($historyFiles as $file) {
            $row = $this->readJson($file);
            if (!is_array($row)) {
                continue;
            }
            $protected = isset($row['type'], $row['status'])
                && $row['type'] === 'update'
                && $row['status'] === 'success'
                && !empty($row['backups']);
            if ($protected) {
                continue;
            }
            if (@filemtime($file) > 0 && @filemtime($file) < $historyCutoff) {
                $candidates['history'][] = basename($file);
                $this->deleteFile($file, $dryRun, $deleted, 'history');
            }
        }

        if (is_dir($this->backupDir)) {
            $items = @scandir($this->backupDir);
            if (is_array($items)) {
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }
                    $path = $this->backupDir . $item;
                    if (!is_dir($path) || isset($referencedBackups[$item])) {
                        continue;
                    }
                    $mtime = @filemtime($path);
                    if ($mtime > 0 && $mtime < $backupCutoff) {
                        $candidates['backups'][] = $item;
                        $bytes = $this->pathBytes($path);
                        if (!$dryRun && $this->removeTree($path)) {
                            $deleted['backups']++;
                            $deleted['bytes'] += $bytes;
                        }
                    }
                }
            }
        }

        return [
            'dry_run' => (bool)$dryRun,
            'candidates' => $candidates,
            'deleted' => $deleted,
            'retention' => [
                'status_days' => self::DEFAULT_STATUS_DAYS,
                'history_days' => self::DEFAULT_HISTORY_DAYS,
                'backup_days' => self::DEFAULT_BACKUP_DAYS,
            ],
        ];
    }

    protected function statusRows()
    {
        $rows = [];
        foreach ($this->jsonFiles($this->statusDir) as $file) {
            $row = $this->readJson($file);
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        usort($rows, function ($a, $b) {
            return $this->rowTimestamp($b) - $this->rowTimestamp($a);
        });
        return $rows;
    }

    protected function historyRows()
    {
        $rows = [];
        $files = $this->jsonFiles($this->historyDir, true);
        foreach ($files as $file) {
            $row = $this->readJson($file);
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    protected function lockState()
    {
        $file = $this->runtimeDir . 'update.lock';
        if (!is_file($file)) {
            return ['exists' => false, 'busy' => false, 'metadata' => '', 'age_seconds' => 0];
        }
        $fp = @fopen($file, 'c+');
        $busy = null;
        if ($fp) {
            if (@flock($fp, LOCK_EX | LOCK_NB)) {
                $busy = false;
                @flock($fp, LOCK_UN);
            } else {
                $busy = true;
            }
            @fclose($fp);
        }
        $mtime = @filemtime($file);
        return [
            'exists' => true,
            'busy' => $busy,
            'metadata' => trim((string)@file_get_contents($file)),
            'age_seconds' => $mtime ? max(0, time() - $mtime) : 0,
        ];
    }

    protected function dirStats($dir, $directoriesOnly)
    {
        $count = 0;
        if (is_dir($dir)) {
            $items = @scandir($dir);
            if (is_array($items)) {
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }
                    $path = $dir . $item;
                    if ($directoriesOnly ? is_dir($path) : is_file($path)) {
                        $count++;
                    }
                }
            }
        }
        return [
            'count' => $count,
            'bytes' => $this->pathBytes($dir),
        ];
    }

    protected function jsonFiles($dir, $descending = false)
    {
        if (!is_dir($dir)) {
            return [];
        }
        $sort = $descending ? SCANDIR_SORT_DESCENDING : SCANDIR_SORT_ASCENDING;
        $items = @scandir($dir, $sort);
        if (!is_array($items)) {
            return [];
        }
        $files = [];
        foreach ($items as $item) {
            if (substr($item, -5) === '.json' && is_file($dir . $item)) {
                $files[] = $dir . $item;
            }
        }
        return $files;
    }

    protected function readJson($file)
    {
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $row = json_decode($raw, true);
        return is_array($row) ? $row : null;
    }

    protected function rowTimestamp(array $row)
    {
        foreach (['updated_at', 'finished_at', 'created_at', 'started_at'] as $key) {
            if (!empty($row[$key])) {
                $value = strtotime($row[$key]);
                if ($value !== false) {
                    return $value;
                }
            }
        }
        return 0;
    }

    protected function deleteFile($file, $dryRun, array &$deleted, $bucket)
    {
        $bytes = is_file($file) ? (int)@filesize($file) : 0;
        if (!$dryRun && @unlink($file)) {
            $deleted[$bucket]++;
            $deleted['bytes'] += $bytes;
        }
    }

    protected function pathBytes($path)
    {
        if (is_file($path)) {
            return (int)@filesize($path);
        }
        if (!is_dir($path)) {
            return 0;
        }
        $bytes = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $bytes += (int)$item->getSize();
            }
        }
        return $bytes;
    }

    protected function removeTree($path)
    {
        if (!is_dir($path)) {
            return true;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $ok = $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            if (!$ok) {
                return false;
            }
        }
        return @rmdir($path);
    }
}
