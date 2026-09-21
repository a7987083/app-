<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\Ipa\IpaScanService;
use app\common\library\Ipa\SecretBox;
use think\Db;

class IpaCenter extends Backend
{
    protected $noNeedRight = [];

    public function index()
    {
        $this->view->assign('summary', [
            'sources' => (int)Db::name('ipa_source')->count(),
            'assets' => (int)Db::name('ipa_asset')->count(),
            'pending' => (int)Db::name('ipa_scan_item')->where('status', 'pending')->count(),
            'failed' => (int)Db::name('ipa_scan_item')->where('status', 'failed')->count(),
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
        $rows = $query->field('id,source_id,name,path,size_bytes,status,bundle_id,app_name,app_version,build_version,last_seen_at,parsed_at')
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
            $data['token_ciphertext'] = SecretBox::encrypt($token);
        }
        if ($id > 0) {
            Db::name('ipa_source')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = $now;
            $id = Db::name('ipa_source')->insertGetId($data);
        }
        $this->success('saved', null, ['id' => (int)$id]);
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
