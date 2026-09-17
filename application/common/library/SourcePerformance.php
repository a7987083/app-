<?php

namespace app\common\library;

/**
 * Lightweight opt-in/slow-request performance telemetry for software-source
 * responses. It never changes the public response body.
 */
class SourcePerformance
{
    const DEFAULT_SLOW_MS = 500;

    public static function enabled()
    {
        return self::envBool('SOURCE_PERF_LOG', false);
    }

    public static function slowThresholdMs()
    {
        $value = getenv('SOURCE_PERF_SLOW_MS');
        if ($value === false || trim((string)$value) === '') {
            return self::DEFAULT_SLOW_MS;
        }
        $value = (int)$value;
        return $value > 0 ? $value : self::DEFAULT_SLOW_MS;
    }

    public static function log(array $metrics)
    {
        $total = isset($metrics['total_ms']) ? (float)$metrics['total_ms'] : 0.0;
        if (!self::enabled() && $total < self::slowThresholdMs()) {
            return;
        }
        error_log('[SourcePerf] ' . json_encode($metrics, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected static function envBool($name, $default)
    {
        $value = getenv($name);
        if ($value === false || trim((string)$value) === '') {
            return (bool)$default;
        }
        return !in_array(strtolower(trim((string)$value)), ['0', 'false', 'off', 'no'], true);
    }
}
