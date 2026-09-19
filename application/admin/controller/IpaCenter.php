<?php
namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\IpaScanService;
use app\common\library\IpaParserService;
use app\common\library\IpaSourceConfig;
use app\common\library\IpaMetadataPayloadStore;
use app\common\library\IpaBindingService;
use app\common\library\IpaWritebackTemplate;
use app\common\library\IpaGovernanceService;
use app\common\library\IpaGovernanceBatchService;
use think\Db;
use RuntimeException;

class IpaCenter extends Backend
{
    protected $noNeedRight = ['source_save', 'source_test'];
    protected $layout = 'default';

    public function index()
    {
        if ($this->request->isAjax()) {
            $metadataTotal = (int)Db::name('ipa_metadata')->count();
            $parsed = (int)Db::name('ipa_metadata')->where('parse_state', 'success')->count();
            $pending = (int)Db::name('ipa_metadata')->where('parse_state', 'pending')->count();
            $failed = (int)Db::name('ipa_metadata')->where('parse_state', 'failed')->count();
            $bound = (int)Db::name('ipa_binding')->count();
            $governance = IpaGovernanceService::stats();
            $source = IpaSourceConfig::first(false);
            $tasks = Db::name('ipa_scan_task')->order('id', 'desc')->limit(5)->select();
            foreach ((array)$tasks as &$task) {
                $task['started_at_text'] = !empty($task['started_at']) ? date('Y-m-d H:i:s', $task['started_at']) : '';
                $task['finished_at_text'] = !empty($task['finished_at']) ? date('Y-m-d H:i:s', $task['finished_at']) : '';
                unset($task['cursor_json']);
            }
            unset($task);
            $governanceTotal = 0;
            foreach ((array)$governance as $value) {
                if (is_numeric($value)) {
                    $governanceTotal += (int)$value;
                }
            }
            $this->success('', null, [
                'stats' => [
                    'metadata_total' => $metadataTotal,
                    'parsed' => $parsed,
                    'pending' => $pending,
                    'failed' => $failed,
                    'bound' => $bound,
                    'unbound' => max(0, $parsed - $bound),
                    'governance' => $governanceTotal,
                ],
                'source' => [
                    'configured' => !empty($source),
                    'last_health' => $source && isset($source['last_health']) ? $source['last_health'] : 'unknown',
                    'last_checked_at' => $source && !empty($source['last_checked_at']) ? date('Y-m-d H:i:s', $source['last_checked_at']) : '',
                    'last_scan_at' => $source && !empty($source['last_scan_at']) ? date('Y-m-d H:i:s', $source['last_scan_at']) : '',
                ],
                'tasks' => $tasks,
            ]);
        }
        return $this->view->fetch();
    }

    public function metadata(){return $this->view->fetch();}
    public function binding(){return $this->view->fetch();}
    public function governance(){return $this->view->fetch();}
    public function task(){return $this->view->fetch();}
    public function writeback(){$this->view->assign('templateVersion',IpaWritebackTemplate::activeVersion());return $this->view->fetch();}
    public function setting(){$source=IpaSourceConfig::first(false);$this->view->assign('source',$source?:[]);return $this->view->fetch();}

    public function taskList()
    {
        $rows=Db::name('ipa_scan_task')->order('id','desc')->limit(50)->select();
        foreach((array)$rows as &$row){
            $row['cursor']=json_decode(isset($row['cursor_json'])?$row['cursor_json']:'',true);
            unset($row['cursor_json']);
            $row['started_at_text']=!empty($row['started_at'])?date('Y-m-d H:i:s',$row['started_at']):'';
            $row['finished_at_text']=!empty($row['finished_at'])?date('Y-m-d H:i:s',$row['finished_at']):'';
        }
        unset($row);
        $this->success('',null,['rows'=>$rows,'total'=>count($rows)]);
    }

    public function scanStart()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        $source=IpaSourceConfig::first(false);
        if(!$source)$this->error('请先配置 IPA 网络源');
        try{
            $taskId=IpaScanService::createTask((int)$source['id'],'manual',(int)$this->auth->id,(bool)$this->request->post('refresh/d',0));
            $spawned=IpaScanService::spawn($taskId);
            $this->success('扫描任务已创建',null,['task_id'=>$taskId,'spawned'=>$spawned,'fallback'=>$spawned?'':'后台启动不可用时可执行 php think ipa:scan --pending']);
        }catch(\Exception $e){$this->error($e->getMessage());}
    }

    public function metadataList()
    {
        $state=trim((string)$this->request->get('state',''));
        $keyword=trim((string)$this->request->get('q',''));
        $page=max(1,(int)$this->request->get('page/d',1));
        $limit=max(10,min(100,(int)$this->request->get('limit/d',50)));
        $sort=trim((string)$this->request->get('sort','id'));
        $order=strtolower(trim((string)$this->request->get('order','desc')))==='asc'?'asc':'desc';
        $allowedSort=['id','file_name','package_name','bundle_id','package_version','parse_state','parsed_at','updated_at'];
        if(!in_array($sort,$allowedSort,true))$sort='id';

        $query=Db::name('ipa_metadata');
        if(in_array($state,['pending','success','failed'],true))$query->where('parse_state',$state);
        if($keyword!=='')$query->where(function($q)use($keyword){$like='%'.$keyword.'%';$q->where('file_name','like',$like)->whereOr('bundle_id','like',$like)->whereOr('package_name','like',$like)->whereOr('remote_path','like',$like);});
        $countQuery=clone $query;
        $total=(int)$countQuery->count();
        $rows=$query->order($sort,$order)->page($page,$limit)->select();
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
        $this->success('',null,['rows'=>$rows,'total'=>$total,'page'=>$page,'limit'=>$limit]);
    }

    public function parseStart()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        $taskId=(int)$this->request->post('task_id/d',0);
        $limit=(int)$this->request->post('limit/d',0);
        $spawned=IpaParserService::spawn($taskId,$limit);
        $this->success('解析任务已启动',null,['spawned'=>$spawned,'fallback'=>$spawned?'':'后台启动不可用时可执行 php think ipa:parse']);
    }

    public function bindingList()
    {
        $data=IpaBindingService::dashboard((int)$this->request->get('limit/d',100));
        foreach($data['rows'] as &$row)$row['bound']=!empty($row['binding_id']);
        unset($row);
        $data['total']=count($data['rows']);
        $this->success('',null,$data);
    }

    public function bindingCandidates(){try{$metadataId=(int)$this->request->get('metadata_id/d',0);$this->success('',null,['rows'=>IpaBindingService::candidates($metadataId,20)]);}catch(\Exception $e){$this->error($e->getMessage());}}
    public function bindingApply(){if(!$this->request->isPost())$this->error('Method not allowed');try{$metadataId=(int)$this->request->post('metadata_id/d',0);$mode=trim((string)$this->request->post('mode','manual'));if($mode==='auto_exact')$row=IpaBindingService::autoBindExactUrl($metadataId,(int)$this->auth->id);else{$categoryId=(int)$this->request->post('category_id/d',0);$row=IpaBindingService::bind($metadataId,$categoryId,'manual',(int)$this->auth->id,false);}$this->success('绑定成功',null,['binding'=>$row]);}catch(\Exception $e){$this->error($e->getMessage());}}
    public function bindingRemove(){if(!$this->request->isPost())$this->error('Method not allowed');try{IpaBindingService::unbind((int)$this->request->post('binding_id/d',0),(int)$this->auth->id);$this->success('已解除绑定');}catch(\Exception $e){$this->error($e->getMessage());}}

    public function governanceRefresh(){if(!$this->request->isPost())$this->error('Method not allowed');try{$this->success('治理异常已重新检测',null,['stats'=>IpaGovernanceService::refreshIssues()]);}catch(\Exception $e){$this->error($e->getMessage());}}
    public function governanceList(){try{$kind=trim((string)$this->request->get('kind',''));$rows=IpaGovernanceService::listIssues($kind,(int)$this->request->get('limit/d',200));$this->success('',null,['stats'=>IpaGovernanceService::stats(),'rows'=>$rows,'total'=>count($rows)]);}catch(\Exception $e){$this->error($e->getMessage());}}
    public function governancePreview(){if(!$this->request->isPost())$this->error('Method not allowed');try{$plan=IpaGovernanceService::preview((int)$this->request->post('issue_id/d',0),trim((string)$this->request->post('mode','')));$this->success('修复预览已生成',null,['plan'=>$plan]);}catch(\Exception $e){$this->error($e->getMessage());}}
    public function governanceApply(){if(!$this->request->isPost())$this->error('Method not allowed');try{$result=IpaGovernanceService::apply((int)$this->request->post('issue_id/d',0),trim((string)$this->request->post('mode','')),trim((string)$this->request->post('plan_hash','')),(int)$this->auth->id);$this->success('修复完成并通过验证',null,$result);}catch(\Exception $e){$this->error($e->getMessage());}}
    public function governanceIgnore(){if(!$this->request->isPost())$this->error('Method not allowed');try{$days=max(1,min(365,(int)$this->request->post('days/d',30)));IpaGovernanceService::ignore((int)$this->request->post('issue_id/d',0),time()+$days*86400,(int)$this->auth->id);$this->success('已忽略该异常');}catch(\Exception $e){$this->error($e->getMessage());}}
    public function governanceVerify(){if(!$this->request->isPost())$this->error('Method not allowed');try{$ok=IpaGovernanceService::verifyIssue((int)$this->request->post('issue_id/d',0));$this->success($ok?'重新检测：已恢复':'重新检测：异常仍存在',null,['resolved'=>$ok]);}catch(\Exception $e){$this->error($e->getMessage());}}
    public function governanceBatchPreview(){if(!$this->request->isPost())$this->error('Method not allowed');try{$ids=json_decode((string)$this->request->post('issue_ids_json','[]'),true);if(!is_array($ids))throw new RuntimeException('issue_ids_json 无效');$this->success('批量治理预览已生成',null,IpaGovernanceBatchService::preview($ids));}catch(\Exception $e){$this->error($e->getMessage());}}
    public function governanceBatchApply(){if(!$this->request->isPost())$this->error('Method not allowed');try{$ids=json_decode((string)$this->request->post('issue_ids_json','[]'),true);if(!is_array($ids))throw new RuntimeException('issue_ids_json 无效');$result=IpaGovernanceBatchService::apply($ids,trim((string)$this->request->post('batch_hash','')),(int)$this->auth->id);$this->success('批量治理执行完成',null,$result);}catch(\Exception $e){$this->error($e->getMessage());}}
    public function governanceFailures(){try{$rows=IpaGovernanceBatchService::failedOperations((int)$this->request->get('limit/d',100));$this->success('',null,['stats'=>IpaGovernanceBatchService::failureStats(),'rows'=>$rows,'total'=>count($rows)]);}catch(\Exception $e){$this->error($e->getMessage());}}

    public function writebackRules(){try{$rules=IpaWritebackTemplate::loadActiveRules();$this->success('',null,['version'=>IpaWritebackTemplate::activeVersion(),'rules'=>$rules,'rows'=>$rules,'total'=>count($rules),'strategies'=>IpaWritebackTemplate::allowedStrategies(),'targets'=>IpaWritebackTemplate::allowedTargets()]);}catch(\Exception $e){$this->error($e->getMessage());}}
    public function writebackSeed(){if(!$this->request->isPost())$this->error('Method not allowed');try{$created=IpaWritebackTemplate::seedDefaults((int)$this->auth->id);$this->success($created?'默认模板已初始化':'模板已经存在',null,['created'=>$created,'version'=>IpaWritebackTemplate::activeVersion()]);}catch(\Exception $e){$this->error($e->getMessage());}}
    public function writebackSave(){if(!$this->request->isPost())$this->error('Method not allowed');try{$raw=(string)$this->request->post('rules_json','');$rules=json_decode($raw,true);if(!is_array($rules))throw new RuntimeException('rules_json 无效');$version=IpaWritebackTemplate::saveNewVersion($rules,(int)$this->auth->id);$this->success('全局模板已保存为新版本',null,['version'=>$version]);}catch(\Exception $e){$this->error($e->getMessage());}}
    public function writebackRandomPreview(){try{$bindings=Db::name('ipa_binding')->order('id','desc')->limit(200)->select();if(!$bindings)$this->error('当前没有已绑定 IPA');shuffle($bindings);$binding=null;$metadata=null;foreach($bindings as $candidate){$m=Db::name('ipa_metadata')->where('id',(int)$candidate['metadata_id'])->where('parse_state','success')->find();if($m){$binding=$candidate;$metadata=$m;break;}}if(!$binding||!$metadata)$this->error('没有“已解析 + 已绑定”的 IPA 可用于测试');$category=Db::name('category')->where('id',(int)$binding['category_id'])->find();if(!$category)$this->error('绑定对应的 category 已不存在');$rules=IpaWritebackTemplate::loadActiveRules();$preview=IpaWritebackTemplate::preview($category,$metadata,$rules);$this->success('',null,['version'=>IpaWritebackTemplate::activeVersion(),'sample'=>['binding_id'=>(int)$binding['id'],'metadata_id'=>(int)$metadata['id'],'category_id'=>(int)$category['id'],'category_name'=>isset($category['name'])?$category['name']:'','remote_path'=>isset($metadata['remote_path'])?$metadata['remote_path']:'','bundle_id'=>isset($metadata['bundle_id'])?$metadata['bundle_id']:'','package_version'=>isset($metadata['package_version'])?$metadata['package_version']:''],'preview'=>$preview]);}catch(\Exception $e){$this->error($e->getMessage());}}

    protected function assertSettingRight(){if(!$this->auth->check('ipa_center/setting'))$this->error(__('You have no permission'),'');}

    public function sourceSave()
    {
        $this->assertSettingRight();
        if(!$this->request->isPost())$this->error('Method not allowed');
        try{
            $id=IpaSourceConfig::save($this->request->post(),(int)$this->auth->id);
            $this->success('IPA 网络源已保存',null,['id'=>$id,'token_configured'=>true]);
        } catch(\Throwable $e){
            $this->error($e->getMessage());
        }
    }

    public function sourceTest()
    {
        $this->assertSettingRight();
        if(!$this->request->isPost())$this->error('Method not allowed');
        try{
            $health=IpaSourceConfig::testInput($this->request->post());
            IpaSourceConfig::updateState(['last_health'=>'ok','last_checked_at'=>time()]);
            $this->success('OpenList 连接正常',null,$health);
        }catch(\Throwable $e){
            IpaSourceConfig::updateState(['last_health'=>'failed','last_checked_at'=>time()]);
            $this->error($e->getMessage());
        }
    }
}
