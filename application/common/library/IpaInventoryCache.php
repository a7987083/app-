<?php

namespace app\common\library;

class IpaInventoryCache
{
    public static function load($sourceKey, $scanPath, $ttl)
    {
        $ttl = max(0,(int)$ttl);
        if ($ttl===0) return null;
        $file = self::filePath($sourceKey,$scanPath);
        if (!is_file($file)) return null;
        $raw = @file_get_contents($file);
        $data = $raw!==false ? json_decode($raw,true) : null;
        if (!is_array($data) || empty($data['cached_at']) || !isset($data['files'])) return null;
        if ((time()-(int)$data['cached_at'])>$ttl) return null;
        $data['cache_hit']=true;
        return $data;
    }

    public static function save($sourceKey,$scanPath,array $inventory)
    {
        $dir=self::cacheDir();
        if (!is_dir($dir) && !@mkdir($dir,0750,true) && !is_dir($dir)) return false;
        $payload=[
            'source_key'=>(string)$sourceKey,'scan_path'=>IpaRemoteFile::normalizePath($scanPath),
            'cached_at'=>time(),'directories'=>isset($inventory['directories'])?(int)$inventory['directories']:0,
            'files'=>isset($inventory['files'])&&is_array($inventory['files'])?$inventory['files']:[],'cache_hit'=>false,
        ];
        $file=self::filePath($sourceKey,$scanPath);
        $tmp=$file.'.tmp.'.getmypid().'.'.mt_rand(1000,9999);
        $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if (@file_put_contents($tmp,$json,LOCK_EX)===false) return false;
        @chmod($tmp,0640);
        if (!@rename($tmp,$file)) { @unlink($tmp); return false; }
        return true;
    }

    public static function clear($sourceKey,$scanPath)
    {
        $file=self::filePath($sourceKey,$scanPath);
        return !is_file($file) || @unlink($file);
    }

    protected static function filePath($sourceKey,$scanPath)
    {
        $key=hash('sha256',(string)$sourceKey.'|'.IpaRemoteFile::normalizePath($scanPath));
        return self::cacheDir().$key.'.json';
    }

    protected static function cacheDir()
    {
        if (defined('RUNTIME_PATH')) return rtrim(RUNTIME_PATH,'/\\').DIRECTORY_SEPARATOR.'ipa'.DIRECTORY_SEPARATOR;
        if (defined('ROOT_PATH')) return rtrim(ROOT_PATH,'/\\').DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'ipa'.DIRECTORY_SEPARATOR;
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'phase20-ipa'.DIRECTORY_SEPARATOR;
    }
}
