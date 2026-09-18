<?php

namespace app\common\library;

/**
 * Phase 20.1 pure helpers and invariants shared by later IPA services.
 * No network or database side effects live here.
 */
final class IpaFoundation
{
    const PARSER_VERSION = 1;

    const TASK_STATES = [
        'queued', 'running', 'interrupted', 'retrying',
        'success', 'failed', 'cancelled'
    ];

    const ISSUE_STATES = [
        'open', 'ignored', 'repairing', 'verified', 'failed', 'resolved'
    ];

    const CONFIDENCE_LEVELS = ['exact', 'derived', 'fallback'];

    public static function remotePathHash($sourceKey, $remotePath)
    {
        return hash('sha256', trim((string)$sourceKey) . "\n" . self::normalizeRemotePath($remotePath));
    }

    public static function normalizeRemotePath($path)
    {
        $path = str_replace('\\', '/', trim((string)$path));
        $path = preg_replace('#/+#', '/', $path);
        if ($path === '') {
            return '/';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        return $path !== '/' ? rtrim($path, '/') : '/';
    }

    public static function idempotencyKey($operation, array $identity)
    {
        ksort($identity);
        return hash('sha256', trim((string)$operation) . "\n" . json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function planHash(array $plan)
    {
        return hash('sha256', self::canonicalJson($plan));
    }

    public static function isTaskState($state)
    {
        return in_array((string)$state, self::TASK_STATES, true);
    }

    public static function isConfidence($value)
    {
        return in_array((string)$value, self::CONFIDENCE_LEVELS, true);
    }

    private static function canonicalJson($value)
    {
        if (is_array($value)) {
            if (self::isAssoc($value)) {
                ksort($value);
            }
            foreach ($value as $key => $item) {
                $value[$key] = is_array($item) ? json_decode(self::canonicalJson($item), true) : $item;
            }
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function isAssoc(array $value)
    {
        if ($value === []) {
            return false;
        }
        return array_keys($value) !== range(0, count($value) - 1);
    }
}
