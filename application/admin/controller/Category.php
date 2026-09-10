<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\model\Category as CategoryModel;
use fast\Tree;
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
    protected $categorylist = [];
    protected $noNeedRight = ['selectpage'];
    protected $searchFields = 'name';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('app\common\model\Category');

        $typeList = CategoryModel::getTypeList();
        $this->view->assign("flagList", $this->model->getFlagList());
        $this->view->assign("typeList", $typeList);
        $this->assignconfig('typeList', $typeList);

        // 列表页改为数据库分页后，不再为每次 index AJAX 请求加载整张分类表和构建 Tree。
        // add/edit 仍保留原 parentList 行为，避免改变历史的父分类选择语义。
        $action = strtolower((string)$this->request->action());
        if ($action === 'add' || $action === 'edit') {
            $this->view->assign("parentList", $this->buildParentList());
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
     * 仅 add/edit 需要完整父分类树，避免列表请求重复加载全部项目。
     */
    protected function buildParentList()
    {
        $tree = Tree::instance();
        $tree->init(collection($this->model->order('weigh desc,id desc')->select())->toArray(), 'pid');
        $this->categorylist = $tree->getTreeList($tree->getTreeArray(0), 'name');

        $categorydata = [0 => ['type' => 'all', 'name' => __('None')]];
        foreach ($this->categorylist as $v) {
            $categorydata[$v['id']] = $v;
        }
        return $categorydata;
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
                $params = $this->normalizeWriteParams($params, true);

                if (isset($params['pid']) && $params['pid'] != $row['pid']) {
                    $childrenIds = Tree::instance()
                        ->init(collection(\app\common\model\Category::select())->toArray())
                        ->getChildrenIds($row['id'], true);
                    if (in_array($params['pid'], $childrenIds)) {
                        $this->error(__('Can not change the parent to child or itself'));
                    }
                }

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
                    $result = Db::name('category')->where(['id' => $ids])->update($params);
                    if ($result !== false) {
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
        return $params;
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
