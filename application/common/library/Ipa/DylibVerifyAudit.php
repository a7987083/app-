<?php

namespace app\common\library\Ipa;

use think\Config;
use think\Db;

class DylibVerifyAudit
{
    public static function logBadRequest(array $payload, $ip = '', $started = null, $code = 'bad_request', $action = 'block')
    {
        $secret = (string)Config::get('ipa_data_center.server_secret');
        $udid = trim((string)(isset($payload['udid']) ? $payload['udid'] : ''));
        $bundleId = trim((string)(isset($payload['bundle_id']) ? $payload['bundle_id'] : ''));
        $dylibKey = trim((string)(isset($payload['dylib_key']) ? $payload['dylib_key'] : ''));
        $version = trim((string)(isset($payload['dylib_version']) ? $payload['dylib_version'] : ''));
        $latency = $started === null ? 0 : (int)round((microtime(true) - (float)$started) * 1000);

        Db::name('dylib_verify_log')->insert([
            'udid' => $udid,
            'ip' => trim((string)$ip),
            'udid_hash' => ($udid !== '' && strlen($secret) >= 32) ? hash_hmac('sha256', $udid, $secret) : '',
            'bundle_id' => $bundleId,
            'dylib_key' => $dylibKey,
            'dylib_version' => $version,
            'result_code' => (string)$code,
            'action' => (string)$action,
            'ip_hash' => ($ip !== '' && strlen($secret) >= 32) ? hash_hmac('sha256', (string)$ip, $secret) : '',
            'latency_ms' => max(0, $latency),
            'created_at' => time(),
        ]);
    }
}
