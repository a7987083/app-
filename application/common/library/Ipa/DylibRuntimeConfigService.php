<?php

namespace app\common\library\Ipa;

use think\Db;

class DylibRuntimeConfigService
{
    public static function bootstrap($dylibKey)
    {
        $dylibKey = trim((string)$dylibKey);
        if ($dylibKey === '') {
            throw new \InvalidArgumentException('dylib_key required');
        }

        $dylib = Db::name('dylib')->where('dylib_key', $dylibKey)->where('enabled', 1)->find();
        if (!$dylib) {
            throw new \RuntimeException('Unknown dylib');
        }

        $config = DylibRuntimeAccessService::runtimeConfig();
        $apiEndpoints = self::urlList(isset($config['api_endpoints_json']) ? $config['api_endpoints_json'] : '[]');
        $bootstrapUrls = self::urlList(isset($config['bootstrap_urls_json']) ? $config['bootstrap_urls_json'] : '[]');
        $verifyPath = trim((string)(isset($config['verify_path']) ? $config['verify_path'] : '/index/dylib_verify/verify'));
        if ($verifyPath === '' || $verifyPath[0] !== '/') {
            $verifyPath = '/index/dylib_verify/verify';
        }

        $version = max(3, (int)(isset($config['config_version']) ? $config['config_version'] : 3));
        $expiresAt = time() + 86400;
        $canonical = self::canonical($version, $apiEndpoints, $bootstrapUrls, $verifyPath, $expiresAt);

        return [
            'ok' => true,
            'config_version' => $version,
            'api_endpoints' => $apiEndpoints,
            'bootstrap_urls' => $bootstrapUrls,
            'verify_path' => $verifyPath,
            'expires_at' => $expiresAt,
            'signature_alg' => DylibSigningKey::algorithm(),
            'key_id' => DylibSigningKey::keyId(),
            'signature' => DylibSigningKey::sign($canonical),
        ];
    }

    public static function canonical($version, array $apiEndpoints, array $bootstrapUrls, $verifyPath, $expiresAt)
    {
        return implode("\n", [
            (string)(int)$version,
            implode(',', array_values($apiEndpoints)),
            implode(',', array_values($bootstrapUrls)),
            (string)$verifyPath,
            (string)(int)$expiresAt,
        ]);
    }

    protected static function urlList($json)
    {
        $decoded = json_decode((string)$json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $url) {
            $url = rtrim(trim((string)$url), '/');
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            if (stripos($url, 'https://') !== 0 && stripos($url, 'http://') !== 0) {
                continue;
            }
            $out[$url] = true;
        }
        return array_keys($out);
    }
}
