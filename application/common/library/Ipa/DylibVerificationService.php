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
        if (strlen($secret) < 32) throw new \RuntimeException('IPA verification server secret is not configured');

        $required = ['udid','bundle_id','dylib_key','dylib_version','app_executable','app_macho_uuid','challenge_id','challenge','device_public_key','device_signature'];
        foreach ($required as $field) {
            if (!isset($payload[$field]) || trim((string)$payload[$field]) === '') return self::result(false, 'bad_request', 'block', 0, '', 'Missing ' . $field);
        }

        $udid = trim((string)$payload['udid']);
        $bundleId = trim((string)$payload['bundle_id']);
        $dylibKey = trim((string)$payload['dylib_key']);
        $version = trim((string)$payload['dylib_version']);
        $build = isset($payload['dylib_build']) ? trim((string)$payload['dylib_build']) : '';
        $sha256 = isset($payload['dylib_sha256']) ? strtolower(trim((string)$payload['dylib_sha256'])) : '';
        $appExecutable = trim((string)$payload['app_executable']);
        $appMachoUuid = strtoupper(trim((string)$payload['app_macho_uuid']));
        $appVersion = isset($payload['app_version']) ? trim((string)$payload['app_version']) : '';
        $appBuild = isset($payload['app_build']) ? trim((string)$payload['app_build']) : '';

        $auth = DylibDeviceAuthService::authenticate($payload);
        if (empty($auth['ok'])) {
            return self::loggedResult(false, isset($auth['code']) ? $auth['code'] : 'device_auth_invalid', 'block', 0, '', isset($auth['message']) ? $auth['message'] : 'Device authentication failed', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }
        $dylib = $auth['dylib'];

        $blackRows = Db::name('black')->where('udid', $udid)->order('id desc')->select();
        if (BlacklistPolicy::findActive($blackRows)) {
            return self::loggedResult(false, 'blacklisted', 'block', 0, '', 'UDID is blocked', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret);
        }

        try {
            $identity = DylibRuntimeAccessService::resolveAppIdentity(array_merge($payload, ['protocol_version' => 3]));
        } catch (\Exception $e) {
            $identity = ['resolved'=>false,'code'=>'app_identity_unavailable','category_id'=>0,'asset_id'=>0,'bundle_id'=>$bundleId,'executable'=>$appExecutable,'macho_uuid'=>$appMachoUuid];
        }
        $access = DylibRuntimeAccessService::resolveAccess($udid, $now, $identity);
        $accessExtras = [
            'protocol_version' => 3,
            'device_key_id' => isset($auth['key_id']) ? (string)$auth['key_id'] : '',
            'access_level' => $access['access_level'],
            'permissions' => $access['permissions'],
            'app_identity' => $access['app_identity'],
        ];
        if ($access['access_level'] === DylibRuntimeAccessService::ACCESS_BLOCK) {
            return self::loggedResult(false, $access['reason'], self::failAction($dylib, null), 0, '', 'Authorization does not apply to this App', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret, $accessExtras);
        }

        $versionQuery = Db::name('dylib_version')->where('dylib_id', (int)$dylib['id'])->where('version', $version);
        if ($build !== '') $versionQuery->where('build', $build);
        $versionRow = $versionQuery->order('id desc')->find();
        if (!$versionRow) {
            return self::loggedResult(false, 'version_unknown', self::failAction($dylib, null), 0, '', 'Unknown dylib version', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret, $accessExtras);
        }

        $action = self::failAction($dylib, $versionRow);
        $state = (string)$versionRow['state'];
        if (in_array($state, ['blocked','revoked'], true)) {
            return self::loggedResult(false, 'version_' . $state, $action, 0, '', (string)$versionRow['notice'], $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret, $accessExtras);
        }
        if (!empty($versionRow['sha256']) && ($sha256 === '' || !hash_equals(strtolower((string)$versionRow['sha256']), $sha256))) {
            return self::loggedResult(false, 'integrity_mismatch', $action, 0, '', 'Dylib fingerprint mismatch', $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret, $accessExtras);
        }

        $offlineGrace = self::offlineGrace($dylib, $versionRow);
        $ttl = max(60, min(max(60, $offlineGrace), max(60, (int)Config::get('ipa_data_center.session_ttl'))));
        $expires = $now + $ttl;
        $tokenPayload = [
            'v'=>3,'u'=>hash_hmac('sha256',$udid,$secret),'b'=>$bundleId,'d'=>$dylibKey,'dv'=>$version,
            'dk'=>isset($auth['key_id']) ? (string)$auth['key_id'] : '',
            'ai'=>!empty($identity['resolved']) ? (int)$identity['category_id'] : 0,
            'al'=>(string)$access['access_level'],'au'=>!empty($identity['macho_uuid']) ? (string)$identity['macho_uuid'] : '',
            'iat'=>$now,'exp'=>$expires,'og'=>$offlineGrace,'st'=>$state,
        ];
        $token = self::signToken($tokenPayload, $secret);
        Db::name('dylib_device_session')->insert([
            'session_hash'=>hash('sha256',$token),'udid_hash'=>hash_hmac('sha256',$udid,$secret),
            'dylib_version_id'=>(int)$versionRow['id'],'bundle_id'=>$bundleId,'issued_at'=>$now,'expires_at'=>$expires,
            'last_seen_at'=>$now,'status'=>'active','created_at'=>$now,
        ]);

        try { $appUpdate = DylibRuntimeAccessService::appUpdate($identity, $appVersion, $appBuild); }
        catch (\Exception $e) { $appUpdate = ['available'=>false,'code'=>'update_lookup_unavailable']; }
        try { $notice = DylibRuntimeAccessService::notice($identity, $access['access_level'], $now); }
        catch (\Exception $e) { $notice = null; }

        $code = $state === 'deprecated' ? 'ok_deprecated' : ($state === 'testing' ? 'ok_testing' : 'ok');
        $extras = array_merge($accessExtras, ['app_update'=>$appUpdate,'notice'=>$notice]);
        return self::loggedResult(true, $code, 'allow', $offlineGrace, $token, (string)$versionRow['notice'], $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret, $extras);
    }

    protected static function failAction(array $dylib, $version)
    {
        if (is_array($version) && !empty($version['fail_action'])) return $version['fail_action'];
        return !empty($dylib['default_fail_action']) ? $dylib['default_fail_action'] : 'disable_feature';
    }

    protected static function offlineGrace(array $dylib, array $version)
    {
        if (isset($version['offline_grace'])) return max(0, min(86400, (int)$version['offline_grace']));
        return max(0, min(86400, (int)$dylib['default_offline_grace']));
    }

    protected static function signToken(array $payload, $secret)
    {
        $body = self::base64url(json_encode($payload, JSON_UNESCAPED_SLASHES));
        return $body . '.' . self::base64url(hash_hmac('sha256', $body, $secret, true));
    }

    protected static function base64url($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected static function loggedResult($ok, $code, $action, $offlineGrace, $token, $message, $udid, $bundleId, $dylibKey, $version, $ip, $started, $secret, array $extras = [])
    {
        Db::name('dylib_verify_log')->insert([
            'udid_hash'=>hash_hmac('sha256',$udid,$secret),'bundle_id'=>$bundleId,'dylib_key'=>$dylibKey,
            'dylib_version'=>$version,'result_code'=>$code,'action'=>$action,
            'ip_hash'=>$ip === '' ? '' : hash_hmac('sha256',$ip,$secret),
            'latency_ms'=>(int)round((microtime(true)-$started)*1000),'created_at'=>time(),
        ]);
        return self::result($ok, $code, $action, $offlineGrace, $token, $message, $extras);
    }

    protected static function result($ok, $code, $action, $offlineGrace, $token, $message, array $extras = [])
    {
        return array_merge([
            'ok'=>(bool)$ok,'code'=>(string)$code,'action'=>(string)$action,
            'offline_grace_seconds'=>(int)$offlineGrace,'token'=>(string)$token,
            'message'=>(string)$message,'server_time'=>time(),
        ], $extras);
    }
}
