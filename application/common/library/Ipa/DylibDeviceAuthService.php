<?php

namespace app\common\library\Ipa;

use app\common\library\AuthorizationLicense;
use think\Config;
use think\Db;

class DylibDeviceAuthService
{
    const ALGORITHM = 'ecdsa-p256-sha256';
    const CHALLENGE_TTL = 60;

    public static function issueChallenge(array $payload)
    {
        $udid = trim((string)(isset($payload['udid']) ? $payload['udid'] : ''));
        $dylibKey = trim((string)(isset($payload['dylib_key']) ? $payload['dylib_key'] : ''));
        $publicKey = self::normalizePublicKey(isset($payload['device_public_key']) ? $payload['device_public_key'] : '');
        if ($udid === '' || $dylibKey === '' || $publicKey === '') {
            return ['ok' => false, 'code' => 'bad_request', 'message' => 'Missing device authentication fields'];
        }
        $dylib = Db::name('dylib')->where('dylib_key', $dylibKey)->where('enabled', 1)->find();
        if (!$dylib) return ['ok' => false, 'code' => 'dylib_unknown', 'message' => 'Unknown dylib'];

        $now = time();
        $challengeId = bin2hex(random_bytes(16));
        $challenge = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $secret = self::serverSecret();
        $udidHash = hash_hmac('sha256', $udid, $secret);
        $publicKeyHash = hash('sha256', $publicKey);
        Db::name('dylib_auth_challenge')->insert([
            'challenge_id' => $challengeId,
            'challenge_hash' => hash('sha256', $challenge),
            'udid_hash' => $udidHash,
            'dylib_id' => (int)$dylib['id'],
            'public_key_hash' => $publicKeyHash,
            'expires_at' => $now + self::CHALLENGE_TTL,
            'consumed_at' => 0,
            'created_at' => $now,
        ]);
        $bound = Db::name('dylib_device_key')->where('udid_hash', $udidHash)->where('dylib_id', (int)$dylib['id'])->where('status', 'active')->find();
        return [
            'ok' => true,
            'code' => 'challenge_issued',
            'challenge_id' => $challengeId,
            'challenge' => $challenge,
            'expires_at' => $now + self::CHALLENGE_TTL,
            'server_time' => $now,
            'enrollment_required' => !$bound,
            'algorithm' => self::ALGORITHM,
        ];
    }

    public static function authenticate(array $payload)
    {
        $udid = trim((string)(isset($payload['udid']) ? $payload['udid'] : ''));
        $dylibKey = trim((string)(isset($payload['dylib_key']) ? $payload['dylib_key'] : ''));
        $challengeId = strtolower(trim((string)(isset($payload['challenge_id']) ? $payload['challenge_id'] : '')));
        $challenge = trim((string)(isset($payload['challenge']) ? $payload['challenge'] : ''));
        $signature = base64_decode((string)(isset($payload['device_signature']) ? $payload['device_signature'] : ''), true);
        $publicKey = self::normalizePublicKey(isset($payload['device_public_key']) ? $payload['device_public_key'] : '');
        if ($udid === '' || $dylibKey === '' || !preg_match('/^[a-f0-9]{32}$/', $challengeId) || $challenge === '' || $signature === false || $publicKey === '') {
            return ['ok' => false, 'code' => 'device_auth_invalid', 'message' => 'Invalid device authentication payload'];
        }
        $dylib = Db::name('dylib')->where('dylib_key', $dylibKey)->where('enabled', 1)->find();
        if (!$dylib) return ['ok' => false, 'code' => 'dylib_unknown', 'message' => 'Unknown dylib'];

        $secret = self::serverSecret();
        $udidHash = hash_hmac('sha256', $udid, $secret);
        $publicKeyHash = hash('sha256', $publicKey);
        $row = Db::name('dylib_auth_challenge')->where('challenge_id', $challengeId)->find();
        if (!$row || (int)$row['consumed_at'] !== 0) return ['ok' => false, 'code' => 'challenge_replayed', 'message' => 'Challenge already used or unknown'];
        if ((int)$row['expires_at'] < time()) return ['ok' => false, 'code' => 'challenge_expired', 'message' => 'Challenge expired'];
        if (!hash_equals((string)$row['udid_hash'], $udidHash) || (int)$row['dylib_id'] !== (int)$dylib['id'] || !hash_equals((string)$row['public_key_hash'], $publicKeyHash) || !hash_equals((string)$row['challenge_hash'], hash('sha256', $challenge))) {
            return ['ok' => false, 'code' => 'challenge_mismatch', 'message' => 'Challenge context mismatch'];
        }
        $canonical = self::canonicalProof($payload, $challengeId, $challenge);
        $keyResource = @openssl_pkey_get_public($publicKey);
        if ($keyResource === false || openssl_verify($canonical, $signature, $keyResource, OPENSSL_ALGO_SHA256) !== 1) {
            return ['ok' => false, 'code' => 'device_signature_invalid', 'message' => 'Device signature verification failed'];
        }

        $bound = Db::name('dylib_device_key')->where('udid_hash', $udidHash)->where('dylib_id', (int)$dylib['id'])->where('status', 'active')->find();
        if ($bound && !hash_equals((string)$bound['public_key_hash'], $publicKeyHash)) {
            return ['ok' => false, 'code' => 'device_key_mismatch', 'message' => 'Device key does not match enrolled key'];
        }
        if (!$bound) {
            $license = AuthorizationLicense::query(isset($payload['license_code']) ? $payload['license_code'] : '', $udid);
            if (empty($license['ok']) || empty($license['active'])) {
                return ['ok' => false, 'code' => 'device_enrollment_denied', 'message' => isset($license['message']) ? (string)$license['message'] : 'Valid card required'];
            }
            $keyId = substr($publicKeyHash, 0, 32);
            Db::name('dylib_device_key')->insert([
                'udid_hash' => $udidHash,
                'dylib_id' => (int)$dylib['id'],
                'key_id' => $keyId,
                'algorithm' => self::ALGORITHM,
                'public_key_pem' => $publicKey,
                'public_key_hash' => $publicKeyHash,
                'status' => 'active',
                'enrolled_at' => time(),
                'last_used_at' => time(),
                'revoked_at' => 0,
                'created_at' => time(),
                'updated_at' => time(),
            ]);
            $bound = Db::name('dylib_device_key')->where('key_id', $keyId)->find();
        }

        $consumed = Db::name('dylib_auth_challenge')->where('id', (int)$row['id'])->where('consumed_at', 0)->update(['consumed_at' => time()]);
        if ((int)$consumed !== 1) return ['ok' => false, 'code' => 'challenge_replayed', 'message' => 'Challenge already consumed'];
        Db::name('dylib_device_key')->where('id', (int)$bound['id'])->update(['last_used_at' => time(), 'updated_at' => time()]);
        return ['ok' => true, 'code' => 'device_authenticated', 'dylib' => $dylib, 'key_id' => (string)$bound['key_id']];
    }

    public static function canonicalProof(array $payload, $challengeId, $challenge)
    {
        return implode("\n", [
            'zonoe-dylib-auth-v3', (string)$challengeId, (string)$challenge,
            trim((string)(isset($payload['udid']) ? $payload['udid'] : '')),
            trim((string)(isset($payload['bundle_id']) ? $payload['bundle_id'] : '')),
            trim((string)(isset($payload['dylib_key']) ? $payload['dylib_key'] : '')),
            trim((string)(isset($payload['dylib_version']) ? $payload['dylib_version'] : '')),
            trim((string)(isset($payload['dylib_build']) ? $payload['dylib_build'] : '')),
            strtolower(trim((string)(isset($payload['dylib_sha256']) ? $payload['dylib_sha256'] : ''))),
            trim((string)(isset($payload['app_executable']) ? $payload['app_executable'] : '')),
            strtoupper(trim((string)(isset($payload['app_macho_uuid']) ? $payload['app_macho_uuid'] : ''))),
            trim((string)(isset($payload['app_version']) ? $payload['app_version'] : '')),
            trim((string)(isset($payload['app_build']) ? $payload['app_build'] : '')),
        ]);
    }

    protected static function normalizePublicKey($pem)
    {
        $pem = trim((string)$pem);
        if ($pem === '' || strlen($pem) > 4096 || strpos($pem, 'BEGIN PUBLIC KEY') === false) return '';
        $key = @openssl_pkey_get_public($pem);
        if ($key === false) return '';
        $details = openssl_pkey_get_details($key);
        return is_array($details) && !empty($details['key']) ? trim((string)$details['key']) . "\n" : '';
    }

    protected static function serverSecret()
    {
        $secret = (string)Config::get('ipa_data_center.server_secret');
        if (strlen($secret) < 32) throw new \RuntimeException('IPA server secret must contain at least 32 bytes');
        return $secret;
    }
}
