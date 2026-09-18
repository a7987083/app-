<?php

namespace app\common\library;

use think\Config;
use think\Db;
use RuntimeException;

class IpaSourceConfig
{
    public static function first($withToken=false)
    {
        $row=Db::name('ipa_source')->order('id','asc')->find();
        if (!$row) return null;
        if ($withToken) $row['token']=self::openToken(isset($row['token_ciphertext'])?$row['token_ciphertext']:'');
        unset($row['token_ciphertext']);
        return $row;
    }

    public static function save(array $input,$adminId=0)
    {
        $baseUrl=rtrim(trim(isset($input['base_url'])?$input['base_url']:''),'/');
        if ($baseUrl==='' || !preg_match('#^https?://#i',$baseUrl)) throw new RuntimeException('OpenList Base URL 必须是 http/https 地址');
        $scanPath=IpaRemoteFile::normalizePath(isset($input['scan_path'])?$input['scan_path']:'/');
        $sourceKey=trim(isset($input['source_key'])?$input['source_key']:'openlist');
        if ($sourceKey==='') $sourceKey='openlist';
        $existing=Db::name('ipa_source')->where('source_key',$sourceKey)->find();
        $now=time();
        $data=[
            'source_key'=>$sourceKey,'source_type'=>'openlist','name'=>trim(isset($input['name'])?$input['name']:'OpenList'),
            'base_url'=>$baseUrl,'api_base'=>'/'.trim(isset($input['api_base'])?$input['api_base']:'/api','/'),
            'scan_path'=>$scanPath,'public_url_template'=>trim(isset($input['public_url_template'])?$input['public_url_template']:''),
            'enabled'=>!empty($input['enabled'])?1:0,'schedule_enabled'=>!empty($input['schedule_enabled'])?1:0,
            'interval_minutes'=>max(5,min(1440,(int)(isset($input['interval_minutes'])?$input['interval_minutes']:10))),
            'batch_size'=>max(1,min(100,(int)(isset($input['batch_size'])?$input['batch_size']:20))),
            'cache_ttl'=>max(60,min(86400,(int)(isset($input['cache_ttl'])?$input['cache_ttl']:1800))),
            'request_timeout'=>max(3,min(120,(int)(isset($input['request_timeout'])?$input['request_timeout']:15))),
            'request_retries'=>max(0,min(5,(int)(isset($input['request_retries'])?$input['request_retries']:2))),
            'admin_id'=>(int)$adminId,'updatetime'=>$now,
        ];
        $token=isset($input['token'])?trim((string)$input['token']):'';
        if ($token!=='') { $data['token_ciphertext']=self::sealToken($token); $data['token_hint']=self::tokenHint($token); }
        if ($existing) { Db::name('ipa_source')->where('id',(int)$existing['id'])->update($data); return (int)$existing['id']; }
        $data['createtime']=$now;
        if (!isset($data['token_ciphertext'])) { $data['token_ciphertext']=''; $data['token_hint']=''; }
        return (int)Db::name('ipa_source')->insertGetId($data);
    }

    public static function clientFromRow(array $row)
    {
        $token=isset($row['token'])?$row['token']:self::openToken(isset($row['token_ciphertext'])?$row['token_ciphertext']:'');
        return new IpaOpenListClient($row['base_url'],isset($row['api_base'])?$row['api_base']:'/api',$token,isset($row['request_timeout'])?$row['request_timeout']:15,isset($row['request_retries'])?$row['request_retries']:2);
    }

    public static function tokenHint($token)
    {
        $token=(string)$token;
        return $token===''?'':'••••'.substr($token,-4);
    }

    public static function sealToken($plain)
    {
        $plain=(string)$plain;
        if ($plain==='') return '';
        if (!function_exists('openssl_encrypt')) throw new RuntimeException('OpenSSL extension is required to protect OpenList token');
        $key=self::secretKey();
        $iv=function_exists('random_bytes')?random_bytes(16):openssl_random_pseudo_bytes(16);
        $cipher=openssl_encrypt($plain,'AES-256-CBC',$key,OPENSSL_RAW_DATA,$iv);
        if ($cipher===false) throw new RuntimeException('Failed to encrypt OpenList token');
        $mac=hash_hmac('sha256',$iv.$cipher,$key,true);
        return 'v1:'.base64_encode($iv.$mac.$cipher);
    }

    public static function openToken($sealed)
    {
        $sealed=trim((string)$sealed);
        if ($sealed==='') return '';
        if (strpos($sealed,'v1:')!==0 || !function_exists('openssl_decrypt')) throw new RuntimeException('Unsupported OpenList token ciphertext');
        $raw=base64_decode(substr($sealed,3),true);
        if ($raw===false || strlen($raw)<49) throw new RuntimeException('Invalid OpenList token ciphertext');
        $iv=substr($raw,0,16); $mac=substr($raw,16,32); $cipher=substr($raw,48); $key=self::secretKey();
        $expected=hash_hmac('sha256',$iv.$cipher,$key,true);
        if (!hash_equals($expected,$mac)) throw new RuntimeException('OpenList token ciphertext integrity check failed');
        $plain=openssl_decrypt($cipher,'AES-256-CBC',$key,OPENSSL_RAW_DATA,$iv);
        if ($plain===false) throw new RuntimeException('Failed to decrypt OpenList token');
        return $plain;
    }

    protected static function secretKey()
    {
        $dbPassword=(string)Config::get('database.password');
        $dbHost=(string)Config::get('database.hostname');
        $root=defined('ROOT_PATH')?ROOT_PATH:__DIR__;
        return hash('sha256','phase20-openlist|'.$dbHost.'|'.$dbPassword.'|'.$root,true);
    }
}
