<?php

namespace app\common\library\Ipa;

/**
 * One documentation source for the Dylib Center API guide and downloadable
 * integration bundle. This describes existing endpoints; it does not turn
 * page/form compatibility routes into JSON APIs.
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
                'next' => '需要首次激活时调用 /appstore；日常 Dylib 授权校验使用 /index/index/apiface 或新的 Dylib Verify。',
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
                'next' => '激活成功后，日常授权检查使用 /index/index/apiface；受保护 Dylib 还应继续调用 Dylib Verify。',
            ],
            [
                'key' => 'dylib_auth',
                'title' => '已激活设备授权校验',
                'method' => 'GET',
                'path' => '/index/index/apiface',
                'response_type' => 'JSON',
                'client_recommended' => true,
                'purpose' => '按 UDID 查询已激活且未过期授权，并返回 expire、ts、nonce、sign 等旧协议字段。',
                'when' => '兼容旧客户端的日常启动授权检查。新 Dylib 安全验证仍应使用 /index/dylib_verify/verify。',
                'params' => [
                    ['name' => 'udid', 'required' => true, 'description' => '设备 UDID'],
                ],
                'next' => '验证通过后可读取 /index/index/dylib 获取旧远程配置，或进入 Dylib Verify 流程。',
            ],
            [
                'key' => 'dylib_config',
                'title' => '旧远程 Dylib 配置',
                'method' => 'GET',
                'path' => '/index/index/dylib',
                'response_type' => 'JSON',
                'client_recommended' => true,
                'purpose' => '返回旧协议的远程 Dylib 配置与当前 UDID 授权状态。',
                'when' => '兼容旧客户端远程配置流程。2412+ Runtime Config 请优先使用 /index/dylib_verify/config。',
                'params' => [
                    ['name' => 'udid', 'required' => true, 'description' => '设备 UDID'],
                ],
                'next' => '需要 2412+ 动态 Endpoint/签名配置时调用 /index/dylib_verify/config。',
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
                'purpose' => '按 dylib_key 获取签名后的运行配置、业务 API Endpoint、Verify Path 与配置版本。',
                'when' => 'Dylib 启动并准备在线验证之前调用；客户端可缓存 Last-Known-Good。',
                'params' => [
                    ['name' => 'dylib_key', 'required' => true, 'description' => '验证中心登记的 Dylib Key'],
                ],
                'next' => '从配置得到 Endpoint/Verify Path 后准备 canonical、signature，再 POST Verify。',
            ],
            [
                'key' => 'verify',
                'title' => 'Dylib 在线验证',
                'method' => 'POST',
                'path' => $verifyPath,
                'response_type' => 'JSON',
                'client_recommended' => true,
                'purpose' => 'Dylib 核心在线验证：校验设备、App 身份、Dylib Key/版本/Build/SHA256、时间戳、Nonce 与 HMAC。',
                'when' => '取得 Runtime Config 后调用；支持 form-urlencoded 或 application/json。',
                'params' => DylibApiContract::verifyRequestFields(),
                'next' => '客户端先判断 ok，再按 code + action 处理；message 只用于展示，不用于业务条件判断。',
            ],
        ];
    }

    public static function machineDocument($dylibKey, $dylibName, $verifyPath, array $runtimeConfig = [])
    {
        return [
            'schema_version' => 1,
            'generated_for' => [
                'dylib_key' => (string)$dylibKey,
                'dylib_name' => (string)$dylibName,
            ],
            'security' => [
                'verify_secret' => '<VERIFY_SECRET>',
                'note' => '真实 Verify Secret 不会写入文档 ZIP。请在受控客户端工程中配置。',
            ],
            'runtime' => [
                'verify_path' => (string)$verifyPath,
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
            . "安全：本包永远不会导出真实 Verify Secret；示例中的 `<VERIFY_SECRET>` 必须由受控工程自行配置。\n";
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
        return "# Signature Protocol\n\n"
            . "算法：`hex_lowercase(HMAC-SHA256(canonical, <VERIFY_SECRET>))`\n\n"
            . "## Protocol v1 canonical\n\n```text\n" . DylibApiContract::canonicalV1() . "\n```\n\n"
            . "## Protocol v2 canonical\n\n```text\n" . DylibApiContract::canonicalV2() . "\n```\n\n"
            . "字段之间使用真实换行符。不要对 JSON key 排序后签名，也不要改变字段顺序。每次请求生成新的 nonce。\n";
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
            . "## 首次卡密激活\n\n```text\n用户输入卡密\n  ↓\n网页可用 POST /authorization 查询\n  ↓\n未激活时 GET /appstore?udid=...&code=...\n  ↓\n绑定/激活\n  ↓\nGET /index/index/apiface?udid=...（旧授权兼容检查）\n```\n\n"
            . "## Dylib 2412+ 验证\n\n```text\nDylib 启动\n  ↓\nGET /index/dylib_verify/config?dylib_key=...\n  ↓\n准备 timestamp + nonce + canonical + HMAC\n  ↓\nPOST Verify Path\n  ↓\nok → code → action → message / permissions / notice / app_update\n```\n";
    }

    protected static function curlExamples($key, $verifyPath)
    {
        return "# cURL Examples\n\n"
            . "```bash\ncurl -G 'https://YOUR_HOST/index/dylib_verify/config' --data-urlencode 'dylib_key=" . $key . "'\n```\n\n"
            . "```bash\ncurl -X POST 'https://YOUR_HOST" . $verifyPath . "' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"udid\":\"<UDID>\",\"bundle_id\":\"com.example.app\",\"dylib_key\":\"" . $key . "\",\"dylib_version\":\"1.0.0\",\"timestamp\":0,\"nonce\":\"<NONCE>\",\"signature\":\"<SIGNATURE>\"}'\n```\n";
    }

    protected static function objectiveCExamples($key, $verifyPath)
    {
        return "# Objective-C Reference\n\n```objc\n// 示例仅展示请求边界；<VERIFY_SECRET> 由受控工程自行配置。\nNSString *path = @\"" . addslashes($verifyPath) . "\";\nNSString *dylibKey = @\"" . addslashes($key) . "\";\n// 1) 生成 timestamp + nonce\n// 2) 按 SIGNATURE.md 固定字段顺序构造 canonical\n// 3) HMAC-SHA256 -> lowercase hex\n// 4) POST JSON 到 path\n// 5) 先判断 ok，再处理 code/action；message 只用于展示\n```\n";
    }

    protected static function swiftExamples($key, $verifyPath)
    {
        return "# Swift Reference\n\n```swift\nlet dylibKey = \"" . addslashes($key) . "\"\nlet verifyPath = \"" . addslashes($verifyPath) . "\"\n// 构造 canonical -> HMAC-SHA256 -> POST JSON。\n// 使用 ok + code + action 做业务判断。\n```\n";
    }

    protected static function pythonExamples($key, $verifyPath)
    {
        return "# Python Reference\n\n```python\nimport hashlib, hmac\nsecret = b'<VERIFY_SECRET>'\ncanonical = '<按 SIGNATURE.md 拼接>'.encode()\nsignature = hmac.new(secret, canonical, hashlib.sha256).hexdigest()\n# POST https://YOUR_HOST" . $verifyPath . "\n# dylib_key = '" . addslashes($key) . "'\n```\n";
    }
}
