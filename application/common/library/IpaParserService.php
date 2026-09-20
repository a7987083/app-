<?php

namespace app\common\library;

use think\Db;
use RuntimeException;
use Throwable;

class IpaParserService
{
    const RETRY_DELAY = 1800;

    /** One worker run consumes at most one IPA. */
    public static function parseBatch($taskId = 0, $limit = 1)
    {
        $taskId=(int)$taskId;$item=self::claimOne($taskId);
        $summary=['selected'=>$item?1:0,'parsed'=>0,'reused'=>0,'failed'=>0,'retrying'=>0,'range_bytes'=>0,'range_requests'=>0];
        if(!$item)return $summary;
        try{
            $result=self::parseOne($item,true);
            if(!empty($result['stale'])){$summary['retrying']++;return $summary;}
            if(!empty($result['reused']))$summary['reused']++;else$summary['parsed']++;
            $summary['range_bytes']+=isset($result['range_bytes'])?(int)$result['range_bytes']:0;$summary['range_requests']+=isset($result['range_requests'])?(int)$result['range_requests']:0;
        }catch(\Exception $e){self::recordFailure($item,$e->getMessage());$summary['failed']++;}
        catch(Throwable $e){self::recordFailure($item,$e->getMessage());$summary['failed']++;}
        return $summary;
    }

    protected static function claimOne($taskId=0)
    {
        $ids=[];
        if((int)$taskId>0){$snapshot=IpaScanService::snapshot((int)$taskId);$ids=isset($snapshot['parse_ids'])&&is_array($snapshot['parse_ids'])?array_values(array_filter(array_map('intval',$snapshot['parse_ids']))):[];if(!$ids)return null;}
        for($attempt=0;$attempt<10;$attempt++){
            $q=Db::name('ipa_metadata')->where('parse_state','pending');if($ids)$q->where('id','in',$ids);$candidate=$q->order('id','asc')->find();
            if(!$candidate){$q=Db::name('ipa_metadata')->where('parse_state','failed')->where('updatetime','<=',time()-self::RETRY_DELAY);if($ids)$q->where('id','in',$ids);$candidate=$q->order('id','asc')->find();}
            if(!$candidate)return null;
            $oldState=(string)$candidate['parse_state'];$affected=Db::name('ipa_metadata')->where('id',(int)$candidate['id'])->where('parse_state',$oldState)->update(['parse_state'=>'parsing','parse_error'=>'','updatetime'=>time()]);
            if((int)$affected!==1)continue;
            return ['metadata_id'=>(int)$candidate['id'],'task_id'=>(int)$taskId,'source_fingerprint'=>self::sourceFingerprint($candidate)];
        }
        return null;
    }

    public static function parseOne(array $item,$alreadyClaimed=false)
    {
        $metadataId=isset($item['metadata_id'])?(int)$item['metadata_id']:(isset($item['id'])?(int)$item['id']:0);if($metadataId<=0)throw new RuntimeException('Metadata id missing');
        $metadata=Db::name('ipa_metadata')->where('id',$metadataId)->find();if(!$metadata)throw new RuntimeException('Metadata row not found');
        $fingerprint=isset($item['source_fingerprint'])?(string)$item['source_fingerprint']:self::sourceFingerprint($metadata);
        $source=IpaSourceConfig::first(true);if(!$source||empty($source['enabled']))throw new RuntimeException('OpenList 已停用');
        if(!$alreadyClaimed){$affected=Db::name('ipa_metadata')->where('id',$metadataId)->where('parse_state','in',['pending','failed'])->update(['parse_state'=>'parsing','parse_error'=>'','updatetime'=>time()]);if((int)$affected!==1)throw new RuntimeException('解析任务已被其他 Worker 领取');}

        $cached=self::findReusableMetadata($metadata);
        if($cached)return self::applyReusableMetadata($metadata,$cached,$fingerprint);

        $client=IpaSourceConfig::clientFromRow($source);$detail=$client->getFile($metadata['remote_path']);
        $rawUrl=isset($detail['raw_url'])?trim((string)$detail['raw_url']):'';$size=isset($detail['size'])?(int)$detail['size']:(int)$metadata['file_size'];if($rawUrl==='')throw new RuntimeException('OpenList 未返回 raw_url');
        $parsed=IpaParserRunner::parse($rawUrl,$size);$normalized=IpaMetadataNormalizer::normalize($parsed);$columns=$normalized['columns'];
        $rawSelected=isset($parsed['raw_selected'])&&is_array($parsed['raw_selected'])?$parsed['raw_selected']:[];$rangeBytes=isset($parsed['range_bytes'])?(int)$parsed['range_bytes']:0;$rangeRequests=isset($parsed['range_requests'])?(int)$parsed['range_requests']:0;
        $normalizedPayload=$normalized['normalized'];if(!isset($normalizedPayload['_parser'])||!is_array($normalizedPayload['_parser']))$normalizedPayload['_parser']=[];$normalizedPayload['_parser']['range_bytes']=$rangeBytes;$normalizedPayload['_parser']['range_requests']=$rangeRequests;
        $fresh=Db::name('ipa_metadata')->where('id',$metadataId)->find();if(!$fresh||self::sourceFingerprint($fresh)!==$fingerprint){if($fresh)Db::name('ipa_metadata')->where('id',$metadataId)->where('parse_state','parsing')->update(['parse_state'=>'pending','parse_error'=>'','updatetime'=>time()]);return ['stale'=>true,'reused'=>false,'range_bytes'=>$rangeBytes,'range_requests'=>$rangeRequests];}
        IpaMetadataPayloadStore::save($metadataId,['confidence'=>$normalized['confidence'],'raw'=>$rawSelected,'normalized'=>$normalizedPayload]);
        Db::startTrans();
        try{
            $fresh=Db::name('ipa_metadata')->where('id',$metadataId)->lock(true)->find();if(!$fresh||self::sourceFingerprint($fresh)!==$fingerprint){if($fresh)Db::name('ipa_metadata')->where('id',$metadataId)->update(['parse_state'=>'pending','updatetime'=>time()]);Db::commit();return ['stale'=>true,'reused'=>false,'range_bytes'=>$rangeBytes,'range_requests'=>$rangeRequests];}
            Db::name('ipa_metadata')->where('id',$metadataId)->update(['file_size'=>$size>0?$size:(int)$metadata['file_size'],'bundle_id'=>$columns['bundle_id'],'package_name'=>$columns['package_name'],'package_version'=>$columns['package_version'],'package_build'=>$columns['package_build'],'minimum_ios'=>$columns['minimum_ios'],'executable'=>$columns['executable'],'parse_state'=>'success','parser_version'=>IpaFoundation::PARSER_VERSION,'confidence_json'=>'','raw_metadata_json'=>'','normalized_metadata_json'=>'','parse_error'=>'','parsed_at'=>time(),'updatetime'=>time()]);
            $remember=array_merge($metadata,$columns,['file_size'=>$size>0?$size:(int)$metadata['file_size'],'parser_version'=>IpaFoundation::PARSER_VERSION,'parsed_at'=>time()]);IpaParseCache::remember($remember);Db::commit();
        }catch(\Exception $e){Db::rollback();throw $e;}catch(Throwable $e){Db::rollback();throw $e;}
        return ['stale'=>false,'reused'=>false,'range_bytes'=>$rangeBytes,'range_requests'=>$rangeRequests];
    }

    protected static function findReusableMetadata(array $metadata)
    {
        $cached=IpaParseCache::find(isset($metadata['md5'])?$metadata['md5']:'',isset($metadata['file_size'])?(int)$metadata['file_size']:0,IpaFoundation::PARSER_VERSION);if($cached){$cached['_cache_id']=(int)$cached['id'];return $cached;}
        $md5=strtolower(trim((string)$metadata['md5']));if($md5===''||(int)$metadata['file_size']<=0)return null;
        $legacy=Db::name('ipa_metadata')->where('id','<>',(int)$metadata['id'])->where('md5',$md5)->where('file_size',(int)$metadata['file_size'])->where('parser_version',IpaFoundation::PARSER_VERSION)->where('parse_state','success')->order('parsed_at','desc')->find();if($legacy){IpaParseCache::remember($legacy);$legacy['_legacy_metadata_id']=(int)$legacy['id'];}return $legacy?:null;
    }

    protected static function applyReusableMetadata(array $metadata,array $cached,$fingerprint)
    {
        $fresh=Db::name('ipa_metadata')->where('id',(int)$metadata['id'])->find();if(!$fresh||self::sourceFingerprint($fresh)!==(string)$fingerprint){if($fresh)Db::name('ipa_metadata')->where('id',(int)$metadata['id'])->where('parse_state','parsing')->update(['parse_state'=>'pending','updatetime'=>time()]);return ['stale'=>true,'reused'=>true,'range_bytes'=>0,'range_requests'=>0];}
        $payload=null;if(!empty($cached['_legacy_metadata_id']))$payload=IpaMetadataPayloadStore::hydrateLegacyRow($cached);elseif(!empty($cached['_cache_id']))$payload=IpaParseCache::payload($cached);
        if($payload)IpaMetadataPayloadStore::save((int)$metadata['id'],['confidence'=>isset($payload['confidence'])&&is_array($payload['confidence'])?$payload['confidence']:[],'raw'=>isset($payload['raw'])&&is_array($payload['raw'])?$payload['raw']:[],'normalized'=>isset($payload['normalized'])&&is_array($payload['normalized'])?$payload['normalized']:[]]);
        Db::name('ipa_metadata')->where('id',(int)$metadata['id'])->update(['bundle_id'=>$cached['bundle_id'],'package_name'=>$cached['package_name'],'package_version'=>$cached['package_version'],'package_build'=>$cached['package_build'],'minimum_ios'=>$cached['minimum_ios'],'executable'=>$cached['executable'],'parse_state'=>'success','parser_version'=>IpaFoundation::PARSER_VERSION,'confidence_json'=>'','raw_metadata_json'=>'','normalized_metadata_json'=>'','parse_error'=>'','parsed_at'=>time(),'updatetime'=>time()]);if(!empty($cached['_cache_id']))IpaParseCache::touch((int)$cached['_cache_id']);
        return ['stale'=>false,'reused'=>true,'range_bytes'=>0,'range_requests'=>0];
    }

    protected static function recordFailure(array $item,$message)
    {
        $metadataId=isset($item['metadata_id'])?(int)$item['metadata_id']:0;if($metadataId>0)Db::name('ipa_metadata')->where('id',$metadataId)->update(['parse_state'=>'failed','parse_error'=>mb_substr((string)$message,0,2000,'UTF-8'),'updatetime'=>time()]);return 'failed';
    }

    protected static function sourceFingerprint(array $row)
    {
        $md5=strtolower(trim((string)(isset($row['md5'])?$row['md5']:'')));if($md5!=='')return 'md5:'.$md5.':'.(int)(isset($row['file_size'])?$row['file_size']:0);
        return 'fallback:'.hash('sha256',implode('|',[(string)(isset($row['remote_path'])?$row['remote_path']:''),(string)(isset($row['etag'])?$row['etag']:''),(int)(isset($row['file_size'])?$row['file_size']:0),(int)(isset($row['remote_mtime'])?$row['remote_mtime']:0)]));
    }

    public static function spawn($taskId=0,$limit=1){IpaWorkerService::enqueueParse((int)$taskId,0);return true;}
}
