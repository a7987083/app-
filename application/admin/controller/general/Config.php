<?php

namespace app\admin\controller\general;

use app\common\controller\Backend;
use app\common\library\ApiEndpointRegistry;
use app\common\library\Email;
use app\common\library\SourceConfigRepository;
use app\common\library\SourceAnnouncementTemplate;
use app\common\library\SourceAppRepository;
use app\common\library\update\UpdateManager;
use app\common\model\Config as ConfigModel;
use think\Db;
use think\Exception;
use think\Validate;

/**
 * 系统配置
 *
 * @icon   fa fa-cogs
 * @remark 可以在此增改系统的变量和分组,也可以自定义分组和变量,如果需要删除请从数据库中删除
 */
class Config extends Backend
{

    /**
     * @var \app\common\model\Config
     */
    protected $model = null;
    protected $noNeedRight = ['check', 'rulelist', 'version_notice', 'update_status', 'update_history', 'update_rollback', 'api_toggle', 'api_save', 'api_delete', 'api_test', 'api_test_schema', 'api_logs', 'announcement_preview'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('Config');
        ConfigModel::event('before_write', function ($row) {
            if (isset($row['name']) && $row['name'] == 'name' && preg_match("/fast" . "admin/i", $row['value'])) {
                throw new Exception(__("Site name incorrect"));
            }
        });
    }

    /**
     * 查看
     */
    public function index()
    {
        $siteList = [];
        $groupList = ConfigModel::getGroupList();
        foreach ($groupList as $k => $v) {
            $siteList[$k]['name'] = $k;
            $siteList[$k]['title'] = $v;
            $siteList[$k]['list'] = [];
        }
        foreach ($this->model->all() as $k => $v) {
            if (!isset($siteList[$v['group']])) {
                continue;
            }
            $value = $v->toArray();
            $value['title'] = __($value['title']);
            if (in_array($value['type'], ['select', 'selects', 'checkbox', 'radio'])) {
                $value['value'] = explode(',', $value['value']);
            }
            $value['content'] = json_decode($value['content'], true);
            $value['tip'] = htmlspecialchars($value['tip']);
            $siteList[$v['group']]['list'][] = $value;
        }

        $index = 0;
        foreach ($siteList as $k => &$v) {
            $v['active'] = !$index ? true : false;
            $index++;
        }
        $this->view->assign('siteList', $siteList);
        $this->view->assign('typeList', ConfigModel::getTypeList());
        $this->view->assign('ruleList', ConfigModel::getRegexList());
        $this->view->assign('groupList', ConfigModel::getGroupList());
        $this->view->assign('apiEndpoints', ApiEndpointRegistry::all($this->request->domain()));
        $this->view->assign('apiHandlers', ApiEndpointRegistry::handlerOptions());
        $this->view->assign('apiLogs', ApiEndpointRegistry::recentLogs(100));
        $this->view->assign('announcementVariables', SourceAnnouncementTemplate::variables());
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a", [], 'trim');
            if ($params) {
                foreach ($params as $k => &$v) {
                    $v = is_array($v) ? implode(',', $v) : $v;
                }
                if (in_array($params['type'], ['select', 'selects', 'checkbox', 'radio', 'array'])) {
                    $params['content'] = json_encode(ConfigModel::decode($params['content']), JSON_UNESCAPED_UNICODE);
                } else {
                    $params['content'] = '';
                }
                try {
                    $result = $this->model->create($params);
                } catch (Exception $e) {
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    try {
                        $this->refreshFile();
                    } catch (Exception $e) {
                        $this->error($e->getMessage());
                    }
                    $this->success();
                } else {
                    $this->error($this->model->getError());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     * @param null $ids
     */
    public function edit($ids = null)
    {
        if ($this->request->isPost()) {
            $this->token();
            $row = $this->request->post("row/a", [], 'trim');
            if ($row) {
                $configList = [];
                foreach ($this->model->all() as $v) {
                    if (isset($row[$v['name']])) {
                        $value = $row[$v['name']];
                        if ($v['name'] === 'message' && !is_array($value)) {
                            $value = SourceAnnouncementTemplate::normalizeTemplate((string)$value);
                        }
                        if (is_array($value) && isset($value['field'])) {
                            $value = json_encode(ConfigModel::getArrayData($value), JSON_UNESCAPED_UNICODE);
                        } else {
                            $value = is_array($value) ? implode(',', $value) : $value;
                        }
                        $v['value'] = $value;
                        $configList[] = $v->toArray();
                    }
                }
                try {
                    $this->model->allowField(true)->saveAll($configList);
                } catch (Exception $e) {
                    $this->error($e->getMessage());
                }
                try {
                    $this->refreshFile();
                } catch (Exception $e) {
                    $this->error($e->getMessage());
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
    }

    /**
     * 删除
     * @param string $ids
     */
    public function del($ids = "")
    {
        $name = $this->request->post('name');
        $config = ConfigModel::getByName($name);
        if ($name && $config) {
            try {
                $config->delete();
                $this->refreshFile();
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
            $this->success();
        } else {
            $this->error(__('Invalid parameters'));
        }
    }

    /**
     * 刷新配置文件
     */
    protected function refreshFile()
    {
        $config = [];
        foreach ($this->model->all() as $k => $v) {
            $value = $v->toArray();
            if (in_array($value['type'], ['selects', 'checkbox', 'images', 'files'])) {
                $value['value'] = explode(',', $value['value']);
            }
            if ($value['type'] == 'array') {
                $value['value'] = (array)json_decode($value['value'], true);
            }
            $config[$value['name']] = $value['value'];
        }
        file_put_contents(
            APP_PATH . 'extra' . DS . 'site.php',
            '<?php' . "\n\nreturn " . var_export($config, true) . ";"
        );
        try {
            SourceConfigRepository::forget();
        } catch (\Throwable $e) {
            error_log('[Config::refreshFile] source config cache invalidation failed: ' . $e->getMessage());
        }
    }

    /**
     * 检测配置项是否存在
     * @internal
     */
    public function check()
    {
        $params = $this->request->post("row/a");
        if ($params) {
            $config = $this->model->get($params);
            if (!$config) {
                return $this->success();
            } else {
                return $this->error(__('Name already exist'));
            }
        } else {
            return $this->error(__('Invalid parameters'));
        }
    }

    /**
     * 规则列表
     * @internal
     */
    public function rulelist()
    {
        $primarykey = $this->request->request("keyField");
        $keyValue = $this->request->request("keyValue", "");
        $keyValueArr = array_filter(explode(',', $keyValue));
        $regexList = \app\common\model\Config::getRegexList();
        $list = [];
        foreach ($regexList as $k => $v) {
            if ($keyValueArr) {
                if (in_array($k, $keyValueArr)) {
                    $list[] = ['id' => $k, 'name' => $v];
                }
            } else {
                $list[] = ['id' => $k, 'name' => $v];
            }
        }
        return json(['list' => $list]);
    }

    /**
     * 发送测试邮件
     * @internal
     */
    public function emailtest()
    {
        $row = $this->request->post('row/a');
        $receiver = $this->request->post("receiver");
        if ($receiver) {
            if (!Validate::is($receiver, "email")) {
                $this->error(__('Please input correct email'));
            }
            \think\Config::set('site', array_merge(\think\Config::get('site'), $row));
            $email = new Email;
            $result = $email
                ->to($receiver)
                ->subject(__("This is a test mail"))
                ->message('<div style="min-height:550px; padding: 100px 55px 200px;">' . __('This is a test mail content') . '</div>')
                ->send();
            if ($result) {
                $this->success();
            } else {
                $this->error($email->getError());
            }
        } else {
            return $this->error(__('Invalid parameters'));
        }
    }

    /**
     * 原版更新源检查。
     */
    public function update()
    {
        return json($this->updateManager()->check('nuosike'));
    }

    public function version_notice()
    {
        return json($this->updateManager()->check('nuosike'));
    }

    public function system_update()
    {
        $force = intval($this->request->param('force', 0)) === 1;
        $jobId = (string)$this->request->param('job_id', '');
        $this->releaseUpdateSession();
        return json($this->updateManager()->install('nuosike', $force, $jobId));
    }

    /**
     * GitHub 稳定 Release 更新检查。
     */
    public function github_update()
    {
        return json($this->updateManager()->check('github'));
    }

    public function github_system_update()
    {
        $force = intval($this->request->param('force', 0)) === 1;
        $jobId = (string)$this->request->param('job_id', '');
        $this->releaseUpdateSession();
        return json($this->updateManager()->install('github', $force, $jobId));
    }

    /**
     * 查询真实更新阶段进度。
     */
    public function update_status()
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
        $jobId = (string)$this->request->param('job_id', '');
        return json($this->updateManager()->status($jobId));
    }

    /**
     * 更新/回滚历史。
     */
    public function update_history()
    {
        $limit = intval($this->request->param('limit', 20));
        return json($this->updateManager()->history($limit));
    }

    /**
     * 从指定成功更新记录回滚。
     */
    public function update_rollback()
    {
        $historyId = (string)$this->request->param('history_id', '');
        $jobId = (string)$this->request->param('job_id', '');
        $this->releaseUpdateSession();
        return json($this->updateManager()->rollback($historyId, $jobId));
    }

    public function api_toggle()
    {
        $endpointKey = trim((string)$this->request->post('endpoint_key', ''));
        $enabled = (int)$this->request->post('enabled', 0) === 1;
        try {
            ApiEndpointRegistry::toggle($endpointKey, $enabled);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('API状态已更新', null, [
            'endpoint_key' => $endpointKey,
            'enabled' => $enabled ? 1 : 0,
        ]);
    }

    public function api_save()
    {
        try {
            $id = (int)$this->request->post('id', 0);
            $params = $this->request->post('row/a', []);
            $endpointKey = ApiEndpointRegistry::saveCustom(is_array($params) ? $params : [], $id);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('API已保存', null, ['endpoint_key' => $endpointKey]);
    }

    public function api_delete()
    {
        try {
            ApiEndpointRegistry::deleteCustom((int)$this->request->post('id', 0));
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('API已删除');
    }

    public function api_logs()
    {
        return json(['code' => 1, 'data' => ApiEndpointRegistry::recentLogs((int)$this->request->get('limit', 100))]);
    }

    public function api_test_schema()
    {
        $endpointKey = trim((string)$this->request->get('endpoint_key', ''));
        $schema = ApiEndpointRegistry::testSchema($endpointKey);
        if (!$schema) {
            $this->error('API不存在');
        }
        $this->success('ok', null, $schema);
    }

    public function api_test()
    {
        $endpointKey = trim((string)$this->request->post('endpoint_key', ''));
        try {
            if ($endpointKey === '') {
                throw new \InvalidArgumentException('请选择API接口');
            }
            $row = ApiEndpointRegistry::endpoint($endpointKey);
            $schema = ApiEndpointRegistry::testSchema($endpointKey);
            if (!$row || !$schema) {
                throw new \InvalidArgumentException('API不存在');
            }

            $input = $this->request->post('test_params/a', []);
            if (!is_array($input)) {
                $input = [];
            }
            $clean = [];
            foreach ((array)$schema['fields'] as $field) {
                $name = isset($field['name']) ? (string)$field['name'] : '';
                if ($name === '') {
                    continue;
                }
                $value = trim(isset($input[$name]) ? (string)$input[$name] : '');
                if (!empty($field['required']) && $value === '') {
                    throw new \InvalidArgumentException((isset($field['label']) ? $field['label'] : $name) . '不能为空');
                }
                if ($value !== '') {
                    $clean[$name] = $value;
                }
            }

            // Compatibility with the old single params field while 1807-era
            // pages are still open in a browser during the upgrade.
            if (!$clean) {
                $legacy = trim((string)$this->request->post('params', ''));
                if ($legacy !== '') {
                    parse_str(ltrim($legacy, '?&'), $legacyValues);
                    if (is_array($legacyValues)) {
                        $clean = $legacyValues;
                    }
                }
            }

            $url = rtrim($this->request->domain(), '/') . (string)$row['path'];
            $method = strtoupper(isset($schema['method']) ? (string)$schema['method'] : (string)$row['method']);
            $encoded = http_build_query($clean, '', '&');
            $ch = null;
            if ($method === 'GET') {
                if ($encoded !== '') {
                    $url .= (strpos($url, '?') === false ? '?' : '&') . $encoded;
                }
                $ch = curl_init($url);
            } else {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            $body = curl_exec($ch);
            $error = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $elapsed = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
            curl_close($ch);

            if ($body === false) {
                throw new \RuntimeException('API测试失败: ' . $error);
            }
            $result = [
                'url' => $url,
                'method' => $method,
                'params' => $clean,
                'status' => $status,
                'elapsed_ms' => round($elapsed, 2),
                'body' => mb_substr((string)$body, 0, 4000, 'UTF-8'),
            ];
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('API测试完成', null, $result);
    }

    public function announcement_preview()
    {
        $template = (string)$this->request->post('message', '');
        $udid = trim((string)$this->request->post('udid', ''));
        $now = time();

        $configRows = SourceConfigRepository::rows();
        $sourceName = (string)SourceConfigRepository::rawValueFromRows($configRows, 'name', '');
        $appRows = SourceAppRepository::rows();
        $cardRows = [];
        if ($udid !== '') {
            $cardRows = Db::table('fa_kami')
                ->where('udid', $udid)
                ->order('id desc')
                ->select();
            $cardRows = is_array($cardRows) ? $cardRows : [];
        }

        $sourceAccess = SourceAnnouncementTemplate::sourceAccessForRows($cardRows, $now);
        $context = SourceAnnouncementTemplate::buildContext(
            $template,
            $sourceName,
            $appRows,
            $cardRows,
            $sourceAccess,
            $now
        );

        $this->success('公告预览已生成', null, [
            'message' => SourceAnnouncementTemplate::render($template, $context),
            'context' => $context,
        ]);
    }

    protected function releaseUpdateSession()
    {
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    protected function updateManager()
    {
        return new UpdateManager(ROOT_PATH);
    }
}
