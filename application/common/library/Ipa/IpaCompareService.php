<?php

namespace app\common\library\Ipa;

use PDO;
use think\Db;

class IpaCompareService
{
    protected static $fields=['name','nickname','bt1a','bt2a'];

    public static function refreshAsset($assetId)
    {
        $asset=Db::name('ipa_asset')->where('id',(int)$assetId)->find();if(!$asset)throw new \InvalidArgumentException('IPA 不存在');
        $openSource=Db::name('ipa_source')->where('id',(int)$asset['source_id'])->find();if(!$openSource)throw new \InvalidArgumentException('OpenList 数据源不存在');
        $proposed=['name'=>(string)$asset['app_name'],'nickname'=>(string)$asset['app_version'],'bt1a'=>IpaWritebackService::stableDownloadUrl($openSource['base_url'],$asset['path']),'bt2a'=>(string)(int)$asset['size_bytes']];
        $basename=basename((string)$asset['path']);Db::name('ipa_compare_result')->where('asset_id',(int)$assetId)->delete();$now=time();
        foreach(IpaSoftwareSourceService::enabledSources() as $source){
            try{
                $pdo=IpaSoftwareSourceService::pdo($source);$table=IpaSoftwareSourceService::quoteIdentifier($source['table_name']);$candidate=self::findCandidate($pdo,$table,$proposed,$basename);
                if(!$candidate){self::store($assetId,$source['id'],0,'none',0,'unmatched',1,['message'=>'数据库中未找到可靠匹配项'],$now);continue;}
                $diff=[];$anomalies=0;foreach(self::$fields as $field){$current=isset($candidate[$field])?(string)$candidate[$field]:'';$next=(string)$proposed[$field];$same=$current===$next;if(!$same)$anomalies++;$diff[$field]=['current'=>$current,'proposed'=>$next,'same'=>$same];}
                self::store($assetId,$source['id'],$candidate['id'],$candidate['_match_type'],$candidate['_match_score'],$anomalies===0?'matched':'anomaly',$anomalies,$diff,$now);
            }catch(\Exception $e){self::store($assetId,$source['id'],0,'error',0,'source_error',1,['message'=>'软件源连接/查询失败：'.$e->getMessage()],$now);}
        }
        return self::details($assetId);
    }

    protected static function store($assetId,$sourceId,$categoryId,$matchType,$score,$status,$count,array $diff,$now)
    {
        return Db::name('ipa_compare_result')->insertGetId(['asset_id'=>(int)$assetId,'software_source_id'=>(int)$sourceId,'category_id'=>(int)$categoryId,'match_type'=>(string)$matchType,'match_score'=>(int)$score,'status'=>(string)$status,'anomaly_count'=>(int)$count,'diff_json'=>json_encode($diff,JSON_UNESCAPED_UNICODE),'compared_at'=>(int)$now,'updated_at'=>(int)$now]);
    }

    protected static function findCandidate(PDO $pdo,$table,array $proposed,$basename)
    {
        $stmt=$pdo->prepare('SELECT id,name,nickname,bt1a,bt2a FROM '.$table.' WHERE bt1a=? LIMIT 2');$stmt->execute([$proposed['bt1a']]);$rows=$stmt->fetchAll();if(count($rows)===1){$rows[0]['_match_type']='url_exact';$rows[0]['_match_score']=100;return $rows[0];}
        $suffix='/'.$basename;$stmt=$pdo->prepare('SELECT id,name,nickname,bt1a,bt2a FROM '.$table.' WHERE RIGHT(bt1a,?)=? LIMIT 3');$stmt->execute([strlen($suffix),$suffix]);$rows=$stmt->fetchAll();if(count($rows)===1){$rows[0]['_match_type']='filename';$rows[0]['_match_score']=90;return $rows[0];}
        if($proposed['name']!==''&&$proposed['nickname']!==''){$stmt=$pdo->prepare('SELECT id,name,nickname,bt1a,bt2a FROM '.$table.' WHERE name=? AND nickname=? LIMIT 3');$stmt->execute([$proposed['name'],$proposed['nickname']]);$rows=$stmt->fetchAll();if(count($rows)===1){$rows[0]['_match_type']='name_version';$rows[0]['_match_score']=80;return $rows[0];}}
        return null;
    }

    public static function details($assetId)
    {
        $rows=Db::name('ipa_compare_result')->where('asset_id',(int)$assetId)->order('id asc')->select();
        foreach($rows as &$row){$source=Db::name('ipa_software_source')->where('id',(int)$row['software_source_id'])->find();$row['source_name']=$source?$source['name']:'';$row['source_slug']=$source?$source['slug']:'';$row['database_name']=$source?$source['database_name']:'';$row['table_name']=$source?$source['table_name']:'';$row['allow_write']=$source?(int)$source['allow_write']:0;$row['enabled']=$source?(int)$source['enabled']:0;$decoded=json_decode((string)$row['diff_json'],true);$row['diff']=is_array($decoded)?$decoded:[];unset($row['diff_json']);}
        unset($row);return $rows;
    }

    public static function summaryForAssets(array $assetIds)
    {
        $out=[];if(!$assetIds)return $out;$rows=Db::name('ipa_compare_result')->field('asset_id,status,anomaly_count')->where('asset_id','in',$assetIds)->select();foreach($rows as $row){$id=(int)$row['asset_id'];if(!isset($out[$id]))$out[$id]=['sources'=>0,'anomalies'=>0,'unmatched'=>0,'errors'=>0];$out[$id]['sources']++;$out[$id]['anomalies']+=(int)$row['anomaly_count'];if($row['status']==='unmatched')$out[$id]['unmatched']++;if($row['status']==='source_error')$out[$id]['errors']++;}return $out;
    }

    public static function applyWriteback($compareId,array $fields)
    {
        $compare=Db::name('ipa_compare_result')->where('id',(int)$compareId)->find();if(!$compare||!(int)$compare['category_id'])throw new \InvalidArgumentException('没有可写回的数据库匹配项');
        $source=IpaSoftwareSourceService::get((int)$compare['software_source_id'],true);if(!(int)$source['enabled'])throw new \RuntimeException('软件源已停用');if(!(int)$source['allow_write'])throw new \RuntimeException('该软件源为只读；请先在“软件源”中明确开启字段写回');
        $diff=json_decode((string)$compare['diff_json'],true);if(!is_array($diff))throw new \RuntimeException('比对结果无效');$data=[];foreach($fields as $field){$field=(string)$field;if(!in_array($field,self::$fields,true)||!isset($diff[$field]))continue;$data[$field]=(string)$diff[$field]['proposed'];}if(!$data)throw new \InvalidArgumentException('没有选择写回字段');
        $pdo=IpaSoftwareSourceService::pdo($source);$table=IpaSoftwareSourceService::quoteIdentifier($source['table_name']);$sets=[];$params=[];foreach($data as $field=>$value){$sets[]=IpaSoftwareSourceService::quoteIdentifier($field).'=?';$params[]=$value;}$params[]=(int)$compare['category_id'];$stmt=$pdo->prepare('UPDATE '.$table.' SET '.implode(',',$sets).' WHERE id=? LIMIT 1');$stmt->execute($params);$changed=$stmt->rowCount();self::refreshAsset((int)$compare['asset_id']);return ['changed'=>$changed,'fields'=>array_keys($data)];
    }
}
