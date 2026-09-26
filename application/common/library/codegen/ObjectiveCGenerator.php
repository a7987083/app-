<?php

namespace app\common\library\codegen;

class ObjectiveCGenerator
{
    const GENERATOR_VERSION = '2.1.0';

    public function buildConfig(array $dylib, array $runtimeConfig, array $version, array $draft = [], $domain = '')
    {
        $prefix = isset($draft['class_prefix']) ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$draft['class_prefix'])) : 'ZON';
        $prefix = substr($prefix, 0, 8);
        if (!preg_match('/^[A-Z][A-Z0-9]{1,7}$/', $prefix)) $prefix = 'ZON';

        $deploymentTarget = isset($draft['deployment_target']) ? trim((string)$draft['deployment_target']) : '13.0';
        if (!preg_match('/^(1[3-9]|2[0-6])(?:\.\d+)?$/', $deploymentTarget)) $deploymentTarget = '13.0';
        $timeout = isset($draft['timeout']) ? (int)$draft['timeout'] : 10;
        $timeout = max(3, min(120, $timeout));

        $apiEndpoints = $this->decodeUrlList(isset($runtimeConfig['api_endpoints_json']) ? $runtimeConfig['api_endpoints_json'] : '[]');
        $bootstrapUrls = $this->decodeUrlList(isset($runtimeConfig['bootstrap_urls_json']) ? $runtimeConfig['bootstrap_urls_json'] : '[]');
        if (!$apiEndpoints && $domain !== '') $apiEndpoints[] = rtrim((string)$domain, '/');

        $verifyPath = isset($runtimeConfig['verify_path']) ? trim((string)$runtimeConfig['verify_path']) : '/index/dylib_verify/verify';
        if ($verifyPath === '' || $verifyPath[0] !== '/') $verifyPath = '/' . ltrim($verifyPath, '/');
        $endpointURL = $apiEndpoints ? rtrim((string)$apiEndpoints[0], '/') . $verifyPath : '';
        $legacyDylibURLs = $this->appendPath($apiEndpoints, '/index/index/dylib');
        $legacyApiFaceURLs = $this->appendPath($apiEndpoints, '/index/index/apiface');

        return [
            'generator_version' => self::GENERATOR_VERSION,
            'class_prefix' => $prefix,
            'deployment_target' => $deploymentTarget,
            'timeout' => $timeout,
            'dylib_id' => isset($dylib['id']) ? (int)$dylib['id'] : 0,
            'dylib_name' => isset($dylib['name']) ? (string)$dylib['name'] : '',
            'dylib_key' => isset($dylib['dylib_key']) ? (string)$dylib['dylib_key'] : '',
            'dylib_enabled' => !empty($dylib['enabled']),
            'verify_secret' => isset($dylib['verify_secret']) ? (string)$dylib['verify_secret'] : '',
            'default_offline_grace' => isset($dylib['default_offline_grace']) ? (int)$dylib['default_offline_grace'] : 900,
            'default_fail_action' => isset($dylib['default_fail_action']) ? (string)$dylib['default_fail_action'] : 'disable_feature',
            'runtime_config_version' => isset($runtimeConfig['config_version']) ? (int)$runtimeConfig['config_version'] : 1,
            'api_endpoints' => $apiEndpoints,
            'bootstrap_urls' => $bootstrapUrls,
            'verify_path' => $verifyPath,
            'endpoint_url' => $endpointURL,
            'legacy_dylib_urls' => $legacyDylibURLs,
            'legacy_apiface_urls' => $legacyApiFaceURLs,
            'version_id' => isset($version['id']) ? (int)$version['id'] : 0,
            'dylib_version' => isset($version['version']) ? (string)$version['version'] : '',
            'dylib_build' => isset($version['build']) ? (string)$version['build'] : '',
            'version_state' => isset($version['state']) ? (string)$version['state'] : '',
            'version_sha256' => isset($version['sha256']) ? (string)$version['sha256'] : '',
            'version_offline_grace' => isset($version['offline_grace']) ? (int)$version['offline_grace'] : (isset($dylib['default_offline_grace']) ? (int)$dylib['default_offline_grace'] : 900),
            'version_fail_action' => isset($version['fail_action']) ? (string)$version['fail_action'] : (isset($dylib['default_fail_action']) ? (string)$dylib['default_fail_action'] : 'disable_feature'),
            'version_notice' => isset($version['notice']) ? (string)$version['notice'] : '',
            'protocol_version' => 2,
            'features' => [
                'app_identity' => true,
                'runtime_config' => true,
                'last_known_good' => true,
                'endpoint_fallback' => true,
                'offline_cache' => true,
                'permissions' => true,
                'app_update' => true,
                'notice' => true,
                'legacy_index_dylib' => true,
                'legacy_index_apiface' => true,
            ],
        ];
    }

    public function validate(array $config)
    {
        $errors = [];
        $warnings = [];
        if (empty($config['dylib_id']) || empty($config['dylib_key'])) $errors[] = '请选择有效的 Dylib';
        if (strlen(isset($config['verify_secret']) ? (string)$config['verify_secret'] : '') < 32) $errors[] = '当前 Dylib 验证密钥不可用，请先在 Dylib 注册中配置';
        if (empty($config['dylib_version'])) $errors[] = '当前 Dylib 尚未登记版本，请先在版本控制中添加版本';
        if (empty($config['bootstrap_urls']) && empty($config['endpoint_url'])) $errors[] = 'Bootstrap 和直连验证地址均为空，无法生成可工作的客户端';
        if (empty($config['bootstrap_urls'])) $warnings[] = '未配置 Bootstrap URL，将主要依赖直连验证地址；不利于后续服务器迁移。';
        if (!empty($config['endpoint_url']) && stripos((string)$config['endpoint_url'], 'http://') === 0) $warnings[] = '验证地址使用 HTTP，iOS ATS 可能阻止明文请求。';
        if (empty($config['dylib_enabled'])) $warnings[] = '当前 Dylib 已停用，生成代码仍可用于接入，但在线验证会返回 dylib_unknown / block。';
        return ['valid' => !$errors, 'errors' => $errors, 'warnings' => $warnings];
    }

    public function configHash(array $config)
    {
        return hash('sha256', $this->json($config));
    }

    public function publicConfig(array $config)
    {
        $public = $config;
        $secret = isset($public['verify_secret']) ? (string)$public['verify_secret'] : '';
        $public['verify_secret'] = $secret === '' ? '' : (substr($secret, 0, 4) . str_repeat('*', max(8, strlen($secret) - 8)) . substr($secret, -4));
        $public['verify_secret_present'] = strlen($secret) >= 32;
        return $public;
    }

    public function generate(array $config)
    {
        $validation = $this->validate($config);
        if (!$validation['valid']) throw new \InvalidArgumentException(implode('；', $validation['errors']));

        $prefix = $config['class_prefix'];
        $verifyHeader = $this->adaptVerifyTemplate($this->templateSource('ZONVerifyClient.h'), $prefix, false);
        $verifyImpl = $this->adaptVerifyTemplate($this->templateSource('ZONVerifyClient.m'), $prefix, true);

        $files = [
            $prefix . 'DylibConfig.h' => $this->configHeader($prefix),
            $prefix . 'DylibConfig.m' => $this->configImplementation($prefix, $config),
            $prefix . 'DylibVerify.h' => $verifyHeader,
            $prefix . 'DylibVerify.m' => $verifyImpl,
            'GeneratedConfig.json' => $this->json($this->publicConfig($config)) . "\n",
            'INTEGRATION.md' => $this->integrationGuide($prefix, $config),
        ];

        $hashes = [];
        foreach ($files as $path => $content) $hashes[$path] = hash('sha256', $content);
        $files['generation-manifest.json'] = $this->json([
            'generator' => 'zonoe-dylib-objective-c',
            'generator_version' => self::GENERATOR_VERSION,
            'config_sha256' => $this->configHash($config),
            'dylib_id' => $config['dylib_id'],
            'dylib_key' => $config['dylib_key'],
            'version_id' => $config['version_id'],
            'files' => $hashes,
        ]) . "\n";
        return $files;
    }

    protected function templateSource($name)
    {
        $root = dirname(dirname(dirname(dirname(__DIR__))));
        $path = $root . DIRECTORY_SEPARATOR . 'clients' . DIRECTORY_SEPARATOR . 'ios' . DIRECTORY_SEPARATOR . 'ZONDylibVerify' . DIRECTORY_SEPARATOR . $name;
        $content = @file_get_contents($path);
        if ($content === false || $content === '') throw new \RuntimeException('Dylib 验证客户端模板缺失：' . $name);
        return $content;
    }

    protected function adaptVerifyTemplate($content, $prefix, $implementation)
    {
        $map = [
            'ZONUDIDProvider' => $prefix . 'DylibUDIDProvider',
            'ZONVerifyConfiguration' => $prefix . 'DylibVerifyConfiguration',
            'ZONVerifyResult' => $prefix . 'DylibVerifyResult',
            'ZONVerifyClient' => $prefix . 'DylibVerify',
        ];
        $content = str_replace(array_keys($map), array_values($map), $content);
        if ($implementation) {
            $content = str_replace('#import "' . $prefix . 'DylibVerify.h"', '#import "' . $prefix . 'DylibVerify.h"', $content);
            $content = str_replace('#import "ZONVerifyClient.h"', '#import "' . $prefix . 'DylibVerify.h"', $content);
        }
        return rtrim($content) . "\n";
    }

    protected function configHeader($prefix)
    {
        return "#import <Foundation/Foundation.h>\n#import \"{$prefix}DylibVerify.h\"\n\nNS_ASSUME_NONNULL_BEGIN\n\n@interface {$prefix}DylibConfig : NSObject\n+ ({$prefix}DylibVerifyConfiguration *)configurationWithUDIDProvider:({$prefix}DylibUDIDProvider)udidProvider;\n+ (NSArray<NSURL *> *)legacyDylibURLs;\n+ (NSArray<NSURL *> *)legacyApiFaceURLs;\n+ (NSDictionary<NSString *, id> *)metadata;\n@end\n\nNS_ASSUME_NONNULL_END\n";
    }

    protected function configImplementation($prefix, array $config)
    {
        $bootstrap = $this->objcURLArray($config['bootstrap_urls']);
        $endpoint = $config['endpoint_url'] !== '' ? '[NSURL URLWithString:' . $this->objcString($config['endpoint_url']) . ']' : 'nil';
        $legacyDylib = $this->objcURLArray(isset($config['legacy_dylib_urls']) ? $config['legacy_dylib_urls'] : []);
        $legacyApiFace = $this->objcURLArray(isset($config['legacy_apiface_urls']) ? $config['legacy_apiface_urls'] : []);
        $metadata = '@{' .
            $this->objcString('dylib_id') . ':@(' . (int)$config['dylib_id'] . '),' .
            $this->objcString('dylib_key') . ':' . $this->objcString($config['dylib_key']) . ',' .
            $this->objcString('version_id') . ':@(' . (int)$config['version_id'] . '),' .
            $this->objcString('version_state') . ':' . $this->objcString($config['version_state']) . ',' .
            $this->objcString('runtime_config_version') . ':@(' . (int)$config['runtime_config_version'] . '),' .
            $this->objcString('default_fail_action') . ':' . $this->objcString($config['default_fail_action']) . ',' .
            $this->objcString('default_offline_grace') . ':@(' . (int)$config['default_offline_grace'] . ')' .
            '}';

        return "#import \"{$prefix}DylibConfig.h\"\n\n@implementation {$prefix}DylibConfig\n\n+ ({$prefix}DylibVerifyConfiguration *)configurationWithUDIDProvider:({$prefix}DylibUDIDProvider)udidProvider\n{\n    {$prefix}DylibVerifyConfiguration *cfg = [{$prefix}DylibVerifyConfiguration new];\n    cfg.bootstrapURLs = {$bootstrap};\n    cfg.endpointURL = {$endpoint};\n    cfg.dylibKey = " . $this->objcString($config['dylib_key']) . ";\n    cfg.dylibVersion = " . $this->objcString($config['dylib_version']) . ";\n    cfg.dylibBuild = " . $this->objcString($config['dylib_build']) . ";\n    cfg.verifySecret = " . $this->objcString($config['verify_secret']) . ";\n    cfg.requestTimeout = " . (int)$config['timeout'] . ".0;\n    cfg.udidProvider = udidProvider;\n    return cfg;\n}\n\n+ (NSArray<NSURL *> *)legacyDylibURLs\n{\n    return {$legacyDylib};\n}\n\n+ (NSArray<NSURL *> *)legacyApiFaceURLs\n{\n    return {$legacyApiFace};\n}\n\n+ (NSDictionary<NSString *, id> *)metadata\n{\n    return {$metadata};\n}\n\n@end\n";
    }

    protected function integrationGuide($prefix, array $config)
    {
        $legacyDylib = isset($config['legacy_dylib_urls'][0]) ? $config['legacy_dylib_urls'][0] : '';
        $legacyApiFace = isset($config['legacy_apiface_urls'][0]) ? $config['legacy_apiface_urls'][0] : '';
        return "# Dylib Objective-C 接入\n\n"
            . "本包由 Dylib 验证中心按当前 Dylib、版本、Bootstrap、API Endpoint 和验证密钥自动生成。参与编译的文件只有 4 个：\n\n"
            . "- `{$prefix}DylibConfig.h`\n- `{$prefix}DylibConfig.m`\n- `{$prefix}DylibVerify.h`\n- `{$prefix}DylibVerify.m`\n\n"
            . "当前 Dylib：`{$config['dylib_name']}` / `{$config['dylib_key']}`，版本 `{$config['dylib_version']}` (`{$config['dylib_build']}`)。\n\n"
            . "## 新验证协议\n\n```objc\n#import \"{$prefix}DylibConfig.h\"\n\n{$prefix}DylibVerifyConfiguration *cfg = [{$prefix}DylibConfig configurationWithUDIDProvider:^NSString *{\n    return ExistingProjectUDID();\n}];\n{$prefix}DylibVerify *client = [[{$prefix}DylibVerify alloc] initWithConfiguration:cfg];\n[client verifyWithCompletion:^({$prefix}DylibVerifyResult *result) {\n    if (!result.isAllowed) return;\n    if ([result.permissions[@\"extra_menu\"] boolValue]) {\n        // show extra menu\n    }\n}];\n```\n\n"
            . "## 旧接口兼容\n\n2409/2410 并未删除历史 `Index::dylib()` / `Index::apiface()`。生成配置会同时导出它们的 API URL，方便旧工程继续调用或做迁移对照。\n\n"
            . "- `Index::dylib()`：`{$legacyDylib}`，GET 参数 `udid`，返回旧版远程 Dylib 配置和授权状态。\n"
            . "- `Index::apiface()`：`{$legacyApiFace}`，GET 参数 `udid`，返回授权结果；当前服务端响应包含 `expire/ts/nonce/sign`，签名 canonical 为 `udid|expire|ts|nonce`。\n\n"
            . "Objective-C 可通过 `[{$prefix}DylibConfig legacyDylibURLs]` 和 `[{$prefix}DylibConfig legacyApiFaceURLs]` 获取按 API Endpoint 顺序生成的候选地址。\n\n"
            . "生成的 `Config.m` 包含该 Dylib 客户端协议所需的共享验证密钥，请仅放入受控工程，不要提交到公开仓库。后台管理密钥、数据库凭据和管理 Token 不会写入生成代码。\n";
    }

    protected function appendPath(array $bases, $path)
    {
        $out = [];
        foreach ($bases as $base) {
            $url = rtrim((string)$base, '/') . '/' . ltrim((string)$path, '/');
            if (!in_array($url, $out, true)) $out[] = $url;
        }
        return $out;
    }

    protected function decodeUrlList($json)
    {
        $rows = json_decode((string)$json, true);
        if (!is_array($rows)) return [];
        $out = [];
        foreach ($rows as $value) {
            $value = rtrim(trim((string)$value), '/');
            if ($value === '' || !preg_match('#^https?://#i', $value) || filter_var($value, FILTER_VALIDATE_URL) === false) continue;
            if (!in_array($value, $out, true)) $out[] = $value;
        }
        return $out;
    }

    protected function objcURLArray(array $urls)
    {
        $items = [];
        foreach ($urls as $url) $items[] = '[NSURL URLWithString:' . $this->objcString($url) . ']';
        return $items ? '@[' . implode(',', $items) . ']' : '@[]';
    }

    protected function objcString($value)
    {
        return '@"' . str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '\\r', '\\n'], (string)$value) . '"';
    }

    protected function json(array $value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}