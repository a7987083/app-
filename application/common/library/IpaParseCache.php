<?php

namespace app\common\library;

use think\Db;

/**
 * Durable MD5 parse-result library, adapted from the mature project's
 * openlist-ipa-metadata-library.json. It is deliberately separate from
 * ipa_metadata so the active work-set can be reconciled/cleaned safely.
 */
class IpaParseCache
{
    public static function find($md5,$size,$parserVersion)
    {
        $md5=strtolower(trim((string)$md5));$size=(int)$size;$parserVersion=(int)$parserVersion;
        if(!preg_match('/^[a-f0-9]{32}$/',$md5)||$size<=0||$parserVersion<=0)return null;
        return Db::name('ipa_parse_cache')->where('md5',$md5)->where('file_size',$size)->where('parser_version',$parserVersion)->find();
    }

    public static function remember(array $metadata)
    {
        $md5=strtolower(trim(isset($metadata['md5'])?(string)$metadata['md5']:''));$size=isset($metadata['file_size'])?(int)$metadata['file_size']:0;
        if(!preg_match('/^[a-f0-9]{32}$/',$md5)||$size<=0)return false;
        $version=isset($metadata['parser_version'])?(int)$metadata['parser_version']:IpaFoundation::PARSER_VERSION;$now=time();
        $row=[
            'md5'=>$md5,'file_size'=>$size,'parser_version'=>$version,
            'bundle_id'=>isset($metadata['bundle_id'])?(string)$metadata['bundle_id']:'',
            'package_name'=>isset($metadata['package_name'])?(string)$metadata['package_name']:'',
            'package_version'=>isset($metadata['package_version'])?(string)$metadata['package_version']:'',
            'package_build'=>isset($metadata['package_build'])?(string)$metadata['package_build']:'',
            'minimum_ios'=>isset($metadata['minimum_ios'])?(string)$metadata['minimum_ios']:'',
            'executable'=>isset($metadata['executable'])?(string)$metadata['executable']:'',
            'parsed_at'=>isset($metadata['parsed_at'])?(int)$metadata['parsed_at']:$now,'last_used_at'=>$now,'updatetime'=>$now
        ];
        $old=Db::name('ipa_parse_cache')->where('md5',$md5)->where('file_size',$size)->where('parser_version',$version)->find();
        if($old){Db::name('ipa_parse_cache')->where('id',(int)$old['id'])->update($row);return (int)$old['id'];}
        $row['createtime']=$now;return (int)Db::name('ipa_parse_cache')->insertGetId($row);
    }

    public static function touch($id){return Db::name('ipa_parse_cache')->where('id',(int)$id)->update(['last_used_at'=>time(),'updatetime'=>time()]);}
}
