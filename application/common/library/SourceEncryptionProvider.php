<?php

namespace app\common\library;

/**
 * Local compatibility implementation for the legacy encrypted software-source
 * payload consumed by the iOS client branch test/qnq-encrypted-source.
 *
 * Wire contract:
 *   JSON bytes -> DES-CBC -> PKCS#7 padding -> Base64
 *   key = "esign_so"
 *   iv  = "urce_enc"
 *
 * This class is intentionally NOT enabled by default. The existing Nuosike
 * path remains authoritative until compatibility vectors and real-device E2E
 * validation are complete.
 */
class SourceEncryptionProvider
{
    const LEGACY_CIPHER = 'DES-CBC';
    const LEGACY_KEY = 'esign_so';
    const LEGACY_IV = 'urce_enc';

    public static function localAvailable()
    {
        if (!function_exists('openssl_encrypt')) {
            return false;
        }

        $methods = openssl_get_cipher_methods(true);
        if (!is_array($methods)) {
            return false;
        }

        foreach ($methods as $method) {
            if (strcasecmp($method, self::LEGACY_CIPHER) === 0) {
                return true;
            }
        }
        return false;
    }

    public static function encryptJson($json)
    {
        if (!is_string($json)) {
            throw new \InvalidArgumentException('Source JSON must be a string');
        }
        if (!self::localAvailable()) {
            throw new \RuntimeException('DES-CBC is unavailable in the current OpenSSL runtime');
        }

        $raw = openssl_encrypt(
            $json,
            self::LEGACY_CIPHER,
            self::LEGACY_KEY,
            OPENSSL_RAW_DATA,
            self::LEGACY_IV
        );
        if ($raw === false) {
            throw new \RuntimeException('Local source encryption failed');
        }

        return base64_encode($raw);
    }

    public static function encryptEncodedContent($content)
    {
        if (!is_string($content) || $content === '') {
            throw new \InvalidArgumentException('content is required');
        }

        $json = base64_decode($content, true);
        if ($json === false) {
            throw new \InvalidArgumentException('content is not valid Base64');
        }

        return self::encryptJson($json);
    }
}
