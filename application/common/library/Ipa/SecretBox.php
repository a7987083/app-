<?php

namespace app\common\library\Ipa;

use think\Config;

/**
 * Encrypt small configuration secrets at rest.
 * PHP 7 compatible: AES-256-CBC + HMAC-SHA256 (encrypt-then-MAC).
 */
class SecretBox
{
    public static function assertConfigured()
    {
        self::masterSecret();
        return true;
    }

    public static function encrypt($plaintext)
    {
        $secret = self::masterSecret();
        $encKey = hash_hmac('sha256', 'ipa-enc', $secret, true);
        $macKey = hash_hmac('sha256', 'ipa-mac', $secret, true);
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt((string)$plaintext, 'AES-256-CBC', $encKey, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new \RuntimeException('Unable to encrypt IPA secret');
        }
        $payload = "v1" . $iv . $ciphertext;
        $mac = hash_hmac('sha256', $payload, $macKey, true);
        return base64_encode($payload . $mac);
    }

    public static function decrypt($encoded)
    {
        if ((string)$encoded === '') {
            return '';
        }
        $raw = base64_decode((string)$encoded, true);
        if ($raw === false || strlen($raw) < 2 + 16 + 32 || substr($raw, 0, 2) !== 'v1') {
            throw new \RuntimeException('Invalid IPA secret payload');
        }
        $secret = self::masterSecret();
        $encKey = hash_hmac('sha256', 'ipa-enc', $secret, true);
        $macKey = hash_hmac('sha256', 'ipa-mac', $secret, true);
        $mac = substr($raw, -32);
        $payload = substr($raw, 0, -32);
        $expected = hash_hmac('sha256', $payload, $macKey, true);
        if (!hash_equals($expected, $mac)) {
            throw new \RuntimeException('IPA secret integrity check failed');
        }
        $iv = substr($payload, 2, 16);
        $ciphertext = substr($payload, 18);
        $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $encKey, OPENSSL_RAW_DATA, $iv);
        if ($plaintext === false) {
            throw new \RuntimeException('Unable to decrypt IPA secret');
        }
        return $plaintext;
    }

    protected static function masterSecret()
    {
        $secret = (string)Config::get('ipa_data_center.server_secret');
        if (strlen($secret) < 32) {
            throw new \RuntimeException('IPA server secret must contain at least 32 bytes');
        }
        return $secret;
    }
}
