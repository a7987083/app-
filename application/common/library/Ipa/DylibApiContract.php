<?php

namespace app\common\library\Ipa;

class DylibApiContract
{
    public static function verifyRequestFields()
    {
        return [
            ['name'=>'protocol_version','type'=>'int','required'=>true,'since'=>'v3','description'=>'固定为 3。'],
            ['name'=>'udid','type'=>'string','required'=>true,'since'=>'v3','description'=>'当前设备标识。'],
            ['name'=>'bundle_id','type'=>'string','required'=>true,'since'=>'v3','description'=>'当前 App Bundle Identifier。'],
            ['name'=>'dylib_key','type'=>'string','required'=>true,'since'=>'v3','description'=>'Dylib 唯一 Key。'],
            ['name'=>'dylib_version','type'=>'string','required'=>true,'since'=>'v3','description'=>'Dylib 版本号。'],
            ['name'=>'dylib_build','type'=>'string','required'=>false,'since'=>'v3','description'=>'内部构建号。'],
            ['name'=>'dylib_sha256','type'=>'string','required'=>false,'since'=>'v3','description'=>'Dylib SHA256。'],
            ['name'=>'app_executable','type'=>'string','required'=>true,'since'=>'v3','description'=>'当前主程序可执行文件名。'],
            ['name'=>'app_macho_uuid','type'=>'string','required'=>true,'since'=>'v3','description'=>'当前主程序 Mach-O UUID。'],
            ['name'=>'app_version','type'=>'string','required'=>false,'since'=>'v3','description'=>'当前 App 版本。'],
            ['name'=>'app_build','type'=>'string','required'=>false,'since'=>'v3','description'=>'当前 App Build。'],
            ['name'=>'challenge_id','type'=>'string','required'=>true,'since'=>'v3','description'=>'服务端一次性 Challenge ID。'],
            ['name'=>'challenge','type'=>'string','required'=>true,'since'=>'v3','description'=>'服务端返回的一次性随机 Challenge。'],
            ['name'=>'device_public_key','type'=>'PEM','required'=>true,'since'=>'v3','description'=>'客户端 P-256 公钥。'],
            ['name'=>'device_signature','type'=>'base64','required'=>true,'since'=>'v3','description'=>'P-256 ECDSA-SHA256 Challenge 签名。'],
            ['name'=>'license_code','type'=>'string','required'=>false,'since'=>'v3','description'=>'仅首次设备公钥绑定时要求，绑定后无需再次发送。'],
        ];
    }

    public static function verifyResponseFields()
    {
        return [
            ['name'=>'ok','type'=>'bool','description'=>'验证是否通过。'],
            ['name'=>'code','type'=>'string','description'=>'稳定业务结果码。'],
            ['name'=>'action','type'=>'string','description'=>'allow / disable_feature / show_message / block。'],
            ['name'=>'offline_grace_seconds','type'=>'int','description'=>'离线缓存允许时间。'],
            ['name'=>'token','type'=>'string','description'=>'短期 Session Token。'],
            ['name'=>'server_time','type'=>'int','description'=>'服务端 Unix 时间。'],
            ['name'=>'protocol_version','type'=>'int','description'=>'固定为 3。'],
            ['name'=>'device_key_id','type'=>'string','description'=>'已绑定设备公钥标识。'],
            ['name'=>'access_level','type'=>'string','description'=>'授权等级。'],
            ['name'=>'permissions','type'=>'object','description'=>'最终权限。'],
            ['name'=>'app_identity','type'=>'object','description'=>'解析后的 App 身份。'],
            ['name'=>'app_update','type'=>'object','description'=>'App 更新信息。'],
            ['name'=>'notice','type'=>'object|null','description'=>'远程通知。'],
        ];
    }

    public static function errorCodes()
    {
        return [
            ['code'=>'challenge_unavailable','ok'=>false,'meaning'=>'Challenge 服务不可用','client'=>'稍后重试或使用有效离线缓存。'],
            ['code'=>'challenge_expired','ok'=>false,'meaning'=>'Challenge 已过期','client'=>'重新申请 Challenge。'],
            ['code'=>'challenge_replayed','ok'=>false,'meaning'=>'Challenge 已消费/重放','client'=>'重新申请 Challenge。'],
            ['code'=>'challenge_mismatch','ok'=>false,'meaning'=>'Challenge 与设备/Dylib/公钥上下文不匹配','client'=>'停止当前请求并重新开始认证。'],
            ['code'=>'device_signature_invalid','ok'=>false,'meaning'=>'设备签名无效','client'=>'检查本机设备私钥。'],
            ['code'=>'device_key_mismatch','ok'=>false,'meaning'=>'公钥与已绑定设备密钥不一致','client'=>'禁止自动覆盖；由后台撤销旧绑定后重新注册。'],
            ['code'=>'device_enrollment_denied','ok'=>false,'meaning'=>'首次设备密钥绑定未通过卡密校验','client'=>'要求用户提供当前有效卡密。'],
            ['code'=>'dylib_unknown','ok'=>false,'meaning'=>'Dylib 不存在或已停用','client'=>'停止受保护功能。'],
            ['code'=>'blacklisted','ok'=>false,'meaning'=>'设备被封禁','client'=>'按 action 处理。'],
            ['code'=>'license_invalid','ok'=>false,'meaning'=>'设备授权无效或已过期','client'=>'提示重新授权。'],
            ['code'=>'app_not_authorized','ok'=>false,'meaning'=>'授权不适用于当前 App','client'=>'按 action 处理。'],
            ['code'=>'version_unknown','ok'=>false,'meaning'=>'Dylib 版本未登记','client'=>'检查版本登记。'],
            ['code'=>'version_blocked','ok'=>false,'meaning'=>'版本已阻止','client'=>'按 action 处理。'],
            ['code'=>'version_revoked','ok'=>false,'meaning'=>'版本已撤销','client'=>'按 action 处理。'],
            ['code'=>'integrity_mismatch','ok'=>false,'meaning'=>'Dylib 指纹不一致','client'=>'停止受保护功能。'],
            ['code'=>'ok','ok'=>true,'meaning'=>'正式版本验证通过','client'=>'消费 permissions/token/notice/app_update。'],
            ['code'=>'ok_testing','ok'=>true,'meaning'=>'测试版本验证通过','client'=>'允许并记录测试状态。'],
            ['code'=>'ok_deprecated','ok'=>true,'meaning'=>'已弃用版本仍允许','client'=>'允许并可提示升级。'],
        ];
    }

    public static function actions()
    {
        return ['allow'=>'允许继续使用','disable_feature'=>'禁用受保护功能','show_message'=>'只显示服务端提示','block'=>'完全阻止受保护能力'];
    }

    public static function canonicalV3()
    {
        return "zonoe-dylib-auth-v3\nchallenge_id\nchallenge\nudid\nbundle_id\ndylib_key\ndylib_version\ndylib_build\ndylib_sha256\napp_executable\napp_macho_uuid\napp_version\napp_build";
    }

    public static function document($dylibKey='',$version='',$build='',$verifyPath='/index/dylib_verify/verify')
    {
        return [
            'scope'=>'API only; client UI is outside the Dylib verification center',
            'protocol_version'=>3,'dylib_key'=>(string)$dylibKey,'dylib_version'=>(string)$version,'dylib_build'=>(string)$build,
            'endpoints'=>[
                'runtime_config'=>['method'=>'GET','path'=>'/index/dylib_verify/config','query'=>['dylib_key']],
                'challenge'=>['method'=>'POST','path'=>'/index/dylib_verify/challenge'],
                'verify'=>['method'=>'POST','path'=>(string)$verifyPath],
            ],
            'request_fields'=>self::verifyRequestFields(),'response_fields'=>self::verifyResponseFields(),
            'canonical_v3'=>self::canonicalV3(),'signature'=>'ECDSA P-256 SHA-256 with device private key',
            'bootstrap_signature'=>'ECDSA P-256 SHA-256 with server private key; client embeds server public key only',
            'actions'=>self::actions(),'result_codes'=>self::errorCodes(),
        ];
    }
}
