<?php

namespace app\common\library;

/**
 * Nuosike wire-compatible software-source encoder.
 *
 * appstore (legacy):
 *   JSON -> RC4(dynamic bkey) -> Base64
 *   bkey is resolved exactly like the client: update.json supplies the RSA
 *   private key, key.json supplies the RSA-encrypted bkey.
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

    public static function localAvailable()
    {
        return function_exists('openssl_pkey_get_private')
            && function_exists('openssl_private_decrypt')
            && function_exists('openssl_pkey_get_public')
            && function_exists('openssl_public_encrypt');
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
        }
        if (!is_string($bkey) || $bkey === '') {
            throw new \RuntimeException('Legacy bkey is empty');
        }

        return base64_encode(self::rc4($json, $bkey));
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

        $payload = self::rc4($json, $keyText);
        $container = pack('V', self::V2_MAGIC)
            . pack('V', strlen($rsaBlob))
            . $rsaBlob
            . pack('V', strlen($payload))
            . $payload;

        return self::encodeV2Codec($container);
    }

    public static function encodeV2Codec($raw)
    {
        if (!is_string($raw)) {
            throw new \InvalidArgumentException('V2 container must be bytes');
        }

        $alphabet = self::V2_ALPHABET;
        $buffer = 0;
        $bitCount = 0;
        $output = '';
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

    protected static function rc4($data, $keyText)
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
