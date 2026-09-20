<?php
namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\IpaScanService;
use app\common\library\IpaParserService;
use app\common\library\IpaSourceConfig;
use app\common\library\IpaMetadataQueryService;
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
            $counts=IpaMetadataQueryService::counts(true);
            $metadataTotal=$counts['total'];$parsed=$counts['success'];$pending=$counts['pending'];$failed=$counts['failed'];
            $bound = (int)Db::name('ipa_binding')->alias('b')->join('__IPA_METADATA__ m','m.id=b.metadata_id','INNER')->where('m.referenced',1)->count();
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
            foreach ((array)$governance as $value) if (is_numeric($value)) $governanceTotal += (int)$value;
            $enabled=$source&&!empty($source['enabled']);
            $this->success('', null, [
                'stats' => [
                    'metadata_total' => $metadataTotal,'parsed' => $parsed,'pending' => $pending,'failed' => $failed,
                    'bound' => $bound,'unbound' => max(0, $parsed - $bound),'governance' => $governanceTotal,
                ],
                'source' => [
                    'configured' => !empty($source),'enabled'=>$enabled,
                    'health_display'=>$source?($enabled?(isset($source['last_health'])?$source['last_health']:'unknown'):'已停用'):'unknown',
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
            $row['cursor']=json_decode(isset($row['cursor_json'])?$row['cursor_json']:'',true);unset($row['cursor_json']);
            $row['started_at_text']=!empty($row['started_at'])?date('Y-m-d H:i:s',$row['started_at']):'';
            $row['finished_at_text']=!empty($row['finished_at'])?date('Y-m-d H:i:s',$row['finished_at']):'';
        }
        unset($row);$this->success('',null,['rows'=>$rows,'total'=>count($rows)]);
    }

    public function scanStart()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        $source=IpaSourceConfig::first(false);if(!$source)$this->error('请先配置 OpenList');
        try{
            $taskId=IpaScanService::createTask((int)$source['id'],'manual',(int)$this->auth->id,(bool)$this->request->post('refresh/d',0));
            $spawned=IpaScanService::spawn($taskId);
        }catch(\Exception $e){$this->error($e->getMessage());}
        $this->success($this->request->post('refresh/d',0)?'强制刷新目录任务已创建':'后台 MD5 扫描任务已创建',null,['task_id'=>$taskId,'spawned'=>$spawned,'fallback'=>$spawned?'':'后台启动不可用时可执行 php think ipa:scan --pending']);
    }

    public function metadataList()
    {
        try{
            $data=IpaMetadataQueryService::listing(
                $this->request->get('state',''),$this->request->get('q',''),
                (int)$this->request->get('page/d',1),(int)$this->request->get('limit/d',50),
                $this->request->get('sort','id'),$this->request->get('order','desc'),true
            );
        }catch(\Exception $e){$this->error($e->getMessage());return;}
        $this->success('',null,$data);
    }

    public function parseStart()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        $source=IpaSourceConfig::first(false);
        if(!$source)$this->error('请先配置 OpenList');
        if(empty($source['enabled']))$this->error('OpenList 已停用');
        $taskId=(int)$this->request->post('task_id/d',0);
        $spawned=IpaParserService::spawn($taskId,1);
        $this->success('后台解析 1 个任务已启动',null,['spawned'=>$spawned,'limit'=>1,'fallback'=>$spawned?'':'后台启动不可用时可执行 php think ipa:parse --limit=1']);
    }

    public function bindingList()
    {
        $data=IpaBindingService::dashboard((int)$this->request->get('limit/d',100));
        foreach($data['rows'] as &$row)$row['bound']=!empty($row['binding_id']);
        unset($row);$data['total']=count($data['rows']);$this->success('',null,$data);
    }

    public function bindingCandidates()
    {
        try{$metadataId=(int)$this->request->get('metadata_id/d',0);$rows=IpaBindingService::candidates($metadataId,20);}
        catch(\Exception $e){$this->error($e->getMessage());}
        $this->success('',null,['rows'=>$rows]);
    }

    public function bindingApply()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        try{
            $metadataId=(int)$this->request->post('metadata_id/d',0);$mode=trim((string)$this->request->post('mode','manual'));
            if($mode==='auto_exact')$row=IpaBindingService::autoBindExactUrl($metadataId,(int)$this->auth->id);
            else{$categoryId=(int)$this->request->post('category_id/d',0);$row=IpaBindingService::bind($metadataId,$categoryId,'manual',(int)$this->auth->id,false);}
        }catch(\Exception $e){$this->error($e->getMessage());}
        $this->success('绑定成功',null,['binding'=>$row]);
    }

    public function bindingRemove()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        try{IpaBindingService::unbind((int)$this->request->post('binding_id/d',0),(int)$this->auth->id);}
        catch(\Exception $e){$this->error($e->getMessage());}
        $this->success('已解除绑定');
    }

    public function governanceRefresh()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        try{$stats=IpaGovernanceService::refreshIssues();}catch(\Exception $e){$this->error($e->getMessage());}
        $this->success('治理异常已重新检测',null,['stats'=>$stats]);
    }

    public function governanceList()
    {
        try{$kind=trim((string)$this->request->get('kind',''));$rows=IpaGovernanceService::listIssues($kind,(int)$this->request->get('limit/d',200));$stats=IpaGovernanceService::stats();}
        catch(\Exception $e){$this->error($e->getMessage());}
        $this->success('',null,['stats'=>$stats,'rows'=>$rows,'total'=>count($rows)]);
    }

    public function governancePreview()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        try{$plan=IpaGovernanceService::preview((int)$this->request->post('issue_id/d',0),trim((string)$this->request->post('mode','')));}
        catch(\Exception $e){$this->error($e->getMessage());}
        $this->success('修复预览已生成',null,['plan'=>$plan]);
    }

    public function governanceApply()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        try{$result=IpaGovernanceService::apply((int)$this->request->post('issue_id/d',0),trim((string)$this->request->post('mode','')),trim((string)$this->request->post('plan_hash','')),(int)$this->auth->id);}
        catch(\Exception $e){$this->error($e->getMessage());}
        $this->success('修复完成并通过验证',null,$result);
    }

    public function governanceIgnore()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        try{$days=max(1,min(365,(int)$this->request->post('days/d',30)));IpaGovernanceService::ignore((int)$this->request->post('issue_id/d',0),time()+$days*86400,(int)$this->auth->id);}
        catch(\Exception $e){$this->error($e->getMessage());}
        $this->success('已忽略该异常');
    }

    public function governanceVerify()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        try{$ok=IpaGovernanceService::verifyIssue((int)$this->request->post('issue_id/d',0));}
        catch(\Exception $e){$this->error($e->getMessage());}
        $this->success($ok?'重新检测：已恢复':'重新检测：异常仍存在',null,['resolved'=>$ok]);
    }

    public function governanceBatchPreview()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        try{$ids=json_decode((string)$this->request->post('issue_ids_json','[]'),true);if(!is_array($ids))throw new RuntimeException('issue_ids_json 无效');$result=IpaGovernanceBatchService::preview($ids);}
        catch(\Exception $e){$this->error($e->getMessage());}
        $this->success('批量治理预览已生成',null,$result);
    }

    public function governanceBatchApply()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        try{$ids=json_decode((string)$this->request->post('issue_ids_json','[]'),true);if(!is_array($ids))throw new RuntimeException('issue_ids_json 无效');$result=IpaGovernanceBatchService::apply($ids,trim((string)$this->request->post('batch_hash','')),(int)$this->auth->id);}
        catch(\Exception $e){$this->error($e->getMessage());}
        $this->success('批量治理执行完成',null,$result);
    }

    public function governanceFailures()
    {
        try{$rows=IpaGovernanceBatchService::failedOperations((int)$this->request->get('limit/d',100));$stats=IpaGovernanceBatchService::failureStats();}
        catch(\Exception $e){$this->error($e->getMessage());}
        $this->success('',null,['stats'=>$stats,'rows'=>$rows,'total'=>count($rows)]);
    }

    public function writebackRules()
    {
        try{$rules=IpaWritebackTemplate::loadActiveRules();$version=IpaWritebackTemplate::activeVersion();$strategies=IpaWritebackTemplate::allowedStrategies();$targets=IpaWritebackTemplate::allowedTargets();}
        catch(\Exception $e){$this->error($e->getMessage());}
        $this->success('',null,['version'=>$version,'rules'=>$rules,'rows'=>$rules,'total'=>count($rules),'strategies'=>$strategies,'targets'=>$targets]);
    }

    public function writebackSeed()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        try{$created=IpaWritebackTemplate::seedDefaults((int)$this->auth->id);$version=IpaWritebackTemplate::activeVersion();}
        catch(\Exception $e){$this->error($e->getMessage());}
        $this->success($created?'默认模板已初始化':'模板已经存在',null,['created'=>$created,'version'=>$version]);
    }

    public function writebackSave()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        try{$raw=(string)$this->request->post('rules_json','');$rules=json_decode($raw,true);if(!is_array($rules))throw new RuntimeException('rules_json 无效');$version=IpaWritebackTemplate::saveNewVersion($rules,(int)$this->auth->id);}
        catch(\Exception $e){$this->error($e->getMessage());}
        $this->success('全局模板已保存为新版本',null,['version'=>$version]);
    }

    public function writebackRandomPreview()
    {
        try{
            $bindings=Db::name('ipa_binding')->order('id','desc')->limit(200)->select();if(!$bindings)throw new RuntimeException('当前没有已绑定 IPA');
            shuffle($bindings);$binding=null;$metadata=null;
            foreach($bindings as $candidate){$m=Db::name('ipa_metadata')->where('id',(int)$candidate['metadata_id'])->where('parse_state','success')->find();if($m){$binding=$candidate;$metadata=$m;break;}}
            if(!$binding||!$metadata)throw new RuntimeException('没有“已解析 + 已绑定”的 IPA 可用于测试');
            $category=Db::name('category')->where('id',(int)$binding['category_id'])->find();if(!$category)throw new RuntimeException('绑定对应的 category 已不存在');
            $rules=IpaWritebackTemplate::loadActiveRules();$preview=IpaWritebackTemplate::preview($category,$metadata,$rules);
            $data=['version'=>IpaWritebackTemplate::activeVersion(),'sample'=>['binding_id'=>(int)$binding['id'],'metadata_id'=>(int)$metadata['id'],'category_id'=>(int)$category['id'],'category_name'=>isset($category['name'])?$category['name']:'','remote_path'=>isset($metadata['remote_path'])?$metadata['remote_path']:'','bundle_id'=>isset($metadata['bundle_id'])?$metadata['bundle_id']:'','package_version'=>isset($metadata['package_version'])?$metadata['package_version']:''],'preview'=>$preview];
        }catch(\Exception $e){$this->error($e->getMessage());}
        $this->success('',null,$data);
    }

    public function task_list(){return $this->taskList();}
    public function scan_start(){return $this->scanStart();}
    public function metadata_list(){return $this->metadataList();}
    public function parse_start(){return $this->parseStart();}
    public function binding_list(){return $this->bindingList();}
    public function binding_candidates(){return $this->bindingCandidates();}
    public function binding_apply(){return $this->bindingApply();}
    public function binding_remove(){return $this->bindingRemove();}
    public function governance_refresh(){return $this->governanceRefresh();}
    public function governance_list(){return $this->governanceList();}
    public function governance_preview(){return $this->governancePreview();}
    public function governance_apply(){return $this->governanceApply();}
    public function governance_ignore(){return $this->governanceIgnore();}
    public function governance_verify(){return $this->governanceVerify();}
    public function governance_batch_preview(){return $this->governanceBatchPreview();}
    public function governance_batch_apply(){return $this->governanceBatchApply();}
    public function governance_failures(){return $this->governanceFailures();}
    public function writeback_rules(){return $this->writebackRules();}
    public function writeback_seed(){return $this->writebackSeed();}
    public function writeback_save(){return $this->writebackSave();}
    public function writeback_random_preview(){return $this->writebackRandomPreview();}
    public function source_save(){return $this->sourceSave();}
    public function source_test(){return $this->sourceTest();}

    protected function assertSettingRight(){if(!$this->auth->check('ipa_center/setting'))$this->error(__('You have no permission'),'');}

    public function sourceSave()
    {
        $this->assertSettingRight();if(!$this->request->isPost())$this->error('Method not allowed');
        try{$id=IpaSourceConfig::save($this->request->post(),(int)$this->auth->id);$source=IpaSourceConfig::first(false);}
        catch(\Throwable $e){$this->error($e->getMessage());}
        $this->success('IPA 网络源已保存',null,['id'=>$id,'token_configured'=>true,'enabled'=>$source&&!empty($source['enabled']),'health_display'=>$source&&!empty($source['enabled'])?(isset($source['last_health'])?$source['last_health']:'unknown'):'已停用']);
    }

    public function sourceTest()
    {
        $this->assertSettingRight();if(!$this->request->isPost())$this->error('Method not allowed');
        $source=IpaSourceConfig::first(false);if(!$source)$this->error('请先保存 OpenList 配置');if(empty($source['enabled']))$this->error('OpenList 已停用，请先启用后再测试连接');
        try{$health=IpaSourceConfig::testInput($this->request->post());IpaSourceConfig::updateState(['last_health'=>'ok','last_checked_at'=>time()]);}
        catch(\Throwable $e){IpaSourceConfig::updateState(['last_health'=>'failed','last_checked_at'=>time()]);$this->error($e->getMessage());}
        $this->success('OpenList 连接正常',null,$health);
    }
}