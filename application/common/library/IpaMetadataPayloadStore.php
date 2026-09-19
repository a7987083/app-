<?php

namespace app\common\library;

use RuntimeException;

/**
 * Stores large/rebuildable parser payloads outside MySQL.
 *
 * The relational ipa_metadata row remains the lightweight searchable index;
 * raw/normalized/confidence parser payloads are kept in runtime/ipa/metadata-detail.
 */
class IpaMetadataPayloadStore
{
    public static function save($metadataId,array $payload)
    {
        $metadataId=(int)$metadataId;
        if($metadataId<=0)throw new RuntimeException('Invalid metadata id');
        $dir=self::dir();
        if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('无法创建 IPA metadata runtime 目录');
        $body=[
            'version'=>1,
            'metadata_id'=>$metadataId,
            'confidence'=>isset($payload['confidence'])&&is_array($payload['confidence'])?$payload['confidence']:[],
            'raw'=>isset($payload['raw'])&&is_array($payload['raw'])?$payload['raw']:[],
            'normalized'=>isset($payload['normalized'])&&is_array($payload['normalized'])?$payload['normalized']:[],
            'updated_at'=>time(),
        ];
        $json=json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
        if($json===false)throw new RuntimeException('IPA metadata payload encoding failed');
        $file=self::path($metadataId);$tmp=$file.'.tmp.'.getmypid().'.'.mt_rand(1000,9999);
        if(@file_put_contents($tmp,$json."\n",LOCK_EX)===false)throw new RuntimeException('无法写入 IPA metadata runtime 文件');
        @chmod($tmp,0640);
        if(!@rename($tmp,$file)){@unlink($tmp);throw new RuntimeException('无法原子替换 IPA metadata runtime 文件');}
        return $file;
    }

    public static function load($metadataId)
    {
        $file=self::path((int)$metadataId);
        if(!is_file($file))return null;
        $raw=@file_get_contents($file);$row=$raw!==false?json_decode($raw,true):null;
        return is_array($row)?$row:null;
    }

    public static function remove($metadataId)
    {
        $file=self::path((int)$metadataId);
        return !is_file($file)||@unlink($file);
    }

    public static function hydrateLegacyRow(array $row)
    {
        $payload=self::load(isset($row['id'])?(int)$row['id']:0);
        if($payload)return $payload;
        $confidence=json_decode(isset($row['confidence_json'])?$row['confidence_json']:'',true);
        $raw=json_decode(isset($row['raw_metadata_json'])?$row['raw_metadata_json']:'',true);
        $normalized=json_decode(isset($row['normalized_metadata_json'])?$row['normalized_metadata_json']:'',true);
        if(!is_array($confidence)&&!is_array($raw)&&!is_array($normalized))return null;
        return [
            'version'=>1,
            'metadata_id'=>isset($row['id'])?(int)$row['id']:0,
            'confidence'=>is_array($confidence)?$confidence:[],
            'raw'=>is_array($raw)?$raw:[],
            'normalized'=>is_array($normalized)?$normalized:[],
            'updated_at'=>isset($row['updatetime'])?(int)$row['updatetime']:0,
        ];
    }

    protected static function path($metadataId)
    {
        return self::dir().(int)$metadataId.'.json';
    }

    protected static function dir()
    {
        $base=defined('RUNTIME_PATH')?rtrim(RUNTIME_PATH,'/\\'):rtrim(ROOT_PATH,'/\\').DIRECTORY_SEPARATOR.'runtime';
        return $base.DIRECTORY_SEPARATOR.'ipa'.DIRECTORY_SEPARATOR.'metadata-detail'.DIRECTORY_SEPARATOR;
    }
}
