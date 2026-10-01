<?php

namespace app\common\library\Ipa;

/**
 * Server-only RSA-2048 signing key for Dylib runtime configuration.
 *
 * The private key is deliberately kept out of the database and repository.
 * It is generated once under runtime/ with mode 0600 and reused thereafter.
 */
class DylibSigningKey
{
    const ALGORITHM = 'rsa-2048-sha256';

    public static function ensure()
    {
        $paths = self::paths();
        self::ensureDirectory(dirname($paths['private']));

        $privateKey = self::readFile($paths['private']);
        if ($privateKey === '') {
            self::generate($paths['private'], $paths['public']);
            $privateKey = self::readFile($paths['private']);
        }
        if ($privateKey === '') {
            throw new \RuntimeException('Dylib signing private key unavailable');
        }

        $resource = @openssl_pkey_get_private($privateKey);
        if ($resource === false) {
            throw new \RuntimeException('Dylib signing private key is invalid');
        }
        $details = openssl_pkey_get_details($resource);
        if (!is_array($details) || empty($details['key'])) {
            throw new \RuntimeException('Unable to derive Dylib signing public key');
        }
        $publicKey = trim((string)$details['key']) . "\n";
        $storedPublic = self::readFile($paths['public']);
        if ($storedPublic !== $publicKey) {
            self::atomicWrite($paths['public'], $publicKey, 0644);
        }

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
            'key_id' => substr(hash('sha256', $publicKey), 0, 32),
            'algorithm' => self::ALGORITHM,
        ];
    }

    public static function publicKeyPem()
    {
        $key = self::ensure();
        return $key['public_key'];
    }

    public static function keyId()
    {
        $key = self::ensure();
        return $key['key_id'];
    }

    public static function algorithm()
    {
        return self::ALGORITHM;
    }

    public static function sign($data)
    {
        $key = self::ensure();
        $signature = '';
        if (!openssl_sign((string)$data, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Unable to sign Dylib runtime configuration');
        }
        return base64_encode($signature);
    }

    protected static function generate($privatePath, $publicPath)
    {
        $lockPath = $privatePath . '.lock';
        $lock = @fopen($lockPath, 'c');
        if (!$lock) {
            throw new \RuntimeException('Unable to lock Dylib signing key path');
        }
        try {
            if (!@flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock Dylib signing key');
            }
            if (self::readFile($privatePath) !== '') {
                return;
            }
            $resource = openssl_pkey_new([
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'private_key_bits' => 2048,
            ]);
            if ($resource === false) {
                throw new \RuntimeException('Unable to generate Dylib signing key');
            }
            $privateKey = '';
            if (!openssl_pkey_export($resource, $privateKey)) {
                throw new \RuntimeException('Unable to export Dylib signing private key');
            }
            $details = openssl_pkey_get_details($resource);
            if (!is_array($details) || empty($details['key'])) {
                throw new \RuntimeException('Unable to export Dylib signing public key');
            }
            self::atomicWrite($privatePath, $privateKey, 0600);
            self::atomicWrite($publicPath, trim((string)$details['key']) . "\n", 0644);
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    protected static function paths()
    {
        if (defined('RUNTIME_PATH')) {
            $base = rtrim((string)RUNTIME_PATH, '/\\');
        } elseif (defined('ROOT_PATH')) {
            $base = rtrim((string)ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . 'runtime';
        } else {
            $base = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'zonoe-runtime';
        }
        $dir = $base . DIRECTORY_SEPARATOR . 'dylib_auth';
        return [
            'private' => $dir . DIRECTORY_SEPARATOR . 'server_signing_private.pem',
            'public' => $dir . DIRECTORY_SEPARATOR . 'server_signing_public.pem',
        ];
    }

    protected static function ensureDirectory($dir)
    {
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
            throw new \RuntimeException('Unable to create Dylib signing key directory');
        }
    }

    protected static function readFile($path)
    {
        $value = @file_get_contents($path);
        return $value === false ? '' : (string)$value;
    }

    protected static function atomicWrite($path, $content, $mode)
    {
        $tmp = $path . '.tmp.' . getmypid() . '.' . substr(md5(uniqid('', true)), 0, 8);
        if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write Dylib signing key file');
        }
        @chmod($tmp, $mode);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Unable to install Dylib signing key file');
        }
        @chmod($path, $mode);
    }
}
