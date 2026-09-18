<?php

namespace app\common\library;

use think\Db;
use RuntimeException;
use Throwable;

class IpaParserService
{
    const RETRY_DELAY = 1800;
    const MAX_RETRIES = 3;

    public static function parseBatch($taskId = 0, $limit = 0)
    {
        $taskId=(int)$taskId;$limit=(int)$limit;
        $query=Db::name('ipa_scan_task_item')->where('stage','parser_pending')->where('state','in',['queued','retrying'])->where(function($q){$q->where('retry_after',0)->whereOr('retry_after','<=',time());})->order('id','asc');
        if($taskId>0)$query->where('task_id',$taskId);
        if($limit<=0){$source=IpaSourceConfig::first(false);$limit=$source?max(1,min(100,(int)$source['batch_size'])):20;}
        $items=$query->limit($limit)->select();
        $summary=['selected'=>count((array)$items),'parsed'=>0,'reused'=>0,'failed'=>0,'retrying'=>0,'range_bytes'=>0,'range_requests'=>0];
        foreach((array)$items as $item){try{$result=self::parseOne($item);if(!empty($result['reused']))$summary['reused']++;else$summary['parsed']++;$summary['range_bytes']+=isset($result['range_bytes'])?(int)$result['range_bytes']:0;$summary['range_requests']+=isset($result['range_requests'])?(int)$result['range_requests']:0;}catch(\Exception $e){$state=self::recordFailure($item,$e->getMessage());$summary['failed']++;if($state==='retrying')$summary['retrying']++;}catch(Throwable $e){$state=self::recordFailure($item,$e->getMessage());$summary['failed']++;if($state==='retrying')$summary['retrying']++;}}
        return $summary;
    }

    public static function parseOne(array $item)
    {
        $metadata=Db::name('ipa_metadata')->where('id',(int)$item['metadata_id'])->find();
        if(!$metadata)throw new RuntimeException('Metadata row not found');
        $source=Db::name('ipa_source')->where('source_key',$metadata['source_key'])->find();
        if(!$source||empty($source['enabled']))throw new RuntimeException('IPA source unavailable');
        Db::name('ipa_scan_task_item')->where('id',(int)$item['id'])->update(['state'=>'running','stage'=>'parsing','updatetime'=>time()]);

        $cached=self::findReusableMetadata($metadata);
        if($cached){self::applyReusableMetadata($metadata,$cached,$item);return ['reused'=>true,'range_bytes'=>0,'range_requests'=>0];}

        $client=IpaSourceConfig::clientFromRow($source);$detail=$client->getFile($metadata['remote_path']);
        $rawUrl=isset($detail['raw_url'])?trim((string)$detail['raw_url']):'';$size=isset($detail['size'])?(int)$detail['size']:(int)$metadata['file_size'];
        if($rawUrl==='')throw new RuntimeException('OpenList 未返回 raw_url');
        $parsed=IpaParserRunner::parse($rawUrl,$size);$normalized=IpaMetadataNormalizer::normalize($parsed);$columns=$normalized['columns'];
        $rawSelected=isset($parsed['raw_selected'])&&is_array($parsed['raw_selected'])?$parsed['raw_selected']:[];$rangeBytes=isset($parsed['range_bytes'])?(int)$parsed['range_bytes']:0;$rangeRequests=isset($parsed['range_requests'])?(int)$parsed['range_requests']:0;
        Db::startTrans();
        try{
            Db::name('ipa_metadata')->where('id',(int)$metadata['id'])->update(['file_size'=>$size>0?$size:(int)$metadata['file_size'],'bundle_id'=>$columns['bundle_id'],'package_name'=>$columns['package_name'],'package_version'=>$columns['package_version'],'package_build'=>$columns['package_build'],'minimum_ios'=>$columns['minimum_ios'],'executable'=>$columns['executable'],'parse_state'=>'success','parser_version'=>IpaFoundation::PARSER_VERSION,'confidence_json'=>json_encode($normalized['confidence'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'raw_metadata_json'=>json_encode($rawSelected,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'normalized_metadata_json'=>json_encode($normalized['normalized'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'parse_error'=>'','parsed_at'=>time(),'updatetime'=>time()]);
            Db::name('ipa_scan_task_item')->where('id',(int)$item['id'])->update(['state'=>'success','stage'=>'parsed','retry_after'=>0,'result_json'=>json_encode(['discovery_type'=>self::discoveryType($item),'parser_version'=>IpaFoundation::PARSER_VERSION,'range_bytes'=>$rangeBytes,'range_requests'=>$rangeRequests,'reused'=>false],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'error_code'=>'','error_message'=>'','updatetime'=>time()]);
            Db::commit();
        }catch(\Exception $e){Db::rollback();throw $e;}catch(Throwable $e){Db::rollback();throw $e;}
        return ['reused'=>false,'range_bytes'=>$rangeBytes,'range_requests'=>$rangeRequests];
    }

    protected static function findReusableMetadata(array $metadata)
    {
        $md5=strtolower(trim((string)$metadata['md5']));if($md5===''||(int)$metadata['file_size']<=0)return null;
        return Db::name('ipa_metadata')->where('id','<>',(int)$metadata['id'])->where('md5',$md5)->where('file_size',(int)$metadata['file_size'])->where('parser_version',IpaFoundation::PARSER_VERSION)->where('parse_state','success')->where('normalized_metadata_json','<>','')->order('parsed_at','desc')->find();
    }

    protected static function applyReusableMetadata(array $metadata,array $cached,array $item)
    {
        Db::startTrans();
        try{
            Db::name('ipa_metadata')->where('id',(int)$metadata['id'])->update(['bundle_id'=>$cached['bundle_id'],'package_name'=>$cached['package_name'],'package_version'=>$cached['package_version'],'package_build'=>$cached['package_build'],'minimum_ios'=>$cached['minimum_ios'],'executable'=>$cached['executable'],'parse_state'=>'success','parser_version'=>IpaFoundation::PARSER_VERSION,'confidence_json'=>$cached['confidence_json'],'raw_metadata_json'=>$cached['raw_metadata_json'],'normalized_metadata_json'=>$cached['normalized_metadata_json'],'parse_error'=>'','parsed_at'=>time(),'updatetime'=>time()]);
            Db::name('ipa_scan_task_item')->where('id',(int)$item['id'])->update(['state'=>'success','stage'=>'parsed','retry_after'=>0,'result_json'=>json_encode(['discovery_type'=>self::discoveryType($item),'parser_version'=>IpaFoundation::PARSER_VERSION,'reused'=>true,'reused_from_metadata_id'=>(int)$cached['id'],'range_bytes'=>0,'range_requests'=>0],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'error_code'=>'','error_message'=>'','updatetime'=>time()]);
            Db::commit();
        }catch(\Exception $e){Db::rollback();throw $e;}catch(Throwable $e){Db::rollback();throw $e;}
    }

    protected static function recordFailure(array $item,$message)
    {
        $retryCount=(int)$item['retry_count']+1;$state=$retryCount>=self::MAX_RETRIES?'failed':'retrying';$retryAfter=$state==='retrying'?time()+self::RETRY_DELAY:0;
        Db::name('ipa_scan_task_item')->where('id',(int)$item['id'])->update(['state'=>$state,'stage'=>$state==='retrying'?'parser_pending':'parse_failed','retry_count'=>$retryCount,'retry_after'=>$retryAfter,'error_code'=>'parse_failed','error_message'=>mb_substr((string)$message,0,2000,'UTF-8'),'updatetime'=>time()]);
        Db::name('ipa_metadata')->where('id',(int)$item['metadata_id'])->update(['parse_state'=>'failed','parse_error'=>mb_substr((string)$message,0,2000,'UTF-8'),'updatetime'=>time()]);return $state;
    }

    protected static function discoveryType(array $item){$result=json_decode(isset($item['result_json'])?$item['result_json']:'',true);return is_array($result)&&!empty($result['discovery_type'])?$result['discovery_type']:'';}

    public static function spawn($taskId=0,$limit=0)
    {
        if(!defined('ROOT_PATH'))return false;$php=defined('PHP_BINARY')&&PHP_BINARY?PHP_BINARY:'php';$think=ROOT_PATH.'think';if(!is_file($think)||!function_exists('exec'))return false;
        $command=escapeshellarg($php).' '.escapeshellarg($think).' ipa:parse';if((int)$taskId>0)$command.=' --task='.(int)$taskId;if((int)$limit>0)$command.=' --limit='.(int)$limit;@exec('nohup '.$command.' >/dev/null 2>&1 &');return true;
    }
}
