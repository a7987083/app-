<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\IpaFoundation;
use app\common\library\IpaMetadataPayloadStore;
use app\common\library\IpaParserService;
use think\Db;

class IpaMetadata extends Backend
{
    protected $layout='default';

    public function sourceList()
    {
        $state=trim((string)$this->request->get('state',''));
        $keyword=trim((string)$this->request->get('q',''));
        $page=max(1,(int)$this->request->get('page/d',1));
        $limit=max(10,min(100,(int)$this->request->get('limit/d',50)));
        $sort=trim((string)$this->request->get('sort','id'));
        $order=strtolower(trim((string)$this->request->get('order','desc')))==='asc'?'asc':'desc';
        $allowedSort=['id','file_name','package_name','bundle_id','package_version','parse_state','parsed_at','updated_at'];
        if(!in_array($sort,$allowedSort,true))$sort='id';
        try{
            // Build count and row queries independently.  Cloning a Query with
            // bound filters on this ThinkPHP/PDO generation leaves duplicated
            // placeholders and produces MySQL/PDO error 2031.
            $countQuery=self::filteredQuery($state,$keyword);
            $total=(int)$countQuery->count();
            $rows=self::filteredQuery($state,$keyword)->order($sort,$order)->page($page,$limit)->select();
            foreach((array)$rows as &$row){
                $payload=IpaMetadataPayloadStore::hydrateLegacyRow($row);
                $normalized=$payload&&isset($payload['normalized'])&&is_array($payload['normalized'])?$payload['normalized']:[];
                $parser=isset($normalized['_parser'])&&is_array($normalized['_parser'])?$normalized['_parser']:[];
                $row['architectures']=isset($normalized['architectures'])&&is_array($normalized['architectures'])?$normalized['architectures']:[];
                $row['primary_icon']=isset($normalized['primary_icon'])&&is_array($normalized['primary_icon'])?$normalized['primary_icon']:null;
                $row['range_bytes']=isset($parser['range_bytes'])?(int)$parser['range_bytes']:0;
                $row['range_requests']=isset($parser['range_requests'])?(int)$parser['range_requests']:0;
                $row['parsed_at_text']=!empty($row['parsed_at'])?date('Y-m-d H:i:s',$row['parsed_at']):'';
                unset($row['raw_metadata_json'],$row['normalized_metadata_json'],$row['confidence_json']);
            }
            unset($row);
        }catch(\Exception $e){$this->error($e->getMessage());return;}
        $this->success('',null,['rows'=>$rows,'total'=>$total,'page'=>$page,'limit'=>$limit]);
    }

    public function parseOne()
    {
        if(!$this->request->isPost()){$this->error('Method not allowed');return;}
        try{$result=IpaParserService::parseBatch(0,1);}
        catch(\Exception $e){$this->error($e->getMessage());return;}
        $processed=isset($result['processed'])?(int)$result['processed']:0;
        $this->success($processed?'已解析 1 个 IPA':'当前没有可解析的 IPA',null,$result);
    }

    protected static function filteredQuery($state,$keyword)
    {
        $query=Db::name('ipa_metadata');
        if(in_array($state,['pending','success','failed'],true))$query->where('parse_state',$state);
        if($keyword!==''){
            $query->where(function($q)use($keyword){$like='%'.$keyword.'%';$q->where('file_name','like',$like)->whereOr('bundle_id','like',$like)->whereOr('package_name','like',$like)->whereOr('remote_path','like',$like);});
        }
        return $query;
    }

    public function source_list(){return $this->sourceList();}
    public function parse_one(){return $this->parseOne();}
}
