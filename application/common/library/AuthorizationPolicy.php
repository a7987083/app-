<?php

namespace app\common\library;

/**
 * Authorization/transfer limits. Defaults keep the public API usable even
 * before Phase 11 configuration rows are installed.
 */
class AuthorizationPolicy
{
    const DEFAULT_MAX_TRANSFERS = 3;
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
        return self::intConfig($values, 'unbind_max_count', self::DEFAULT_MAX_TRANSFERS, 1);
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

    public static function usedTransfers(array $rows)
    {
        $used = 0;
        foreach ($rows as $row) {
            $count = isset($row['transfer_count']) ? (int)$row['transfer_count'] : 0;
            if ($count > $used) {
                $used = $count;
            }
        }
        return $used;
    }

    public static function remainingTransfers($used, $max)
    {
        return max(0, (int)$max - max(0, (int)$used));
    }
}
