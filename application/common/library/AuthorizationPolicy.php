<?php

namespace app\common\library;

/**
 * Authorization/transfer limits.
 * transfer_count on fa_kami means REMAINING self-service transfer quota.
 */
class AuthorizationPolicy
{
    const DEFAULT_MAX_TRANSFERS = 100;
    const DEFAULT_DAILY_TRANSFERS = 1;
    const DEFAULT_COOLDOWN_SECONDS = 3600;
    const DEFAULT_IP_HOURLY_ATTEMPTS = 10;

    public static function intConfig(array $values, $name, $default, $min = 0)
    {
        if (!array_key_exists($name, $values) || $values[$name] === '') {
            return (int)$default;
        }
        $value = (int)$values[$name];
        return $value >= $min ? $value : (int)$default;
    }

    public static function maxTransfers(array $values)
    {
        return self::intConfig($values, 'unbind_max_count', self::DEFAULT_MAX_TRANSFERS, 0);
    }

    public static function dailyTransfers(array $values)
    {
        return self::intConfig($values, 'unbind_daily_limit', self::DEFAULT_DAILY_TRANSFERS, 1);
    }

    public static function cooldownSeconds(array $values)
    {
        return self::intConfig($values, 'unbind_cooldown_seconds', self::DEFAULT_COOLDOWN_SECONDS, 0);
    }

    public static function ipHourlyAttempts(array $values)
    {
        return self::intConfig($values, 'unbind_ip_hour_limit', self::DEFAULT_IP_HOURLY_ATTEMPTS, 1);
    }

    /**
     * Active rows in one entitlement chain are kept at the same remaining
     * quota. max() also makes an administrator top-up on any active card take
     * effect immediately; the next successful transfer synchronizes all rows.
     */
    public static function remainingQuota(array $rows, $fallback = self::DEFAULT_MAX_TRANSFERS)
    {
        if (!$rows) {
            return max(0, (int)$fallback);
        }
        $remaining = null;
        foreach ($rows as $row) {
            if (!array_key_exists('transfer_count', $row)) {
                continue;
            }
            $value = max(0, (int)$row['transfer_count']);
            if ($remaining === null || $value > $remaining) {
                $remaining = $value;
            }
        }
        return $remaining === null ? max(0, (int)$fallback) : $remaining;
    }

    // Compatibility helpers retained for older tests/callers.
    public static function usedTransfers(array $rows)
    {
        $remaining = self::remainingQuota($rows, self::DEFAULT_MAX_TRANSFERS);
        return max(0, self::DEFAULT_MAX_TRANSFERS - $remaining);
    }

    public static function remainingTransfers($used, $max)
    {
        return max(0, (int)$max - max(0, (int)$used));
    }
}
