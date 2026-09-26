<?php

namespace app\common\library\codegen;

use app\common\library\Ipa\DylibApiContract;

class ObjectiveCGenerator
{
    const GENERATOR_VERSION = '2.2.0';

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
                'api_reference' => true,
                'error_code_reference' => true,
                'multi_language_examples' => true,
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
        if (empty($config['dylib_enabled'])) $warnings[] = '当前 Dylib 已停用，生成代码仍可用于接入测试，但在线验证会返回 dylib_unknown / block。';
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
            'API_REFERENCE.md' => $this->apiReference($config),
            'ERROR_CODES.md' => $this->errorCodeGuide(),
            'EXAMPLES.md' => $this->exampleGuide($prefix, $config),
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
        return "# Dylib API 接入示例\n\n"
            . "> 定位：本 ZIP 中的 Objective-C 代码仅用于验证 API 的接入测试与参考，不是业务客户端 UI。UDID 获取、卡密输入、公告弹窗、悬浮窗、授权信息页等都由实际客户端工程自行实现。\n\n"
            . "当前 Dylib：`{$config['dylib_name']}` / `{$config['dylib_key']}`，版本 `{$config['dylib_version']}` (`{$config['dylib_build']}`)。\n\n"
            . "请先阅读：`API_REFERENCE.md`、`ERROR_CODES.md`、`EXAMPLES.md`。\n\n"
            . "## Objective-C 快速验证\n\n```objc\n#import \"{$prefix}DylibConfig.h\"\n\n{$prefix}DylibVerifyConfiguration *cfg = [{$prefix}DylibConfig configurationWithUDIDProvider:^NSString *{\n    return ExistingProjectUDID(); // 由你的客户端工程提供\n}];\n{$prefix}DylibVerify *client = [[{$prefix}DylibVerify alloc] initWithConfiguration:cfg];\n[client verifyWithCompletion:^({$prefix}DylibVerifyResult *result) {\n    NSLog(@\"code=%@ message=%@\", result.code, result.message);\n    if (!result.isAllowed) {\n        // 按 result.code / result.action 处理；message 可用于显示。\n        return;\n    }\n    // 验证通过后由你的客户端决定 UI / 菜单 / 功能。\n}];\n```\n\n"
            . "## 旧接口兼容\n\n"
            . "- `Index::dylib()`：`{$legacyDylib}`，GET 参数 `udid`。\n"
            . "- `Index::apiface()`：`{$legacyApiFace}`，GET 参数 `udid`；canonical 为 `udid|expire|ts|nonce`。\n\n"
            . "生成的 Config.m 含当前 Dylib 的 Verify Secret，仅应放入受控工程；不要提交到公开仓库。\n";
    }

    protected function apiReference(array $config)
    {
        $doc = DylibApiContract::document($config['dylib_key'], $config['dylib_version'], $config['dylib_build'], $config['verify_path']);
        $out = "# Dylib Verification API Reference\n\n";
        $out .= "**职责边界：** 验证中心只提供 API、签名规则和返回协议；客户端 UI/输入流程由调用方工程自行实现。\n\n";
        $out .= "当前 Dylib：`{$config['dylib_key']}`，版本 `{$config['dylib_version']}`，Build `{$config['dylib_build']}`。\n\n";
        $out .= "## Endpoint\n\n- Runtime Config: `GET /index/dylib_verify/config?dylib_key={$config['dylib_key']}`\n- Verify: `POST {$config['verify_path']}`\n- Content-Type: `application/x-www-form-urlencoded` 或 `application/json`\n\n";
        $out .= "## 请求字段\n\n|字段|类型|必填|协议|说明|\n|---|---|---|---|---|\n";
        foreach ($doc['request_fields'] as $f) $out .= '|' . $f['name'] . '|' . $f['type'] . '|' . ($f['required'] ? '是' : '否/按协议') . '|' . $f['since'] . '|' . $f['description'] . "|\n";
        $out .= "\n## HMAC-SHA256\n\n算法：`hex_lowercase(HMAC-SHA256(canonical, verify_secret))`。字段之间使用换行符 `\\n`，不能使用 JSON 字段顺序代替 canonical。\n\n### Protocol v1\n\n```text\n" . DylibApiContract::canonicalV1() . "\n```\n\n### Protocol v2\n\n```text\n" . DylibApiContract::canonicalV2() . "\n```\n\n";
        $out .= "## 返回字段\n\n|字段|类型|说明|\n|---|---|---|\n";
        foreach ($doc['response_fields'] as $f) $out .= '|' . $f['name'] . '|' . $f['type'] . '|' . $f['description'] . "|\n";
        $out .= "\n## 客户端处理原则\n\n1. 业务判断使用 `ok` + `code`，不要解析 `message` 文本。\n2. `message` 可直接作为服务器提示显示给用户。\n3. `action` 是验证中心给出的受保护能力处理建议。\n4. `notice` / `app_update` 是数据，如何展示由客户端决定。\n5. 网络异常时结合 `offline_grace_seconds` 与客户端已有缓存策略处理。\n";
        return $out;
    }

    protected function errorCodeGuide()
    {
        $out = "# Dylib Verification Result Codes\n\n`code` 是客户端应使用的稳定业务结果码；`message` 是可展示文本，不应作为程序判断条件。\n\n|code|ok|含义|客户端建议|\n|---|---|---|---|\n";
        foreach (DylibApiContract::errorCodes() as $row) $out .= '|' . $row['code'] . '|' . ($row['ok'] ? 'true' : 'false') . '|' . $row['meaning'] . '|' . $row['client'] . "|\n";
        $out .= "\n## action\n\n";
        foreach (DylibApiContract::actions() as $action => $meaning) $out .= '- `' . $action . '`：' . $meaning . "\n";
        return $out;
    }

    protected function exampleGuide($prefix, array $config)
    {
        $url = $config['endpoint_url'] !== '' ? $config['endpoint_url'] : rtrim(isset($config['api_endpoints'][0]) ? $config['api_endpoints'][0] : '', '/') . $config['verify_path'];
        $jsonExample = '{"udid":"<由客户端提供>","bundle_id":"com.example.app","dylib_key":"' . $config['dylib_key'] . '","dylib_version":"' . $config['dylib_version'] . '","dylib_build":"' . $config['dylib_build'] . '","dylib_sha256":"<optional>","timestamp":<unix>,"nonce":"<random>","signature":"<hmac-sha256>","protocol_version":2,"app_executable":"ExampleApp","app_macho_uuid":"<UUID>","app_version":"1.0","app_build":"1"}';
        return "# API 调用示例\n\n这些示例只演示如何调用 API；不会生成卡密弹窗、UDID 页面或其他业务 UI。\n\n"
            . "## cURL\n\n```bash\ncurl -X POST '" . $url . "' \\\n  -H 'Content-Type: application/json' \\\n  --data '" . $jsonExample . "'\n```\n\n"
            . "## Objective-C\n\n```objc\n#import \"{$prefix}DylibConfig.h\"\n{$prefix}DylibVerifyConfiguration *cfg = [{$prefix}DylibConfig configurationWithUDIDProvider:^NSString *{ return YourUDID(); }];\n{$prefix}DylibVerify *api = [[{$prefix}DylibVerify alloc] initWithConfiguration:cfg];\n[api verifyWithCompletion:^({$prefix}DylibVerifyResult *r) {\n    if (!r.isAllowed) { NSLog(@\"%@ / %@\", r.code, r.message); return; }\n    NSLog(@\"permissions=%@ notice=%@\", r.permissions, r.notice);\n}];\n```\n\n"
            . "## Swift / Python\n\n其他语言需要严格复刻 `API_REFERENCE.md` 中 canonical 字段顺序后做 HMAC-SHA256。不要对 JSON 字典排序后直接签名。\n";
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
