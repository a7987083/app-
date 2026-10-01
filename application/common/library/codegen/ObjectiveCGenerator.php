<?php

namespace app\common\library\codegen;

class ObjectiveCGenerator
{
    const GENERATOR_VERSION = '3.0.0';

    public function buildConfig(array $dylib, array $runtimeConfig, array $version, array $draft = [], $domain = '')
    {
        $prefix = isset($draft['class_prefix']) ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$draft['class_prefix'])) : 'ZON';
        $prefix = substr($prefix, 0, 8);
        if (!preg_match('/^[A-Z][A-Z0-9]{1,7}$/', $prefix)) $prefix = 'ZON';
        $deploymentTarget = isset($draft['deployment_target']) ? trim((string)$draft['deployment_target']) : '13.0';
        if (!preg_match('/^(1[3-9]|2[0-6])(?:\.\d+)?$/', $deploymentTarget)) $deploymentTarget = '13.0';
        $timeout = max(3, min(120, isset($draft['timeout']) ? (int)$draft['timeout'] : 10));
        $apiEndpoints = $this->decodeUrlList(isset($runtimeConfig['api_endpoints_json']) ? $runtimeConfig['api_endpoints_json'] : '[]');
        $bootstrapUrls = $this->decodeUrlList(isset($runtimeConfig['bootstrap_urls_json']) ? $runtimeConfig['bootstrap_urls_json'] : '[]');
        if (!$apiEndpoints && $domain !== '') $apiEndpoints[] = rtrim((string)$domain, '/');
        $verifyPath = isset($runtimeConfig['verify_path']) ? trim((string)$runtimeConfig['verify_path']) : '/index/dylib_verify/verify';
        if ($verifyPath === '' || $verifyPath[0] !== '/') $verifyPath = '/' . ltrim($verifyPath, '/');
        $challengePath = '/index/dylib_verify/challenge';
        return [
            'generator_version'=>self::GENERATOR_VERSION,'class_prefix'=>$prefix,'deployment_target'=>$deploymentTarget,'timeout'=>$timeout,
            'dylib_id'=>isset($dylib['id'])?(int)$dylib['id']:0,'dylib_name'=>isset($dylib['name'])?(string)$dylib['name']:'',
            'dylib_key'=>isset($dylib['dylib_key'])?(string)$dylib['dylib_key']:'','dylib_enabled'=>!empty($dylib['enabled']),
            'server_public_key'=>isset($dylib['server_public_key'])?(string)$dylib['server_public_key']:'',
            'server_key_id'=>isset($dylib['server_key_id'])?(string)$dylib['server_key_id']:'',
            'default_offline_grace'=>isset($dylib['default_offline_grace'])?(int)$dylib['default_offline_grace']:900,
            'default_fail_action'=>isset($dylib['default_fail_action'])?(string)$dylib['default_fail_action']:'disable_feature',
            'runtime_config_version'=>max(3,isset($runtimeConfig['config_version'])?(int)$runtimeConfig['config_version']:3),
            'api_endpoints'=>$apiEndpoints,'bootstrap_urls'=>$bootstrapUrls,'challenge_path'=>$challengePath,'verify_path'=>$verifyPath,
            'endpoint_url'=>$apiEndpoints?rtrim((string)$apiEndpoints[0],'/').$verifyPath:'',
            'version_id'=>isset($version['id'])?(int)$version['id']:0,'dylib_version'=>isset($version['version'])?(string)$version['version']:'',
            'dylib_build'=>isset($version['build'])?(string)$version['build']:'','version_state'=>isset($version['state'])?(string)$version['state']:'',
            'version_sha256'=>isset($version['sha256'])?(string)$version['sha256']:'',
            'version_offline_grace'=>isset($version['offline_grace'])?(int)$version['offline_grace']:(isset($dylib['default_offline_grace'])?(int)$dylib['default_offline_grace']:900),
            'version_fail_action'=>isset($version['fail_action'])?(string)$version['fail_action']:(isset($dylib['default_fail_action'])?(string)$dylib['default_fail_action']:'disable_feature'),
            'version_notice'=>isset($version['notice'])?(string)$version['notice']:'','protocol_version'=>3,
            'features'=>['secretless_auth'=>true,'device_key'=>true,'challenge'=>true,'runtime_config'=>true,'offline_cache'=>true,'permissions'=>true,'app_update'=>true,'notice'=>true],
        ];
    }

    public function validate(array $config)
    {
        $errors=[]; $warnings=[];
        if (empty($config['dylib_id'])||empty($config['dylib_key'])) $errors[]='请选择有效的 Dylib';
        if (empty($config['dylib_version'])) $errors[]='当前 Dylib 尚未登记版本，请先在版本控制中添加版本';
        if (empty($config['server_public_key'])||strpos((string)$config['server_public_key'],'BEGIN PUBLIC KEY')===false) $errors[]='服务端签名公钥不可用';
        if (empty($config['bootstrap_urls'])&&empty($config['endpoint_url'])) $errors[]='Bootstrap 和直连验证地址均为空，无法生成可工作的客户端';
        if (empty($config['bootstrap_urls'])) $warnings[]='未配置 Bootstrap URL，将主要依赖直连验证地址。';
        if (!empty($config['endpoint_url'])&&stripos((string)$config['endpoint_url'],'http://')===0) $warnings[]='验证地址使用 HTTP，iOS ATS 可能阻止明文请求。';
        if (empty($config['dylib_enabled'])) $warnings[]='当前 Dylib 已停用。';
        return ['valid'=>!$errors,'errors'=>$errors,'warnings'=>$warnings];
    }

    public function configHash(array $config) { return hash('sha256',$this->json($config)); }
    public function publicConfig(array $config) { return $config; }

    public function generate(array $config)
    {
        $validation=$this->validate($config);
        if (!$validation['valid']) throw new \InvalidArgumentException(implode('；',$validation['errors']));
        $prefix=$config['class_prefix'];
        $verifyHeader=$this->adaptVerifyTemplate($this->templateSource('ZONVerifyClient.h'),$prefix,false);
        $verifyImpl=$this->adaptVerifyTemplate($this->templateSource('ZONVerifyClient.m'),$prefix,true);
        $files=[
            $prefix.'DylibConfig.h'=>$this->configHeader($prefix),
            $prefix.'DylibConfig.m'=>$this->configImplementation($prefix,$config),
            $prefix.'DylibVerify.h'=>$verifyHeader,
            $prefix.'DylibVerify.m'=>$verifyImpl,
            'GeneratedConfig.json'=>$this->json($this->publicConfig($config))."\n",
            'INTEGRATION.md'=>$this->integrationGuide($prefix,$config),
            'API_REFERENCE.md'=>$this->apiReference($config),
            'ERROR_CODES.md'=>$this->errorCodeGuide(),
        ];
        $hashes=[]; foreach ($files as $path=>$content) $hashes[$path]=hash('sha256',$content);
        $files['generation-manifest.json']=$this->json(['generator'=>'zonoe-dylib-objective-c','generator_version'=>self::GENERATOR_VERSION,'config_sha256'=>$this->configHash($config),'dylib_id'=>$config['dylib_id'],'dylib_key'=>$config['dylib_key'],'version_id'=>$config['version_id'],'files'=>$hashes])."\n";
        return $files;
    }

    protected function templateSource($name)
    {
        $root=dirname(dirname(dirname(dirname(__DIR__))));
        $path=$root.DIRECTORY_SEPARATOR.'clients'.DIRECTORY_SEPARATOR.'ios'.DIRECTORY_SEPARATOR.'ZONDylibVerify'.DIRECTORY_SEPARATOR.$name;
        $content=@file_get_contents($path);
        if ($content===false||$content==='') throw new \RuntimeException('Dylib 验证客户端模板缺失：'.$name);
        return $content;
    }

    protected function adaptVerifyTemplate($content,$prefix,$implementation)
    {
        $map=['ZONUDIDProvider'=>$prefix.'DylibUDIDProvider','ZONLicenseCodeProvider'=>$prefix.'DylibLicenseCodeProvider','ZONVerifyConfiguration'=>$prefix.'DylibVerifyConfiguration','ZONVerifyResult'=>$prefix.'DylibVerifyResult','ZONVerifyClient'=>$prefix.'DylibVerify'];
        $content=str_replace(array_keys($map),array_values($map),$content);
        if ($implementation) $content=str_replace('#import "ZONVerifyClient.h"','#import "'.$prefix.'DylibVerify.h"',$content);
        return rtrim($content)."\n";
    }

    protected function configHeader($prefix)
    {
        return "#import <Foundation/Foundation.h>\n#import \"{$prefix}DylibVerify.h\"\n\nNS_ASSUME_NONNULL_BEGIN\n@interface {$prefix}DylibConfig : NSObject\n+ ({$prefix}DylibVerifyConfiguration *)configurationWithUDIDProvider:({$prefix}DylibUDIDProvider)udidProvider licenseCodeProvider:({$prefix}DylibLicenseCodeProvider)licenseCodeProvider;\n+ (NSDictionary<NSString *, id> *)metadata;\n@end\nNS_ASSUME_NONNULL_END\n";
    }

    protected function configImplementation($prefix,array $config)
    {
        $bootstrap=$this->objcURLArray($config['bootstrap_urls']);
        $endpoint=$config['endpoint_url']!==''?'[NSURL URLWithString:'.$this->objcString($config['endpoint_url']).']':'nil';
        $metadata='@{'.$this->objcString('dylib_id').':@('.(int)$config['dylib_id'].'),'.$this->objcString('dylib_key').':'.$this->objcString($config['dylib_key']).','.$this->objcString('version_id').':@('.(int)$config['version_id'].')}';
        return "#import \"{$prefix}DylibConfig.h\"\n@implementation {$prefix}DylibConfig\n+ ({$prefix}DylibVerifyConfiguration *)configurationWithUDIDProvider:({$prefix}DylibUDIDProvider)udidProvider licenseCodeProvider:({$prefix}DylibLicenseCodeProvider)licenseCodeProvider { {$prefix}DylibVerifyConfiguration *cfg=[{$prefix}DylibVerifyConfiguration new]; cfg.bootstrapURLs={$bootstrap}; cfg.endpointURL={$endpoint}; cfg.dylibKey=".$this->objcString($config['dylib_key'])."; cfg.dylibVersion=".$this->objcString($config['dylib_version'])."; cfg.dylibBuild=".$this->objcString($config['dylib_build'])."; cfg.serverPublicKeyPEM=".$this->objcString($config['server_public_key'])."; cfg.serverKeyID=".$this->objcString($config['server_key_id'])."; cfg.requestTimeout=".(int)$config['timeout'].".0; cfg.udidProvider=udidProvider; cfg.licenseCodeProvider=licenseCodeProvider; return cfg; }\n+ (NSDictionary<NSString *,id> *)metadata { return {$metadata}; }\n@end\n";
    }

    protected function integrationGuide($prefix,array $config)
    {
        return "# Dylib Secretless Auth 接入\n\n本版本不再包含 Verify Secret。客户端首次绑定设备公钥时需要宿主提供当前卡密；绑定完成后后续验证仅使用设备私钥。\n\n```objc\n{$prefix}DylibVerifyConfiguration *cfg=[{$prefix}DylibConfig configurationWithUDIDProvider:^NSString *{ return ExistingProjectUDID(); } licenseCodeProvider:^NSString *{ return ExistingProjectLicenseCode(); }];\n{$prefix}DylibVerify *client=[[{$prefix}DylibVerify alloc] initWithConfiguration:cfg];\n[client verifyWithCompletion:^({$prefix}DylibVerifyResult *result) { NSLog(@\"%@ %@\", result.code, result.message); }];\n```\n";
    }

    protected function apiReference(array $config)
    {
        return "# API Reference\n\nProtocol: 3\n\n1. POST `/index/dylib_verify/challenge` with udid, dylib_key, device_public_key.\n2. Sign the returned challenge with the local P-256 private key.\n3. POST `{$config['verify_path']}` with App/Dylib identity, challenge fields and device_signature.\n4. First enrollment additionally sends license_code. No Verify Secret exists in the client.\n";
    }

    protected function errorCodeGuide()
    {
        return "# Error Codes\n\n- challenge_expired / challenge_replayed / challenge_mismatch\n- device_signature_invalid / device_key_mismatch / device_enrollment_denied\n- blacklisted / license_invalid / app_not_authorized\n- version_unknown / version_blocked / version_revoked / integrity_mismatch\n";
    }

    protected function decodeUrlList($json)
    {
        $decoded=json_decode((string)$json,true); if (!is_array($decoded)) return [];
        $out=[]; foreach ($decoded as $url) { $url=rtrim(trim((string)$url),'/'); if ($url!==''&&filter_var($url,FILTER_VALIDATE_URL)) $out[$url]=true; }
        return array_keys($out);
    }

    protected function objcURLArray(array $urls)
    {
        if (!$urls) return '@[]'; $items=[]; foreach ($urls as $url) $items[]='[NSURL URLWithString:'.$this->objcString($url).']'; return '@['.implode(',',$items).']';
    }
    protected function objcString($value) { return '@'.json_encode((string)$value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    protected function json($value) { return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT); }
}
