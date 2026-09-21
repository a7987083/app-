<?php

namespace app\common\library\Ipa;

use app\common\library\BlacklistPolicy;
use think\Config;
use think\Db;

class DylibVerificationService
{
    public static function verify(array $payload, $ip = '')
    {
        $started = microtime(true);
        $now = time();
        $secret = (string)Config::get('ipa_data_center.server_secret');
        if (strlen($secret) < 32) {
            throw new \RuntimeException('IPA verification server secret is not configured');
        }

        $required = ['udid', 'bundle_id', 'dylib_key', 'dylib_version', 'timestamp', 'nonce'];
        foreach ($required as $field) {
            if (!isset($payload[$field]) || trim((string)$payload[$field]) === '') {
                return self::result(false, 'bad_request', 'block', 0, '', 'Missing ' . $field);
            }
        }

        $udid = trim((string)$payload['udid']);
        $bundleId = trim((string)$payload['bundle_id']);
        $dylibKey = trim((string)$payload['dylib_key']);
        $version = trim((string)$payload['dylib_version']);
        $build = isset($payload['dylib_build']) ? trim((string)$payload['dylib_build']) : '';
        $sha256 = isset($payload['dylib_sha256']) ? strtolower(trim((string)$payload['dylib_sha256'])) : '';
        $timestamp = (int)$payload['timestamp'];
        $nonce = trim((string)$payload['nonce']);

        $skew = max(30, min(1800, (int)Config::get('ipa_data_center.verify_timestamp_skew')));
        if (abs($now - $timestamp) > $skew) {
            return self::loggedResult(false, 'timestamp_invalid', 'block', 0, '', 'Request timestamp expired', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }
        if (strlen($nonce) < 16 || strlen($nonce) > 128) {
            return self::loggedResult(false, 'nonce_invalid', 'block', 0, '', 'Invalid nonce', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }

        $nonceHash = hash_hmac('sha256', $nonce, $secret);
        try {
            Db::name('dylib_nonce')->insert([
                'nonce_hash' => $nonceHash,
                'expires_at' => $now + $skew,
                'created_at' => $now,
            ]);
        } catch (\think\exception\PDOException $e) {
            if (stripos($e->getMessage(), 'Duplicate entry') !== false) {
                return self::loggedResult(false, 'replay_detected', 'block', 0, '', 'Replay detected', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
            }
            throw $e;
        }

        $license = Db::name('kami')
            ->where('udid', $udid)
            ->where('jh', 1)
            ->where('endtime', '>', $now)
            ->order('endtime desc')
            ->find();
        if (!$license) {
            return self::loggedResult(false, 'license_invalid', 'block', 0, '', 'UDID authorization unavailable', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }

        $blackRows = Db::name('black')->where('udid', $udid)->order('id desc')->select();
        if (BlacklistPolicy::findActive($blackRows)) {
            return self::loggedResult(false, 'blacklisted', 'block', 0, '', 'UDID is blocked', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }

        $dylib = Db::name('dylib')->where('dylib_key', $dylibKey)->where('enabled', 1)->find();
        if (!$dylib) {
            return self::loggedResult(false, 'dylib_unknown', 'block', 0, '', 'Unknown dylib', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }

        $binding = Db::name('dylib_app_binding')
            ->where('dylib_id', (int)$dylib['id'])
            ->where('bundle_id', $bundleId)
            ->where('enabled', 1)
            ->find();
        if (!$binding) {
            return self::loggedResult(false, 'bundle_not_allowed', self::failAction($dylib, null, null), 0, '', 'Bundle ID not bound', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }

        $versionQuery = Db::name('dylib_version')
            ->where('dylib_id', (int)$dylib['id'])
            ->where('version', $version);
        if ($build !== '') {
            $versionQuery->where('build', $build);
        }
        $versionRow = $versionQuery->order('id desc')->find();
        if (!$versionRow) {
            return self::loggedResult(false, 'version_unknown', self::failAction($dylib, $binding, null), 0, '', 'Unknown dylib version', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }

        $action = self::failAction($dylib, $binding, $versionRow);
        $state = (string)$versionRow['state'];
        if (in_array($state, ['blocked', 'revoked'], true)) {
            return self::loggedResult(false, 'version_' . $state, $action, 0, '', (string)$versionRow['notice'], $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }

        if (!empty($versionRow['sha256'])) {
            if ($sha256 === '' || !hash_equals(strtolower($versionRow['sha256']), $sha256)) {
                return self::loggedResult(false, 'integrity_mismatch', $action, 0, '', 'Dylib fingerprint mismatch', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
            }
        }

        $offlineGrace = self::offlineGrace($dylib, $binding, $versionRow);
        $ttl = max(60, min($offlineGrace, max(60, (int)Config::get('ipa_data_center.session_ttl'))));
        $expires = $now + $ttl;
        $tokenPayload = [
            'v' => 1,
            'u' => hash_hmac('sha256', $udid, $secret),
            'b' => $bundleId,
            'd' => $dylibKey,
            'dv' => $version,
            'iat' => $now,
            'exp' => $expires,
            'og' => $offlineGrace,
            'st' => $state,
        ];
        $token = self::signToken($tokenPayload, $secret);
        Db::name('dylib_device_session')->insert([
            'session_hash' => hash('sha256', $token),
            'udid_hash' => hash_hmac('sha256', $udid, $secret),
            'dylib_version_id' => (int)$versionRow['id'],
            'bundle_id' => $bundleId,
            'issued_at' => $now,
            'expires_at' => $expires,
            'last_seen_at' => $now,
            'status' => 'active',
            'created_at' => $now,
        ]);

        $code = $state === 'deprecated' ? 'ok_deprecated' : ($state === 'testing' ? 'ok_testing' : 'ok');
        return self::loggedResult(true, $code, 'allow', $offlineGrace, $token, (string)$versionRow['notice'], $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
    }

    protected static function failAction(array $dylib, $binding, $version)
    {
        if (is_array($binding) && !empty($binding['fail_action_override'])) {
            return $binding['fail_action_override'];
        }
        if (is_array($version) && !empty($version['fail_action'])) {
            return $version['fail_action'];
        }
        return !empty($dylib['default_fail_action']) ? $dylib['default_fail_action'] : 'disable_feature';
    }

    protected static function offlineGrace(array $dylib, array $binding, array $version)
    {
        if (!empty($binding['offline_grace_override'])) {
            return max(0, min(86400, (int)$binding['offline_grace_override']));
        }
        if (isset($version['offline_grace'])) {
            return max(0, min(86400, (int)$version['offline_grace']));
        }
        return max(0, min(86400, (int)$dylib['default_offline_grace']));
    }

    protected static function signToken(array $payload, $secret)
    {
        $body = self::base64url(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $sig = self::base64url(hash_hmac('sha256', $body, $secret, true));
        return $body . '.' . $sig;
    }

    protected static function base64url($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected static function loggedResult($ok, $code, $action, $offlineGrace, $token, $message, $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret)
    {
        $latency = (int)round((microtime(true) - $started) * 1000);
        Db::name('dylib_verify_log')->insert([
            'udid_hash' => hash_hmac('sha256', $udid, $secret),
            'bundle_id' => $bundleId,
            'dylib_key' => $dylibKey,
            'dylib_version' => $version,
            'result_code' => $code,
            'action' => $action,
            'ip_hash' => $ip === '' ? '' : hash_hmac('sha256', $ip, $secret),
            'latency_ms' => $latency,
            'created_at' => time(),
        ]);
        return self::result($ok, $code, $action, $offlineGrace, $token, $message);
    }

    protected static function result($ok, $code, $action, $offlineGrace, $token, $message)
    {
        return [
            'ok' => (bool)$ok,
            'code' => (string)$code,
            'action' => (string)$action,
            'offline_grace_seconds' => (int)$offlineGrace,
            'token' => (string)$token,
            'message' => (string)$message,
            'server_time' => time(),
        ];
    }
}
