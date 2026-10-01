<?php

namespace app\common\library\Ipa;

class DylibApiDocumentation
{
    public static function catalog($verifyPath='/index/dylib_verify/verify')
    {
        $verifyPath=trim((string)$verifyPath); if($verifyPath===''||$verifyPath[0]!=='/')$verifyPath='/index/dylib_verify/verify';
        return [
            ['key'=>'authorization','title'=>'卡密授权查询','method'=>'POST','path'=>'/authorization','response_type'=>'HTML 页面兼容入口','client_recommended'=>false,'purpose'=>'查询指定卡密与 UDID 授权状态。','when'=>'网页自助查询时使用。','params'=>[['name'=>'code','required'=>true,'description'=>'卡密'],['name'=>'udid','required'=>true,'description'=>'设备 UDID']],'next'=>'首次激活使用 /appstore。'],
            ['key'=>'appstore','title'=>'卡密激活 / 软件源刷新','method'=>'GET','path'=>'/appstore','response_type'=>'JSON/软件源响应','client_recommended'=>true,'purpose'=>'激活卡密或刷新软件源。','when'=>'首次激活或刷新时调用。','params'=>[['name'=>'udid','required'=>true,'description'=>'设备 UDID'],['name'=>'code','required'=>false,'description'=>'激活时提供卡密']],'next'=>'受保护 Dylib 使用 Protocol v3。'],
            ['key'=>'runtime_config','title'=>'Dylib Runtime Config','method'=>'GET','path'=>'/index/dylib_verify/config','response_type'=>'JSON','client_recommended'=>true,'purpose'=>'获取 P-256 签名后的 Endpoint/Challenge/Verify 配置。','when'=>'Dylib 在线验证前调用。','params'=>[['name'=>'dylib_key','required'=>true,'description'=>'Dylib Key']],'next'=>'用客户端内置服务端公钥验证签名，然后请求 Challenge。'],
            ['key'=>'challenge','title'=>'设备 Challenge','method'=>'POST','path'=>'/index/dylib_verify/challenge','response_type'=>'JSON','client_recommended'=>true,'purpose'=>'创建一次性 60 秒设备认证 Challenge。','when'=>'每次在线验证前调用。','params'=>[['name'=>'udid','required'=>true,'description'=>'设备 UDID'],['name'=>'dylib_key','required'=>true,'description'=>'Dylib Key'],['name'=>'device_public_key','required'=>true,'description'=>'P-256 SPKI PEM 公钥']],'next'=>'使用本机私钥签 v3 canonical body。'],
            ['key'=>'verify','title'=>'Dylib 在线验证','method'=>'POST','path'=>$verifyPath,'response_type'=>'JSON','client_recommended'=>true,'purpose'=>'验证设备签名、授权、App 身份、Dylib 版本及 SHA256。','when'=>'获取 Challenge 并签名后调用。','params'=>DylibApiContract::verifyRequestFields(),'next'=>'先判断 ok，再处理 code/action；成功后消费 token/permissions/notice/app_update。'],
        ];
    }

    public static function machineDocument($dylibKey,$dylibName,$verifyPath,array $runtimeConfig=[])
    {
        return [
            'schema_version'=>3,
            'generated_for'=>['dylib_key'=>(string)$dylibKey,'dylib_name'=>(string)$dylibName],
            'security'=>[
                'protocol'=>'secretless-device-key-v3','device_signature'=>'ECDSA P-256 SHA-256','runtime_config_signature'=>'ECDSA P-256 SHA-256',
                'note'=>'客户端不包含 Verify Secret 或其他全局共享对称密钥；首次设备密钥绑定才提交当前有效卡密。',
            ],
            'runtime'=>['verify_path'=>(string)$verifyPath,'challenge_path'=>'/index/dylib_verify/challenge','config_version'=>isset($runtimeConfig['config_version'])?(int)$runtimeConfig['config_version']:3],
            'apis'=>self::catalog($verifyPath),'verify_contract'=>DylibApiContract::document($dylibKey,'','',$verifyPath),
        ];
    }

    public static function exportFiles($dylibKey,$dylibName,$verifyPath,array $runtimeConfig=[])
    {
        $doc=self::machineDocument($dylibKey,$dylibName,$verifyPath,$runtimeConfig);$apis=$doc['apis'];
        return [
            'README.md'=>self::readme($dylibKey,$dylibName),
            'API_OVERVIEW.md'=>self::overviewMarkdown($apis),
            'API_REFERENCE.md'=>self::referenceMarkdown($apis),
            'ERROR_CODES.md'=>self::errorMarkdown(),
            'SIGNATURE.md'=>self::signatureMarkdown(),
            'RESPONSE_MODEL.md'=>self::responseMarkdown(),
            'FLOW.md'=>self::flowMarkdown(),
            'examples/Objective-C.md'=>self::objectiveCExamples($dylibKey,$verifyPath),
            'schemas/api.json'=>json_encode($doc,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n",
            'schemas/error_codes.json'=>json_encode(DylibApiContract::errorCodes(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n",
        ];
    }

    protected static function readme($key,$name){return "# Dylib API Integration\n\nDylib: ".($name!==''?$name:$key)." (`{$key}`)\n\nProtocol v3 不再使用 Verify Secret。客户端本机生成 P-256 密钥对，私钥留在 Keychain；服务端只保存公钥。\n";}
    protected static function overviewMarkdown(array $apis){$out="# API Overview\n\n";foreach($apis as $i=>$api)$out.=($i+1).". **{$api['title']}** — `{$api['method']} {$api['path']}`\n";return $out;}
    protected static function referenceMarkdown(array $apis){$out="# API Reference\n\n";foreach($apis as $i=>$api){$out.="## ".($i+1).". {$api['title']}\n\n- 方法：`{$api['method']}`\n- 路径：`{$api['path']}`\n- 用途：{$api['purpose']}\n\n| 参数 | 必填 | 说明 |\n|---|---|---|\n";foreach($api['params'] as $f)$out.='| `'.$f['name'].'` | '.(!empty($f['required'])?'是':'否').' | '.str_replace('|','\\|',isset($f['description'])?$f['description']:'')." |\n";$out.="\n**下一步：** {$api['next']}\n\n";}return $out;}
    protected static function errorMarkdown(){$out="# Dylib Verify Result Codes\n\n| code | ok | 含义 | 客户端建议 |\n|---|---:|---|---|\n";foreach(DylibApiContract::errorCodes() as $r)$out.='| `'.$r['code'].'` | '.($r['ok']?'true':'false').' | '.$r['meaning'].' | '.$r['client']." |\n";return $out;}
    protected static function signatureMarkdown(){return "# Signature Protocol v3\n\n算法：`ECDSA P-256 SHA-256`。\n\n```text\n".DylibApiContract::canonicalV3()."\n```\n\n客户端对以上真实换行拼接文本使用本机 Device Private Key 签名，发送 Base64 DER signature。Challenge 一次性使用且有效期很短。\n\nRuntime Config 使用独立 Server Private Key 签名；客户端仅持有 Server Public Key。\n";}
    protected static function responseMarkdown(){$out="# Verify Response Model\n\n| 字段 | 类型 | 说明 |\n|---|---|---|\n";foreach(DylibApiContract::verifyResponseFields() as $f)$out.='| `'.$f['name'].'` | '.$f['type'].' | '.$f['description']." |\n";return $out;}
    protected static function flowMarkdown(){return "# Recommended Flow\n\n```text\n生成/加载 Device P-256 KeyPair\n  ↓\nGET Runtime Config + Server Public Key 验签\n  ↓\nPOST Challenge\n  ↓\nDevice Private Key 签 v3 canonical\n  ↓\nPOST Verify\n  ↓\n首次绑定：额外提交当前有效 license_code\n  ↓\nServer 验设备签名 + 授权 + App + Version + SHA256\n  ↓\n返回短期 Session Token / permissions / notice / app_update\n```\n";}
    protected static function objectiveCExamples($key,$verifyPath){return "# Objective-C Reference\n\n建议直接使用验证中心生成的 `ZONVerifyClient` / 项目前缀版本。\n\n```objc\n// 私钥由 Security.framework 生成并持久化到 Keychain。\n// SecKeyCreateSignature(..., kSecKeyAlgorithmECDSASignatureMessageX962SHA256, ...)\n// 首次 enrollment_required=true 时从宿主项目提供当前卡密。\nNSString *dylibKey = @\"".addslashes($key)."\";\nNSString *verifyPath = @\"".addslashes($verifyPath)."\";\n```\n";}
}
