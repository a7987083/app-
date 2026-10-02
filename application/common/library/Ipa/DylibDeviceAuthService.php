<?php

namespace app\common\library\Ipa;

use think\Config;
use think\Db;

class DylibDeviceAuthService
{
    const ALGORITHM = 'ecdsa-p256-sha256';
    const CHALLENGE_TTL = 60;
    const DEVICE_KEY_RETENTION = 31536000; // 365 days

    public static function issueChallenge(array $payload)
    {
        $udid = trim((string)(isset($payload['udid']) ? $payload['udid'] : ''));
        $dylibKey = trim((string)(isset($payload['dylib_key']) ? $payload['dylib_key'] : ''));
        $authProof = trim((string)(isset($payload['auth_proof']) ? $payload['auth_proof'] : ''));
        $publicKey = self::normalizePublicKey(isset($payload['device_public_key']) ? $payload['device_public_key'] : '');
        if ($udid === '' || $dylibKey === '' || $publicKey === '') {
            return ['ok' => false, 'code' => 'bad_request', 'message' => 'Missing device authentication fields'];
        }

        $dylib = Db::name('dylib')->where('dylib_key', $dylibKey)->where('enabled', 1)->find();
        if (!$dylib) {
            return ['ok' => false, 'code' => 'dylib_unknown', 'message' => 'Unknown dylib'];
        }

        $secret = self::serverSecret();
        $udidHash = hash_hmac('sha256', $udid, $secret);
        $publicKeyHash = hash('sha256', $publicKey);
        $bound = Db::name('dylib_device_key')
            ->where('udid_hash', $udidHash)
            ->where('dylib_id', (int)$dylib['id'])
            ->where('public_key_hash', $publicKeyHash)
            ->where('status', 'active')
            ->find();

        // Each UDID + Dylib may enroll multiple independent device keys.
        // A key that is not enrolled yet must prove that the UDID is currently
        // authorized. Existing keys continue through the normal challenge flow.
        if (!$bound) {
            if ($authProof === '') {
                return ['ok' => false, 'code' => 'auth_proof_missing', 'message' => 'First device enrollment requires active UDID auth proof'];
            }
            $proof = DylibAuthProofService::validate($authProof, $udid);
            if (empty($proof['ok'])) {
                return $proof;
            }
            if (!self::hasActiveAuthorization($udid)) {
                return ['ok' => false, 'code' => 'authorization_inactive', 'message' => 'UDID authorization is not active'];
            }
        } elseif ($authProof !== '') {
            $proof = DylibAuthProofService::validate($authProof, $udid);
            if (empty($proof['ok'])) {
                return $proof;
            }
        }

        $now = time();
        $challengeId = bin2hex(random_bytes(16));
        $challenge = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challengeHash = $authProof !== ''
            ? hash('sha256', $challenge . "\n" . $authProof)
            : hash('sha256', $challenge);

        Db::name('dylib_auth_challenge')->insert([
            'challenge_id' => $challengeId,
            'challenge_hash' => $challengeHash,
            'udid_hash' => $udidHash,
            'dylib_id' => (int)$dylib['id'],
            'public_key_hash' => $publicKeyHash,
            'expires_at' => $now + self::CHALLENGE_TTL,
            'consumed_at' => 0,
            'created_at' => $now,
        ]);

        return [
            'ok' => true,
            'code' => 'challenge_issued',
            'challenge_id' => $challengeId,
            'challenge' => $challenge,
            'expires_at' => $now + self::CHALLENGE_TTL,
            'server_time' => $now,
            'enrollment_required' => !$bound,
            'auth_proof_bound' => $authProof !== '',
            'algorithm' => self::ALGORITHM,
        ];
    }

    public static function authenticate(array $payload)
    {
        $udid = trim((string)(isset($payload['udid']) ? $payload['udid'] : ''));
        $dylibKey = trim((string)(isset($payload['dylib_key']) ? $payload['dylib_key'] : ''));
        $authProof = trim((string)(isset($payload['auth_proof']) ? $payload['auth_proof'] : ''));
        $challengeId = strtolower(trim((string)(isset($payload['challenge_id']) ? $payload['challenge_id'] : '')));
        $challenge = trim((string)(isset($payload['challenge']) ? $payload['challenge'] : ''));
        $signature = base64_decode((string)(isset($payload['device_signature']) ? $payload['device_signature'] : ''), true);
        $publicKey = self::normalizePublicKey(isset($payload['device_public_key']) ? $payload['device_public_key'] : '');

        if ($udid === '' || $dylibKey === '' || !preg_match('/^[a-f0-9]{32}$/', $challengeId) || $challenge === '' || $signature === false || $publicKey === '') {
            return ['ok' => false, 'code' => 'device_auth_invalid', 'message' => 'Invalid device authentication payload'];
        }

        $dylib = Db::name('dylib')->where('dylib_key', $dylibKey)->where('enabled', 1)->find();
        if (!$dylib) {
            return ['ok' => false, 'code' => 'dylib_unknown', 'message' => 'Unknown dylib'];
        }

        $secret = self::serverSecret();
        $udidHash = hash_hmac('sha256', $udid, $secret);
        $publicKeyHash = hash('sha256', $publicKey);
        $row = Db::name('dylib_auth_challenge')->where('challenge_id', $challengeId)->find();
        $bound = Db::name('dylib_device_key')
            ->where('udid_hash', $udidHash)
            ->where('dylib_id', (int)$dylib['id'])
            ->where('public_key_hash', $publicKeyHash)
            ->where('status', 'active')
            ->find();

        if (!$row || (int)$row['consumed_at'] !== 0) {
            return ['ok' => false, 'code' => 'challenge_replayed', 'message' => 'Challenge already used or unknown'];
        }
        if ((int)$row['expires_at'] < time()) {
            return ['ok' => false, 'code' => 'challenge_expired', 'message' => 'Challenge expired'];
        }

        if (!$bound && $authProof === '') {
            return ['ok' => false, 'code' => 'auth_proof_missing', 'message' => 'First device enrollment requires active UDID auth proof'];
        }
        if ($authProof !== '') {
            $proof = DylibAuthProofService::validate($authProof, $udid);
            if (empty($proof['ok'])) {
                return $proof;
            }
            if (!self::hasActiveAuthorization($udid)) {
                return ['ok' => false, 'code' => 'authorization_inactive', 'message' => 'UDID authorization is not active'];
            }
        }

        $expectedChallengeHash = $authProof !== ''
            ? hash('sha256', $challenge . "\n" . $authProof)
            : hash('sha256', $challenge);
        if (!hash_equals((string)$row['udid_hash'], $udidHash)
            || (int)$row['dylib_id'] !== (int)$dylib['id']
            || !hash_equals((string)$row['public_key_hash'], $publicKeyHash)
            || !hash_equals((string)$row['challenge_hash'], $expectedChallengeHash)) {
            return ['ok' => false, 'code' => 'challenge_mismatch', 'message' => 'Challenge context mismatch'];
        }

        $canonical = self::canonicalProof($payload, $challengeId, $challenge);
        $keyResource = @openssl_pkey_get_public($publicKey);
        if ($keyResource === false || openssl_verify($canonical, $signature, $keyResource, OPENSSL_ALGO_SHA256) !== 1) {
            return ['ok' => false, 'code' => 'device_signature_invalid', 'message' => 'Device signature verification failed'];
        }

        if (!$bound) {
            // First enrollment is authorized by the active-UDID auth_proof that
            // was bound to this challenge. No card/license code is accepted here.
            self::cleanupStaleKeys(time());
            $keyId = substr(hash('sha256', $udidHash . "\n" . (int)$dylib['id'] . "\n" . $publicKeyHash), 0, 32);
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
            $bound = Db::name('dylib_device_key')
                ->where('udid_hash', $udidHash)
                ->where('dylib_id', (int)$dylib['id'])
                ->where('public_key_hash', $publicKeyHash)
                ->where('status', 'active')
                ->find();
        }

        $consumed = Db::name('dylib_auth_challenge')
            ->where('id', (int)$row['id'])
            ->where('consumed_at', 0)
            ->update(['consumed_at' => time()]);
        if ((int)$consumed !== 1) {
            return ['ok' => false, 'code' => 'challenge_replayed', 'message' => 'Challenge already consumed'];
        }

        Db::name('dylib_device_key')->where('id', (int)$bound['id'])->update([
            'last_used_at' => time(),
            'updated_at' => time(),
        ]);

        return [
            'ok' => true,
            'code' => 'device_authenticated',
            'dylib' => $dylib,
            'key_id' => (string)$bound['key_id'],
        ];
    }

    public static function canonicalProof(array $payload, $challengeId, $challenge)
    {
        $fields = [
            'zonoe-dylib-auth-v3',
            (string)$challengeId,
            (string)$challenge,
        ];
        $authProof = trim((string)(isset($payload['auth_proof']) ? $payload['auth_proof'] : ''));
        if ($authProof !== '') {
            $fields[] = $authProof;
        }
        $fields[] = trim((string)(isset($payload['udid']) ? $payload['udid'] : ''));
        $fields[] = trim((string)(isset($payload['bundle_id']) ? $payload['bundle_id'] : ''));
        $fields[] = trim((string)(isset($payload['dylib_key']) ? $payload['dylib_key'] : ''));
        $fields[] = trim((string)(isset($payload['dylib_version']) ? $payload['dylib_version'] : ''));
        $fields[] = trim((string)(isset($payload['dylib_build']) ? $payload['dylib_build'] : ''));
        $fields[] = strtolower(trim((string)(isset($payload['dylib_sha256']) ? $payload['dylib_sha256'] : '')));
        $fields[] = trim((string)(isset($payload['app_executable']) ? $payload['app_executable'] : ''));
        $fields[] = strtoupper(trim((string)(isset($payload['app_macho_uuid']) ? $payload['app_macho_uuid'] : '')));
        $fields[] = trim((string)(isset($payload['app_version']) ? $payload['app_version'] : ''));
        $fields[] = trim((string)(isset($payload['app_build']) ? $payload['app_build'] : ''));
        return implode("\n", $fields);
    }

    protected static function cleanupStaleKeys($now)
    {
        $cutoff = max(0, (int)$now - self::DEVICE_KEY_RETENTION);
        Db::name('dylib_device_key')
            ->where('last_used_at', '>', 0)
            ->where('last_used_at', '<', $cutoff)
            ->delete();
    }

    protected static function hasActiveAuthorization($udid)
    {
        return (bool)Db::name('kami')
            ->where('udid', trim((string)$udid))
            ->where('jh', 1)
            ->where('endtime', '>', time())
            ->find();
    }

    protected static function normalizePublicKey($pem)
    {
        $pem = trim((string)$pem);
        if ($pem === '' || strlen($pem) > 4096 || strpos($pem, 'BEGIN PUBLIC KEY') === false) {
            return '';
        }
        $key = @openssl_pkey_get_public($pem);
        if ($key === false) {
            return '';
        }
        $details = openssl_pkey_get_details($key);
        return is_array($details) && !empty($details['key']) ? trim((string)$details['key']) . "\n" : '';
    }

    protected static function serverSecret()
    {
        $secret = (string)Config::get('ipa_data_center.server_secret');
        if (strlen($secret) < 32) {
            throw new \RuntimeException('IPA server secret must contain at least 32 bytes');
        }
        return $secret;
    }
}
