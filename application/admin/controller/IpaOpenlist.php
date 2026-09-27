<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\Ipa\OpenListClient;
use app\common\library\Ipa\SecretBox;
use think\Db;

/**
 * IPA 解析第一阶段：OpenList Token 连接配置。
 *
 * 这里只负责连接配置与真实 API 连通性校验，不启动扫描或解析任务。
 */
class IpaOpenlist extends Backend
{
    protected $noNeedRight = [];

    public function index()
    {
        $sources = Db::name('ipa_source')
            ->field('id,name,base_url,root_path,enabled,request_timeout,updated_at')
            ->order('id desc')
            ->select();
        $this->view->assign('sources', $sources);
        return $this->view->fetch();
    }

    public function save()
    {
        if (!$this->request->isPost()) {
            $this->error('仅支持 POST');
        }

        $id = (int)$this->request->post('id', 0);
        $baseUrl = rtrim(trim((string)$this->request->post('base_url', '')), '/');
        $rootPath = $this->normalizePath((string)$this->request->post('root_path', '/'));
        $token = trim((string)$this->request->post('token', ''));
        $timeout = max(3, min(120, (int)$this->request->post('request_timeout', 20)));

        $this->assertBaseUrl($baseUrl);

        $current = null;
        if ($id > 0) {
            $current = Db::name('ipa_source')->where('id', $id)->find();
            if (!$current) {
                $this->error('OpenList 连接不存在');
            }
        }

        // Token 模式是本页面唯一认证方式。新增连接必须提供 Token；
        // 编辑时留空表示保留原加密 Token，绝不从服务端回显明文。
        if ($token === '' && !$current) {
            $this->error('请输入 OpenList Token');
        }
        if ($token === '' && $current && empty($current['token_ciphertext'])) {
            $this->error('当前连接尚未保存 Token，请输入 OpenList Token');
        }

        $now = time();
        $data = [
            'name' => 'OpenList',
            'base_url' => $baseUrl,
            'root_path' => $rootPath,
            'enabled' => 1,
            'request_timeout' => $timeout,
            // 保留现有扫描字段的兼容默认值，本阶段不启用扫描功能。
            'scan_page_size' => $current && !empty($current['scan_page_size']) ? (int)$current['scan_page_size'] : 500,
            'updated_at' => $now,
        ];

        if ($token !== '') {
            try {
                $data['token_ciphertext'] = SecretBox::encrypt($token);
            } catch (\Exception $e) {
                $this->error('Token 加密失败：' . $e->getMessage());
            }
        }

        if ($current) {
            Db::name('ipa_source')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = $now;
            $id = (int)Db::name('ipa_source')->insertGetId($data);
        }

        $this->success('OpenList 连接已保存', null, ['id' => $id]);
    }

    public function test()
    {
        if (!$this->request->isPost()) {
            $this->error('仅支持 POST');
        }

        $id = (int)$this->request->post('id', 0);
        $baseUrl = rtrim(trim((string)$this->request->post('base_url', '')), '/');
        $rootPath = $this->normalizePath((string)$this->request->post('root_path', '/'));
        $token = trim((string)$this->request->post('token', ''));
        $timeout = max(3, min(120, (int)$this->request->post('request_timeout', 20)));

        $this->assertBaseUrl($baseUrl);

        // 编辑已有配置时允许 Token 输入框留空：此时使用数据库中的加密 Token。
        if ($token === '' && $id > 0) {
            $source = Db::name('ipa_source')->where('id', $id)->find();
            if (!$source) {
                $this->error('OpenList 连接不存在');
            }
            if (empty($source['token_ciphertext'])) {
                $this->error('当前连接没有可用 Token，请重新输入');
            }
            try {
                $token = SecretBox::decrypt((string)$source['token_ciphertext']);
            } catch (\Exception $e) {
                $this->error('无法读取已保存 Token：' . $e->getMessage());
            }
        }

        if ($token === '') {
            $this->error('请输入 OpenList Token');
        }

        try {
            // 真实调用 OpenList /api/fs/list；不是只测首页 HTTP 状态。
            // 这一步同时验证 API、Token 权限和配置的根目录。
            $client = new OpenListClient($baseUrl, $token, $timeout);
            $result = $client->listDirectory($rootPath, 1, 1, false);
        } catch (\Exception $e) {
            $this->error('OpenList 连接失败：' . $e->getMessage());
        }

        $total = isset($result['total']) ? (int)$result['total'] : null;
        $this->success('OpenList Token 验证成功', null, [
            'base_url' => $baseUrl,
            'root_path' => $rootPath,
            'total' => $total,
        ]);
    }

    protected function assertBaseUrl($baseUrl)
    {
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $this->error('OpenList 地址无效');
        }
        if (stripos($baseUrl, 'https://') !== 0 && stripos($baseUrl, 'http://') !== 0) {
            $this->error('OpenList 地址仅允许 HTTP/HTTPS');
        }
    }

    protected function normalizePath($path)
    {
        $path = trim((string)$path);
        if ($path === '' || $path === '/') {
            return '/';
        }
        return '/' . ltrim(preg_replace('#/+#', '/', $path), '/');
    }
}
