<?php

namespace app\common\library\Ipa;

use PDO;
use think\Db;

class IpaCompareService
{
    protected static $fields = ['name','nickname','bt1a','bt2a'];

    public static function refreshAsset($assetId)
    {
        $asset = Db::name('ipa_asset')->where('id',(int)$assetId)->find();
        if (!$asset) throw new \InvalidArgumentException('IPA 不存在');
        $openSource = Db::name('ipa_source')->where('id',(int)$asset['source_id'])->find();
        if (!$openSource) throw new \InvalidArgumentException('OpenList 数据源不存在');
        $proposed = [
            'name'=>(string)$asset['app_name'],
            'nickname'=>(string)$asset['app_version'],
            'bt1a'=>IpaWritebackService::stableDownloadUrl($openSource['base_url'],$asset['path']),
            'bt2a'=>(string)(int)$asset['size_bytes'],
        ];
        $basename = basename((string)$asset['path']);
        Db::name('ipa_compare_result')->where('asset_id',(int)$assetId)->delete();
        $now=time();
        $stored=[];
        foreach (IpaSoftwareSourceService::enabledSources() as $source) {
            try {
                $pdo=IpaSoftwareSourceService::pdo($source);
                $table=IpaSoftwareSourceService::quoteIdentifier($source['table_name']);
                $candidate=self::findCandidate($pdo,$table,$proposed,$basename);
                if (!$candidate) {
                    $id=Db::name('ipa_compare_result')->insertGetId([
                        'asset_id'=>(int)$assetId,'software_source_id'=>(int)$source['id'],'category_id'=>0,
                        'match_type'=>'none','match_score'=>0,'status'=>'unmatched','anomaly_count'=>1,
                        'diff_json'=>json_encode(['message'=>'数据库中未找到可靠匹配项'],JSON_UNESCAPED_UNICODE),
                        'compared_at'=>$now,'updated_at'=>$now,
                    ]);
                    $stored[]=$id;
                    continue;
                }
                $diff=[];$anomalies=0;
                foreach (self::$fields as $field) {
                    $current=isset($candidate[$field])?(string)$candidate[$field]:'';
                    $next=(string)$proposed[$field];
                    $same=$current===$next;
                    if (!$same) $anomalies++;
                    $diff[$field]=['current'=>$current,'proposed'=>$next,'same'=>$same];
                }
                $status=$anomalies===0?'matched':'anomaly';
                $id=Db::name('ipa_compare_result')->insertGetId([
                    'asset_id'=>(int)$assetId,'software_source_id'=>(int)$source['id'],'category_id'=>(int)$candidate['id'],
                    'match_type'=>$candidate['_match_type'],'match_score'=>(int)$candidate['_match_score'],'status'=>$status,
                    'anomaly_count'=>$anomalies,'diff_json'=>json_encode($diff,JSON_UNESCAPED_UNICODE),
                    'compared_at'=>$now,'updated_at'=>$now,
                ]);
                $stored[]=$id;
            } catch (\Exception $e) {
                $id=Db::name('ipa_compare_result')->insertGetId([
                    'asset_id'=>(int)$assetId,'software_source_id'=>(int)$source['id'],'category_id'=>0,
                    'match_type'=>'error','match_score'=>0,'status'=>'source_error','anomaly_count'=>1,
                    'diff_json'=>json_encode(['message'=>'软件源连接/查询失败：'.$e->getMessage()],JSON_UNESCAPED_UNICODE),
                    'compared_at'=>$now,'updated_at'=>$now,
                ]);
                $stored[]=$id;
            }
        }
        return self::details($assetId);
    }

    protected static function findCandidate(PDO $pdo,$table,array $proposed,$basename)
    {
        $sql='SELECT id,name,nickname,bt1a,bt2a FROM '.$table.' WHERE bt1a=? LIMIT 2';
        $stmt=$pdo->prepare($sql);$stmt->execute([$proposed['bt1a']]);$rows=$stmt->fetchAll();
        if (count($rows)===1) { $rows[0]['_match_type']='url_exact';$rows[0]['_match_score']=100;return $rows[0]; }
        $needle='%/'.str_replace(['%','_'],['\\%','\\_'],$basename);
        $stmt=$pdo->prepare('SELECT id,name,nickname,bt1a,bt2a FROM '.$table.' WHERE bt1a LIKE ? ESCAPE "\\\\" LIMIT 3');
        $stmt->execute([$needle]);$rows=$stmt->fetchAll();
        if (count($rows)===1) { $rows[0]['_match_type']='filename';$rows[0]['_match_score']=90;return $rows[0]; }
        if ($proposed['name']!=='' && $proposed['nickname']!=='') {
            $stmt=$pdo->prepare('SELECT id,name,nickname,bt1a,bt2a FROM '.$table.' WHERE name=? AND nickname=? LIMIT 3');
            $stmt->execute([$proposed['name'],$proposed['nickname']]);$rows=$stmt->fetchAll();
            if (count($rows)===1) { $rows[0]['_match_type']='name_version';$rows[0]['_match_score']=80;return $rows[0]; }
        }
        return null;
    }

    public static function details($assetId)
    {
        $rows=Db::name('ipa_compare_result')->alias('r')
            ->join('ipa_software_source s','s.id=r.software_source_id','LEFT')
            ->field('r.*,s.name source_name,s.slug source_slug,s.database_name,s.table_name,s.allow_write,s.enabled')
            ->where('r.asset_id',(int)$assetId)->order('s.priority desc,r.id asc')->select();
        foreach ($rows as &$row) {
            $decoded=json_decode((string)$row['diff_json'],true);
            $row['diff']=is_array($decoded)?$decoded:[];
            unset($row['diff_json']);
        }
        unset($row);
        return $rows;
    }

    public static function summaryForAssets(array $assetIds)
    {
        $out=[];
        if (!$assetIds) return $out;
        $rows=Db::name('ipa_compare_result')->field('asset_id,status,anomaly_count')->where('asset_id','in',$assetIds)->select();
        foreach ($rows as $row) {
            $id=(int)$row['asset_id'];
            if (!isset($out[$id])) $out[$id]=['sources'=>0,'anomalies'=>0,'unmatched'=>0,'errors'=>0];
            $out[$id]['sources']++;
            $out[$id]['anomalies']+=(int)$row['anomaly_count'];
            if ($row['status']==='unmatched') $out[$id]['unmatched']++;
            if ($row['status']==='source_error') $out[$id]['errors']++;
        }
        return $out;
    }

    public static function applyWriteback($compareId,array $fields)
    {
        $compare=Db::name('ipa_compare_result')->where('id',(int)$compareId)->find();
        if (!$compare || !(int)$compare['category_id']) throw new \InvalidArgumentException('没有可写回的数据库匹配项');
        $source=IpaSoftwareSourceService::get((int)$compare['software_source_id'],true);
        if (!(int)$source['enabled']) throw new \RuntimeException('软件源已停用');
        if (!(int)$source['allow_write']) throw new \RuntimeException('该软件源为只读；请先在“软件源”中明确开启字段写回');
        $diff=json_decode((string)$compare['diff_json'],true);
        if (!is_array($diff)) throw new \RuntimeException('比对结果无效');
        $data=[];
        foreach ($fields as $field) {
            $field=(string)$field;
            if (!in_array($field,self::$fields,true) || !isset($diff[$field])) continue;
            $data[$field]=(string)$diff[$field]['proposed'];
        }
        if (!$data) throw new \InvalidArgumentException('没有选择写回字段');
        $pdo=IpaSoftwareSourceService::pdo($source);
        $table=IpaSoftwareSourceService::quoteIdentifier($source['table_name']);
        $sets=[];$params=[];
        foreach ($data as $field=>$value) { $sets[]=IpaSoftwareSourceService::quoteIdentifier($field).'=?';$params[]=$value; }
        $params[]=(int)$compare['category_id'];
        $stmt=$pdo->prepare('UPDATE '.$table.' SET '.implode(',',$sets).' WHERE id=? LIMIT 1');
        $stmt->execute($params);
        self::refreshAsset((int)$compare['asset_id']);
        return ['changed'=>$stmt->rowCount(),'fields'=>array_keys($data)];
    }
}
