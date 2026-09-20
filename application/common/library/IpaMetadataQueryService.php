<?php

namespace app\common\library;

use think\Db;

/** Server-side metadata listing for the current IPA metadata index. */
class IpaMetadataQueryService
{
    public static function listing($state='',$keyword='',$page=1,$limit=50,$sort='id',$order='desc',$referencedOnly=true)
    {
        $state=trim((string)$state);$keyword=trim((string)$keyword);$page=max(1,(int)$page);$limit=max(10,min(100,(int)$limit));
        $allowedSort=['id','file_name','package_name','bundle_id','package_version','parse_state','parsed_at','updatetime'];if($sort==='updated_at')$sort='updatetime';if(!in_array($sort,$allowedSort,true))$sort='id';$order=strtolower((string)$order)==='asc'?'asc':'desc';
        // $referencedOnly is retained for API compatibility. Since 1918 the authoritative
        // work-set lives in the latest scan JSON snapshot, not a metadata table flag.
        $total=(int)self::filteredQuery($state,$keyword)->count();$rows=self::filteredQuery($state,$keyword)->order($sort,$order)->page($page,$limit)->select();
        foreach((array)$rows as &$row){$payload=IpaMetadataPayloadStore::hydrateLegacyRow($row);$normalized=$payload&&isset($payload['normalized'])&&is_array($payload['normalized'])?$payload['normalized']:[];$parser=isset($normalized['_parser'])&&is_array($normalized['_parser'])?$normalized['_parser']:[];$row['architectures']=isset($normalized['architectures'])&&is_array($normalized['architectures'])?$normalized['architectures']:[];$row['primary_icon']=isset($normalized['primary_icon'])&&is_array($normalized['primary_icon'])?$normalized['primary_icon']:null;$row['range_bytes']=isset($parser['range_bytes'])?(int)$parser['range_bytes']:0;$row['range_requests']=isset($parser['range_requests'])?(int)$parser['range_requests']:0;$row['parsed_at_text']=!empty($row['parsed_at'])?date('Y-m-d H:i:s',$row['parsed_at']):'';unset($row['raw_metadata_json'],$row['normalized_metadata_json'],$row['confidence_json']);}unset($row);
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'limit'=>$limit];
    }

    public static function counts($referencedOnly=true)
    {
        return ['total'=>(int)Db::name('ipa_metadata')->count(),'success'=>(int)Db::name('ipa_metadata')->where('parse_state','success')->count(),'pending'=>(int)Db::name('ipa_metadata')->where('parse_state','pending')->count(),'failed'=>(int)Db::name('ipa_metadata')->where('parse_state','failed')->count()];
    }

    protected static function filteredQuery($state,$keyword)
    {
        $query=Db::name('ipa_metadata');if(in_array($state,['pending','success','failed'],true))$query->where('parse_state',$state);if($keyword!==''){$query->where(function($q)use($keyword){$like='%'.$keyword.'%';$q->where('file_name','like',$like)->whereOr('bundle_id','like',$like)->whereOr('package_name','like',$like)->whereOr('remote_path','like',$like);});}return $query;
    }
}
