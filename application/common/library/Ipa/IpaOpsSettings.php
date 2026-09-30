<?php

namespace app\common\library\Ipa;

use think\Db;

class IpaOpsSettings
{
    // Legacy quota keys remain readable/writable so existing admin forms and
    // databases stay compatible during the 2426 rolling upgrade. Parser V2 does
    // not use them to throttle or schedule work.
    protected static $defaults = [
        'parse_enabled' => '1',
        'parse_window_minutes' => '5',
        'parse_window_limit' => '3',
        'parse_hour_limit' => '24',
        'parse_day_limit' => '150',
        'parse_retry_minutes' => '30',
        'worker_alive_seconds' => '180',
    ];

    public static function all()
    {
        $out = self::$defaults;
        try {
            foreach (Db::name('ipa_setting')->select() as $row) {
                $out[(string)$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (\Exception $e) {}
        return [
            'parse_enabled' => ((int)$out['parse_enabled']) === 1,
            'parse_window_minutes' => max(1, min(1440, (int)$out['parse_window_minutes'])),
            'parse_window_limit' => max(1, min(1000, (int)$out['parse_window_limit'])),
            'parse_hour_limit' => max(1, min(10000, (int)$out['parse_hour_limit'])),
            'parse_day_limit' => max(1, min(100000, (int)$out['parse_day_limit'])),
            'parse_retry_minutes' => max(1, min(10080, (int)$out['parse_retry_minutes'])),
            'worker_alive_seconds' => max(30, min(1800, (int)$out['worker_alive_seconds'])),
        ];
    }

    public static function save(array $values)
    {
        $cfg = self::all();
        if (array_key_exists('parse_enabled', $values)) {
            $cfg['parse_enabled'] = !empty($values['parse_enabled']);
        }
        foreach (['parse_window_minutes','parse_window_limit','parse_hour_limit','parse_day_limit','parse_retry_minutes','worker_alive_seconds'] as $key) {
            if (array_key_exists($key, $values)) {
                $cfg[$key] = (int)$values[$key];
            }
        }
        $cfg['parse_window_minutes'] = max(1, min(1440, (int)$cfg['parse_window_minutes']));
        $cfg['parse_window_limit'] = max(1, min(1000, (int)$cfg['parse_window_limit']));
        $cfg['parse_hour_limit'] = max(1, min(10000, (int)$cfg['parse_hour_limit']));
        $cfg['parse_day_limit'] = max(1, min(100000, (int)$cfg['parse_day_limit']));
        $cfg['parse_retry_minutes'] = max(1, min(10080, (int)$cfg['parse_retry_minutes']));
        $cfg['worker_alive_seconds'] = max(30, min(1800, (int)$cfg['worker_alive_seconds']));

        $now = time();
        foreach ($cfg as $key => $value) {
            $stored = is_bool($value) ? ($value ? '1' : '0') : (string)$value;
            if (Db::name('ipa_setting')->where('setting_key', $key)->find()) {
                Db::name('ipa_setting')->where('setting_key', $key)->update(['setting_value' => $stored, 'updated_at' => $now]);
            } else {
                Db::name('ipa_setting')->insert(['setting_key' => $key, 'setting_value' => $stored, 'updated_at' => $now]);
            }
        }
        return self::all();
    }

    /**
     * Compatibility API for existing UI. Parser V2 has no time-window/hour/day
     * throttling, so this method must not execute COUNT queries in the worker hot path.
     */
    public static function parseQuotaStatus($now = null)
    {
        $cfg = self::all();
        return [
            'allowed' => $cfg['parse_enabled'],
            'window_used' => 0,
            'hour_used' => 0,
            'day_used' => 0,
            'settings' => $cfg,
            'mode' => 'v2_unlimited',
        ];
    }

    public static function recordAttempt($assetId, $workerId, $result, $error = '')
    {
        Db::name('ipa_parse_attempt')->insert([
            'asset_id' => (int)$assetId,
            'worker_id' => substr((string)$workerId, 0, 128),
            'result' => substr((string)$result, 0, 24),
            'error' => substr((string)$error, 0, 2000),
            'created_at' => time(),
        ]);
    }
}
