<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\SourceAppRepository;
use app\common\library\SourceChangeLog;
use app\common\model\Category as CategoryModel;
use think\Db;

/**
 * 分类管理
 *
 * @icon   fa fa-list
 * @remark 用于统一管理网站的所有分类,分类可进行无限级分类,分类类型请在常规管理->系统配置->字典配置中添加
 */
class Category extends Backend
{

    /**
     * @var \app\common\model\Category
     */
    protected $model = null;
    protected $noNeedRight = ['selectpage'];
    protected $searchFields = 'name';
    protected $renewalEntryAvailable = false;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('app\common\model\Category');

        $typeList = CategoryModel::getTypeList();
        $this->view->assign("flagList", $this->model->getFlagList());
        $this->view->assign("typeList", $typeList);
        $this->assignconfig('typeList', $typeList);

        // 项目管理的 pid 控件历史上一直隐藏。2 万级数据下为隐藏控件构建整张分类树
        // 会导致 add/edit 无意义地全表扫描、PHP Tree 运算和海量 option 渲染。
        // 新增固定 pid=0；编辑保持原 pid，不再加载 parentList。
        $action = strtolower((string)$this->request->action());
        if ($action === 'add' || $action === 'edit') {
            $this->renewalEntryAvailable = $this->ensureRenewalEntryColumn();
            $this->view->assign('renewalEntryAvailable', $this->renewalEntryAvailable);
        }
    }

    /**
     * 查看
     */
    public function index()
    {
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            // 快速搜索只匹配应用名称；分页、排序由 BootstrapTable 的 server 模式传入。
            list($where, $sort, $order, $offset, $limit) = $this->buildparams('name');
            $type = $this->request->request("type");
            $typeList = CategoryModel::getTypeList();

            $offset = max(0, (int)$offset);
            $limit = (int)$limit;
            $allowedLimits = [200, 500, 1000];
            if (!in_array($limit, $allowedLimits, true)) {
                $limit = 1000;
            }

            $countQuery = Db::name('category')->where($where);
            if ($type !== null && $type !== '' && $type !== 'all') {
                $countQuery->where('type', '=', $type);
            }
            $total = $countQuery->count();

            // 只取后台列表和列选择器真正使用的字段，避免把安装地址等无关字段传到浏览器。
            $listQuery = Db::name('category')
                ->field('id,type,name,nickname,keywords,bt2b,beizhu,image,weigh,status')
                ->where($where);
            if ($type !== null && $type !== '' && $type !== 'all') {
                $listQuery->where('type', '=', $type);
            }
            $list = $listQuery
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as &$row) {
                if (isset($typeList[$row['type']])) {
                    $row['type'] = $typeList[$row['type']];
                }
            }
            unset($row);

            return json([
                'total' => (int)$total,
                'rows' => $list,
            ]);
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }

        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $modify = isset($params['modify']) ? $params['modify'] : null;
                unset($params['modify']);

                // pid 不在项目管理 UI 中开放修改。即使客户端手工提交其它 pid，也保持原值，
                // 从而彻底避免为父子循环校验再次全表加载 Category。
                $params['pid'] = (int)$row['pid'];
                $params = $this->normalizeWriteParams($params, true);

                try {
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate)
                            ? ($this->modelSceneValidate ? $name . '.edit' : $name)
                            : $this->modelValidate;
                        $row->validate($validate);
                    }
                    if ($modify == '2') {
                        $params['updatetime'] = time();
                    }
                    // 保留历史直接 Db update 行为；成功修改后显式写入 V3 revision。
                    $result = Db::name('category')->where(['id' => $ids])->update($params);
                    if ($result !== false) {
                        if ((int)$result > 0) {
                            SourceAppRepository::forget();
                            SourceChangeLog::record((int)$ids, 'update');
                        }
                        $this->success();
                    } else {
                        $this->error($row->getError());
                    }
                } catch (\think\exception\PDOException $e) {
                    $this->error($e->getMessage());
                } catch (\think\Exception $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        if (!$row['bt2a']) {
            $row['bt2a'] = 0;
        }
        $row['bt2a'] = round($row['bt2a'] / (1024 * 1024), 2);
        $row['keywords'] = $this->decodeKeywordsNewlines($row['keywords']);
        if (!isset($row['renewal_entry'])) {
            $row['renewal_entry'] = 0;
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * Selectpage搜索
     *
     * @internal
     */
    public function selectpage()
    {
        return parent::selectpage();
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                // 项目新增固定为根级 App，不接受客户端构造父分类。
                $params['pid'] = 0;
                $params = $this->normalizeWriteParams($params, false);
                $category = new CategoryModel();
                $category->allowField(true)->save($params);
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    /**
     * 统一后台写入前的数据规范化。
     * 编辑保持历史行为：文件大小无条件按 MB 转字节；新增仅在大于 0 时转换。
     */
    protected function normalizeWriteParams(array $params, $isEdit)
    {
        if (isset($params['bt1b'])) {
            $params['bt1b'] = ltrim($params['bt1b'], '#');
        }
        if (isset($params['keywords'])) {
            $params['keywords'] = $this->encodeKeywordsNewlines($params['keywords']);
        }
        if (isset($params['bt2a'])) {
            if ($isEdit || $params['bt2a'] > 0) {
                $params['bt2a'] = $params['bt2a'] * 1024 * 1024;
            }
        }
        if (array_key_exists('renewal_entry', $params)) {
            if ($this->renewalEntryAvailable) {
                $params['renewal_entry'] = (int)$params['renewal_entry'] === 1 ? 1 : 0;
            } else {
                unset($params['renewal_entry']);
            }
        }
        return $params;
    }

    /**
     * 1704 在线升级若因数据库权限/旧安装状态漏掉 renewal_entry，
     * 后台新增/编辑应用时尝试自修复；修复失败也只隐藏该选项，不能让整个编辑页报错。
     */
    protected function ensureRenewalEntryColumn()
    {
        try {
            $columns = Db::query("SHOW COLUMNS FROM `fa_category` LIKE 'renewal_entry'");
            if (!empty($columns)) {
                return true;
            }

            $bt2b = Db::query("SHOW COLUMNS FROM `fa_category` LIKE 'bt2b'");
            $sql = "ALTER TABLE `fa_category` ADD COLUMN `renewal_entry` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '续费入口:1是,0否'";
            if (!empty($bt2b)) {
                $sql .= " AFTER `bt2b`";
            }
            Db::execute($sql);

            $columns = Db::query("SHOW COLUMNS FROM `fa_category` LIKE 'renewal_entry'");
            return !empty($columns);
        } catch (\Exception $e) {
            error_log('[Category] renewal_entry schema repair failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 真实换行转为字面量 \n，保持接口 JSON 仍输出合法转义
     */
    protected function encodeKeywordsNewlines($value)
    {
        $value = str_replace(["\r\n", "\r"], "\n", (string)$value);
        return str_replace("\n", '\\n', $value);
    }

    /**
     * 字面量 \n 转为真实换行，供后台 textarea 显示
     */
    protected function decodeKeywordsNewlines($value)
    {
        $value = str_replace(["\r\n", "\r"], "\n", (string)$value);
        return str_replace('\\n', "\n", $value);
    }
}
