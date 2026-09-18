<?php

namespace app\common\library;

/**
 * Persistent software-source runtime clock.
 *
 * This is deliberately stored below runtime/ because the online updater treats
 * runtime as a protected path. The first successful observation becomes the
 * stable start timestamp and survives PHP/Nginx restarts and online updates.
 */
class SourceServerRuntime
{
    const RELATIVE_FILE = 'runtime/persistent/server_runtime.json';

    public static function info($now = null, $root = null)
    {
        $now = $now === null ? time() : max(1, (int)$now);
        $root = self::root($root);
        $file = $root . str_replace('/', DIRECTORY_SEPARATOR, self::RELATIVE_FILE);
        $backup = $file . '.bak';
        $lockFile = $file . '.lock';
        $dir = dirname($file);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return self::fallback($now, 'runtime_dir_unavailable');
        }

        $lock = @fopen($lockFile, 'c');
        if ($lock) {
            @flock($lock, LOCK_EX);
        }

        try {
            $data = self::readValid($file);
            if (!$data) {
                $data = self::readValid($backup);
                if ($data) {
                    self::atomicWrite($file, $data);
                }
            }
            if (!$data) {
                $data = [
                    'version' => 1,
                    'started_at' => $now,
                    'created_at' => date('Y-m-d H:i:s', $now),
                ];
                if (self::atomicWrite($file, $data)) {
                    self::atomicWrite($backup, $data);
                }
            } elseif (!is_file($backup)) {
                self::atomicWrite($backup, $data);
            }
        } finally {
            if ($lock) {
                @flock($lock, LOCK_UN);
                @fclose($lock);
            }
        }

        $startedAt = isset($data['started_at']) ? (int)$data['started_at'] : $now;
        if ($startedAt <= 0 || $startedAt > $now) {
            $startedAt = $now;
        }
        return [
            'started_at' => $startedAt,
            'created_at' => isset($data['created_at']) ? (string)$data['created_at'] : date('Y-m-d H:i:s', $startedAt),
            'seconds' => max(0, $now - $startedAt),
            'display' => self::formatElapsed(max(0, $now - $startedAt)),
            'file' => str_replace('\\', '/', self::RELATIVE_FILE),
        ];
    }

    public static function display($now = null, $root = null)
    {
        $info = self::info($now, $root);
        return $info['display'];
    }

    public static function formatElapsed($seconds)
    {
        $seconds = max(0, (int)$seconds);
        $days = (int)floor($seconds / 86400);
        $hours = (int)floor(($seconds % 86400) / 3600);
        $minutes = (int)floor(($seconds % 3600) / 60);
        if ($days > 0) {
            return $days . '天' . $hours . '小时' . $minutes . '分钟';
        }
        if ($hours > 0) {
            return $hours . '小时' . $minutes . '分钟';
        }
        return $minutes . '分钟';
    }

    protected static function fallback($now, $reason)
    {
        return [
            'started_at' => $now,
            'created_at' => date('Y-m-d H:i:s', $now),
            'seconds' => 0,
            'display' => '0分钟',
            'file' => str_replace('\\', '/', self::RELATIVE_FILE),
            'warning' => $reason,
        ];
    }

    protected static function root($root)
    {
        if ($root !== null && $root !== '') {
            return rtrim((string)$root, '/\\') . DIRECTORY_SEPARATOR;
        }
        if (defined('ROOT_PATH')) {
            return rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR;
        }
        return dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR;
    }

    protected static function readValid($file)
    {
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['started_at']) || (int)$data['started_at'] <= 0) {
            return null;
        }
        return [
            'version' => 1,
            'started_at' => (int)$data['started_at'],
            'created_at' => isset($data['created_at']) ? (string)$data['created_at'] : date('Y-m-d H:i:s', (int)$data['started_at']),
        ];
    }

    protected static function atomicWrite($file, array $data)
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        $tmp = $file . '.tmp.' . getmypid() . '.' . substr(md5(uniqid('', true)), 0, 8);
        if (@file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
            return false;
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        @chmod($file, 0600);
        return true;
    }
}
