<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\CardAccessPolicy;
use app\common\library\CardCodeGenerator;
use app\common\library\AuthorizationSchema;
use think\Db;

class Kami extends Backend
{
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        AuthorizationSchema::ensure();
        $this->model = new \app\admin\model\Kami;
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if ($params) {
                $count = isset($params['kami']) ? intval($params['kami']) : 0;
                $prefix = isset($params['udid']) ? trim($params['udid']) : '';
                $type = isset($params['Kmyp']) ? intval($params['Kmyp']) : 0;
                $rawScope = isset($params['card_scope']) ? intval($params['card_scope']) : CardAccessPolicy::SCOPE_SOURCE;
                if (!in_array($rawScope, [CardAccessPolicy::SCOPE_SOURCE, CardAccessPolicy::SCOPE_VERIFY, CardAccessPolicy::SCOPE_APPS], true)) {
                    $this->error('请选择有效的卡密用途');
                }
                $scope = $rawScope;
                $appIds = isset($params['app_ids']) && is_array($params['app_ids'])
                    ? CardAccessPolicy::normalizeAppIds($params['app_ids'])
                    : [];
                $transferQuota = isset($params['transfer_count']) ? intval($params['transfer_count']) : 100;

                if ($count <= 0) {
                    $this->error('数量需大于0');
                }
                if (!in_array($type, [1, 2, 3, 4, 5], true)) {
                    $this->error('请选择有效的卡密类型');
                }
                if ($transferQuota < 0 || $transferQuota > 1000000) {
                    $this->error('换绑次数需在0到1000000之间');
                }
                if ($scope === CardAccessPolicy::SCOPE_APPS) {
                    $appIds = $this->validateTargetApps($appIds);
                    if (!$appIds) {
                        $this->error('指定App卡至少需要选择一个可锁定App');
                    }
                } else {
                    $appIds = [];
                }

                try {
                    $codes = CardCodeGenerator::generateUniqueBatch(
                        $count,
                        $prefix,
                        function (array $candidates) {
                            return Db::table('fa_kami')
                                ->where('kami', 'in', $candidates)
                                ->column('kami');
                        }
                    );
                } catch (\Exception $e) {
                    $this->error($e->getMessage());
                    return;
                }

                $createdAt = time();
                $html = '<br>';
                Db::startTrans();
                try {
                    foreach ($codes as $code) {
                        $kamiId = Db::table('fa_kami')->insertGetId([
                            'kami' => $code,
                            'udid' => '',
                            'kmyp' => $type,
                            'transfer_count' => $transferQuota,
                            'card_scope' => $scope,
                            'addtime' => $createdAt,
                            'usetime' => 0,
                            'endtime' => 0,
                        ]);
                        if (!$kamiId) {
                            throw new \RuntimeException('卡密写入失败');
                        }
                        if ($scope === CardAccessPolicy::SCOPE_APPS) {
                            $this->replaceTargetApps($kamiId, $appIds);
                        }
                        $html .= $code . '<br>';
                    }
                    Db::table('fa_kmstr')->where('id', 1)->update(['kmstr' => $html]);
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                    return;
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        $stlst = Db::table('fa_kmstr')->where('id', 1)->find();
        $this->view->assign('strLst', $stlst ? $stlst['kmstr'] : '');
        $this->view->assign('appOptions', $this->appOptions());
        return parent::add();
    }

    /**
     * transfer_count is the remaining quota of one entitlement chain.
     * Phase 16 keeps quotas isolated between whole-source, verification-only,
     * and individual App-set chains on the same UDID.
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
            $params = $this->request->post('row/a');
            if ($params) {
                $params = $this->preExcludeFields($params);
                $rawScope = isset($params['card_scope']) ? intval($params['card_scope']) : (int)$row['card_scope'];
                if (!in_array($rawScope, [CardAccessPolicy::SCOPE_SOURCE, CardAccessPolicy::SCOPE_VERIFY, CardAccessPolicy::SCOPE_APPS], true)) {
                    $this->error('请选择有效的卡密用途');
                }
                $scope = $rawScope;
                $appIds = isset($params['app_ids']) && is_array($params['app_ids'])
                    ? CardAccessPolicy::normalizeAppIds($params['app_ids'])
                    : [];
                unset($params['app_ids']);
                $params['card_scope'] = $scope;

                if ($scope === CardAccessPolicy::SCOPE_APPS) {
                    $appIds = $this->validateTargetApps($appIds);
                    if (!$appIds) {
                        $this->error('指定App卡至少需要选择一个可锁定App');
                    }
                } else {
                    $appIds = [];
                }

                $quotaChanged = array_key_exists('transfer_count', $params);
                $quota = $quotaChanged ? intval($params['transfer_count']) : null;
                if ($quotaChanged && ($quota < 0 || $quota > 1000000)) {
                    $this->error('换绑次数需在0到1000000之间');
                }
                if ($quotaChanged) {
                    $params['transfer_count'] = $quota;
                }

                Db::startTrans();
                try {
                    $result = $row->allowField(true)->save($params);
                    $this->replaceTargetApps((int)$row['id'], $appIds);
                    if ($quotaChanged) {
                        $this->syncQuotaForChain($row, $quota, time());
                    }
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                    return;
                }
                if ($result !== false) {
                    $this->success();
                }
                $this->error(__('No rows were updated'));
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        $this->view->assign('row', $row);
        $this->view->assign('appOptions', $this->appOptions());
        $this->view->assign('selectedAppIds', $this->targetAppIds((int)$row['id']));
        return $this->view->fetch();
    }

    protected function appOptions()
    {
        $rows = Db::table('fa_category')
            ->field('id,name')
            ->where('status', 'normal')
            ->where('bt2b', '1')
            ->order('weigh desc,id desc')
            ->select();
        $options = [];
        foreach ($rows as $item) {
            $id = isset($item['id']) ? (int)$item['id'] : 0;
            if ($id > 0) {
                $name = isset($item['name']) ? trim((string)$item['name']) : '';
                $options[$id] = ($name !== '' ? $name : ('App #' . $id)) . ' (#' . $id . ')';
            }
        }
        return $options;
    }

    protected function validateTargetApps(array $appIds)
    {
        $appIds = CardAccessPolicy::normalizeAppIds($appIds);
        if (!$appIds) {
            return [];
        }
        $options = $this->appOptions();
        $valid = [];
        foreach ($appIds as $appId) {
            if (isset($options[$appId])) {
                $valid[] = $appId;
            }
        }
        if (count($valid) !== count($appIds)) {
            $this->error('指定App中包含不存在、已停用或非锁定项目');
        }
        return $valid;
    }

    protected function targetAppIds($kamiId)
    {
        $ids = Db::table('fa_kami_app')
            ->where('kami_id', (int)$kamiId)
            ->order('app_id asc')
            ->column('app_id');
        return CardAccessPolicy::normalizeAppIds(is_array($ids) ? $ids : []);
    }

    protected function replaceTargetApps($kamiId, array $appIds)
    {
        $kamiId = (int)$kamiId;
        if ($kamiId <= 0) {
            throw new \RuntimeException('卡密ID无效');
        }
        Db::table('fa_kami_app')->where('kami_id', $kamiId)->delete();
        foreach (CardAccessPolicy::normalizeAppIds($appIds) as $appId) {
            $inserted = Db::table('fa_kami_app')->insert([
                'kami_id' => $kamiId,
                'app_id' => $appId,
            ]);
            if ($inserted !== 1) {
                throw new \RuntimeException('指定App授权写入失败');
            }
        }
    }

    protected function syncQuotaForChain($row, $quota, $now)
    {
        $udid = trim((string)$row['udid']);
        if ($udid === '' || (int)$row['jh'] !== 1 || (int)$row['endtime'] <= $now) {
            return;
        }
        $scope = CardAccessPolicy::scopeForRow($row->toArray());
        $targetApps = $scope === CardAccessPolicy::SCOPE_APPS
            ? $this->targetAppIds((int)$row['id'])
            : [];
        $activeRows = Db::table('fa_kami')
            ->where('udid', $udid)
            ->where('jh', 1)
            ->where('endtime', '>', $now)
            ->select();
        foreach ($activeRows as $active) {
            if (CardAccessPolicy::scopeForRow($active) !== $scope) {
                continue;
            }
            if ($scope === CardAccessPolicy::SCOPE_APPS && !CardAccessPolicy::sameAppSet($this->targetAppIds((int)$active['id']), $targetApps)) {
                continue;
            }
            Db::table('fa_kami')->where('id', (int)$active['id'])->update(['transfer_count' => (int)$quota]);
        }
    }
}
