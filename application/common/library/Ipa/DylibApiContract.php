<?php

namespace app\common\library\Ipa;

/**
 * Canonical public documentation contract for the Dylib verification APIs.
 *
 * This class documents the protocol that already exists in
 * DylibVerify/DylibVerificationService. It does not own client UI and it does
 * not change the wire format. Client projects decide how to collect UDID/card
 * data and how to present messages; the verification center only defines the
 * API contract and examples.
 */
class DylibApiContract
{
    public static function verifyRequestFields()
    {
        return [
            ['name' => 'udid', 'type' => 'string', 'required' => true, 'since' => 'v1', 'description' => '调用方提供的设备标识；验证中心不负责采集或展示。'],
            ['name' => 'bundle_id', 'type' => 'string', 'required' => true, 'since' => 'v1', 'description' => '当前 App Bundle Identifier。'],
            ['name' => 'dylib_key', 'type' => 'string', 'required' => true, 'since' => 'v1', 'description' => '验证中心登记的 Dylib 唯一 Key。'],
            ['name' => 'dylib_version', 'type' => 'string', 'required' => true, 'since' => 'v1', 'description' => 'Dylib 版本号。'],
            ['name' => 'dylib_build', 'type' => 'string', 'required' => false, 'since' => 'v1', 'description' => '内部构建号；填写后服务端按 version + build 精确匹配。'],
            ['name' => 'dylib_sha256', 'type' => 'string', 'required' => false, 'since' => 'v1', 'description' => 'Dylib 文件 SHA256；版本登记了指纹时必须匹配。'],
            ['name' => 'timestamp', 'type' => 'int', 'required' => true, 'since' => 'v1', 'description' => 'Unix 秒级时间戳；超出服务端允许时差会返回 timestamp_invalid。'],
            ['name' => 'nonce', 'type' => 'string', 'required' => true, 'since' => 'v1', 'description' => '16~128 字符随机串；同一有效 nonce 重放会返回 replay_detected。'],
            ['name' => 'signature', 'type' => 'string', 'required' => true, 'since' => 'v1', 'description' => '64 位小写十六进制 HMAC-SHA256。'],
            ['name' => 'protocol_version', 'type' => 'int', 'required' => false, 'since' => 'v2', 'description' => '协议版本；v2 使用值 2。'],
            ['name' => 'app_executable', 'type' => 'string', 'required' => false, 'since' => 'v2', 'description' => 'v2 必填：当前主程序可执行文件名。'],
            ['name' => 'app_macho_uuid', 'type' => 'string', 'required' => false, 'since' => 'v2', 'description' => 'v2 必填：当前主程序 Mach-O UUID。'],
            ['name' => 'app_version', 'type' => 'string', 'required' => false, 'since' => 'v2', 'description' => '当前 App 对外版本，用于 app_update 判断。'],
            ['name' => 'app_build', 'type' => 'string', 'required' => false, 'since' => 'v2', 'description' => '当前 App Build，用于 app_update 判断。'],
        ];
    }

    public static function verifyResponseFields()
    {
        return [
            ['name' => 'ok', 'type' => 'bool', 'description' => '验证是否通过。'],
            ['name' => 'code', 'type' => 'string', 'description' => '稳定业务结果码；客户端应优先按该字段分支处理。'],
            ['name' => 'action', 'type' => 'string', 'description' => '服务端建议动作：allow / disable_feature / show_message / block。'],
            ['name' => 'offline_grace_seconds', 'type' => 'int', 'description' => '允许客户端离线使用缓存结果的秒数。'],
            ['name' => 'token', 'type' => 'string', 'description' => '验证通过时返回的短期签名会话 token；失败通常为空。'],
            ['name' => 'message', 'type' => 'string', 'description' => '服务端可展示消息；客户端可原样提示，但不要靠 message 文本判断业务状态。'],
            ['name' => 'server_time', 'type' => 'int', 'description' => '服务端 Unix 时间。'],
            ['name' => 'protocol_version', 'type' => 'int', 'description' => '本次验证采用的协议版本。'],
            ['name' => 'access_level', 'type' => 'string', 'description' => '服务端解析后的授权等级。'],
            ['name' => 'permissions', 'type' => 'object', 'description' => '服务端最终权限集合；客户端消费结果，不自行推导卡类型。'],
            ['name' => 'app_identity', 'type' => 'object', 'description' => '服务端解析出的 App 身份信息。'],
            ['name' => 'app_update', 'type' => 'object', 'description' => 'App 更新提示数据；是否展示由客户端决定。'],
            ['name' => 'notice', 'type' => 'object|null', 'description' => '远程通知数据；验证中心只返回内容，不负责客户端弹窗。'],
        ];
    }

    public static function errorCodes()
    {
        return [
            ['code' => 'bad_request', 'ok' => false, 'meaning' => '必填请求参数缺失', 'client' => '检查请求字段；可直接展示 message。'],
            ['code' => 'method_not_allowed', 'ok' => false, 'meaning' => '验证接口不是 POST', 'client' => '改用 POST，不应重试原请求。'],
            ['code' => 'timestamp_invalid', 'ok' => false, 'meaning' => '请求时间超出允许窗口', 'client' => '校准时间并重新签名请求。'],
            ['code' => 'nonce_invalid', 'ok' => false, 'meaning' => 'Nonce 长度或格式无效', 'client' => '重新生成 16~128 字符随机 nonce。'],
            ['code' => 'signature_invalid', 'ok' => false, 'meaning' => 'signature 格式不是 64 位小写 hex', 'client' => '检查 HMAC 输出格式。'],
            ['code' => 'signature_mismatch', 'ok' => false, 'meaning' => 'HMAC 与服务端计算结果不一致', 'client' => '检查 canonical 字段顺序、值和 Verify Secret。'],
            ['code' => 'replay_detected', 'ok' => false, 'meaning' => 'Nonce 已使用，检测到重放', 'client' => '生成新 nonce、新 timestamp 并重新签名。'],
            ['code' => 'dylib_unknown', 'ok' => false, 'meaning' => 'Dylib Key 不存在或 Dylib 已停用', 'client' => '停止受保护功能并展示 message。'],
            ['code' => 'dylib_key_unconfigured', 'ok' => false, 'meaning' => '当前 Dylib 未配置验证密钥', 'client' => '属于服务端配置问题，不应循环重试。'],
            ['code' => 'dylib_key_unavailable', 'ok' => false, 'meaning' => '验证密钥当前不可解密/不可用', 'client' => '属于服务端配置问题，按 action 处理。'],
            ['code' => 'blacklisted', 'ok' => false, 'meaning' => '设备在服务端黑名单中', 'client' => '按 action 处理并展示 message。'],
            ['code' => 'app_identity_incomplete', 'ok' => false, 'meaning' => 'Protocol v2 缺少 executable 或 Mach-O UUID', 'client' => '补齐 v2 App 身份字段后重新签名。'],
            ['code' => 'app_identity_unknown', 'ok' => false, 'meaning' => '服务端无法识别当前 App 身份', 'client' => '检查 BundleID / executable / Mach-O UUID。'],
            ['code' => 'app_identity_unbound', 'ok' => false, 'meaning' => '解析到的 App 尚未绑定服务端分类', 'client' => '属于后台数据配置问题。'],
            ['code' => 'app_identity_ambiguous', 'ok' => false, 'meaning' => 'App 身份匹配到多个候选', 'client' => '属于后台数据问题，客户端按 action 处理。'],
            ['code' => 'app_identity_inactive', 'ok' => false, 'meaning' => '对应 App 已停用或不可用', 'client' => '按 action 处理。'],
            ['code' => 'app_identity_required', 'ok' => false, 'meaning' => '当前授权范围要求 v2 App 身份', 'client' => '升级为 Protocol v2 请求。'],
            ['code' => 'app_not_authorized', 'ok' => false, 'meaning' => '当前授权不适用于该 App', 'client' => '展示 message；不要在客户端自行放宽范围。'],
            ['code' => 'license_invalid', 'ok' => false, 'meaning' => '设备授权无效或已过期', 'client' => '客户端可提示用户重新授权。'],
            ['code' => 'version_unknown', 'ok' => false, 'meaning' => 'Dylib 版本未登记', 'client' => '检查 dylib_version/build 是否与后台登记一致。'],
            ['code' => 'version_blocked', 'ok' => false, 'meaning' => 'Dylib 版本已阻止', 'client' => '按 action 处理并展示版本 notice/message。'],
            ['code' => 'version_revoked', 'ok' => false, 'meaning' => 'Dylib 版本已撤销', 'client' => '按 action 处理并展示版本 notice/message。'],
            ['code' => 'integrity_mismatch', 'ok' => false, 'meaning' => 'Dylib SHA256 与登记值不一致', 'client' => '停止受保护功能并检查加载文件。'],
            ['code' => 'server_error', 'ok' => false, 'meaning' => '验证服务异常', 'client' => '按 action/offline_grace_seconds 处理临时故障。'],
            ['code' => 'config_unavailable', 'ok' => false, 'meaning' => 'Bootstrap/运行配置暂不可用', 'client' => '使用 Last-Known-Good 或 endpointURL 兜底。'],
            ['code' => 'ok', 'ok' => true, 'meaning' => '正式版本验证通过', 'client' => '消费 permissions / token / notice / app_update。'],
            ['code' => 'ok_testing', 'ok' => true, 'meaning' => '测试版本验证通过', 'client' => '与 ok 一样允许，但可记录测试状态。'],
            ['code' => 'ok_deprecated', 'ok' => true, 'meaning' => '已弃用版本仍允许验证', 'client' => '允许使用，可按 message 提示升级。'],
        ];
    }

    public static function actions()
    {
        return [
            'allow' => '允许继续使用',
            'disable_feature' => '禁用受保护功能',
            'show_message' => '只显示服务端提示',
            'block' => '完全阻止受保护能力',
        ];
    }

    public static function canonicalV1()
    {
        return "udid\nbundle_id\ndylib_key\ndylib_version\ndylib_build\ndylib_sha256\ntimestamp\nnonce";
    }

    public static function canonicalV2()
    {
        return self::canonicalV1() . "\nprotocol_version\napp_executable\napp_macho_uuid\napp_version\napp_build";
    }

    public static function document($dylibKey = '', $version = '', $build = '', $verifyPath = '/index/dylib_verify/verify')
    {
        return [
            'scope' => 'API only; client UI is outside the Dylib verification center',
            'dylib_key' => (string)$dylibKey,
            'dylib_version' => (string)$version,
            'dylib_build' => (string)$build,
            'endpoints' => [
                'runtime_config' => ['method' => 'GET', 'path' => '/index/dylib_verify/config', 'query' => ['dylib_key']],
                'verify' => ['method' => 'POST', 'path' => (string)$verifyPath, 'content_types' => ['application/x-www-form-urlencoded', 'application/json']],
            ],
            'request_fields' => self::verifyRequestFields(),
            'response_fields' => self::verifyResponseFields(),
            'canonical_v1' => self::canonicalV1(),
            'canonical_v2' => self::canonicalV2(),
            'signature' => 'hex_lowercase(HMAC-SHA256(canonical, verify_secret))',
            'actions' => self::actions(),
            'result_codes' => self::errorCodes(),
        ];
    }
}
