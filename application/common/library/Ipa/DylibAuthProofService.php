<?php

namespace app\common\library\Ipa;

use app\common\library\SourceConfigRepository;

/**
 * Short-lived server proof used to bridge the legacy /apiface active-UDID
 * decision into Protocol v3 device-key enrollment without sending card codes.
 */
class DylibAuthProofService
{
    const VERSION = 1;
    const TTL = 120;

    public static function issue($udid, $authorizationExpiresAt)
    {
        $udid = trim((string)$udid);
        $now = time();
        $authorizationExpiresAt = (int)$authorizationExpiresAt;
        if ($udid === '' || $authorizationExpiresAt <= $now) {
            throw new \InvalidArgumentException('Active UDID authorization required');
        }

        $secret = self::secret();
        $expiresAt = min($authorizationExpiresAt, $now + self::TTL);
        $payload = [
            'v' => self::VERSION,
            'uh' => hash_hmac('sha256', $udid, $secret),
            'iat' => $now,
            'exp' => $expiresAt,
            'n' => bin2hex(random_bytes(12)),
            'aud' => 'dylib-device-auth',
        ];
        $body = self::base64url(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = self::base64url(hash_hmac('sha256', 'zonoe-auth-proof-v1\n' . $body, $secret, true));
        return [
            'proof' => $body . '.' . $signature,
            'expires_at' => $expiresAt,
        ];
    }

    public static function validate($proof, $udid)
    {
        $proof = trim((string)$proof);
        $udid = trim((string)$udid);
        if ($proof === '' || $udid === '' || strlen($proof) > 2048) {
            return ['ok' => false, 'code' => 'auth_proof_missing', 'message' => 'Active UDID auth proof required'];
        }

        $parts = explode('.', $proof);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return ['ok' => false, 'code' => 'auth_proof_invalid', 'message' => 'Invalid auth proof'];
        }

        $secret = self::secret();
        $expected = self::base64url(hash_hmac('sha256', 'zonoe-auth-proof-v1\n' . $parts[0], $secret, true));
        if (!hash_equals($expected, $parts[1])) {
            return ['ok' => false, 'code' => 'auth_proof_invalid', 'message' => 'Invalid auth proof signature'];
        }

        $decoded = self::base64urlDecode($parts[0]);
        $payload = $decoded === false ? null : json_decode($decoded, true);
        if (!is_array($payload)
            || (int)(isset($payload['v']) ? $payload['v'] : 0) !== self::VERSION
            || (string)(isset($payload['aud']) ? $payload['aud'] : '') !== 'dylib-device-auth') {
            return ['ok' => false, 'code' => 'auth_proof_invalid', 'message' => 'Invalid auth proof payload'];
        }

        $now = time();
        $issuedAt = (int)(isset($payload['iat']) ? $payload['iat'] : 0);
        $expiresAt = (int)(isset($payload['exp']) ? $payload['exp'] : 0);
        if ($issuedAt <= 0 || $issuedAt > $now + 30 || $expiresAt < $now || $expiresAt - $issuedAt > self::TTL) {
            return ['ok' => false, 'code' => 'auth_proof_expired', 'message' => 'Auth proof expired'];
        }

        $udidHash = hash_hmac('sha256', $udid, $secret);
        if (empty($payload['uh']) || !hash_equals((string)$payload['uh'], $udidHash)) {
            return ['ok' => false, 'code' => 'auth_proof_mismatch', 'message' => 'Auth proof does not match UDID'];
        }

        return [
            'ok' => true,
            'code' => 'auth_proof_valid',
            'expires_at' => $expiresAt,
        ];
    }

    protected static function secret()
    {
        $secret = (string)SourceConfigRepository::get('unlock_sign_key', '', false);
        if (strlen($secret) < 32) {
            throw new \RuntimeException('unlock_sign_key must contain at least 32 bytes');
        }
        return $secret;
    }

    protected static function base64url($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected static function base64urlDecode($value)
    {
        $value = strtr((string)$value, '-_', '+/');
        $padding = strlen($value) % 4;
        if ($padding) {
            $value .= str_repeat('=', 4 - $padding);
        }
        return base64_decode($value, true);
    }
}
