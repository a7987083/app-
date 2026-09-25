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

        // 2406 keeps the v1 required fields intact. v2 app identity is additive so
        // already-issued clients can continue to verify basic/global permissions.
        $required = ['udid', 'bundle_id', 'dylib_key', 'dylib_version', 'timestamp', 'nonce', 'signature'];
        foreach ($required as $field) {
            if (!isset($payload[$field]) || trim((string)$payload[$field]) === '') {
                return self::result(false, 'bad_request', 'block', 0, '', 'Missing ' . $field);
            }
        }

        $protocol = isset($payload['protocol_version']) ? max(1, (int)$payload['protocol_version']) : 1;
        $udid = trim((string)$payload['udid']);
        $bundleId = trim((string)$payload['bundle_id']);
        $dylibKey = trim((string)$payload['dylib_key']);
        $version = trim((string)$payload['dylib_version']);
        $build = isset($payload['dylib_build']) ? trim((string)$payload['dylib_build']) : '';
        $sha256 = isset($payload['dylib_sha256']) ? strtolower(trim((string)$payload['dylib_sha256'])) : '';
        $timestamp = (int)$payload['timestamp'];
        $nonce = trim((string)$payload['nonce']);
        $signature = strtolower(trim((string)$payload['signature']));
        $appExecutable = isset($payload['app_executable']) ? trim((string)$payload['app_executable']) : '';
        $appMachoUuid = isset($payload['app_macho_uuid']) ? strtoupper(trim((string)$payload['app_macho_uuid'])) : '';
        $appVersion = isset($payload['app_version']) ? trim((string)$payload['app_version']) : '';
        $appBuild = isset($payload['app_build']) ? trim((string)$payload['app_build']) : '';

        if ($protocol >= 2 && ($appExecutable === '' || $appMachoUuid === '')) {
            return self::loggedResult(false, 'app_identity_incomplete', 'block', 0, '', 'Protocol v2 requires executable and Mach-O UUID', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }

        $skew = max(30, min(1800, (int)Config::get('ipa_data_center.verify_timestamp_skew')));
        if (abs($now - $timestamp) > $skew) {
            return self::loggedResult(false, 'timestamp_invalid', 'block', 0, '', 'Request timestamp expired', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }
        if (strlen($nonce) < 16 || strlen($nonce) > 128) {
            return self::loggedResult(false, 'nonce_invalid', 'block', 0, '', 'Invalid nonce', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $signature)) {
            return self::loggedResult(false, 'signature_invalid', 'block', 0, '', 'Invalid request signature format', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }

        $dylib = Db::name('dylib')->where('dylib_key', $dylibKey)->where('enabled', 1)->find();
        if (!$dylib) {
            return self::loggedResult(false, 'dylib_unknown', 'block', 0, '', 'Unknown dylib', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }
        if (empty($dylib['verify_secret_ciphertext'])) {
            return self::loggedResult(false, 'dylib_key_unconfigured', 'block', 0, '', 'Dylib verification key unavailable', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }

        try {
            $verifySecret = SecretBox::decrypt((string)$dylib['verify_secret_ciphertext']);
        } catch (\Exception $e) {
            return self::loggedResult(false, 'dylib_key_unavailable', 'block', 0, '', 'Dylib verification key unavailable', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }

        $canonical = $protocol >= 2
            ? self::canonicalRequestV2($udid, $bundleId, $dylibKey, $version, $build, $sha256, $timestamp, $nonce, $protocol, $appExecutable, $appMachoUuid, $appVersion, $appBuild)
            : self::canonicalRequest($udid, $bundleId, $dylibKey, $version, $build, $sha256, $timestamp, $nonce);
        $expectedSignature = hash_hmac('sha256', $canonical, $verifySecret);
        if (!hash_equals($expectedSignature, $signature)) {
            return self::loggedResult(false, 'signature_mismatch', 'block', 0, '', 'Request signature mismatch', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }

        // Only authenticated requests may reserve a nonce. This prevents unauthenticated
        // callers from filling the replay-protection table with arbitrary values.
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

        $blackRows = Db::name('black')->where('udid', $udid)->order('id desc')->select();
        if (BlacklistPolicy::findActive($blackRows)) {
            return self::loggedResult(false, 'blacklisted', 'block', 0, '', 'UDID is blocked', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }

        // Dylib-level BundleID whitelisting is retired in 2406. The running App is
        // resolved against parsed IPA evidence only for scope=3 card elevation.
        try {
            $identity = DylibRuntimeAccessService::resolveAppIdentity($payload);
        } catch (\Exception $e) {
            $identity = [
                'resolved' => false,
                'code' => 'app_identity_unavailable',
                'category_id' => 0,
                'asset_id' => 0,
                'bundle_id' => $bundleId,
                'executable' => $appExecutable,
                'macho_uuid' => $appMachoUuid,
            ];
        }
        $access = DylibRuntimeAccessService::resolveAccess($udid, $now, $identity);
        $accessExtras = [
            'protocol_version' => $protocol,
            'access_level' => $access['access_level'],
            'permissions' => $access['permissions'],
            'app_identity' => $access['app_identity'],
        ];
        if ($access['access_level'] === DylibRuntimeAccessService::ACCESS_BLOCK) {
            return self::loggedResult(false, $access['reason'], self::failAction($dylib, null), 0, '', 'Authorization does not apply to this App', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret, $accessExtras);
        }

        $versionQuery = Db::name('dylib_version')
            ->where('dylib_id', (int)$dylib['id'])
            ->where('version', $version);
        if ($build !== '') {
            $versionQuery->where('build', $build);
        }
        $versionRow = $versionQuery->order('id desc')->find();
        if (!$versionRow) {
            return self::loggedResult(false, 'version_unknown', self::failAction($dylib, null), 0, '', 'Unknown dylib version', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret, $accessExtras);
        }

        $action = self::failAction($dylib, $versionRow);
        $state = (string)$versionRow['state'];
        if (in_array($state, ['blocked', 'revoked'], true)) {
            return self::loggedResult(false, 'version_' . $state, $action, 0, '', (string)$versionRow['notice'], $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret, $accessExtras);
        }

        if (!empty($versionRow['sha256'])) {
            if ($sha256 === '' || !hash_equals(strtolower($versionRow['sha256']), $sha256)) {
                return self::loggedResult(false, 'integrity_mismatch', $action, 0, '', 'Dylib fingerprint mismatch', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret, $accessExtras);
            }
        }

        $offlineGrace = self::offlineGrace($dylib, $versionRow);
        $ttl = max(60, min($offlineGrace, max(60, (int)Config::get('ipa_data_center.session_ttl'))));
        $expires = $now + $ttl;
        $tokenPayload = [
            'v' => 2,
            'u' => hash_hmac('sha256', $udid, $secret),
            'b' => $bundleId,
            'd' => $dylibKey,
            'dv' => $version,
            'ai' => !empty($identity['resolved']) ? (int)$identity['category_id'] : 0,
            'al' => (string)$access['access_level'],
            'au' => !empty($identity['macho_uuid']) ? (string)$identity['macho_uuid'] : '',
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

        $appUpdate = ['available' => false];
        $notice = null;
        try {
            $appUpdate = DylibRuntimeAccessService::appUpdate($identity, $appVersion, $appBuild);
        } catch (\Exception $e) {
            $appUpdate = ['available' => false, 'code' => 'update_lookup_unavailable'];
        }
        try {
            $notice = DylibRuntimeAccessService::notice($identity, $access['access_level'], $now);
        } catch (\Exception $e) {
            $notice = null;
        }

        $code = $state === 'deprecated' ? 'ok_deprecated' : ($state === 'testing' ? 'ok_testing' : 'ok');
        $extras = array_merge($accessExtras, [
            'app_update' => $appUpdate,
            'notice' => $notice,
        ]);
        return self::loggedResult(true, $code, 'allow', $offlineGrace, $token, (string)$versionRow['notice'], $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret, $extras);
    }

    /** v1 signing contract: immutable for compatibility. */
    public static function canonicalRequest($udid, $bundleId, $dylibKey, $version, $build, $sha256, $timestamp, $nonce)
    {
        return implode("\n", [
            (string)$udid,
            (string)$bundleId,
            (string)$dylibKey,
            (string)$version,
            (string)$build,
            strtolower((string)$sha256),
            (string)(int)$timestamp,
            (string)$nonce,
        ]);
    }

    /** v2 app identity fields are appended after the complete v1 canonical body. */
    public static function canonicalRequestV2($udid, $bundleId, $dylibKey, $version, $build, $sha256, $timestamp, $nonce, $protocol, $appExecutable, $appMachoUuid, $appVersion, $appBuild)
    {
        return self::canonicalRequest($udid, $bundleId, $dylibKey, $version, $build, $sha256, $timestamp, $nonce) . "\n" . implode("\n", [
            (string)(int)$protocol,
            (string)$appExecutable,
            strtoupper((string)$appMachoUuid),
            (string)$appVersion,
            (string)$appBuild,
        ]);
    }

    protected static function failAction(array $dylib, $version)
    {
        if (is_array($version) && !empty($version['fail_action'])) {
            return $version['fail_action'];
        }
        return !empty($dylib['default_fail_action']) ? $dylib['default_fail_action'] : 'disable_feature';
    }

    protected static function offlineGrace(array $dylib, array $version)
    {
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

    protected static function loggedResult($ok, $code, $action, $offlineGrace, $token, $message, $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret, array $extras = [])
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
        return self::result($ok, $code, $action, $offlineGrace, $token, $message, $extras);
    }

    protected static function result($ok, $code, $action, $offlineGrace, $token, $message, array $extras = [])
    {
        return array_merge([
            'ok' => (bool)$ok,
            'code' => (string)$code,
            'action' => (string)$action,
            'offline_grace_seconds' => (int)$offlineGrace,
            'token' => (string)$token,
            'message' => (string)$message,
            'server_time' => time(),
        ], $extras);
    }
}
