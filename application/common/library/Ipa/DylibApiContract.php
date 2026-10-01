<?php

namespace app\common\library\Ipa;

/**
 * Canonical public documentation contract for the Dylib verification APIs.
 * Client projects still own UI/input flows; the verification center defines
 * endpoint, authentication and response contracts only.
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
            ['name' => 'protocol_version', 'type' => 'int', 'required' => true, 'since' => 'v3', 'description' => '2430 Secretless Auth 固定使用 3。'],
            ['name' => 'app_executable', 'type' => 'string', 'required' => true, 'since' => 'v2', 'description' => '当前主程序可执行文件名。'],
            ['name' => 'app_macho_uuid', 'type' => 'string', 'required' => true, 'since' => 'v2', 'description' => '当前主程序 Mach-O UUID。'],
            ['name' => 'app_version', 'type' => 'string', 'required' => false, 'since' => 'v2', 'description' => '当前 App 对外版本，用于 app_update 判断。'],
            ['name' => 'app_build', 'type' => 'string', 'required' => false, 'since' => 'v2', 'description' => '当前 App Build，用于 app_update 判断。'],
            ['name' => 'challenge_id', 'type' => 'string', 'required' => true, 'since' => 'v3', 'description' => '由 challenge API 返回的一次性 Challenge ID。'],
            ['name' => 'challenge', 'type' => 'string', 'required' => true, 'since' => 'v3', 'description' => '由 challenge API 返回的一次性随机值。'],
            ['name' => 'device_public_key', 'type' => 'PEM', 'required' => true, 'since' => 'v3', 'description' => '设备 P-256 公钥；私钥保存在设备 Keychain。'],
            ['name' => 'device_signature', 'type' => 'base64 DER', 'required' => true, 'since' => 'v3', 'description' => '设备私钥对 canonical proof 的 ECDSA-SHA256 签名。'],
            ['name' => 'license_code', 'type' => 'string', 'required' => false, 'since' => 'v3', 'description' => '仅首次设备密钥绑定时需要，用于把新公钥绑定到现有授权链。'],
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
            ['name' => 'device_key_id', 'type' => 'string', 'description' => '服务端已绑定设备公钥的 Key ID。'],
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
            ['code' => 'method_not_allowed', 'ok' => false, 'meaning' => '接口请求方法错误', 'client' => '按 API 文档改用正确方法。'],
            ['code' => 'protocol_unsupported', 'ok' => false, 'meaning' => '客户端协议版本低于 v3', 'client' => '更新为 2430 生成的 Secretless 客户端。'],
            ['code' => 'challenge_unavailable', 'ok' => false, 'meaning' => 'Challenge 服务暂不可用', 'client' => '尝试备用 endpoint 或 offline grace。'],
            ['code' => 'challenge_expired', 'ok' => false, 'meaning' => '一次性 Challenge 已过期', 'client' => '重新请求 Challenge 后再次签名。'],
            ['code' => 'challenge_replayed', 'ok' => false, 'meaning' => 'Challenge 已使用或不存在', 'client' => '重新获取 Challenge；不要复用旧证明。'],
            ['code' => 'challenge_mismatch', 'ok' => false, 'meaning' => 'Challenge 与 UDID/Dylib/公钥上下文不匹配', 'client' => '丢弃本次 Challenge，重新开始认证。'],
            ['code' => 'device_auth_invalid', 'ok' => false, 'meaning' => '设备认证字段或公钥无效', 'client' => '检查 P-256 公钥、Challenge 和签名字段。'],
            ['code' => 'device_signature_invalid', 'ok' => false, 'meaning' => '设备签名验证失败', 'client' => '检查 canonical proof 和设备私钥。'],
            ['code' => 'device_key_mismatch', 'ok' => false, 'meaning' => 'UDID 已绑定其它设备公钥', 'client' => '需要后台撤销旧设备密钥后重新绑定。'],
            ['code' => 'device_enrollment_denied', 'ok' => false, 'meaning' => '首次设备公钥绑定没有通过现有授权链', 'client' => '提供有效卡密/授权后重新绑定。'],
            ['code' => 'dylib_unknown', 'ok' => false, 'meaning' => 'Dylib Key 不存在或 Dylib 已停用', 'client' => '停止受保护功能并展示 message。'],
            ['code' => 'blacklisted', 'ok' => false, 'meaning' => '设备在服务端黑名单中', 'client' => '按 action 处理并展示 message。'],
            ['code' => 'app_identity_incomplete', 'ok' => false, 'meaning' => '缺少 executable 或 Mach-O UUID', 'client' => '补齐 App 身份字段后重新认证。'],
            ['code' => 'app_identity_unknown', 'ok' => false, 'meaning' => '服务端无法识别当前 App 身份', 'client' => '检查 BundleID / executable / Mach-O UUID。'],
            ['code' => 'app_identity_unbound', 'ok' => false, 'meaning' => '解析到的 App 尚未绑定服务端分类', 'client' => '属于后台数据配置问题。'],
            ['code' => 'app_identity_ambiguous', 'ok' => false, 'meaning' => 'App 身份匹配到多个候选', 'client' => '属于后台数据问题，客户端按 action 处理。'],
            ['code' => 'app_identity_inactive', 'ok' => false, 'meaning' => '对应 App 已停用或不可用', 'client' => '按 action 处理。'],
            ['code' => 'app_identity_required', 'ok' => false, 'meaning' => '当前授权范围要求 App 身份', 'client' => '使用 v3 请求并提供完整 App Identity。'],
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

    public static function canonicalV3()
    {
        return "zonoe-dylib-auth-v3\nchallenge_id\nchallenge\nudid\nbundle_id\ndylib_key\ndylib_version\ndylib_build\ndylib_sha256\napp_executable\napp_macho_uuid\napp_version\napp_build";
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
                'challenge' => ['method' => 'POST', 'path' => '/index/dylib_verify/challenge', 'content_types' => ['application/x-www-form-urlencoded', 'application/json']],
                'verify' => ['method' => 'POST', 'path' => (string)$verifyPath, 'content_types' => ['application/x-www-form-urlencoded', 'application/json']],
            ],
            'request_fields' => self::verifyRequestFields(),
            'response_fields' => self::verifyResponseFields(),
            'canonical_v3' => self::canonicalV3(),
            'signature' => 'base64(ECDSA-P256-SHA256(canonical_v3, device_private_key)); Bootstrap uses RSA-2048-SHA256 server signature',
            'actions' => self::actions(),
            'result_codes' => self::errorCodes(),
        ];
    }
}
