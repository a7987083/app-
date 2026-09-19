<?php

namespace app\common\library;

/**
 * Per-directory OpenList cache.  It is disposable runtime state: durable
 * connection settings live in MySQL.  Scope includes endpoint, path/prefix
 * and a token fingerprint so credentials/config changes never reuse stale
 * directory data.
 */
class IpaDirectoryCache
{
    public static function scopeKey(array $source)
    {
        $token=isset($source['token'])?(string)$source['token']:'';
        $parts=[
            rtrim((string)$source['base_url'],'/'),
            isset($source['scan_path'])?IpaRemoteFile::normalizePath($source['scan_path']):'/',
            isset($source['public_url_template'])?trim((string)$source['public_url_template']):'',
            substr(hash('sha256',$token),0,16),
        ];
        return hash('sha256',implode('|',$parts));
    }

    public static function load($scopeKey,$dir,$ttl)
    {
        $ttl=max(0,(int)$ttl);if($ttl===0)return null;
        $file=self::filePath($scopeKey,$dir);if(!is_file($file))return null;
        $raw=@file_get_contents($file);$row=$raw!==false?json_decode($raw,true):null;
        if(!is_array($row)||empty($row['fetched_at'])||!isset($row['files']))return null;
        if(time()-(int)$row['fetched_at']>$ttl)return null;
        $row['cache_hit']=true;return $row;
    }

    public static function save($scopeKey,$dir,array $files,$total=0)
    {
        $cacheDir=self::cacheDir();if(!is_dir($cacheDir)&&!@mkdir($cacheDir,0750,true)&&!is_dir($cacheDir))return false;
        $payload=['scope_key'=>(string)$scopeKey,'directory'=>IpaRemoteFile::normalizePath($dir),'fetched_at'=>time(),'total'=>(int)$total,'files'=>$files,'cache_hit'=>false];
        $file=self::filePath($scopeKey,$dir);$tmp=$file.'.tmp.'.getmypid().'.'.mt_rand(1000,9999);
        $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if($json===false||@file_put_contents($tmp,$json,LOCK_EX)===false)return false;
        @chmod($tmp,0640);if(!@rename($tmp,$file)){@unlink($tmp);return false;}return true;
    }

    protected static function filePath($scopeKey,$dir)
    {
        return self::cacheDir().hash('sha256',(string)$scopeKey.'|'.IpaRemoteFile::normalizePath($dir)).'.json';
    }

    protected static function cacheDir()
    {
        $base=defined('RUNTIME_PATH')?rtrim(RUNTIME_PATH,'/\\'):(defined('ROOT_PATH')?rtrim(ROOT_PATH,'/\\').DIRECTORY_SEPARATOR.'runtime':sys_get_temp_dir().DIRECTORY_SEPARATOR.'phase20-ipa');
        return $base.DIRECTORY_SEPARATOR.'ipa'.DIRECTORY_SEPARATOR.'directory-cache'.DIRECTORY_SEPARATOR;
    }
}
