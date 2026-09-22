<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\Ipa\IpaScanService;
use app\common\library\Ipa\IpaWritebackService;
use app\common\library\Ipa\SecretBox;
use app\common\library\Ipa\WorkerState;
use think\Db;

class IpaCenter extends Backend
{
    protected $noNeedRight = [];

    public function index()
    {
        $workers = [];
        try {
            $workers = WorkerState::snapshot(15);
        } catch (\Exception $e) {
            // Upgrade-safe: the UI can still load before the worker-state migration is applied.
        }
        $this->view->assign('workers', $workers);
        $this->view->assign('summary', [
            'sources' => (int)Db::name('ipa_source')->count(),
            'assets' => (int)Db::name('ipa_asset')->count(),
            'pending' => (int)Db::name('ipa_scan_item')->where('status', 'pending')->count(),
            'failed' => (int)Db::name('ipa_scan_item')->where('status', 'failed')->count(),
            'parse_pending' => (int)Db::name('ipa_asset')->where('status', 'discovered')->count(),
            'parse_failed' => (int)Db::name('ipa_asset')->where('status', 'parse_failed')->count(),
            'dylibs' => (int)Db::name('dylib')->count(),
            'verify24h' => (int)Db::name('dylib_verify_log')->where('created_at', '>=', time() - 86400)->count(),
        ]);
        $this->view->assign('sources', Db::name('ipa_source')->field('id,name,base_url,root_path,enabled,scan_page_size,request_timeout,updated_at')->order('id desc')->select());
        return $this->view->fetch();
    }

    public function assets()
    {
        $offset = max(0, (int)$this->request->get('offset', 0));
        $limit = max(20, min(500, (int)$this->request->get('limit', 100)));
        $search = trim((string)$this->request->get('search', ''));
        $query = Db::name('ipa_asset');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereLike('name', '%' . $search . '%')->whereOr('bundle_id', 'like', '%' . $search . '%');
            });
        }
        $total = (clone $query)->count();
        $rows = $query->field('id,source_id,name,path,size_bytes,status,bundle_id,app_name,app_version,build_version,last_error,last_seen_at,parsed_at')
            ->order('id desc')->limit($offset, $limit)->select();
        return json(['total' => (int)$total, 'rows' => $rows]);
    }

    public function jobs()
    {
        $offset = max(0, (int)$this->request->get('offset', 0));
        $limit = max(20, min(200, (int)$this->request->get('limit', 50)));
        $total = Db::name('ipa_scan_job')->count();
        $rows = Db::name('ipa_scan_job')
            ->field('id,source_id,mode,status,root_path,discovered_count,processed_count,failed_count,worker_id,started_at,finished_at,created_at')
            ->order('id desc')->limit($offset, $limit)->select();
        return json(['total' => (int)$total, 'rows' => $rows]);
    }

    public function categorySearch()
    {
        $q = trim((string)$this->request->get('q', ''));
        $limit = max(1, min(50, (int)$this->request->get('limit', 20)));
        $query = Db::name('category')->field('id,name,nickname,bt1a,bt2a,status')->where('pid', 0);
        if ($q !== '') {
            if (ctype_digit($q)) {
                $query->where(function ($where) use ($q) {
                    $where->where('id', (int)$q)->whereOr('name', 'like', '%' . $q . '%');
                });
            } else {
                $query->where('name', 'like', '%' . $q . '%');
            }
        }
        return json(['rows' => $query->order('id desc')->limit($limit)->select()]);
    }

    public function writebackPreview()
    {
        try {
            $data = IpaWritebackService::preview(
                (int)$this->request->request('asset_id', 0),
                (int)$this->request->request('category_id', 0)
            );
            return json(['code' => 1, 'msg' => 'ok', 'data' => $data]);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => $e->getMessage(), 'data' => null]);
        }
    }

    public function writebackApply()
    {
        if (!$this->request->isPost()) {
            $this->error('POST required');
        }
        $fields = $this->request->post('fields/a', []);
        try {
            $result = IpaWritebackService::apply(
                (int)$this->request->post('asset_id', 0),
                (int)$this->request->post('category_id', 0),
                is_array($fields) ? $fields : [],
                (int)$this->auth->id
            );
            $this->success('writeback applied', null, $result);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    public function saveSource()
    {
        if (!$this->request->isPost()) {
            $this->error('POST required');
        }
        $id = (int)$this->request->post('id', 0);
        $name = trim((string)$this->request->post('name', ''));
        $baseUrl = rtrim(trim((string)$this->request->post('base_url', '')), '/');
        $rootPath = trim((string)$this->request->post('root_path', '/'));
        $token = trim((string)$this->request->post('token', ''));
        if ($name === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $this->error('Invalid source name or URL');
        }
        if (stripos($baseUrl, 'https://') !== 0 && stripos($baseUrl, 'http://') !== 0) {
            $this->error('Only HTTP/HTTPS OpenList URLs are allowed');
        }
        if ($id > 0 && !Db::name('ipa_source')->where('id', $id)->find()) {
            $this->error('OpenList source not found');
        }
        $rootPath = '/' . ltrim(preg_replace('#/+#', '/', $rootPath), '/');
        $now = time();
        $data = [
            'name' => $name,
            'base_url' => $baseUrl,
            'root_path' => $rootPath,
            'enabled' => (int)$this->request->post('enabled', 1) ? 1 : 0,
            'scan_page_size' => max(20, min(1000, (int)$this->request->post('scan_page_size', 500))),
            'request_timeout' => max(3, min(120, (int)$this->request->post('request_timeout', 20))),
            'updated_at' => $now,
        ];
        if ($token !== '') {
            try {
                $data['token_ciphertext'] = SecretBox::encrypt($token);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        }
        if ($id > 0) {
            Db::name('ipa_source')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = $now;
            $id = Db::name('ipa_source')->insertGetId($data);
        }
        $this->success('saved', null, ['id' => (int)$id]);
    }

    public function deleteSource()
    {
        if (!$this->request->isPost()) {
            $this->error('POST required');
        }
        $id = (int)$this->request->post('id', 0);
        $source = Db::name('ipa_source')->where('id', $id)->find();
        if (!$source) {
            $this->error('OpenList source not found');
        }
        $activeJobs = Db::name('ipa_scan_job')->where('source_id', $id)->where('status', 'in', ['pending', 'running'])->count();
        $parsing = Db::name('ipa_asset')->where('source_id', $id)->where('status', 'parsing')->count();
        if ((int)$activeJobs > 0 || (int)$parsing > 0) {
            $this->error('Source has active scan/parse work; wait for workers to finish before deleting');
        }

        Db::startTrans();
        try {
            while (true) {
                $rows = Db::name('ipa_asset')->field('id')->where('source_id', $id)->limit(500)->select();
                if (!$rows) break;
                $assetIds = [];
                foreach ($rows as $row) $assetIds[] = (int)$row['id'];
                Db::name('ipa_binary')->where('asset_id', 'in', $assetIds)->delete();
                Db::name('ipa_category_binding')->where('asset_id', 'in', $assetIds)->delete();
                Db::name('ipa_asset')->where('id', 'in', $assetIds)->delete();
            }
            Db::name('ipa_scan_item')->where('source_id', $id)->delete();
            Db::name('ipa_scan_job')->where('source_id', $id)->delete();
            Db::name('ipa_source')->where('id', $id)->delete();
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('source deleted');
    }

    public function retryParse()
    {
        if (!$this->request->isPost()) {
            $this->error('POST required');
        }
        $assetId = (int)$this->request->post('asset_id', 0);
        $asset = Db::name('ipa_asset')->where('id', $assetId)->find();
        if (!$asset) {
            $this->error('IPA asset not found');
        }
        if ((string)$asset['status'] === 'parsing') {
            $this->error('IPA is currently parsing');
        }
        Db::name('ipa_asset')->where('id', $assetId)->update([
            'status' => 'discovered',
            'last_error' => null,
            'parsed_at' => 0,
            'updated_at' => time(),
        ]);
        $this->success('IPA queued for parsing');
    }

    public function startScan()
    {
        if (!$this->request->isPost()) {
            $this->error('POST required');
        }
        try {
            $jobId = IpaScanService::createJob((int)$this->request->post('source_id'), (string)$this->request->post('mode', 'incremental'));
            $this->success('scan queued', null, ['job_id' => (int)$jobId]);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
