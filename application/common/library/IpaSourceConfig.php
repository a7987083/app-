<?php

namespace app\common\library;

use think\Db;
use RuntimeException;

/**
 * Phase 20 OpenList configuration store.
 *
 * Configuration is intentionally kept outside MySQL.  The runtime file is
 * small, atomically replaced and protected with an installation-local key.
 * Existing fa_ipa_source data is imported lazily once for upgrade safety.
 */
class IpaSourceConfig
{
    const API_BASE = '/api';
    const FORMAT_VERSION = 2;

    public static function first($withToken=false)
    {
        $row=self::readConfig();
        if (!$row) $row=self::importLegacyDatabaseConfig();
        if (!$row) return null;
        return self::publicRow($row,$withToken);
    }

    public static function save(array $input,$adminId=0)
    {
        $existing=self::readConfig();
        if (!$existing) $existing=self::importLegacyDatabaseConfig();
        $candidate=self::candidate($input,$existing,true);
        $token=trim((string)$candidate['token']);
        if ($token==='') throw new RuntimeException('请填写 OpenList 令牌（OpenList 设置 → 其他 → 令牌）');
        $now=time();
        $row=[
            'version'=>self::FORMAT_VERSION,
            'source_key'=>'openlist',
            'base_url'=>$candidate['base_url'],
            'scan_path'=>$candidate['scan_path'],
            'public_url_template'=>$candidate['public_url_template'],
            'enabled'=>!empty($input['enabled'])?1:0,
            'schedule_enabled'=>!empty($input['schedule_enabled'])?1:0,
            'interval_minutes'=>max(5,min(1440,(int)(isset($input['interval_minutes'])?$input['interval_minutes']:(isset($existing['interval_minutes'])?$existing['interval_minutes']:10)))),
            'batch_size'=>max(1,min(100,(int)(isset($input['batch_size'])?$input['batch_size']:(isset($existing['batch_size'])?$existing['batch_size']:20)))),
            'cache_ttl'=>max(60,min(86400,(int)(isset($input['cache_ttl'])?$input['cache_ttl']:(isset($existing['cache_ttl'])?$existing['cache_ttl']:1800)))),
            'request_timeout'=>max(3,min(120,(int)$candidate['request_timeout'])),
            'request_retries'=>max(0,min(5,(int)$candidate['request_retries'])),
            'token_ciphertext'=>self::sealToken($token),
            'last_health'=>isset($existing['last_health'])?$existing['last_health']:'unknown',
            'last_checked_at'=>isset($existing['last_checked_at'])?(int)$existing['last_checked_at']:0,
            'last_scan_at'=>isset($existing['last_scan_at'])?(int)$existing['last_scan_at']:0,
            'admin_id'=>(int)$adminId,
            'created_at'=>isset($existing['created_at'])?(int)$existing['created_at']:$now,
            'updated_at'=>$now,
        ];
        self::writeConfig($row);
        return 1;
    }

    public static function testInput(array $input)
    {
        $existing=self::readConfig();
        if (!$existing) $existing=self::importLegacyDatabaseConfig();
        $candidate=self::candidate($input,$existing,true);
        if (empty($candidate['token'])) throw new RuntimeException('请填写 OpenList 令牌（OpenList 设置 → 其他 → 令牌）');
        return self::clientFromRow($candidate)->health($candidate['scan_path']);
    }

    public static function updateState(array $patch)
    {
        $row=self::readConfig();
        if (!$row) return false;
        foreach (['last_health','last_checked_at','last_scan_at'] as $key) {
            if (array_key_exists($key,$patch)) $row[$key]=$patch[$key];
        }
        $row['updated_at']=time();
        self::writeConfig($row);
        return true;
    }

    protected static function candidate(array $input,$existing=null,$withStoredToken=false)
    {
        $baseUrl=rtrim(trim(isset($input['base_url'])?$input['base_url']:(isset($existing['base_url'])?$existing['base_url']:'')),'/');
        if ($baseUrl==='' || !preg_match('#^https?://#i',$baseUrl)) throw new RuntimeException('OpenList 地址必须是 http/https 地址');
        $scanPath=IpaRemoteFile::normalizePath(isset($input['scan_path'])?$input['scan_path']:(isset($existing['scan_path'])?$existing['scan_path']:'/'));
        $publicUrlTemplate=trim(isset($input['public_url_template'])?$input['public_url_template']:(isset($existing['public_url_template'])?$existing['public_url_template']:''));
        if ($publicUrlTemplate!=='' && !preg_match('#^https?://#i',$publicUrlTemplate)) throw new RuntimeException('公开下载地址前缀必须是 http/https 地址');
        $token=trim((string)(isset($input['token'])?$input['token']:''));
        if ($token==='' && $withStoredToken && $existing && !empty($existing['token_ciphertext'])) $token=self::openToken($existing['token_ciphertext']);
        return [
            'base_url'=>$baseUrl,'api_base'=>self::API_BASE,'scan_path'=>$scanPath,'public_url_template'=>$publicUrlTemplate,'token'=>$token,
            'request_timeout'=>max(3,min(120,(int)(isset($input['request_timeout'])?$input['request_timeout']:(isset($existing['request_timeout'])?$existing['request_timeout']:15)))),
            'request_retries'=>max(0,min(5,(int)(isset($input['request_retries'])?$input['request_retries']:(isset($existing['request_retries'])?$existing['request_retries']:2)))),
        ];
    }

    public static function clientFromRow(array $row)
    {
        $token=isset($row['token'])?$row['token']:self::openToken(isset($row['token_ciphertext'])?$row['token_ciphertext']:'');
        return new IpaOpenListClient($row['base_url'],self::API_BASE,$token,isset($row['request_timeout'])?$row['request_timeout']:15,isset($row['request_retries'])?$row['request_retries']:2);
    }

    public static function tokenHint($token)
    {
        return trim((string)$token)===''?'':'已配置';
    }

    protected static function publicRow(array $row,$withToken)
    {
        $token='';
        if (!empty($row['token_ciphertext'])) {
            try {$token=self::openToken($row['token_ciphertext']);} catch (\Exception $e) { if ($withToken) throw $e; }
        }
        $out=$row;
        $out['id']=1;$out['source_key']='openlist';$out['source_type']='openlist';$out['name']='OpenList';$out['api_base']=self::API_BASE;
        $out['token_configured']=$token!=='';
        $out['token_hint']=$token!==''?'已配置':'';
        if ($withToken) $out['token']=$token;
        unset($out['token_ciphertext']);
        return $out;
    }

    protected static function importLegacyDatabaseConfig()
    {
        try {
            $row=Db::name('ipa_source')->where('source_key','openlist')->find();
            if (!$row) return null;
            $token='';
            if (!empty($row['token_ciphertext'])) {
                try {$token=self::openLegacyToken($row['token_ciphertext']);} catch (\Exception $e) {$token='';}
            }
            $now=time();
            $new=[
                'version'=>self::FORMAT_VERSION,'source_key'=>'openlist','base_url'=>(string)$row['base_url'],'scan_path'=>(string)$row['scan_path'],
                'public_url_template'=>(string)$row['public_url_template'],'enabled'=>(int)$row['enabled'],'schedule_enabled'=>(int)$row['schedule_enabled'],
                'interval_minutes'=>(int)$row['interval_minutes'],'batch_size'=>(int)$row['batch_size'],'cache_ttl'=>(int)$row['cache_ttl'],
                'request_timeout'=>(int)$row['request_timeout'],'request_retries'=>(int)$row['request_retries'],'token_ciphertext'=>$token!==''?self::sealToken($token):'',
                'last_health'=>isset($row['last_health'])?$row['last_health']:'unknown','last_checked_at'=>isset($row['last_checked_at'])?(int)$row['last_checked_at']:0,
                'last_scan_at'=>isset($row['last_scan_at'])?(int)$row['last_scan_at']:0,'admin_id'=>isset($row['admin_id'])?(int)$row['admin_id']:0,
                'created_at'=>isset($row['createtime'])?(int)$row['createtime']:$now,'updated_at'=>$now,
            ];
            self::writeConfig($new);
            return $new;
        } catch (\Exception $e) { return null; }
    }

    protected static function readConfig()
    {
        $file=self::configPath();
        if (!is_file($file)) return null;
        $raw=@file_get_contents($file);$row=$raw!==false?json_decode($raw,true):null;
        return is_array($row)?$row:null;
    }

    protected static function writeConfig(array $row)
    {
        $dir=self::runtimeDir();
        if (!is_dir($dir) && !@mkdir($dir,0750,true) && !is_dir($dir)) throw new RuntimeException('无法创建 IPA runtime 目录：'.$dir);
        $json=json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
        if ($json===false) throw new RuntimeException('OpenList 配置编码失败');
        $file=self::configPath();$tmp=$file.'.tmp.'.getmypid().'.'.mt_rand(1000,9999);
        if (@file_put_contents($tmp,$json."\n",LOCK_EX)===false) throw new RuntimeException('无法写入 OpenList 配置，请检查 runtime/ipa 写权限');
        @chmod($tmp,0640);
        if (!@rename($tmp,$file)) {@unlink($tmp);throw new RuntimeException('无法原子替换 OpenList 配置文件');}
    }

    public static function sealToken($plain)
    {
        $plain=(string)$plain;if($plain==='')return '';
        if(!function_exists('openssl_encrypt'))throw new RuntimeException('OpenSSL extension is required to protect OpenList token');
        $key=self::secretKey();$iv=random_bytes(16);$cipher=openssl_encrypt($plain,'AES-256-CBC',$key,OPENSSL_RAW_DATA,$iv);
        if($cipher===false)throw new RuntimeException('Failed to encrypt OpenList token');
        $mac=hash_hmac('sha256',$iv.$cipher,$key,true);
        return 'v2:'.base64_encode($iv.$mac.$cipher);
    }

    public static function openToken($sealed)
    {
        $sealed=trim((string)$sealed);if($sealed==='')return '';
        if(strpos($sealed,'v2:')!==0||!function_exists('openssl_decrypt'))throw new RuntimeException('Unsupported OpenList token ciphertext');
        $raw=base64_decode(substr($sealed,3),true);if($raw===false||strlen($raw)<49)throw new RuntimeException('Invalid OpenList token ciphertext');
        $iv=substr($raw,0,16);$mac=substr($raw,16,32);$cipher=substr($raw,48);$key=self::secretKey();
        if(!hash_equals(hash_hmac('sha256',$iv.$cipher,$key,true),$mac))throw new RuntimeException('OpenList token ciphertext integrity check failed');
        $plain=openssl_decrypt($cipher,'AES-256-CBC',$key,OPENSSL_RAW_DATA,$iv);if($plain===false)throw new RuntimeException('Failed to decrypt OpenList token');
        return $plain;
    }

    protected static function openLegacyToken($sealed)
    {
        $sealed=trim((string)$sealed);if($sealed==='')return '';
        if(strpos($sealed,'v1:')!==0||!function_exists('openssl_decrypt'))throw new RuntimeException('Unsupported legacy token ciphertext');
        $raw=base64_decode(substr($sealed,3),true);if($raw===false||strlen($raw)<49)throw new RuntimeException('Invalid legacy token ciphertext');
        $iv=substr($raw,0,16);$mac=substr($raw,16,32);$cipher=substr($raw,48);
        $dbPassword=(string)\think\Config::get('database.password');$dbHost=(string)\think\Config::get('database.hostname');$root=defined('ROOT_PATH')?ROOT_PATH:__DIR__;
        $key=hash('sha256','phase20-openlist|'.$dbHost.'|'.$dbPassword.'|'.$root,true);
        if(!hash_equals(hash_hmac('sha256',$iv.$cipher,$key,true),$mac))throw new RuntimeException('Legacy token integrity check failed');
        $plain=openssl_decrypt($cipher,'AES-256-CBC',$key,OPENSSL_RAW_DATA,$iv);if($plain===false)throw new RuntimeException('Failed to decrypt legacy token');
        return $plain;
    }

    protected static function secretKey()
    {
        $env=getenv('IPA_CONFIG_KEY');
        if($env!==false&&strlen(trim($env))>=16)return hash('sha256',trim($env),true);
        $file=self::keyPath();
        if(is_file($file)){$raw=trim((string)@file_get_contents($file));if($raw!=='')return hash('sha256',$raw,true);}
        $dir=self::runtimeDir();if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('无法创建 IPA runtime 目录');
        $raw=bin2hex(random_bytes(32));
        if(@file_put_contents($file,$raw."\n",LOCK_EX)===false)throw new RuntimeException('无法创建 OpenList 配置密钥文件');
        @chmod($file,0600);return hash('sha256',$raw,true);
    }

    protected static function runtimeDir(){return (defined('RUNTIME_PATH')?rtrim(RUNTIME_PATH,'/\\'):rtrim(ROOT_PATH,'/\\').DIRECTORY_SEPARATOR.'runtime').DIRECTORY_SEPARATOR.'ipa'.DIRECTORY_SEPARATOR;}
    protected static function configPath(){return self::runtimeDir().'openlist.json';}
    protected static function keyPath(){return self::runtimeDir().'.openlist-key';}
}
