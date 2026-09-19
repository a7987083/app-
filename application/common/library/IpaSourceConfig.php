<?php

namespace app\common\library;

use think\Db;
use RuntimeException;

/**
 * Phase 20 OpenList configuration store.
 *
 * MySQL (fa_ipa_source) is the durable source of truth.  Older releases kept
 * the configuration under runtime/ipa; that file is now migration-only so a
 * cache/runtime cleanup can no longer erase production connection settings.
 */
class IpaSourceConfig
{
    const API_BASE = '/api';
    const FORMAT_VERSION = 3;

    public static function first($withToken=false)
    {
        $row=self::readDatabaseConfig();
        if (!$row) {
            try {$row=self::migrateRuntimeConfig();}
            catch (\Exception $e) {
                // Keep the settings page recoverable if an old runtime key was
                // already lost.  A newly entered token can still repair it.
                $runtime=self::readRuntimeConfig();
                if (!$runtime) return null;
                return self::publicRow($runtime,$withToken);
            }
        }
        if (!$row) return null;
        return self::publicRow($row,$withToken);
    }

    public static function save(array $input,$adminId=0)
    {
        $existing=self::readDatabaseConfig();
        if (!$existing) {
            try {$existing=self::migrateRuntimeConfig();} catch (\Exception $e) {$existing=null;}
        }
        $candidate=self::candidate($input,$existing,true);
        $token=trim((string)$candidate['token']);
        if ($token==='') throw new RuntimeException('首次配置 OpenList 必须填写令牌（OpenList 设置 → 其他 → 令牌）');

        $now=time();
        $row=[
            'source_key'=>'openlist',
            'source_type'=>'openlist',
            'name'=>'OpenList',
            'base_url'=>$candidate['base_url'],
            'api_base'=>self::API_BASE,
            'scan_path'=>$candidate['scan_path'],
            'public_url_template'=>$candidate['public_url_template'],
            'token_ciphertext'=>self::sealToken($token),
            'enabled'=>!empty($input['enabled'])?1:0,
            'schedule_enabled'=>!empty($input['schedule_enabled'])?1:0,
            'interval_minutes'=>max(5,min(1440,(int)(isset($input['interval_minutes'])?$input['interval_minutes']:(isset($existing['interval_minutes'])?$existing['interval_minutes']:10)))),
            'batch_size'=>max(1,min(100,(int)(isset($input['batch_size'])?$input['batch_size']:(isset($existing['batch_size'])?$existing['batch_size']:20)))),
            'cache_ttl'=>max(60,min(86400,(int)(isset($input['cache_ttl'])?$input['cache_ttl']:(isset($existing['cache_ttl'])?$existing['cache_ttl']:1800)))),
            'request_timeout'=>max(3,min(120,(int)$candidate['request_timeout'])),
            'request_retries'=>max(0,min(5,(int)$candidate['request_retries'])),
            'last_health'=>isset($existing['last_health'])?$existing['last_health']:'unknown',
            'last_checked_at'=>isset($existing['last_checked_at'])?(int)$existing['last_checked_at']:0,
            'last_scan_at'=>isset($existing['last_scan_at'])?(int)$existing['last_scan_at']:0,
            'admin_id'=>(int)$adminId,
            'createtime'=>isset($existing['createtime'])?(int)$existing['createtime']:$now,
            'updatetime'=>$now,
        ];
        $id=self::writeDatabaseConfig($row,$existing);

        // A successful save must prove that the database ciphertext can be
        // read back before the UI reports success.
        $saved=self::readDatabaseConfig();
        if (!$saved || empty($saved['token_ciphertext'])) throw new RuntimeException('OpenList 配置保存后读取失败，请检查 fa_ipa_source');
        $roundTrip=self::openToken($saved['token_ciphertext']);
        if (!hash_equals($token,$roundTrip)) throw new RuntimeException('OpenList 令牌保存校验失败');
        return $id;
    }

    public static function testInput(array $input)
    {
        return self::testSaved();
    }

    public static function testSaved()
    {
        $source=self::first(true);
        if (!$source) throw new RuntimeException('请先保存 OpenList 配置');
        if (empty($source['base_url']) || empty($source['token'])) throw new RuntimeException('请先保存 OpenList URL 和令牌');
        $health=self::clientFromRow($source)->health($source['scan_path']);
        $health['path']=$source['scan_path'];
        return $health;
    }

    public static function updateState(array $patch)
    {
        $allowed=[];
        foreach (['last_health','last_checked_at','last_scan_at'] as $key) {
            if (array_key_exists($key,$patch)) $allowed[$key]=$patch[$key];
        }
        if (!$allowed) return true;
        $allowed['updatetime']=time();
        try {
            return Db::name('ipa_source')->where('source_key','openlist')->update($allowed)!==false;
        } catch (\Exception $e) {
            return false;
        }
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
        $token='';$tokenError='';
        if (!empty($row['token_ciphertext'])) {
            try {$token=self::openToken($row['token_ciphertext']);}
            catch (\Exception $e) {
                $tokenError=$e->getMessage();
                if ($withToken) throw $e;
            }
        }
        $out=$row;
        $out['id']=isset($row['id'])?(int)$row['id']:1;
        $out['source_key']='openlist';$out['source_type']='openlist';$out['name']='OpenList';$out['api_base']=self::API_BASE;
        $out['token_configured']=$token!=='';$out['token_hint']=$token!==''?'已配置':'';$out['token_error']=$tokenError;
        if ($withToken) $out['token']=$token;
        unset($out['token_ciphertext']);
        return $out;
    }

    protected static function readDatabaseConfig()
    {
        try {return Db::name('ipa_source')->where('source_key','openlist')->find();}
        catch (\Exception $e) {throw new RuntimeException('无法读取 IPA 网络源配置：'.$e->getMessage());}
    }

    protected static function writeDatabaseConfig(array $row,$existing=null)
    {
        try {
            if ($existing && !empty($existing['id'])) {
                Db::name('ipa_source')->where('id',(int)$existing['id'])->update($row);
                return (int)$existing['id'];
            }
            return (int)Db::name('ipa_source')->insertGetId($row);
        } catch (\Exception $e) {
            throw new RuntimeException('无法保存 IPA 网络源配置到 fa_ipa_source：'.$e->getMessage());
        }
    }

    /** Import the pre-DB-primary runtime configuration exactly once. */
    protected static function migrateRuntimeConfig()
    {
        $legacy=self::readRuntimeConfig();
        if (!$legacy) return null;
        $token='';
        if (!empty($legacy['token_ciphertext'])) $token=self::openToken($legacy['token_ciphertext']);
        if ($token==='') return null;
        $now=time();
        $row=[
            'source_key'=>'openlist','source_type'=>'openlist','name'=>'OpenList',
            'base_url'=>(string)$legacy['base_url'],'api_base'=>self::API_BASE,
            'scan_path'=>isset($legacy['scan_path'])?(string)$legacy['scan_path']:'/',
            'public_url_template'=>isset($legacy['public_url_template'])?(string)$legacy['public_url_template']:'',
            'token_ciphertext'=>self::sealToken($token),
            'enabled'=>!empty($legacy['enabled'])?1:0,'schedule_enabled'=>!empty($legacy['schedule_enabled'])?1:0,
            'interval_minutes'=>isset($legacy['interval_minutes'])?(int)$legacy['interval_minutes']:10,
            'batch_size'=>isset($legacy['batch_size'])?(int)$legacy['batch_size']:20,
            'cache_ttl'=>isset($legacy['cache_ttl'])?(int)$legacy['cache_ttl']:1800,
            'request_timeout'=>isset($legacy['request_timeout'])?(int)$legacy['request_timeout']:15,
            'request_retries'=>isset($legacy['request_retries'])?(int)$legacy['request_retries']:2,
            'last_health'=>isset($legacy['last_health'])?$legacy['last_health']:'unknown',
            'last_checked_at'=>isset($legacy['last_checked_at'])?(int)$legacy['last_checked_at']:0,
            'last_scan_at'=>isset($legacy['last_scan_at'])?(int)$legacy['last_scan_at']:0,
            'admin_id'=>isset($legacy['admin_id'])?(int)$legacy['admin_id']:0,
            'createtime'=>isset($legacy['created_at'])?(int)$legacy['created_at']:$now,'updatetime'=>$now,
        ];
        $id=self::writeDatabaseConfig($row,null);$row['id']=$id;
        return $row;
    }

    protected static function readRuntimeConfig()
    {
        $file=self::configPath();
        if (!is_file($file)) return null;
        $raw=@file_get_contents($file);$row=$raw!==false?json_decode($raw,true):null;
        return is_array($row)?$row:null;
    }

    public static function sealToken($plain)
    {
        $plain=(string)$plain;if($plain==='')return '';
        if(!function_exists('openssl_encrypt'))throw new RuntimeException('OpenSSL extension is required to protect OpenList token');
        $key=self::stableKey();$iv=random_bytes(16);$cipher=openssl_encrypt($plain,'AES-256-CBC',$key,OPENSSL_RAW_DATA,$iv);
        if($cipher===false)throw new RuntimeException('Failed to encrypt OpenList token');
        $mac=hash_hmac('sha256',$iv.$cipher,$key,true);
        return 'v3:'.base64_encode($iv.$mac.$cipher);
    }

    public static function openToken($sealed)
    {
        $sealed=trim((string)$sealed);if($sealed==='')return '';
        if(strpos($sealed,'v1:')===0)return self::openLegacyToken($sealed);
        if(strpos($sealed,'v2:')===0)return self::openWithKey($sealed,self::runtimeSecretKey(false),'v2');
        if(strpos($sealed,'v3:')===0)return self::openWithKey($sealed,self::stableKey(),'v3');
        throw new RuntimeException('Unsupported OpenList token ciphertext');
    }

    protected static function openWithKey($sealed,$key,$version)
    {
        if(!function_exists('openssl_decrypt'))throw new RuntimeException('OpenSSL extension is required to open OpenList token');
        $raw=base64_decode(substr($sealed,3),true);if($raw===false||strlen($raw)<49)throw new RuntimeException('Invalid OpenList token ciphertext');
        $iv=substr($raw,0,16);$mac=substr($raw,16,32);$cipher=substr($raw,48);
        if(!hash_equals(hash_hmac('sha256',$iv.$cipher,$key,true),$mac))throw new RuntimeException('OpenList token ciphertext integrity check failed');
        $plain=openssl_decrypt($cipher,'AES-256-CBC',$key,OPENSSL_RAW_DATA,$iv);if($plain===false)throw new RuntimeException('Failed to decrypt OpenList token');
        return $plain;
    }

    protected static function openLegacyToken($sealed)
    {
        if(strpos($sealed,'v1:')!==0||!function_exists('openssl_decrypt'))throw new RuntimeException('Unsupported legacy token ciphertext');
        $raw=base64_decode(substr($sealed,3),true);if($raw===false||strlen($raw)<49)throw new RuntimeException('Invalid legacy token ciphertext');
        $iv=substr($raw,0,16);$mac=substr($raw,16,32);$cipher=substr($raw,48);
        $dbPassword=(string)\think\Config::get('database.password');$dbHost=(string)\think\Config::get('database.hostname');$root=defined('ROOT_PATH')?ROOT_PATH:__DIR__;
        $key=hash('sha256','phase20-openlist|'.$dbHost.'|'.$dbPassword.'|'.$root,true);
        if(!hash_equals(hash_hmac('sha256',$iv.$cipher,$key,true),$mac))throw new RuntimeException('Legacy token integrity check failed');
        $plain=openssl_decrypt($cipher,'AES-256-CBC',$key,OPENSSL_RAW_DATA,$iv);if($plain===false)throw new RuntimeException('Failed to decrypt legacy token');
        return $plain;
    }

    /** Stable across runtime/cache cleanup; IPA_CONFIG_KEY remains the preferred override. */
    protected static function stableKey()
    {
        $env=getenv('IPA_CONFIG_KEY');
        if($env!==false&&strlen(trim($env))>=16)return hash('sha256',trim($env),true);
        $host=(string)\think\Config::get('database.hostname');$name=(string)\think\Config::get('database.database');
        $user=(string)\think\Config::get('database.username');$password=(string)\think\Config::get('database.password');
        return hash('sha256','phase20-openlist-v3|'.$host.'|'.$name.'|'.$user.'|'.$password,true);
    }

    /** Used only to decrypt configurations produced by 2026091908-1911. */
    protected static function runtimeSecretKey($create=false)
    {
        $env=getenv('IPA_CONFIG_KEY');
        if($env!==false&&strlen(trim($env))>=16)return hash('sha256',trim($env),true);
        $file=self::keyPath();
        if(is_file($file)){$raw=trim((string)@file_get_contents($file));if($raw!=='')return hash('sha256',$raw,true);}
        if(!$create)throw new RuntimeException('旧 OpenList 配置密钥已丢失，请重新填写令牌后保存');
        $dir=self::runtimeDir();if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('无法创建 IPA runtime 目录');
        $raw=bin2hex(random_bytes(32));if(@file_put_contents($file,$raw."\n",LOCK_EX)===false)throw new RuntimeException('无法创建 OpenList 配置密钥文件');
        @chmod($file,0600);return hash('sha256',$raw,true);
    }

    protected static function runtimeDir(){return (defined('RUNTIME_PATH')?rtrim(RUNTIME_PATH,'/\\'):rtrim(ROOT_PATH,'/\\').DIRECTORY_SEPARATOR.'runtime').DIRECTORY_SEPARATOR.'ipa'.DIRECTORY_SEPARATOR;}
    protected static function configPath(){return self::runtimeDir().'openlist.json';}
    protected static function keyPath(){return self::runtimeDir().'.openlist-key';}
}
