<?php

namespace app\common\library;

/**
 * Nuosike wire-compatible software-source encoder.
 *
 * appstore (legacy):
 *   JSON -> RC4(dynamic bkey) -> Base64
 *   bkey is resolved exactly like the client: update.json supplies the RSA
 *   private key, key.json supplies the RSA-encrypted bkey. The resolved bkey
 *   is cached outside the public web root so normal requests do not perform
 *   two remote HTTPS fetches every time.
 *
 * appstore_v2:
 *   JSON -> RC4(random 15-char key)
 *   RC4 key -> RSA PKCS#1 v1.5 using the public key paired with the client key
 *   container = LE32(0xFEEDFACF) + LE32(rsa_len) + rsa + LE32(payload_len) + payload
 *   container -> Nuosike variable-width alphabet codec
 */
class SourceEncryptionProvider
{
    const LEGACY_UPDATE_URL = 'https://api.nuosike.com/update.json';
    const LEGACY_KEY_URL = 'https://api.nuosike.com/key.json';
    const LEGACY_BKEY_CACHE_TTL = 900;
    const LEGACY_BKEY_STALE_TTL = 86400;
    const LEGACY_KEYSTREAM_MIN_BYTES = 262144;
    const LEGACY_KEYSTREAM_MAX_BYTES = 16777216;
    const V2_MAGIC = 0xFEEDFACF;
    const V2_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    const V2_KEY_LENGTH = 15;
    const V2_PUBLIC_KEY_PEM = "-----BEGIN PUBLIC KEY-----\n"
        . "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvgJlkLVTXIrgaUrHYshP\n"
        . "Ue4PjQRAaU/uwYxbTBw0HzYdF5YymH9vnvcFowTybyFHqc5Xe38PBcqZfavp5o0S\n"
        . "47R8YSt1Rg7tuI9oW8KWGp0nXJz7hszcyDqtBKquzur1gaigKywNuHvRNkLvb76A\n"
        . "EPkRKKa7iE7pDeRNNAZpssHO4GMhxXsR0vifXhG6vrFeJUX1Wioky8h7ckPH0Nlo\n"
        . "MgNxxiVEgjNfefmpAYsOBeR3fWAYx7uM4trWr98fdqvXDhG+gNV9oI/9cF4sUDqm\n"
        . "kcW1WSOw1R1asndkDYrdCqCT+xJWeKzLj+TowlP8dnV9KrEoKJ0kBk3zCMkoIjjp\n"
        . "MQIDAQAB\n"
        . "-----END PUBLIC KEY-----\n";

    protected static $lastLegacyKeySource = 'none';

    public static function localAvailable()
    {
        return function_exists('openssl_pkey_get_private')
            && function_exists('openssl_private_decrypt')
            && function_exists('openssl_pkey_get_public')
            && function_exists('openssl_public_encrypt');
    }

    public static function lastLegacyKeySource()
    {
        return self::$lastLegacyKeySource;
    }

    public static function encryptEncodedContent($content, $appType = 'appstore')
    {
        if (!is_string($content) || $content === '') {
            throw new \InvalidArgumentException('content is required');
        }

        $json = base64_decode($content, true);
        if ($json === false) {
            throw new \InvalidArgumentException('content is not valid Base64');
        }

        return self::encryptJson($json, $appType);
    }

    /**
     * Direct local-provider entrypoint. Avoids the historical JSON -> Base64 ->
     * Base64 decode round trip when encryption happens in this process.
     */
    public static function encryptJson($json, $appType = 'appstore')
    {
        if (!is_string($json)) {
            throw new \InvalidArgumentException('Source JSON must be a string');
        }

        return $appType === 'appstore_v2'
            ? self::encryptV2Json($json)
            : self::encryptLegacyJson($json);
    }

    public static function encryptLegacyJson($json, $bkey = null)
    {
        if (!is_string($json)) {
            throw new \InvalidArgumentException('Source JSON must be a string');
        }
        if ($bkey === null) {
            $bkey = self::loadLegacyBKey();
        } else {
            self::$lastLegacyKeySource = 'supplied';
        }
        if (!is_string($bkey) || $bkey === '') {
            throw new \RuntimeException('Legacy bkey is empty');
        }

        return base64_encode(self::rc4LegacyCached($json, $bkey));
    }

    public static function encryptV2Json($json, $keyText = null)
    {
        if (!is_string($json)) {
            throw new \InvalidArgumentException('Source JSON must be a string');
        }

        $keyText = $keyText === null ? self::randomKey(self::V2_KEY_LENGTH) : (string)$keyText;
        if ($keyText === '') {
            throw new \RuntimeException('V2 RC4 key is empty');
        }

        $publicKey = openssl_pkey_get_public(self::V2_PUBLIC_KEY_PEM);
        if ($publicKey === false) {
            throw new \RuntimeException('V2 RSA public key import failed');
        }

        $rsaBlob = '';
        $ok = openssl_public_encrypt($keyText, $rsaBlob, $publicKey, OPENSSL_PKCS1_PADDING);
        if (is_resource($publicKey)) {
            openssl_free_key($publicKey);
        }
        if (!$ok || $rsaBlob === '') {
            throw new \RuntimeException('V2 RSA key encryption failed');
        }

        $header = pack('V', self::V2_MAGIC)
            . pack('V', strlen($rsaBlob))
            . $rsaBlob
            . pack('V', strlen($json));

        // V2 used to build a full RC4 payload and then scan that payload again
        // in encodeV2Parts().  On PHP 7 that means two byte-wise interpreter
        // passes plus a second large string.  Fuse the RC4 PRGA and codec so the
        // encrypted byte is consumed immediately and the wire format stays
        // byte-for-byte identical.
        return self::encodeV2EncryptedJson($header, $json, $keyText);
    }

    public static function encodeV2Codec($raw)
    {
        if (!is_string($raw)) {
            throw new \InvalidArgumentException('V2 container must be bytes');
        }

        return self::encodeV2Parts([$raw]);
    }

    protected static function encodeV2Parts(array $parts)
    {
        $alphabet = self::V2_ALPHABET;
        $buffer = 0;
        $bitCount = 0;
        $output = '';

        foreach ($parts as $raw) {
            if (!is_string($raw)) {
                throw new \InvalidArgumentException('V2 container part must be bytes');
            }

            $length = strlen($raw);
            for ($i = 0; $i < $length; $i++) {
                $buffer |= ord($raw[$i]) << $bitCount;
                $bitCount += 8;

                while ($bitCount >= 5) {
                    $value5 = $buffer & 31;
                    if ($value5 === 30 || $value5 === 31) {
                        $output .= $alphabet[$value5];
                        $buffer >>= 5;
                        $bitCount -= 5;
                        continue;
                    }
                    if ($bitCount < 6) {
                        break;
                    }
                    $value6 = $buffer & 63;
                    $output .= $alphabet[$value6];
                    $buffer >>= 6;
                    $bitCount -= 6;
                }
            }
        }

        if ($bitCount > 0) {
            $value5 = $buffer & 31;
            if ($bitCount >= 5 && ($value5 === 30 || $value5 === 31)) {
                $output .= $alphabet[$value5];
            } else {
                $output .= $alphabet[$buffer & 63];
            }
        }

        return $output;
    }

    /**
     * Stateful V2 fast path: encode the small unencrypted header first, then
     * feed each RC4-produced payload byte directly into the existing variable
     * width codec state.  This removes the full-size intermediate $payload and
     * the second payload traversal without changing any protocol primitive.
     */
    protected static function encodeV2EncryptedJson($header, $json, $keyText)
    {
        if (!is_string($header) || !is_string($json) || !is_string($keyText)) {
            throw new \InvalidArgumentException('V2 fused encoder expects strings');
        }

        $key = self::legacyKeyBytes($keyText);
        $keyLength = count($key);
        if ($keyLength === 0) {
            throw new \RuntimeException('RC4 key is empty');
        }

        $state = range(0, 255);
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $state[$i] + $key[$i % $keyLength]) & 0xff;
            $tmp = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $tmp;
        }

        $alphabet = self::V2_ALPHABET;
        $buffer = 0;
        $bitCount = 0;
        $output = '';

        $headerLength = strlen($header);
        for ($n = 0; $n < $headerLength; $n++) {
            $buffer |= ord($header[$n]) << $bitCount;
            $bitCount += 8;
            while ($bitCount >= 5) {
                $value5 = $buffer & 31;
                if ($value5 === 30 || $value5 === 31) {
                    $output .= $alphabet[$value5];
                    $buffer >>= 5;
                    $bitCount -= 5;
                    continue;
                }
                if ($bitCount < 6) {
                    break;
                }
                $output .= $alphabet[$buffer & 63];
                $buffer >>= 6;
                $bitCount -= 6;
            }
        }

        $i = 0;
        $j = 0;
        $length = strlen($json);
        for ($n = 0; $n < $length; $n++) {
            $i = ($i + 1) & 0xff;
            $j = ($j + $state[$i]) & 0xff;
            $tmp = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $tmp;
            $k = $state[($state[$i] + $state[$j]) & 0xff];

            $buffer |= (ord($json[$n]) ^ $k) << $bitCount;
            $bitCount += 8;
            while ($bitCount >= 5) {
                $value5 = $buffer & 31;
                if ($value5 === 30 || $value5 === 31) {
                    $output .= $alphabet[$value5];
                    $buffer >>= 5;
                    $bitCount -= 5;
                    continue;
                }
                if ($bitCount < 6) {
                    break;
                }
                $output .= $alphabet[$buffer & 63];
                $buffer >>= 6;
                $bitCount -= 6;
            }
        }

        if ($bitCount > 0) {
            $value5 = $buffer & 31;
            if ($bitCount >= 5 && ($value5 === 30 || $value5 === 31)) {
                $output .= $alphabet[$value5];
            } else {
                $output .= $alphabet[$buffer & 63];
            }
        }

        return $output;
    }

    protected static function loadLegacyBKey()
    {
        if (!self::localAvailable() || !function_exists('curl_init')) {
            throw new \RuntimeException('Required curl/OpenSSL functions are unavailable');
        }

        $now = time();
        $cached = self::readLegacyBKeyCache();
        if ($cached && ($now - $cached['fetched_at']) <= self::legacyBKeyCacheTtl()) {
            self::$lastLegacyKeySource = 'cache';
            return $cached['bkey'];
        }

        try {
            $bkey = self::fetchLegacyBKey();
            self::writeLegacyBKeyCache($bkey, $now);
            self::$lastLegacyKeySource = 'remote';
            return $bkey;
        } catch (\Exception $e) {
            if ($cached && ($now - $cached['fetched_at']) <= self::legacyBKeyStaleTtl()) {
                self::$lastLegacyKeySource = 'stale-cache';
                error_log('[SourceEncryptionProvider] legacy bkey refresh failed; using stale cache: ' . $e->getMessage());
                return $cached['bkey'];
            }
            throw $e;
        }
    }

    protected static function fetchLegacyBKey()
    {
        $update = self::getJson(self::LEGACY_UPDATE_URL, 'update.json');
        if (!isset($update['rule']) || !is_string($update['rule'])) {
            throw new \RuntimeException('update.json missing rule');
        }
        $privatePem = base64_decode($update['rule'], true);
        if ($privatePem === false || strpos($privatePem, 'PRIVATE KEY') === false) {
            throw new \RuntimeException('update.json rule is not a private-key PEM');
        }

        $keyJson = self::getJson(self::LEGACY_KEY_URL, 'key.json');
        if (!isset($keyJson['rule']) || !is_string($keyJson['rule'])) {
            throw new \RuntimeException('key.json missing rule');
        }
        $ciphertext = base64_decode($keyJson['rule'], true);
        if ($ciphertext === false || $ciphertext === '') {
            throw new \RuntimeException('key.json rule is not valid Base64');
        }

        $privateKey = openssl_pkey_get_private($privatePem);
        if ($privateKey === false) {
            throw new \RuntimeException('Legacy RSA private key import failed');
        }
        $bkey = '';
        $ok = openssl_private_decrypt($ciphertext, $bkey, $privateKey, OPENSSL_PKCS1_PADDING);
        if (is_resource($privateKey)) {
            openssl_free_key($privateKey);
        }
        if (!$ok || $bkey === '') {
            throw new \RuntimeException('Legacy bkey RSA decrypt failed');
        }
        return $bkey;
    }

    protected static function legacyBKeyCacheTtl()
    {
        $value = getenv('SOURCE_LEGACY_BKEY_TTL');
        $ttl = $value === false || trim((string)$value) === '' ? self::LEGACY_BKEY_CACHE_TTL : (int)$value;
        return $ttl > 0 ? $ttl : self::LEGACY_BKEY_CACHE_TTL;
    }

    protected static function legacyBKeyStaleTtl()
    {
        $value = getenv('SOURCE_LEGACY_BKEY_STALE_TTL');
        $ttl = $value === false || trim((string)$value) === '' ? self::LEGACY_BKEY_STALE_TTL : (int)$value;
        return $ttl > 0 ? $ttl : self::LEGACY_BKEY_STALE_TTL;
    }

    protected static function legacyBKeyCacheFile()
    {
        $custom = getenv('SOURCE_LEGACY_BKEY_CACHE_FILE');
        if ($custom !== false && trim((string)$custom) !== '') {
            return trim((string)$custom);
        }
        $runtime = defined('RUNTIME_PATH') ? RUNTIME_PATH : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR;
        return rtrim($runtime, '/\\') . DIRECTORY_SEPARATOR . 'source_crypto' . DIRECTORY_SEPARATOR . 'legacy_bkey.json';
    }

    protected static function readLegacyBKeyCache()
    {
        $file = self::legacyBKeyCacheFile();
        if (!is_file($file) || !is_readable($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['bkey']) || empty($data['fetched_at'])) {
            return null;
        }
        $bkey = base64_decode((string)$data['bkey'], true);
        if ($bkey === false || $bkey === '') {
            return null;
        }
        return [
            'bkey' => $bkey,
            'fetched_at' => (int)$data['fetched_at'],
        ];
    }

    protected static function writeLegacyBKeyCache($bkey, $fetchedAt)
    {
        $file = self::legacyBKeyCacheFile();
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            error_log('[SourceEncryptionProvider] unable to create legacy bkey cache directory');
            return;
        }
        $payload = json_encode([
            'version' => 1,
            'fetched_at' => (int)$fetchedAt,
            'bkey' => base64_encode((string)$bkey),
        ]);
        $tmp = $file . '.tmp.' . getmypid() . '.' . mt_rand(1000, 9999);
        if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
            return;
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
        }
    }

    protected static function getJson($url, $label)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'User-Agent: zonoe/3.0',
            'Cache-Control: no-cache',
        ]);
        $body = curl_exec($curl);
        $errno = curl_errno($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $errno !== 0 || $status < 200 || $status >= 300) {
            throw new \RuntimeException($label . ' request failed');
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new \RuntimeException($label . ' is not a JSON object');
        }
        return $json;
    }

    /**
     * Legacy RC4 fast path.
     *
     * The protocol reuses the same legacy bkey, therefore the RC4 keystream is
     * deterministic. Large responses cache that keystream outside the public
     * web root and perform the actual XOR with PHP's binary string operator,
     * which runs in C instead of one PHP loop iteration per payload byte.
     */
    protected static function rc4LegacyCached($data, $keyText)
    {
        $length = strlen($data);
        if (!self::legacyKeystreamEnabled()
            || $length < self::LEGACY_KEYSTREAM_MIN_BYTES
            || $length > self::LEGACY_KEYSTREAM_MAX_BYTES) {
            return self::rc4Reference($data, $keyText);
        }

        try {
            $stream = self::legacyKeystream($keyText, $length);
            if (is_string($stream) && strlen($stream) === $length) {
                return $data ^ $stream;
            }
        } catch (\Throwable $e) {
            error_log('[SourceEncryptionProvider] legacy keystream fast path failed: ' . $e->getMessage());
        }

        return self::rc4Reference($data, $keyText);
    }

    protected static function legacyKeystreamEnabled()
    {
        $value = getenv('SOURCE_LEGACY_KEYSTREAM_CACHE');
        if ($value === false || trim((string)$value) === '') {
            return true;
        }
        return !in_array(strtolower(trim((string)$value)), ['0', 'false', 'off', 'no'], true);
    }

    protected static function legacyKeystream($keyText, $length)
    {
        $file = self::legacyKeystreamCacheFile($keyText);
        $stream = self::readKeystreamPrefix($file, $length);
        if (is_string($stream)) {
            return $stream;
        }

        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create legacy keystream cache directory');
        }

        $lockFile = $file . '.lock';
        $lock = @fopen($lockFile, 'c');
        if (!$lock) {
            throw new \RuntimeException('Unable to open legacy keystream cache lock');
        }

        try {
            if (!@flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock legacy keystream cache');
            }

            $stream = self::readKeystreamPrefix($file, $length);
            if (is_string($stream)) {
                @flock($lock, LOCK_UN);
                fclose($lock);
                return $stream;
            }

            $stream = self::rc4Reference(str_repeat("\0", $length), $keyText);
            $tmp = $file . '.tmp.' . getmypid() . '.' . mt_rand(1000, 9999);
            if (@file_put_contents($tmp, $stream, LOCK_EX) !== $length) {
                @unlink($tmp);
                throw new \RuntimeException('Unable to write legacy keystream cache');
            }
            @chmod($tmp, 0600);
            if (!@rename($tmp, $file)) {
                @unlink($tmp);
                throw new \RuntimeException('Unable to publish legacy keystream cache');
            }
            @flock($lock, LOCK_UN);
            fclose($lock);
            return $stream;
        } catch (\Throwable $e) {
            @flock($lock, LOCK_UN);
            fclose($lock);
            throw $e;
        }
    }

    protected static function legacyKeystreamCacheFile($keyText)
    {
        $custom = getenv('SOURCE_LEGACY_KEYSTREAM_CACHE_DIR');
        if ($custom !== false && trim((string)$custom) !== '') {
            $dir = rtrim(trim((string)$custom), '/\\');
        } else {
            $runtime = defined('RUNTIME_PATH') ? RUNTIME_PATH : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR;
            $dir = rtrim($runtime, '/\\') . DIRECTORY_SEPARATOR . 'source_crypto';
        }
        return $dir . DIRECTORY_SEPARATOR . 'legacy_rc4_' . hash('sha256', (string)$keyText) . '.bin';
    }

    protected static function readKeystreamPrefix($file, $length)
    {
        if (!is_file($file) || !is_readable($file) || filesize($file) < $length) {
            return null;
        }
        $stream = @file_get_contents($file, false, null, 0, $length);
        return is_string($stream) && strlen($stream) === $length ? $stream : null;
    }

    /**
     * Reference RC4 implementation kept for protocol regression tests, V2
     * equivalence checks, and as a fail-open fallback for legacy keystreams.
     */
    protected static function rc4Reference($data, $keyText)
    {
        $key = self::legacyKeyBytes($keyText);
        $keyLength = count($key);
        if ($keyLength === 0) {
            throw new \RuntimeException('RC4 key is empty');
        }

        $state = range(0, 255);
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $state[$i] + $key[$i % $keyLength]) & 0xff;
            $tmp = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $tmp;
        }

        $i = 0;
        $j = 0;
        $output = '';
        $length = strlen($data);
        for ($n = 0; $n < $length; $n++) {
            $i = ($i + 1) & 0xff;
            $j = ($j + $state[$i]) & 0xff;
            $tmp = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $tmp;
            $k = $state[($state[$i] + $state[$j]) & 0xff];
            $output .= chr(ord($data[$n]) ^ $k);
        }
        return $output;
    }

    protected static function legacyKeyBytes($keyText)
    {
        if (function_exists('iconv')) {
            $utf16 = @iconv('UTF-8', 'UTF-16LE//IGNORE', $keyText);
            if (is_string($utf16) && $utf16 !== '') {
                $bytes = [];
                for ($i = 0, $length = strlen($utf16); $i + 1 < $length; $i += 2) {
                    $bytes[] = ord($utf16[$i]);
                }
                if ($bytes) {
                    return $bytes;
                }
            }
        }

        $bytes = [];
        for ($i = 0, $length = strlen($keyText); $i < $length; $i++) {
            $bytes[] = ord($keyText[$i]);
        }
        return $bytes;
    }

    protected static function randomKey($length)
    {
        $alphabet = self::V2_ALPHABET;
        $max = strlen($alphabet) - 1;
        $key = '';
        for ($i = 0; $i < $length; $i++) {
            $key .= $alphabet[random_int(0, $max)];
        }
        return $key;
    }
}
