<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\Ipa\IpaSoftwareSourceService;

class IpaSourceCenter extends Backend
{
    protected $noNeedRight = [];

    public function index()
    {
        return $this->view->fetch();
    }

    public function listSources()
    {
        $rows = IpaSoftwareSourceService::listSources();
        return json(['total' => count($rows), 'rows' => $rows]);
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $row = (array)$this->request->post('row/a', []);
            try {
                $id = IpaSoftwareSourceService::save($row);
                $this->success('软件源已保存', null, ['id' => $id]);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        }
        return $this->view->fetch();
    }

    public function edit($ids = null)
    {
        $id = (int)($ids !== null ? $ids : $this->request->param('ids', 0));
        if ($id <= 0) {
            $this->error('软件源 ID 无效');
        }
        if ($this->request->isPost()) {
            $row = (array)$this->request->post('row/a', []);
            $row['id'] = $id;
            try {
                IpaSoftwareSourceService::save($row);
                $this->success('软件源已保存');
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        }
        try {
            $row = IpaSoftwareSourceService::get($id, false);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

    public function del($ids = null)
    {
        if (!$this->request->isPost()) {
            $this->error('仅支持 POST');
        }
        $raw = $ids !== null ? $ids : $this->request->post('ids', '');
        if ($raw === '' || $raw === null) {
            $raw = $this->request->post('id', '');
        }
        $list = is_array($raw) ? $raw : explode(',', (string)$raw);
        $deleted = 0;
        try {
            foreach ($list as $value) {
                $id = (int)$value;
                if ($id <= 0) {
                    continue;
                }
                IpaSoftwareSourceService::delete($id);
                $deleted++;
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        if ($deleted < 1) {
            $this->error('未选择软件源');
        }
        $this->success('软件源已删除');
    }

    public function testSource()
    {
        if (!$this->request->isPost()) {
            $this->error('仅支持 POST');
        }
        try {
            $data = IpaSoftwareSourceService::test((int)$this->request->post('id', 0));
            $this->success('连接成功，共 ' . $data['rows'] . ' 条记录', null, $data);
        } catch (\Exception $e) {
            $message = trim((string)$e->getMessage());
            $this->error('连接失败' . ($message !== '' ? '：' . $message : '；请检查 PHP PDO MySQL、地址、端口、账号、密码和数据库权限'));
        }
    }

    // 兼容 2026092203 已发布前端；2026092204 前端改走 add/edit/del。
    public function saveSource()
    {
        if (!$this->request->isPost()) {
            $this->error('仅支持 POST');
        }
        try {
            $id = IpaSoftwareSourceService::save($this->request->post());
            $this->success('软件源已保存', null, ['id' => $id]);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    public function deleteSource()
    {
        if (!$this->request->isPost()) {
            $this->error('仅支持 POST');
        }
        try {
            IpaSoftwareSourceService::delete((int)$this->request->post('id', 0));
            $this->success('软件源已删除');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
