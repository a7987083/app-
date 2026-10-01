<?php

namespace app\common\library\Ipa;

/**
 * One documentation source for the Dylib Center API guide and downloadable
 * integration bundle. Existing 2428 APIs remain documented; protocol v3 only
 * replaces the Dylib Verify authentication mechanism.
 */
class DylibApiDocumentation
{
    public static function catalog($verifyPath = '/index/dylib_verify/verify')
    {
        $verifyPath = trim((string)$verifyPath);
        if ($verifyPath === '' || $verifyPath[0] !== '/') {
            $verifyPath = '/index/dylib_verify/verify';
        }

        return [
            [
                'key' => 'authorization',
                'title' => '卡密授权查询',
                'method' => 'POST',
                'path' => '/authorization',
                'response_type' => 'HTML 页面兼容入口',
                'client_recommended' => false,
                'purpose' => '查询指定卡密与 UDID 的授权状态。当前实现把 AuthorizationLicense 查询结果渲染到页面，不是纯 JSON 响应。',
                'when' => '网页自助查询授权信息时使用；OC/Swift 客户端不要把它当 JSON API 解析。',
                'params' => [
                    ['name' => 'code', 'required' => true, 'description' => '卡密'],
                    ['name' => 'udid', 'required' => true, 'description' => '25 或 40 字符设备 UDID'],
                ],
                'next' => '需要首次激活时调用 /appstore；日常 Dylib 授权校验使用 /index/index/apiface 或 Dylib Verify。',
            ],
            [
                'key' => 'appstore',
                'title' => '卡密激活 / 软件源刷新',
                'method' => 'GET',
                'path' => '/appstore',
                'response_type' => 'JSON/软件源响应',
                'client_recommended' => true,
                'purpose' => '带 code 时激活一次性卡密并绑定 UDID；不带 code 时按当前 UDID 刷新软件源数据。',
                'when' => '首次激活卡密或软件源刷新时调用。',
                'params' => [
                    ['name' => 'udid', 'required' => true, 'description' => '设备 UDID'],
                    ['name' => 'code', 'required' => false, 'description' => '卡密；激活时必填，仅刷新时可省略'],
                ],
                'next' => '激活成功后，日常授权检查使用 /index/index/apiface；首次 Secretless 设备密钥绑定使用同一已激活卡密。',
            ],
            [
                'key' => 'dylib_auth',
                'title' => '已激活设备授权校验',
                'method' => 'GET',
                'path' => '/index/index/apiface',
                'response_type' => 'JSON',
                'client_recommended' => true,
                'purpose' => '按 UDID 查询已激活且未过期授权，并返回 expire、ts、nonce、sign 等旧协议字段。',
                'when' => '保留 2428 既有客户端/业务调用。新的 Dylib 安全验证使用 Challenge + Verify。',
                'params' => [
                    ['name' => 'udid', 'required' => true, 'description' => '设备 UDID'],
                ],
                'next' => '验证通过后可读取 /index/index/dylib 获取旧远程配置，或进入 Dylib Verify v3 流程。',
            ],
            [
                'key' => 'dylib_config',
                'title' => '旧远程 Dylib 配置',
                'method' => 'GET',
                'path' => '/index/index/dylib',
                'response_type' => 'JSON',
                'client_recommended' => true,
                'purpose' => '返回旧协议的远程 Dylib 配置与当前 UDID 授权状态。',
                'when' => '保留 2428 远程配置流程。Runtime Config 请优先使用 /index/dylib_verify/config。',
                'params' => [
                    ['name' => 'udid', 'required' => true, 'description' => '设备 UDID'],
                ],
                'next' => '需要动态 Endpoint/服务器签名配置时调用 /index/dylib_verify/config。',
            ],
            [
                'key' => 'unbind',
                'title' => '设备换绑提交',
                'method' => 'POST',
                'path' => '/unbind',
                'response_type' => 'HTML 页面兼容入口',
                'client_recommended' => false,
                'purpose' => '提交卡密、旧 UDID、新 UDID 执行自助换绑。当前 Controller 把结果渲染到页面，不是纯 JSON 响应。',
                'when' => '网页自助换绑使用；OC/Swift 客户端不要按 JSON 解析该入口。',
                'params' => [
                    ['name' => 'code', 'required' => true, 'description' => '已激活卡密'],
                    ['name' => 'old_udid', 'required' => true, 'description' => '原设备 UDID'],
                    ['name' => 'new_udid', 'required' => true, 'description' => '新设备 UDID'],
                ],
                'next' => '换绑后使用 /unbind/query?udid=新UDID 查询当前设备是否具备有效授权。',
            ],
            [
                'key' => 'unbind_query',
                'title' => '换绑状态查询',
                'method' => 'GET',
                'path' => '/unbind/query',
                'response_type' => 'JSON',
                'client_recommended' => true,
                'purpose' => '查询当前 UDID 有效授权、剩余换绑次数、每日限制、冷却时间和 can_transfer。',
                'when' => '换绑前检查资格，或换绑后刷新状态。',
                'params' => [
                    ['name' => 'udid', 'required' => true, 'description' => '设备 UDID'],
                ],
                'next' => 'can_transfer=true 时可进入网页换绑流程；授权客户端继续做正常授权/验证。',
            ],
            [
                'key' => 'runtime_config',
                'title' => 'Dylib Runtime Config',
                'method' => 'GET',
                'path' => '/index/dylib_verify/config',
                'response_type' => 'JSON',
                'client_recommended' => true,
                'purpose' => '按 dylib_key 获取 RSA 签名后的运行配置、业务 API Endpoint、Verify Path 与配置版本。',
                'when' => 'Dylib 启动并准备在线验证之前调用；客户端可缓存 Last-Known-Good。',
                'params' => [
                    ['name' => 'dylib_key', 'required' => true, 'description' => '验证中心登记的 Dylib Key'],
                ],
                'next' => '验证服务器 RSA 签名后，从配置得到 Endpoint/Verify Path，然后请求 Challenge。',
            ],
            [
                'key' => 'challenge',
                'title' => 'Dylib 一次性 Challenge',
                'method' => 'POST',
                'path' => '/index/dylib_verify/challenge',
                'response_type' => 'JSON',
                'client_recommended' => true,
                'purpose' => '为 Protocol v3 设备证明签发短期一次性 Challenge，并判断当前设备公钥是否需要首次绑定。',
                'when' => '每次在线 Verify 前调用。Challenge 只能消费一次且有短 TTL。',
                'params' => [
                    ['name' => 'udid', 'required' => true, 'description' => '设备 UDID'],
                    ['name' => 'dylib_key', 'required' => true, 'description' => 'Dylib Key'],
                    ['name' => 'device_public_key', 'required' => true, 'description' => '设备 P-256 SPKI PEM 公钥'],
                ],
                'next' => '使用设备 Keychain 私钥对 canonical v3 签名，再 POST Verify。首次绑定时同时提供已激活卡密。',
            ],
            [
                'key' => 'verify',
                'title' => 'Dylib 在线验证',
                'method' => 'POST',
                'path' => $verifyPath,
                'response_type' => 'JSON',
                'client_recommended' => true,
                'purpose' => 'Dylib 核心在线验证：校验一次性 Challenge、设备公钥证明、App 身份、Dylib Key/版本/Build/SHA256，并继续执行 2428 授权/权限/公告/更新逻辑。',
                'when' => '取得 Challenge 并完成 P-256 签名后调用；支持 form-urlencoded 或 application/json。',
                'params' => DylibApiContract::verifyRequestFields(),
                'next' => '客户端先判断 ok，再按 code + action 处理；message 只用于展示，不用于业务条件判断。',
            ],
        ];
    }

    public static function machineDocument($dylibKey, $dylibName, $verifyPath, array $runtimeConfig = [])
    {
        return [
            'schema_version' => 2,
            'generated_for' => [
                'dylib_key' => (string)$dylibKey,
                'dylib_name' => (string)$dylibName,
            ],
            'security' => [
                'protocol' => 'secretless-v3',
                'device_signature' => 'ECDSA P-256 SHA-256; private key stays in device Keychain',
                'runtime_config_signature' => 'RSA-2048 SHA-256; client contains only server public key',
                'verify_secret' => false,
            ],
            'runtime' => [
                'verify_path' => (string)$verifyPath,
                'challenge_path' => '/index/dylib_verify/challenge',
                'config_version' => isset($runtimeConfig['config_version']) ? (int)$runtimeConfig['config_version'] : 0,
            ],
            'apis' => self::catalog($verifyPath),
            'verify_contract' => DylibApiContract::document($dylibKey, '', '', $verifyPath),
        ];
    }

    public static function exportFiles($dylibKey, $dylibName, $verifyPath, array $runtimeConfig = [])
    {
        $doc = self::machineDocument($dylibKey, $dylibName, $verifyPath, $runtimeConfig);
        $apis = $doc['apis'];
        $files = [];
        $files['README.md'] = self::readme($dylibKey, $dylibName);
        $files['API_OVERVIEW.md'] = self::overviewMarkdown($apis);
        $files['API_REFERENCE.md'] = self::referenceMarkdown($apis);
        $files['ERROR_CODES.md'] = self::errorMarkdown();
        $files['SIGNATURE.md'] = self::signatureMarkdown();
        $files['RESPONSE_MODEL.md'] = self::responseMarkdown();
        $files['FLOW.md'] = self::flowMarkdown();
        $files['examples/curl.md'] = self::curlExamples($dylibKey, $verifyPath);
        $files['examples/Objective-C.md'] = self::objectiveCExamples($dylibKey, $verifyPath);
        $files['examples/Swift.md'] = self::swiftExamples($dylibKey, $verifyPath);
        $files['examples/Python.md'] = self::pythonExamples($dylibKey, $verifyPath);
        $files['schemas/api.json'] = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        $files['schemas/error_codes.json'] = json_encode(DylibApiContract::errorCodes(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        return $files;
    }

    protected static function readme($key, $name)
    {
        return "# Dylib API Integration\n\n"
            . "Dylib: " . ($name !== '' ? $name : $key) . " (`" . $key . "`)\n\n"
            . "本包是验证中心 API 接入资料，不包含客户端 UI。OC/Swift 示例只用于演示请求、签名与结果处理。\n\n"
            . "安全：客户端不包含全局 Verify Secret 或服务器私钥。每台设备使用独立 P-256 Keychain 私钥；Bootstrap 只嵌入服务器 RSA 公钥。\n";
    }

    protected static function overviewMarkdown(array $apis)
    {
        $out = "# API Overview\n\n共 " . count($apis) . " 个已整理入口。`HTML 页面兼容入口` 不是纯 JSON API，客户端不要按 JSON 解析。\n\n";
        foreach ($apis as $i => $api) {
            $out .= ($i + 1) . ". **" . $api['title'] . "** — `" . $api['method'] . " " . $api['path'] . "` — " . $api['response_type'] . "\n";
        }
        return $out;
    }

    protected static function referenceMarkdown(array $apis)
    {
        $out = "# API Reference\n\n";
        foreach ($apis as $i => $api) {
            $out .= "## " . ($i + 1) . ". " . $api['title'] . "\n\n";
            $out .= "- 方法：`" . $api['method'] . "`\n- 路径：`" . $api['path'] . "`\n- 返回类型：" . $api['response_type'] . "\n- 推荐给程序客户端：" . ($api['client_recommended'] ? '是' : '否') . "\n";
            $out .= "- 用途：" . $api['purpose'] . "\n- 调用时机：" . $api['when'] . "\n\n";
            $out .= "| 参数 | 必填 | 说明 |\n|---|---|---|\n";
            foreach ($api['params'] as $field) {
                $required = !empty($field['required']) ? '是' : '否';
                $description = isset($field['description']) ? $field['description'] : '';
                $out .= '| `' . $field['name'] . '` | ' . $required . ' | ' . str_replace('|', '\\|', $description) . " |\n";
            }
            $out .= "\n**下一步：** " . $api['next'] . "\n\n";
        }
        return $out;
    }

    protected static function errorMarkdown()
    {
        $out = "# Dylib Verify Result Codes\n\n客户端业务判断使用 `ok + code`，未知 code 按 `action` 处理。`message` 可以展示，但不要解析文字做逻辑。\n\n";
        $out .= "| code | ok | 含义 | 客户端建议 |\n|---|---:|---|---|\n";
        foreach (DylibApiContract::errorCodes() as $row) {
            $out .= '| `' . $row['code'] . '` | ' . ($row['ok'] ? 'true' : 'false') . ' | ' . $row['meaning'] . ' | ' . $row['client'] . " |\n";
        }
        return $out;
    }

    protected static function signatureMarkdown()
    {
        return "# Signature Protocol v3\n\n"
            . "## Device proof\n\n设备生成独立 P-256 私钥并保存到 Keychain；服务器只保存公钥。每次验证先申请一次性 Challenge，再签名以下 canonical：\n\n"
            . "```text\n" . DylibApiContract::canonicalV3() . "\n```\n\n"
            . "签名算法：ECDSA P-256 + SHA-256；iOS `SecKeyCreateSignature` 输出 DER signature，传输时 Base64。\n\n"
            . "## Runtime Config\n\nBootstrap/Runtime Config 使用服务器 RSA-2048 + SHA-256 签名。客户端生成配置只嵌入服务器公钥和 Key ID，服务器私钥不进入数据库、仓库或客户端。\n\n"
            . "字段之间使用真实换行符，不要使用 JSON key 排序替代 canonical。一次性 Challenge 不可复用。\n";
    }

    protected static function responseMarkdown()
    {
        $out = "# Verify Response Model\n\n| 字段 | 类型 | 说明 |\n|---|---|---|\n";
        foreach (DylibApiContract::verifyResponseFields() as $field) {
            $out .= '| `' . $field['name'] . '` | ' . $field['type'] . ' | ' . $field['description'] . " |\n";
        }
        $out .= "\n## action\n\n";
        foreach (DylibApiContract::actions() as $key => $description) {
            $out .= '- `' . $key . '`：' . $description . "\n";
        }
        return $out;
    }

    protected static function flowMarkdown()
    {
        return "# Recommended Flows\n\n"
            . "## 首次卡密激活\n\n```text\n用户输入卡密\n  ↓\n网页可用 POST /authorization 查询\n  ↓\n未激活时 GET /appstore?udid=...&code=...\n  ↓\n绑定/激活\n  ↓\nGET /index/index/apiface?udid=...（原 API 保留）\n```\n\n"
            . "## Dylib Secretless v3 验证\n\n```text\nDylib 启动\n  ↓\nGET /index/dylib_verify/config?dylib_key=...\n  ↓\nRSA 公钥验签 Runtime Config\n  ↓\nPOST /index/dylib_verify/challenge\n  ↓\n设备 P-256 私钥签 canonical v3\n  ↓\n首次绑定：附带当前已激活卡密\n  ↓\nPOST Verify Path\n  ↓\nok → code → action → message / permissions / notice / app_update\n```\n";
    }

    protected static function curlExamples($key, $verifyPath)
    {
        return "# cURL Examples\n\n"
            . "```bash\ncurl -G 'https://YOUR_HOST/index/dylib_verify/config' --data-urlencode 'dylib_key=" . $key . "'\n```\n\n"
            . "```bash\ncurl -X POST 'https://YOUR_HOST/index/dylib_verify/challenge' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"udid\":\"<UDID>\",\"dylib_key\":\"" . $key . "\",\"device_public_key\":\"<P-256-SPKI-PEM>\"}'\n```\n\n"
            . "```bash\ncurl -X POST 'https://YOUR_HOST" . $verifyPath . "' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"protocol_version\":3,\"udid\":\"<UDID>\",\"bundle_id\":\"com.example.app\",\"dylib_key\":\"" . $key . "\",\"dylib_version\":\"1.0.0\",\"app_executable\":\"ExampleApp\",\"app_macho_uuid\":\"<UUID>\",\"challenge_id\":\"<ID>\",\"challenge\":\"<VALUE>\",\"device_public_key\":\"<PEM>\",\"device_signature\":\"<BASE64-DER>\"}'\n```\n";
    }

    protected static function objectiveCExamples($key, $verifyPath)
    {
        return "# Objective-C Reference\n\n```objc\n// 保留 2428 的 API/结果处理模型，只替换认证层。\nNSString *path = @\"" . addslashes($verifyPath) . "\";\nNSString *dylibKey = @\"" . addslashes($key) . "\";\n// 1) Keychain 创建/读取 P-256 私钥\n// 2) POST /index/dylib_verify/challenge\n// 3) 按 SIGNATURE.md 固定字段顺序构造 canonical v3\n// 4) SecKeyCreateSignature(ECDSA-SHA256) -> Base64 DER\n// 5) POST JSON 到 path；首次绑定附带已激活卡密\n// 6) 先判断 ok，再处理 code/action；message 只用于展示\n```\n";
    }

    protected static function swiftExamples($key, $verifyPath)
    {
        return "# Swift Reference\n\n```swift\nlet dylibKey = \"" . addslashes($key) . "\"\nlet verifyPath = \"" . addslashes($verifyPath) . "\"\n// Security.framework P-256 Keychain key -> Challenge -> ECDSA-SHA256 -> POST Verify。\n// Runtime Config 使用生成配置中的服务器 RSA 公钥验签。\n// 使用 ok + code + action 做业务判断。\n```\n";
    }

    protected static function pythonExamples($key, $verifyPath)
    {
        return "# Python Reference\n\n```python\n# 伪代码：使用独立 P-256 私钥，不存在 Verify Secret。\n# 1. POST /index/dylib_verify/challenge\n# 2. canonical = '<按 SIGNATURE.md 拼接>'\n# 3. signature = base64(ECDSA_P256_SHA256(device_private_key, canonical))\n# 4. POST https://YOUR_HOST" . $verifyPath . "\n# dylib_key = '" . addslashes($key) . "'\n```\n";
    }
}
