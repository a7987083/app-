<?php

namespace app\common\library;

use think\Db;
use RuntimeException;
use Throwable;

class IpaParserService
{
    const RETRY_DELAY = 1800;
    const MAX_RETRIES = 3;

    /** One worker run consumes at most one IPA. */
    public static function parseBatch($taskId = 0, $limit = 1)
    {
        $taskId=(int)$taskId;
        $item=self::claimOne($taskId);
        $summary=['selected'=>$item?1:0,'parsed'=>0,'reused'=>0,'failed'=>0,'retrying'=>0,'range_bytes'=>0,'range_requests'=>0];
        if(!$item)return $summary;
        try{
            $result=self::parseOne($item,true);
            if(!empty($result['reused']))$summary['reused']++;else$summary['parsed']++;
            $summary['range_bytes']+=isset($result['range_bytes'])?(int)$result['range_bytes']:0;$summary['range_requests']+=isset($result['range_requests'])?(int)$result['range_requests']:0;
        }catch(\Exception $e){$state=self::recordFailure($item,$e->getMessage());$summary['failed']++;if($state==='retrying')$summary['retrying']++;}
        catch(Throwable $e){$state=self::recordFailure($item,$e->getMessage());$summary['failed']++;if($state==='retrying')$summary['retrying']++;}
        IpaScanService::pruneTaskItems(90);
        return $summary;
    }

    protected static function claimOne($taskId=0)
    {
        for($attempt=0;$attempt<10;$attempt++){
            $query=Db::name('ipa_scan_task_item')->where('stage','parser_pending')->where('state','in',['queued','retrying'])->where(function($q){$q->where('retry_after',0)->whereOr('retry_after','<=',time());})->order('id','asc');
            if((int)$taskId>0)$query->where('task_id',(int)$taskId);
            $candidate=$query->find();if(!$candidate)return null;
            $affected=Db::name('ipa_scan_task_item')->where('id',(int)$candidate['id'])->where('stage','parser_pending')->where('state','in',['queued','retrying'])->update(['state'=>'running','stage'=>'parsing','updatetime'=>time()]);
            if((int)$affected!==1)continue;
            $candidate['state']='running';$candidate['stage']='parsing';
            Db::name('ipa_metadata')->where('id',(int)$candidate['metadata_id'])->where('referenced',1)->update(['parse_state'=>'parsing','updatetime'=>time()]);
            return $candidate;
        }
        return null;
    }

    public static function parseOne(array $item,$alreadyClaimed=false)
    {
        $metadata=Db::name('ipa_metadata')->where('id',(int)$item['metadata_id'])->find();
        if(!$metadata)throw new RuntimeException('Metadata row not found');
        if(isset($metadata['referenced'])&&(int)$metadata['referenced']!==1)throw new RuntimeException('IPA 已不再被 MySQL 软件源引用');
        $source=IpaSourceConfig::first(true);
        if(!$source||empty($source['enabled']))throw new RuntimeException('OpenList 已停用');
        if(!$alreadyClaimed){
            $affected=Db::name('ipa_scan_task_item')->where('id',(int)$item['id'])->where('state','in',['queued','retrying'])->update(['state'=>'running','stage'=>'parsing','updatetime'=>time()]);
            if((int)$affected!==1)throw new RuntimeException('解析任务已被其他 Worker 领取');
            Db::name('ipa_metadata')->where('id',(int)$metadata['id'])->update(['parse_state'=>'parsing','updatetime'=>time()]);
        }

        $cached=self::findReusableMetadata($metadata);
        if($cached){self::applyReusableMetadata($metadata,$cached,$item);return ['reused'=>true,'range_bytes'=>0,'range_requests'=>0];}

        $client=IpaSourceConfig::clientFromRow($source);$detail=$client->getFile($metadata['remote_path']);
        $rawUrl=isset($detail['raw_url'])?trim((string)$detail['raw_url']):'';$size=isset($detail['size'])?(int)$detail['size']:(int)$metadata['file_size'];
        if($rawUrl==='')throw new RuntimeException('OpenList 未返回 raw_url');
        $parsed=IpaParserRunner::parse($rawUrl,$size);$normalized=IpaMetadataNormalizer::normalize($parsed);$columns=$normalized['columns'];
        $rawSelected=isset($parsed['raw_selected'])&&is_array($parsed['raw_selected'])?$parsed['raw_selected']:[];$rangeBytes=isset($parsed['range_bytes'])?(int)$parsed['range_bytes']:0;$rangeRequests=isset($parsed['range_requests'])?(int)$parsed['range_requests']:0;
        $normalizedPayload=$normalized['normalized'];
        if(!isset($normalizedPayload['_parser'])||!is_array($normalizedPayload['_parser']))$normalizedPayload['_parser']=[];
        $normalizedPayload['_parser']['range_bytes']=$rangeBytes;$normalizedPayload['_parser']['range_requests']=$rangeRequests;
        IpaMetadataPayloadStore::save((int)$metadata['id'],['confidence'=>$normalized['confidence'],'raw'=>$rawSelected,'normalized'=>$normalizedPayload]);
        Db::startTrans();
        try{
            $fresh=Db::name('ipa_metadata')->where('id',(int)$metadata['id'])->find();$needsReparse=$fresh&&!empty($fresh['needs_reparse']);
            $nextState=$needsReparse?'pending':'success';
            Db::name('ipa_metadata')->where('id',(int)$metadata['id'])->update([
                'file_size'=>$size>0?$size:(int)$metadata['file_size'],'bundle_id'=>$columns['bundle_id'],'package_name'=>$columns['package_name'],'package_version'=>$columns['package_version'],'package_build'=>$columns['package_build'],'minimum_ios'=>$columns['minimum_ios'],'executable'=>$columns['executable'],
                'parse_state'=>$nextState,'parser_version'=>IpaFoundation::PARSER_VERSION,'needs_reparse'=>0,
                'confidence_json'=>'','raw_metadata_json'=>'','normalized_metadata_json'=>'',
                'parse_error'=>'','parsed_at'=>time(),'updatetime'=>time()
            ]);
            Db::name('ipa_scan_task_item')->where('id',(int)$item['id'])->update(['state'=>'success','stage'=>'parsed','retry_after'=>0,'result_json'=>json_encode(['discovery_type'=>self::discoveryType($item),'parser_version'=>IpaFoundation::PARSER_VERSION,'range_bytes'=>$rangeBytes,'range_requests'=>$rangeRequests,'reused'=>false,'needs_reparse'=>$needsReparse],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'error_code'=>'','error_message'=>'','updatetime'=>time()]);
            $remember=array_merge($metadata,$columns,['file_size'=>$size>0?$size:(int)$metadata['file_size'],'parser_version'=>IpaFoundation::PARSER_VERSION,'parsed_at'=>time()]);IpaParseCache::remember($remember);
            Db::commit();
        }catch(\Exception $e){Db::rollback();throw $e;}catch(Throwable $e){Db::rollback();throw $e;}
        return ['reused'=>false,'range_bytes'=>$rangeBytes,'range_requests'=>$rangeRequests];
    }

    protected static function findReusableMetadata(array $metadata)
    {
        $cached=IpaParseCache::find(isset($metadata['md5'])?$metadata['md5']:'',isset($metadata['file_size'])?(int)$metadata['file_size']:0,IpaFoundation::PARSER_VERSION);
        if($cached){$cached['_cache_id']=(int)$cached['id'];return $cached;}
        // One-time bridge for 1913 data: reuse an existing successful metadata
        // row and seed the new durable cache without requiring a full reparse.
        $md5=strtolower(trim((string)$metadata['md5']));if($md5===''||(int)$metadata['file_size']<=0)return null;
        $legacy=Db::name('ipa_metadata')->where('id','<>',(int)$metadata['id'])->where('md5',$md5)->where('file_size',(int)$metadata['file_size'])->where('parser_version',IpaFoundation::PARSER_VERSION)->where('parse_state','success')->order('parsed_at','desc')->find();
        if($legacy){IpaParseCache::remember($legacy);$legacy['_legacy_metadata_id']=(int)$legacy['id'];}
        return $legacy?:null;
    }

    protected static function applyReusableMetadata(array $metadata,array $cached,array $item)
    {
        $payload=null;
        if(!empty($cached['_legacy_metadata_id']))$payload=IpaMetadataPayloadStore::hydrateLegacyRow($cached);
        elseif(!empty($cached['_cache_id']))$payload=IpaParseCache::payload($cached);
        if($payload)IpaMetadataPayloadStore::save((int)$metadata['id'],[
            'confidence'=>isset($payload['confidence'])&&is_array($payload['confidence'])?$payload['confidence']:[],
            'raw'=>isset($payload['raw'])&&is_array($payload['raw'])?$payload['raw']:[],
            'normalized'=>isset($payload['normalized'])&&is_array($payload['normalized'])?$payload['normalized']:[]
        ]);
        Db::startTrans();
        try{
            $fresh=Db::name('ipa_metadata')->where('id',(int)$metadata['id'])->find();$needsReparse=$fresh&&!empty($fresh['needs_reparse']);$nextState=$needsReparse?'pending':'success';
            Db::name('ipa_metadata')->where('id',(int)$metadata['id'])->update(['bundle_id'=>$cached['bundle_id'],'package_name'=>$cached['package_name'],'package_version'=>$cached['package_version'],'package_build'=>$cached['package_build'],'minimum_ios'=>$cached['minimum_ios'],'executable'=>$cached['executable'],'parse_state'=>$nextState,'parser_version'=>IpaFoundation::PARSER_VERSION,'needs_reparse'=>0,'confidence_json'=>'','raw_metadata_json'=>'','normalized_metadata_json'=>'','parse_error'=>'','parsed_at'=>time(),'updatetime'=>time()]);
            Db::name('ipa_scan_task_item')->where('id',(int)$item['id'])->update(['state'=>'success','stage'=>'parsed','retry_after'=>0,'result_json'=>json_encode(['discovery_type'=>self::discoveryType($item),'parser_version'=>IpaFoundation::PARSER_VERSION,'reused'=>true,'reused_from_cache_id'=>isset($cached['_cache_id'])?(int)$cached['_cache_id']:0,'range_bytes'=>0,'range_requests'=>0,'needs_reparse'=>$needsReparse],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'error_code'=>'','error_message'=>'','updatetime'=>time()]);
            if(!empty($cached['_cache_id']))IpaParseCache::touch((int)$cached['_cache_id']);
            Db::commit();
        }catch(\Exception $e){Db::rollback();throw $e;}catch(Throwable $e){Db::rollback();throw $e;}
    }

    protected static function recordFailure(array $item,$message)
    {
        $retryCount=(int)$item['retry_count']+1;$state=$retryCount>=self::MAX_RETRIES?'failed':'retrying';$retryAfter=$state==='retrying'?time()+self::RETRY_DELAY:0;
        Db::name('ipa_scan_task_item')->where('id',(int)$item['id'])->update(['state'=>$state,'stage'=>$state==='retrying'?'parser_pending':'parse_failed','retry_count'=>$retryCount,'retry_after'=>$retryAfter,'error_code'=>'parse_failed','error_message'=>mb_substr((string)$message,0,2000,'UTF-8'),'updatetime'=>time()]);
        $metadataState=$state==='retrying'?'pending':'failed';
        Db::name('ipa_metadata')->where('id',(int)$item['metadata_id'])->update(['parse_state'=>$metadataState,'parse_error'=>mb_substr((string)$message,0,2000,'UTF-8'),'updatetime'=>time()]);return $state;
    }

    protected static function discoveryType(array $item){$result=json_decode(isset($item['result_json'])?$item['result_json']:'',true);return is_array($result)&&!empty($result['discovery_type'])?$result['discovery_type']:'';}

    public static function spawn($taskId=0,$limit=1)
    {
        if(!defined('ROOT_PATH')||!function_exists('exec'))return false;$think=ROOT_PATH.'think';if(!is_file($think))return false;
        $php=self::cliPhpBinary();if($php==='')return false;
        $command='nohup '.escapeshellarg($php).' '.escapeshellarg($think).' ipa:parse';if((int)$taskId>0)$command.=' --task='.(int)$taskId;$command.=' --limit=1 >/dev/null 2>&1 & echo $!';
        $out=[];$code=0;@exec($command,$out,$code);return $code===0&&!empty($out)&&((int)$out[0])>0;
    }

    protected static function cliPhpBinary()
    {
        $candidates=['/usr/bin/php','/usr/local/bin/php'];if(defined('PHP_BINARY')&&PHP_BINARY)$candidates[]=(string)PHP_BINARY;
        foreach(array_unique($candidates) as $candidate){if(!is_file($candidate)||!is_executable($candidate))continue;$base=strtolower(basename($candidate));if(strpos($base,'fpm')!==false||strpos($base,'cgi')!==false)continue;return $candidate;}return '';
    }
}
