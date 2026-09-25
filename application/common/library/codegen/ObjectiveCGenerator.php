<?php
namespace app\common\library\codegen;

use app\common\library\ApiEndpointRegistry;

class ObjectiveCGenerator
{
    const GENERATOR_VERSION = '1.0.0';

    public function currentConfig($domain = '')
    {
        $rows = ApiEndpointRegistry::all($domain);
        $endpoints = [];
        foreach ((array)$rows as $row) {
            $key = isset($row['endpoint_key']) ? trim((string)$row['endpoint_key']) : '';
            if ($key === '') continue;
            $schema = ApiEndpointRegistry::testSchema($key);
            $fields = [];
            foreach ((array)(is_array($schema) && isset($schema['fields']) ? $schema['fields'] : []) as $field) {
                if (!is_array($field) || empty($field['name'])) continue;
                $fields[] = [
                    'name' => (string)$field['name'],
                    'label' => isset($field['label']) ? (string)$field['label'] : (string)$field['name'],
                    'required' => !empty($field['required']),
                ];
            }
            $endpoints[] = [
                'key' => $key,
                'name' => isset($row['name']) ? (string)$row['name'] : $key,
                'path' => isset($row['path']) ? (string)$row['path'] : '',
                'method' => is_array($schema) && !empty($schema['method']) ? (string)$schema['method'] : (isset($row['method']) ? (string)$row['method'] : 'GET'),
                'auth' => isset($row['auth']) ? (string)$row['auth'] : '',
                'description' => isset($row['description']) ? (string)$row['description'] : '',
                'enabled' => !isset($row['enabled']) || (bool)$row['enabled'],
                'fields' => $fields,
            ];
        }
        return [
            'project_name' => 'ZONGeneratedAPI', 'class_prefix' => 'ZON',
            'base_url' => rtrim((string)$domain, '/'), 'deployment_target' => '13.0',
            'timeout' => 15, 'user_agent' => 'ZONOE-ObjectiveC/' . self::GENERATOR_VERSION,
            'include_disabled' => false, 'endpoints' => $endpoints,
        ];
    }

    public function normalize(array $input, $domain = '')
    {
        $out = $this->currentConfig($domain);
        foreach (['project_name','class_prefix','base_url','deployment_target','user_agent'] as $key) {
            if (array_key_exists($key, $input)) $out[$key] = trim((string)$input[$key]);
        }
        if (array_key_exists('timeout', $input)) $out['timeout'] = max(1, min(120, (int)$input['timeout']));
        if (array_key_exists('include_disabled', $input)) $out['include_disabled'] = $this->boolValue($input['include_disabled']);
        $out['project_name'] = substr(preg_replace('/[^A-Za-z0-9_.-]/', '', $out['project_name']), 0, 64);
        $out['class_prefix'] = substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $out['class_prefix'])), 0, 8);
        $out['base_url'] = rtrim(substr($out['base_url'], 0, 512), '/');
        if (!preg_match('/^(1[3-9]|2[0-6])(?:\.\d+)?$/', $out['deployment_target'])) $out['deployment_target'] = '13.0';
        $out['user_agent'] = substr($out['user_agent'], 0, 120);
        $out['endpoints'] = $this->normalizeEndpoints(isset($input['endpoints']) && is_array($input['endpoints']) ? $input['endpoints'] : $out['endpoints']);
        return $out;
    }

    public function validate(array $config)
    {
        $errors = []; $warnings = [];
        if ($config['project_name'] === '') $errors[] = '工程名称不能为空';
        if (!preg_match('/^[A-Z][A-Z0-9]{1,7}$/', $config['class_prefix'])) $errors[] = '类前缀必须为 2-8 位大写字母/数字，且以字母开头';
        if (!preg_match('#^https?://#i', $config['base_url']) || filter_var($config['base_url'], FILTER_VALIDATE_URL) === false) $errors[] = 'Base URL 必须是有效的 HTTP/HTTPS 地址';
        if (stripos($config['base_url'], 'http://') === 0) $warnings[] = 'Base URL 使用 HTTP，iOS ATS 可能阻止明文请求';
        if (!$config['endpoints']) $warnings[] = '当前没有可生成的接口';
        $warnings[] = '当前 API Registry 没有完整 Response Schema，本版不会伪造 Objective-C Model。';
        return ['valid' => !$errors, 'errors' => $errors, 'warnings' => $warnings];
    }

    public function configHash(array $config) { return hash('sha256', $this->json($config)); }

    public function generate(array $config)
    {
        $v = $this->validate($config);
        if (!$v['valid']) throw new \InvalidArgumentException(implode('；', $v['errors']));
        $p = $config['class_prefix'];
        $files = [
            $p.'APIConfig.h' => $this->configH($p),
            $p.'APIConfig.m' => $this->configM($p, $config),
            $p.'APIEndpoints.h' => $this->endpointsH($p),
            $p.'APIEndpoints.m' => $this->endpointsM($p, $config),
            $p.'APIClient.h' => $this->clientH($p),
            $p.'APIClient.m' => $this->clientM($p),
            'GeneratedConfig.json' => $this->json($config) . "\n",
            'API_REFERENCE.md' => $this->reference($config),
            'INTEGRATION.md' => $this->guide($config),
        ];
        $hashes = [];
        foreach ($files as $path => $content) $hashes[$path] = hash('sha256', $content);
        $files['generation-manifest.json'] = $this->json([
            'generator' => 'zonoe-objective-c', 'generator_version' => self::GENERATOR_VERSION,
            'config_sha256' => $this->configHash($config), 'files' => $hashes,
        ]) . "\n";
        return $files;
    }

    protected function normalizeEndpoints(array $rows)
    {
        $out = []; $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $key = preg_replace('/[^A-Za-z0-9_.-]/', '', isset($row['key']) ? (string)$row['key'] : (isset($row['endpoint_key']) ? (string)$row['endpoint_key'] : ''));
            if ($key === '' || isset($seen[$key])) continue;
            $seen[$key] = true;
            $method = isset($row['method']) && stripos((string)$row['method'], 'POST') !== false ? 'POST' : 'GET';
            $path = isset($row['path']) ? trim((string)$row['path']) : '/';
            if ($path === '' || $path[0] !== '/') $path = '/' . ltrim($path, '/');
            $fields = [];
            foreach ((array)(isset($row['fields']) ? $row['fields'] : []) as $field) {
                if (!is_array($field) || empty($field['name'])) continue;
                $name = preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$field['name']);
                if ($name !== '') $fields[] = ['name'=>$name,'label'=>isset($field['label'])?(string)$field['label']:$name,'required'=>!empty($field['required'])];
            }
            $out[] = [
                'key'=>$key,'name'=>substr(isset($row['name'])?(string)$row['name']:$key,0,120),
                'path'=>substr($path,0,240),'method'=>$method,'auth'=>substr(isset($row['auth'])?(string)$row['auth']:'',0,120),
                'description'=>substr(isset($row['description'])?(string)$row['description']:'',0,300),
                'enabled'=>!array_key_exists('enabled',$row)||$this->boolValue($row['enabled']),'fields'=>$fields,
            ];
        }
        return $out;
    }

    protected function boolValue($v) { return is_bool($v) ? $v : in_array(strtolower(trim((string)$v)), ['1','true','yes','on'], true); }
    protected function json(array $v) { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT); }
    protected function s($v) { return '@"' . str_replace(['\\','"',"\r","\n"], ['\\\\','\\"','\\r','\\n'], (string)$v) . '"'; }
    protected function active(array $c) { return !empty($c['include_disabled']) ? $c['endpoints'] : array_values(array_filter($c['endpoints'], function($e){ return !empty($e['enabled']); })); }

    protected function configH($p) { return "#import <Foundation/Foundation.h>\nNS_ASSUME_NONNULL_BEGIN\n@interface {$p}APIConfig : NSObject\n+ (NSString *)baseURL; + (NSTimeInterval)timeout; + (NSString *)userAgent;\n@end\nNS_ASSUME_NONNULL_END\n"; }
    protected function configM($p,array $c) { return "#import \"{$p}APIConfig.h\"\n@implementation {$p}APIConfig\n+ (NSString *)baseURL{return ".$this->s($c['base_url']).";}\n+ (NSTimeInterval)timeout{return ".(int)$c['timeout'].";}\n+ (NSString *)userAgent{return ".$this->s($c['user_agent']).";}\n@end\n"; }
    protected function endpointsH($p) { return "#import <Foundation/Foundation.h>\nNS_ASSUME_NONNULL_BEGIN\n@interface {$p}APIEndpoints : NSObject\n+ (NSDictionary<NSString *,NSDictionary *> *)all;\n+ (nullable NSDictionary *)endpointForKey:(NSString *)key;\n@end\nNS_ASSUME_NONNULL_END\n"; }

    protected function endpointsM($p,array $c)
    {
        $rows = [];
        foreach ($this->active($c) as $e) {
            $fields=[];
            foreach ($e['fields'] as $f) $fields[]='@{'.$this->s('name').':'.$this->s($f['name']).','.$this->s('label').':'.$this->s($f['label']).','.$this->s('required').':@('.(!empty($f['required'])?'YES':'NO').')}';
            $rows[]=$this->s($e['key']).':@{'.$this->s('key').':'.$this->s($e['key']).','.$this->s('name').':'.$this->s($e['name']).','.$this->s('path').':'.$this->s($e['path']).','.$this->s('method').':'.$this->s($e['method']).','.$this->s('auth').':'.$this->s($e['auth']).','.$this->s('description').':'.$this->s($e['description']).','.$this->s('fields').':'.($fields?'@['.implode(',',$fields).']':'@[]').'}';
        }
        $dict=$rows?'@{'.implode(",\n",$rows).'}':'@{}';
        return "#import \"{$p}APIEndpoints.h\"\n@implementation {$p}APIEndpoints\n+ (NSDictionary *)all{static NSDictionary *v;static dispatch_once_t once;dispatch_once(&once,^{v={$dict};});return v;}\n+ (NSDictionary *)endpointForKey:(NSString *)key{return [self all][key];}\n@end\n";
    }

    protected function clientH($p)
    {
        return "#import <Foundation/Foundation.h>\nNS_ASSUME_NONNULL_BEGIN\ntypedef NSDictionary<NSString *,NSString *> * _Nonnull (^{$p}APIHeaderProvider)(NSDictionary *,NSDictionary *);\ntypedef void (^{$p}APICompletion)(id _Nullable,NSHTTPURLResponse * _Nullable,NSError * _Nullable);\n@interface {$p}APIClient : NSObject\n@property(nonatomic,copy,nullable) {$p}APIHeaderProvider headerProvider;\n+ (instancetype)sharedClient;\n- (nullable NSURLSessionDataTask *)requestEndpointKey:(NSString *)key parameters:(nullable NSDictionary *)parameters completion:({$p}APICompletion)completion;\n@end\nNS_ASSUME_NONNULL_END\n";
    }

    protected function clientM($p)
    {
        return "#import \"{$p}APIClient.h\"\n#import \"{$p}APIConfig.h\"\n#import \"{$p}APIEndpoints.h\"\n@implementation {$p}APIClient\n+ (instancetype)sharedClient{static id c;static dispatch_once_t once;dispatch_once(&once,^{c=[self new];});return c;}\n- (NSURLSessionDataTask *)requestEndpointKey:(NSString *)key parameters:(NSDictionary *)parameters completion:({$p}APICompletion)completion{\nNSDictionary *e=[{$p}APIEndpoints endpointForKey:key];if(!e){if(completion)completion(nil,nil,[NSError errorWithDomain:@\"{$p}APIClient\" code:-1 userInfo:@{NSLocalizedDescriptionKey:@\"Unknown endpoint key\"}]);return nil;}\nNSString *method=[e[@\"method\"] uppercaseString]?:@\"GET\";NSURLComponents *u=[NSURLComponents componentsWithString:[[{$p}APIConfig baseURL] stringByAppendingString:e[@\"path\"]?:@\"/\"]];NSDictionary *pms=parameters?:@{};\nNSMutableArray *items=[NSMutableArray array];[pms enumerateKeysAndObjectsUsingBlock:^(id k,id v,BOOL *stop){[items addObject:[NSURLQueryItem queryItemWithName:[k description] value:[v description]]];}];if([method isEqualToString:@\"GET\"])u.queryItems=items;\nif(!u.URL){if(completion)completion(nil,nil,[NSError errorWithDomain:@\"{$p}APIClient\" code:-2 userInfo:@{NSLocalizedDescriptionKey:@\"Invalid URL\"}]);return nil;}\nNSMutableURLRequest *r=[NSMutableURLRequest requestWithURL:u.URL cachePolicy:NSURLRequestReloadIgnoringLocalCacheData timeoutInterval:[{$p}APIConfig timeout]];r.HTTPMethod=method;[r setValue:@\"application/json\" forHTTPHeaderField:@\"Accept\"];[r setValue:[{$p}APIConfig userAgent] forHTTPHeaderField:@\"User-Agent\"];\nif(![method isEqualToString:@\"GET\"]&&items.count){NSURLComponents *b=[NSURLComponents new];b.queryItems=items;r.HTTPBody=[b.percentEncodedQuery dataUsingEncoding:NSUTF8StringEncoding];[r setValue:@\"application/x-www-form-urlencoded; charset=utf-8\" forHTTPHeaderField:@\"Content-Type\"];}\nif(self.headerProvider){NSDictionary *h=self.headerProvider(e,pms);[h enumerateKeysAndObjectsUsingBlock:^(NSString *k,NSString *v,BOOL *stop){[r setValue:v forHTTPHeaderField:k];}];}\nNSURLSessionDataTask *t=[[NSURLSession sharedSession] dataTaskWithRequest:r completionHandler:^(NSData *d,NSURLResponse *resp,NSError *err){NSError *pe=nil;id obj=d.length?[NSJSONSerialization JSONObjectWithData:d options:0 error:&pe]:nil;dispatch_async(dispatch_get_main_queue(),^{if(completion)completion(obj,(NSHTTPURLResponse *)resp,err?:pe);});}];[t resume];return t;}\n@end\n";
    }

    protected function reference(array $c)
    {
        $out="# API Reference\n\nBase URL: `{$c['base_url']}`\n\n";
        foreach ($this->active($c) as $e) {
            $out.="## {$e['name']}\n\n- Key: `{$e['key']}`\n- Method: `{$e['method']}`\n- Path: `{$e['path']}`\n- Auth: ".($e['auth']?:'无明确元数据')."\n- 参数编码: ".($e['method']==='GET'?'URL Query':'application/x-www-form-urlencoded')."\n\n";
            if ($e['fields']) { $out.="| 字段 | 说明 | 必填 |\n|---|---|---|\n"; foreach($e['fields'] as $f)$out.='| `'.$f['name'].'` | '.$f['label'].' | '.(!empty($f['required'])?'Yes':'No')." |\n"; $out.="\n"; }
            $out.="> Response Schema 尚未在当前 API Registry 中结构化，因此生成器不会猜测 Model。\n\n";
        }
        return $out;
    }

    protected function guide(array $c)
    {
        $p=$c['class_prefix'];
        return "# Objective-C Integration\n\nDeployment Target: iOS {$c['deployment_target']}+\n\n1. 将 `{$p}APIConfig.*`、`{$p}APIEndpoints.*`、`{$p}APIClient.*` 加入 Xcode Target。\n2. UDID、卡密、HMAC、Token 等敏感值不要写死进生成文件，通过 `headerProvider` 或请求参数在运行时注入。\n3. 旧 HMAC/字段顺序协议继续使用现有签名实现；生成器不擅自改变协议。\n\n```objc\n[[{$p}APIClient sharedClient] requestEndpointKey:@\"appstore\" parameters:@{@\"udid\":udid} completion:^(id obj,NSHTTPURLResponse *resp,NSError *err){ /* handle */ }];\n```\n";
    }
}
