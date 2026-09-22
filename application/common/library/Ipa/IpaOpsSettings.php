<?php

namespace app\common\library\Ipa;

use think\Db;

class IpaOpsSettings
{
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
        if (array_key_exists('parse_enabled', $values)) $cfg['parse_enabled'] = !empty($values['parse_enabled']);
        foreach (['parse_window_minutes','parse_window_limit','parse_hour_limit','parse_day_limit','parse_retry_minutes','worker_alive_seconds'] as $key) {
            if (array_key_exists($key, $values)) $cfg[$key] = (int)$values[$key];
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
                Db::name('ipa_setting')->where('setting_key', $key)->update(['setting_value'=>$stored,'updated_at'=>$now]);
            } else {
                Db::name('ipa_setting')->insert(['setting_key'=>$key,'setting_value'=>$stored,'updated_at'=>$now]);
            }
        }
        return self::all();
    }

    public static function parseQuotaStatus($now = null)
    {
        $now = $now === null ? time() : (int)$now;
        $cfg = self::all();
        $window = (int)Db::name('ipa_parse_attempt')->where('created_at', '>=', $now - ($cfg['parse_window_minutes'] * 60))->count();
        $hour = (int)Db::name('ipa_parse_attempt')->where('created_at', '>=', $now - 3600)->count();
        $dayStart = strtotime(date('Y-m-d 00:00:00', $now));
        $day = (int)Db::name('ipa_parse_attempt')->where('created_at', '>=', $dayStart)->count();
        return ['allowed'=>$cfg['parse_enabled'] && $window < $cfg['parse_window_limit'] && $hour < $cfg['parse_hour_limit'] && $day < $cfg['parse_day_limit'],'window_used'=>$window,'hour_used'=>$hour,'day_used'=>$day,'settings'=>$cfg];
    }

    public static function recordAttempt($assetId, $workerId, $result, $error = '')
    {
        Db::name('ipa_parse_attempt')->insert(['asset_id'=>(int)$assetId,'worker_id'=>substr((string)$workerId,0,128),'result'=>substr((string)$result,0,24),'error'=>substr((string)$error,0,2000),'created_at'=>time()]);
    }
}
