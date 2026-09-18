<?php

namespace app\common\library;

use think\Db;
use think\Exception;

/**
 * Phase 20.5 global metadata -> category write-back template service.
 * Preview-first: actual category mutation is delegated to Phase 20.6.
 */
class IpaWritebackTemplate
{
    const TEMPLATE_KEY = 'default';
    private static $allowedTargets = ['name','nickname','image','bt1a','bt2a','keywords'];
    private static $allowedStrategies = ['preview','empty','changed','always','managed_block','ignore'];

    public static function allowedTargets(){return self::$allowedTargets;}
    public static function allowedStrategies(){return self::$allowedStrategies;}
    public static function defaultRules(){return [
        ['rule_key'=>'app_name','source_field'=>'package_name','target_field'=>'name','strategy'=>'changed','min_confidence'=>'exact','enabled'=>1],
        ['rule_key'=>'version','source_field'=>'package_version','target_field'=>'nickname','strategy'=>'changed','min_confidence'=>'exact','enabled'=>1],
        ['rule_key'=>'icon','source_field'=>'primary_icon','target_field'=>'image','strategy'=>'empty','min_confidence'=>'derived','enabled'=>1],
        ['rule_key'=>'download_url','source_field'=>'public_url','target_field'=>'bt1a','strategy'=>'changed','min_confidence'=>'exact','enabled'=>1],
        ['rule_key'=>'file_size','source_field'=>'file_size','target_field'=>'bt2a','strategy'=>'changed','min_confidence'=>'exact','enabled'=>1],
        ['rule_key'=>'description_meta','source_field'=>'managed_description','target_field'=>'keywords','strategy'=>'managed_block','min_confidence'=>'derived','enabled'=>1],
    ];}
    public static function confidenceRank($v){$m=['fallback'=>1,'derived'=>2,'exact'=>3];return isset($m[$v])?$m[$v]:0;}
    public static function validateRule(array $r){
        if(empty($r['rule_key'])||empty($r['source_field']))throw new Exception('rule_key/source_field required');
        if(!in_array($r['target_field'],self::$allowedTargets,true))throw new Exception('write-back target is not allowed: '.$r['target_field']);
        if(!in_array($r['strategy'],self::$allowedStrategies,true))throw new Exception('write-back strategy is not allowed: '.$r['strategy']);
        $c=isset($r['min_confidence'])?$r['min_confidence']:'exact';if(self::confidenceRank($c)===0)throw new Exception('invalid min_confidence');return true;
    }
    public static function validateRules(array $rules){
        $targets=[];$keys=[];foreach($rules as $r){self::validateRule($r);if(isset($keys[$r['rule_key']]))throw new Exception('duplicate rule_key: '.$r['rule_key']);$keys[$r['rule_key']]=1;if(!empty($r['enabled'])&&$r['strategy']!=='ignore'){if(isset($targets[$r['target_field']]))throw new Exception('multiple enabled rules target category.'.$r['target_field']);$targets[$r['target_field']]=1;}}return true;
    }
    public static function managedBlock($existing,$body){$s='<!-- IPA_META_START -->';$e='<!-- IPA_META_END -->';$block=$s."\n".trim((string)$body)."\n".$e;$existing=(string)$existing;$p='/<!-- IPA_META_START -->.*?<!-- IPA_META_END -->/s';if(preg_match($p,$existing))return trim(preg_replace($p,$block,$existing));return trim($existing)===''?$block:rtrim($existing)."\n\n".$block;}
    public static function shouldApply($strategy,$oldValue,$newValue){$old=trim((string)$oldValue);$new=trim((string)$newValue);if($strategy==='ignore'||$strategy==='preview'||$new==='')return false;if($strategy==='empty')return $old==='';if($strategy==='changed')return $old!==$new;if($strategy==='always'||$strategy==='managed_block')return true;return false;}
    public static function normalizeSource(array $m){$n=[];if(!empty($m['normalized_metadata_json'])){$d=json_decode($m['normalized_metadata_json'],true);if(is_array($d))$n=$d;}$name=isset($m['package_name'])?$m['package_name']:'';$ver=isset($m['package_version'])?$m['package_version']:'';$min=isset($m['minimum_ios'])?$m['minimum_ios']:'';$bid=isset($m['bundle_id'])?$m['bundle_id']:'';$build=isset($m['package_build'])?$m['package_build']:'';$icon=isset($n['primary_icon'])?(is_array($n['primary_icon'])?(isset($n['primary_icon']['name'])?$n['primary_icon']['name']:''):$n['primary_icon']):'';$lines=[];if($bid!=='')$lines[]='Bundle ID：'.$bid;if($ver!=='')$lines[]='版本：'.$ver.($build!==''?' ('.$build.')':'');if($min!=='')$lines[]='最低系统要求：iOS '.$min;return ['package_name'=>$name,'package_version'=>$ver,'primary_icon'=>$icon,'public_url'=>isset($m['public_url'])?$m['public_url']:'','file_size'=>isset($m['file_size'])?(string)$m['file_size']:'','managed_description'=>implode("\n",$lines)];}
    public static function preview(array $category,array $metadata,array $rules){$src=self::normalizeSource($metadata);$cm=[];if(!empty($metadata['confidence_json'])){$d=json_decode($metadata['confidence_json'],true);if(is_array($d))$cm=$d;}$rows=[];foreach($rules as $r){self::validateRule($r);if(isset($r['enabled'])&&!$r['enabled'])continue;$f=$r['target_field'];$sf=$r['source_field'];$old=isset($category[$f])?$category[$f]:'';$raw=isset($src[$sf])?$src[$sf]:'';$new=$r['strategy']==='managed_block'?self::managedBlock($old,$raw):$raw;$actual=isset($cm[$sf])?$cm[$sf]:(($sf==='primary_icon'||$sf==='managed_description')?'derived':'exact');$ok=self::confidenceRank($actual)>=self::confidenceRank($r['min_confidence']);$rows[]=['rule_key'=>$r['rule_key'],'source_field'=>$sf,'target_field'=>'category.'.$f,'old_value'=>$old,'new_value'=>$new,'strategy'=>$r['strategy'],'confidence'=>$actual,'confidence_ok'=>$ok,'will_apply'=>$ok&&self::shouldApply($r['strategy'],$old,$new)];}return $rows;}
    public static function activeVersion($templateKey=self::TEMPLATE_KEY){return (int)Db::name('ipa_writeback_rule')->where('template_key',$templateKey)->max('template_version');}
    public static function loadActiveRules($templateKey=self::TEMPLATE_KEY){$v=self::activeVersion($templateKey);if(!$v)return self::defaultRules();return Db::name('ipa_writeback_rule')->where(['template_key'=>$templateKey,'template_version'=>$v])->order('id asc')->select();}
    private static function audit($type,$adminId,$before,$after,$suffix){$now=time();$operationId=substr(hash('sha256',$type.'|'.$suffix.'|'.uniqid('',true)),0,36);$idem=hash('sha256',$type.'|'.$suffix);Db::name('ipa_operation_log')->insert(['operation_id'=>$operationId,'idempotency_key'=>$idem,'operation_type'=>$type,'category_id'=>0,'metadata_id'=>0,'plan_hash'=>hash('sha256',json_encode($after,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),'state'=>'success','before_json'=>json_encode($before,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'after_json'=>json_encode($after,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'result_json'=>'{"verified":true}','error_message'=>'','admin_id'=>(int)$adminId,'started_at'=>$now,'finished_at'=>$now,'createtime'=>$now,'updatetime'=>$now]);}
    public static function seedDefaults($adminId=0){if(self::activeVersion())return false;$rules=self::defaultRules();self::validateRules($rules);Db::startTrans();try{$now=time();foreach($rules as $r){$r['template_key']=self::TEMPLATE_KEY;$r['template_version']=1;$r['options_json']='{}';$r['admin_id']=(int)$adminId;$r['createtime']=$now;$r['updatetime']=$now;Db::name('ipa_writeback_rule')->insert($r);}self::audit('writeback_template_seed',$adminId,[],['version'=>1,'rules'=>$rules],'default-v1');Db::commit();return true;}catch(\Exception $e){Db::rollback();throw $e;}}
    public static function saveNewVersion(array $rules,$adminId=0){self::validateRules($rules);$oldVersion=self::activeVersion();$old=self::loadActiveRules();$newVersion=max(1,$oldVersion+1);Db::startTrans();try{$now=time();foreach($rules as $r){$row=['template_key'=>self::TEMPLATE_KEY,'template_version'=>$newVersion,'rule_key'=>(string)$r['rule_key'],'source_field'=>(string)$r['source_field'],'target_field'=>(string)$r['target_field'],'strategy'=>(string)$r['strategy'],'min_confidence'=>(string)$r['min_confidence'],'options_json'=>isset($r['options_json'])?(is_string($r['options_json'])?$r['options_json']:json_encode($r['options_json'])):'{}','enabled'=>!empty($r['enabled'])?1:0,'admin_id'=>(int)$adminId,'createtime'=>$now,'updatetime'=>$now];Db::name('ipa_writeback_rule')->insert($row);}self::audit('writeback_template_version',$adminId,['version'=>$oldVersion,'rules'=>$old],['version'=>$newVersion,'rules'=>$rules],'default-v'.$newVersion);Db::commit();return $newVersion;}catch(\Exception $e){Db::rollback();throw $e;}}
}
