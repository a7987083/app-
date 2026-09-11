<?php

namespace app\common\library\update;

class UpdateRuntimeStore
{
    protected $root;
    protected $base;
    protected $statusDir;
    protected $historyDir;

    public function __construct($root)
    {
        $this->root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        $this->base = $this->root . 'runtime' . DIRECTORY_SEPARATOR . 'update' . DIRECTORY_SEPARATOR;
        $this->statusDir = $this->base . 'status' . DIRECTORY_SEPARATOR;
        $this->historyDir = $this->base . 'history' . DIRECTORY_SEPARATOR;
    }

    public static function validId($id)
    {
        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{8,80}$/', $id);
    }

    public function begin($jobId, $source, $fromVersion, $toVersion = '')
    {
        if (!self::validId($jobId)) {
            return false;
        }
        return $this->writeStatus($jobId, [
            'job_id' => $jobId,
            'source' => (string)$source,
            'status' => 'running',
            'stage' => 'preparing',
            'progress' => 3,
            'message' => '正在准备更新',
            'from_version' => (string)$fromVersion,
            'to_version' => (string)$toVersion,
            'started_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'rollback' => false,
        ]);
    }

    public function progress($jobId, $stage, $progress, $message, array $extra = [])
    {
        if (!self::validId($jobId)) {
            return false;
        }
        $current = $this->status($jobId);
        if (!is_array($current)) {
            $current = ['job_id' => $jobId];
        }
        $next = array_merge($current, $extra, [
            'status' => 'running',
            'stage' => (string)$stage,
            'progress' => max(0, min(100, intval($progress))),
            'message' => (string)$message,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->writeStatus($jobId, $next);
    }

    public function success($jobId, array $extra = [])
    {
        $current = $this->status($jobId);
        if (!is_array($current)) {
            $current = ['job_id' => $jobId];
        }
        return $this->writeStatus($jobId, array_merge($current, $extra, [
            'status' => 'success',
            'stage' => 'complete',
            'progress' => 100,
            'message' => '更新成功',
            'finished_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]));
    }

    public function fail($jobId, $message, $rollback, array $extra = [])
    {
        $current = $this->status($jobId);
        if (!is_array($current)) {
            $current = ['job_id' => $jobId];
        }
        return $this->writeStatus($jobId, array_merge($current, $extra, [
            'status' => 'failed',
            'stage' => 'failed',
            'message' => (string)$message,
            'rollback' => (bool)$rollback,
            'finished_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]));
    }

    public function status($jobId)
    {
        if (!self::validId($jobId)) {
            return null;
        }
        return $this->readJson($this->statusDir . $jobId . '.json');
    }

    public function recordHistory(array $entry)
    {
        $id = isset($entry['id']) && self::validId($entry['id']) ? $entry['id'] : $this->newId('hist');
        $entry['id'] = $id;
        if (!isset($entry['created_at'])) {
            $entry['created_at'] = date('Y-m-d H:i:s');
        }
        if (!$this->ensureDir($this->historyDir)) {
            return false;
        }
        return $this->atomicWrite($this->historyDir . date('Ymd_His') . '_' . $id . '.json', $entry) ? $id : false;
    }

    public function history($limit = 20)
    {
        $limit = max(1, min(100, intval($limit)));
        if (!is_dir($this->historyDir)) {
            return [];
        }
        $files = @scandir($this->historyDir, SCANDIR_SORT_DESCENDING);
        if (!is_array($files)) {
            return [];
        }
        $rows = [];
        foreach ($files as $file) {
            if (substr($file, -5) !== '.json') {
                continue;
            }
            $row = $this->readJson($this->historyDir . $file);
            if (is_array($row)) {
                $rows[] = $row;
                if (count($rows) >= $limit) {
                    break;
                }
            }
        }
        return $rows;
    }

    public function historyById($id)
    {
        if (!self::validId($id) || !is_dir($this->historyDir)) {
            return null;
        }
        $files = @scandir($this->historyDir, SCANDIR_SORT_DESCENDING);
        if (!is_array($files)) {
            return null;
        }
        foreach ($files as $file) {
            if (substr($file, -5) !== '.json') {
                continue;
            }
            $row = $this->readJson($this->historyDir . $file);
            if (is_array($row) && isset($row['id']) && $row['id'] === $id) {
                return $row;
            }
        }
        return null;
    }

    public function newId($prefix = 'job')
    {
        try {
            $random = bin2hex(random_bytes(6));
        } catch (\Exception $e) {
            $random = substr(md5(uniqid('', true)), 0, 12);
        }
        return preg_replace('/[^A-Za-z0-9_-]/', '', $prefix) . '_' . date('YmdHis') . '_' . $random;
    }

    protected function writeStatus($jobId, array $data)
    {
        if (!$this->ensureDir($this->statusDir)) {
            return false;
        }
        return $this->atomicWrite($this->statusDir . $jobId . '.json', $data);
    }

    protected function readJson($file)
    {
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    protected function atomicWrite($file, array $data)
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        $tmp = $file . '.tmp.' . getmypid() . '.' . substr(md5(uniqid('', true)), 0, 6);
        if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
            return false;
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    protected function ensureDir($dir)
    {
        return is_dir($dir) || @mkdir($dir, 0755, true);
    }
}
