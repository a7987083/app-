<?php

namespace app\common\library\Ipa;

/**
 * One documentation source for the Dylib Center API guide and downloadable
 * integration bundle. Existing legacy APIs remain documented; Protocol v3.1
 * uses active-UDID auth_proof + Challenge + device P-256 proof.
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
                    ['name' => 'udid', 'required' => true, 'description' => '设备 UDID（当前兼容 1-128 字符）'],
                ],
                'next' => '需要首次激活时调用 /appstore；Dylib 在线验证由 /index/index/apiface 签发 auth_proof 后进入 Challenge/Verify。',
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
                    ['name' => 'code', 'required' => false, 'description' => '卡密；激活时必填，仅刷新可省略'],
                ],
                'next' => '激活成功后，Dylib 客户端只需要已授权 UDID；不再保存卡密用于 Device Key enrollment。',
            ],
            [
                'key' => 'dylib_auth',
                'title' => '已激活设备授权校验 / Auth Proof',
                'method' => 'GET',
                'path' => '/index/index/apiface',
                'response_type' => 'JSON',
                'client_recommended' => true,
                'purpose' => '按 UDID 查询已激活且未过期授权。保留 expire、ts、nonce、sign 等旧字段，并为 Protocol v3.1 追加短时 auth_proof。',
                'when' => '新版 Dylib 在线验证每次 Challenge 前调用；旧客户端仍可消费原字段。',
                'params' => [
                    ['name' => 'udid', 'required' => true, 'description' => '设备 UDID'],
                ],
                'next' => '取出 auth_proof，与同一 UDID、公钥一起 POST /index/dylib_verify/challenge。',
            ],
            [
                'key' => 'dylib_config',
                'title' => '旧远程 Dylib 配置',
                'method' => 'GET',
                'path' => '/index/index/dylib',
                'response_type' => 'JSON',
                'client_recommended' => true,
                'purpose' => '返回旧协议的远程 Dylib 配置与当前 UDID 授权状态。',
                'when' => '保留旧远程配置流程。Runtime Config 请优先使用 /index/dylib_verify/config。',
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
                    ['name' => 'old_udid', 'required' => true, 'description' => '原设备 UDID（1-128 字符）'],
                    ['name' => 'new_udid', 'required' => true, 'description' => '新设备 UDID（1-128 字符）'],
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
                'next' => '授权客户端继续走 /apiface auth_proof → Challenge → Verify。',
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
                'next' => '验证服务器 RSA 签名后，从配置得到 Endpoint/Verify Path；随后用 UDID 请求 /apiface auth_proof。',
            ],
            [
                'key' => 'challenge',
                'title' => 'Dylib 一次性 Challenge',
                'method' => 'POST',
                'path' => '/index/dylib_verify/challenge',
                'response_type' => 'JSON',
                'client_recommended' => true,
                'purpose' => '为 Protocol v3.1 设备证明签发短期一次性 Challenge。首次 Device Key enrollment 必须有 active-UDID auth_proof；已登记 active Device Key 的旧 v3 客户端保留兼容路径。',
                'when' => '每次在线 Verify 前调用。Challenge 只能消费一次且有短 TTL。',
                'params' => [
                    ['name' => 'udid', 'required' => true, 'description' => '设备 UDID'],
                    ['name' => 'dylib_key', 'required' => true, 'description' => 'Dylib Key'],
                    ['name' => 'device_public_key', 'required' => true, 'description' => '设备 P-256 SPKI PEM 公钥'],
                    ['name' => 'auth_proof', 'required' => false, 'description' => '新版客户端必带；首次 Device Key enrollment 强制需要，由 /apiface 签发'],
                ],
                'next' => '使用同一 auth_proof + Challenge 构造 canonical，并由设备 Keychain 私钥做 ECDSA-SHA256 签名后 POST Verify。',
            ],
            [
                'key' => 'verify',
                'title' => 'Dylib 在线验证',
                'method' => 'POST',
                'path' => $verifyPath,
                'response_type' => 'JSON',
                'client_recommended' => true,
                'purpose' => 'Dylib 核心在线验证：校验 active-UDID proof、一次性 Challenge、设备公钥证明、App 身份、Dylib Key/版本/Build/SHA256，并执行授权/权限/公告/更新逻辑。',
                'when' => '取得 auth_proof + Challenge 并完成 P-256 签名后调用；支持 form-urlencoded 或 application/json。',
                'params' => DylibApiContract::verifyRequestFields(),
                'next' => '客户端先判断 ok，再按 code + action 处理；message 只用于展示，不用于业务条件判断。',
            ],
        ];
    }

    public static function machineDocument($dylibKey, $dylibName, $verifyPath, array $runtimeConfig = [])
    {
        return [
            'schema_version' => 3,
            'generated_for' => [
                'dylib_key' => (string)$dylibKey,
                'dylib_name' => (string)$dylibName,
            ],
            'security' => [
                'protocol' => 'secretless-v3.1-active-udid-proof',
                'active_udid_proof' => 'short-lived server HMAC returned by /index/index/apiface after active-UDID check',
                'device_signature' => 'ECDSA P-256 SHA-256; private key stays in device Keychain',
                'runtime_config_signature' => 'RSA-2048 SHA-256; client contains only server public key',
                'verify_secret' => false,
                'license_code_in_device_enrollment' => false,
            ],
            'runtime' => [
                'auth_proof_path' => '/index/index/apiface',
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
            . "安全：卡密只用于原始 UDID 激活，不进入 Device Key enrollment；Dylib 通过短时 active-UDID auth_proof + 一次性 Challenge + 设备 P-256 Keychain 私钥完成验证。Bootstrap 只嵌入服务器 RSA 公钥。\n";
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
                $required = !empty($field['required']) ? '是' : '否/兼容路径';
                $description = isset($field['description']) ? $field['description'] : '';
                $out .= '| `' . $field['name'] . '` | ' . $required . ' | ' . str_replace('|', '\\|', $description) . " |\n";
            }
            $out .= "\n**下一步：** " . $api['next'] . "\n\n";
        }
        return $out;
    }

    protected static function errorMarkdown()
    {
        $out = "# Dylib Verify Result Codes\n\n客户端业务判断使用 `ok + code`，未知 code 按 `action` 处理。`message` 可以展示，但不要解析文字做业务逻辑。\n\n| code | ok | 含义 | 客户端处理 |\n|---|---|---|---|\n";
        foreach (DylibApiContract::errorCodes() as $row) {
            $out .= '| `' . $row['code'] . '` | ' . ($row['ok'] ? 'true' : 'false') . ' | ' . $row['meaning'] . ' | ' . $row['client'] . " |\n";
        }
        return $out;
    }

    protected static function signatureMarkdown()
    {
        return "# Signature Contract\n\n"
            . "## Protocol v3.1 device proof\n\n"
            . "1. `GET /index/index/apiface?udid=...` 获取短时 `auth_proof`。\n"
            . "2. 同一 `auth_proof` 随 Challenge 请求提交。\n"
            . "3. 新 canonical：\n\n```text\n" . DylibApiContract::canonicalV3() . "\n```\n\n"
            . "4. 使用设备 Keychain P-256 私钥做 ECDSA-SHA256，DER 签名 Base64 后作为 `device_signature`。\n"
            . "5. Verify 必须提交与 Challenge 相同的 `auth_proof`。\n\n"
            . "已登记 Device Key 的旧 v3 客户端保留旧 canonical 兼容路径；首次 Device Key enrollment 不接受 license_code 替代 auth_proof。\n";
    }

    protected static function responseMarkdown()
    {
        $out = "# Response Model\n\nVerify 客户端先看 `ok`，再看稳定 `code`，最后按 `action` 决定受保护功能行为；`message` 只用于展示。\n\n";
        foreach (DylibApiContract::actions() as $key => $description) {
            $out .= '- `' . $key . '`：' . $description . "\n";
        }
        return $out;
    }

    protected static function flowMarkdown()
    {
        return "# Recommended Flows\n\n"
            . "## 首次卡密激活\n\n```text\n用户输入卡密\n  ↓\nGET /appstore?udid=...&code=...\n  ↓\n服务器把卡密绑定/激活到 UDID\n  ↓\n以后 Dylib 不再保存这张卡密\n```\n\n"
            . "## Dylib Secretless v3.1 验证\n\n```text\nDylib 启动\n  ↓\nGET /index/dylib_verify/config?dylib_key=...\n  ↓\nRSA 公钥验签 Runtime Config\n  ↓\nGET /index/index/apiface?udid=...\n  ↓\n服务器确认 active UDID → auth_proof\n  ↓\nPOST /index/dylib_verify/challenge\n  (udid + dylib_key + public_key + auth_proof)\n  ↓\n设备 P-256 私钥签 canonical v3.1\n  ↓\nPOST Verify Path\n  (同一 auth_proof + challenge + signature + App Identity)\n  ↓\n首次：登记 Device Key；以后：校验已登记 Key\n  ↓\nok → code → action → permissions / notice / app_update\n```\n";
    }

    protected static function curlExamples($key, $verifyPath)
    {
        return "# cURL Examples\n\n"
            . "```bash\ncurl -G 'https://YOUR_HOST/index/dylib_verify/config' --data-urlencode 'dylib_key=" . $key . "'\n```\n\n"
            . "```bash\ncurl -G 'https://YOUR_HOST/index/index/apiface' --data-urlencode 'udid=<UDID>'\n# 从返回中取 auth_proof\n```\n\n"
            . "```bash\ncurl -X POST 'https://YOUR_HOST/index/dylib_verify/challenge' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"udid\":\"<UDID>\",\"dylib_key\":\"" . $key . "\",\"device_public_key\":\"<P-256-SPKI-PEM>\",\"auth_proof\":\"<AUTH_PROOF>\"}'\n```\n\n"
            . "```bash\ncurl -X POST 'https://YOUR_HOST" . $verifyPath . "' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"protocol_version\":3,\"udid\":\"<UDID>\",\"bundle_id\":\"com.example.app\",\"dylib_key\":\"" . $key . "\",\"dylib_version\":\"1.0.0\",\"app_executable\":\"ExampleApp\",\"app_macho_uuid\":\"<UUID>\",\"auth_proof\":\"<AUTH_PROOF>\",\"challenge_id\":\"<ID>\",\"challenge\":\"<VALUE>\",\"device_public_key\":\"<PEM>\",\"device_signature\":\"<BASE64-DER>\"}'\n```\n";
    }

    protected static function objectiveCExamples($key, $verifyPath)
    {
        return "# Objective-C Reference\n\n```objc\nNSString *path = @\"" . addslashes($verifyPath) . "\";\nNSString *dylibKey = @\"" . addslashes($key) . "\";\n// 1) 根据 UDID GET /index/index/apiface 获取 auth_proof\n// 2) Keychain 创建/读取 P-256 私钥\n// 3) POST Challenge：udid + dylib_key + public_key + auth_proof\n// 4) 按 SIGNATURE.md 构造 canonical v3.1（包含 auth_proof）\n// 5) SecKeyCreateSignature(ECDSA-SHA256) -> Base64 DER\n// 6) POST Verify；首次 enrollment 不再发送卡密\n// 7) 先判断 ok，再处理 code/action；message 只用于展示\n```\n";
    }

    protected static function swiftExamples($key, $verifyPath)
    {
        return "# Swift Reference\n\n```swift\nlet dylibKey = \"" . addslashes($key) . "\"\nlet verifyPath = \"" . addslashes($verifyPath) . "\"\n// /apiface auth_proof -> Challenge -> P-256 ECDSA-SHA256 -> Verify。\n// Runtime Config 使用生成配置中的服务器 RSA 公钥验签。\n// 不保存卡密；使用 ok + code + action 做业务判断。\n```\n";
    }

    protected static function pythonExamples($key, $verifyPath)
    {
        return "# Python Reference\n\n```python\n# 伪代码：使用独立 P-256 私钥，不存在 Verify Secret，也不发送 license_code。\n# 1. auth_proof = GET /index/index/apiface?udid=...\n# 2. POST /index/dylib_verify/challenge with auth_proof\n# 3. canonical = '<按 SIGNATURE.md 拼接，包含 auth_proof>'\n# 4. signature = base64(ECDSA_P256_SHA256(device_private_key, canonical))\n# 5. POST https://YOUR_HOST" . $verifyPath . " with the same auth_proof\n# dylib_key = '" . addslashes($key) . "'\n```\n";
    }
}
