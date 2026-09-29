<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\Ipa\IpaCompareService;
use think\Db;

/**
 * IPA 资产列表专用查询端点。
 *
 * 采用与 FastAdmin 卡密列表一致的 search/filter/op 查询参数，
 * 同时对虚拟字段 source_name / compare_state 做服务端映射。
 *
 * 2026092420：
 * - count/select 使用独立 Query Builder，避免带绑定参数的查询对象 clone/复用；
 * - commonSearch 存在有效 filter 时优先执行，避免残留 quick search 继续 AND 筛选；
 * - 单值等值条件使用显式三参数 where(field, '=', value)。
 */
class IpaAssets extends Backend
{
    protected $noNeedRight = ['index'];

    public function index()
    {
        $offset = max(0, (int)$this->request->get('offset', 0));
        $limit = max(20, min(500, (int)$this->request->get('limit', 100)));
        $search = trim((string)$this->request->get('search', ''));
        $filter = json_decode((string)$this->request->get('filter', ''), true);
        $op = json_decode((string)$this->request->get('op', ''), true);
        $filter = is_array($filter) ? $filter : [];
        $op = is_array($op) ? $op : [];

        $sort = (string)$this->request->get('sort', 'id');
        $order = strtoupper((string)$this->request->get('order', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $sortable = ['id','source_id','name','bundle_id','app_name','app_version','build_version','size_bytes','status','last_seen_at','parsed_at','updated_at'];
        if (!in_array($sort, $sortable, true)) {
            $sort = 'id';
        }

        // 不 clone / 复用带 PDO bindings 的 Query Builder。
        // count 和 rows 分别重新构造完整条件，避免 PHP 7 + 当前 ThinkPHP/PDO
        // 组合下出现占位符与绑定参数状态串扰（SQLSTATE 2031）。
        $countQuery = $this->buildAssetQuery($search, $filter, $op);
        $total = $countQuery->count();

        $rowQuery = $this->buildAssetQuery($search, $filter, $op);
        $rows = $rowQuery
            ->field('id,source_id,name,path,size_bytes,status,bundle_id,app_name,app_version,build_version,last_error,last_seen_at,parsed_at,updated_at')
            ->order($sort, $order)
            ->limit($offset, $limit)
            ->select();

        $ids = [];
        $sourceIds = [];
        foreach ($rows as $row) {
            $ids[] = (int)$row['id'];
            $sourceIds[(int)$row['source_id']] = (int)$row['source_id'];
        }

        $sourceNames = [];
        if ($sourceIds) {
            $sources = Db::name('ipa_source')
                ->field('id,name')
                ->where('id', 'in', array_values($sourceIds))
                ->select();
            foreach ($sources as $source) {
                $sourceNames[(int)$source['id']] = (string)$source['name'];
            }
        }

        $summaries = [];
        try {
            $summaries = IpaCompareService::summaryForAssets($ids);
        } catch (\Exception $e) {
            $summaries = [];
        }

        foreach ($rows as &$row) {
            $summary = isset($summaries[(int)$row['id']])
                ? $summaries[(int)$row['id']]
                : ['sources'=>0,'anomalies'=>0,'unmatched'=>0,'errors'=>0];
            $row['source_name'] = isset($sourceNames[(int)$row['source_id']])
                ? $sourceNames[(int)$row['source_id']]
                : '已删除数据源';
            $row['compare_summary'] = $summary;
            $row['compare_state'] = $this->compareState((string)$row['status'], $summary);
        }
        unset($row);

        return json(['total'=>(int)$total, 'rows'=>$rows]);
    }

    protected function buildAssetQuery($search, array $filter, array $op)
    {
        $query = Db::name('ipa_asset');

        // FastAdmin commonSearch 会保留 BootstrapTable 右上角 quick-search 文本。
        // 只要存在有效字段筛选，就让 commonSearch 独占查询，避免旧 search 再次 AND。
        if ($this->hasCommonFilters($filter)) {
            $this->applyCommonSearch($query, $filter, $op);
        } else {
            $this->applyQuickSearch($query, $search);
        }

        return $query;
    }

    protected function hasCommonFilters(array $filter)
    {
        foreach ($filter as $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    if (trim((string)$item) !== '') {
                        return true;
                    }
                }
                continue;
            }
            if (trim((string)$value) !== '') {
                return true;
            }
        }
        return false;
    }

    protected function applyQuickSearch($query, $search)
    {
        if ($search === '') {
            return;
        }

        $like = '%' . $search . '%';
        $sourceIds = Db::name('ipa_source')->where('name', 'like', $like)->column('id');
        $compareIds = $this->compareIdsForKeyword($search);
        $statusAliases = [
            '待解析'=>'discovered',
            '解析中'=>'parsing',
            '已解析'=>'parsed',
            '解析失败'=>'parse_failed',
            '已缺失'=>'missing',
        ];
        $statusSearch = isset($statusAliases[$search]) ? $statusAliases[$search] : $search;

        $query->where(function ($q) use ($search, $like, $sourceIds, $compareIds, $statusSearch) {
            $q->where('name', 'like', $like)
                ->whereOr('path', 'like', $like)
                ->whereOr('bundle_id', 'like', $like)
                ->whereOr('app_name', 'like', $like)
                ->whereOr('app_version', 'like', $like)
                ->whereOr('build_version', 'like', $like)
                ->whereOr('status', 'like', '%' . $statusSearch . '%');
            if (ctype_digit($search)) {
                $q->whereOr('id', '=', (int)$search)->whereOr('source_id', '=', (int)$search);
            }
            if ($sourceIds) {
                $q->whereOr('source_id', 'in', array_values($sourceIds));
            }
            if ($compareIds) {
                $q->whereOr('id', 'in', $compareIds);
            }
        });
    }

    protected function applyCommonSearch($query, array $filter, array $op)
    {
        foreach ($filter as $field => $value) {
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            $value = trim((string)$value);
            if ($value === '') {
                continue;
            }

            switch ($field) {
                case 'id':
                case 'source_id':
                    if (ctype_digit($value)) {
                        $query->where($field, '=', (int)$value);
                    }
                    break;

                case 'name':
                case 'path':
                case 'bundle_id':
                case 'app_name':
                case 'app_version':
                case 'build_version':
                    $query->where($field, 'like', '%' . $value . '%');
                    break;

                case 'status':
                    $statuses = array_values(array_filter(explode(',', $value), 'strlen'));
                    if ($statuses) {
                        if (count($statuses) > 1) {
                            $query->where('status', 'in', $statuses);
                        } else {
                            $query->where('status', '=', $statuses[0]);
                        }
                    }
                    break;

                case 'source_name':
                    $ids = Db::name('ipa_source')->where('name', 'like', '%' . $value . '%')->column('id');
                    if ($ids) {
                        $query->where('source_id', 'in', array_values($ids));
                    } else {
                        $query->where('id', '=', 0);
                    }
                    break;

                case 'compare_state':
                    $ids = $this->compareIdsForState($value);
                    if ($ids) {
                        $query->where('id', 'in', $ids);
                    } else {
                        $query->where('id', '=', 0);
                    }
                    break;

                default:
                    break;
            }
        }
    }

    protected function compareIdsForKeyword($keyword)
    {
        $map = [
            '异常'=>'abnormal',
            '字段异常'=>'anomaly',
            '未匹配'=>'unmatched',
            '源错误'=>'source_error',
            '错误'=>'source_error',
            '正常'=>'normal',
            '待比对'=>'pending',
        ];
        return isset($map[$keyword]) ? $this->compareIdsForState($map[$keyword]) : [];
    }

    protected function compareIdsForState($state)
    {
        $state = trim((string)$state);
        if ($state === 'abnormal') {
            return $this->uniqueInts(Db::name('ipa_compare_result')
                ->where('status', 'in', ['anomaly','unmatched','source_error'])
                ->column('asset_id'));
        }
        if ($state === 'anomaly') {
            return $this->uniqueInts(Db::name('ipa_compare_result')->where('status', '=', 'anomaly')->column('asset_id'));
        }
        if ($state === 'unmatched') {
            return $this->uniqueInts(Db::name('ipa_compare_result')->where('status', '=', 'unmatched')->column('asset_id'));
        }
        if ($state === 'source_error') {
            return $this->uniqueInts(Db::name('ipa_compare_result')->where('status', '=', 'source_error')->column('asset_id'));
        }
        if ($state === 'normal') {
            $good = $this->uniqueInts(Db::name('ipa_compare_result')->where('status', '=', 'matched')->column('asset_id'));
            $bad = $this->uniqueInts(Db::name('ipa_compare_result')
                ->where('status', 'in', ['anomaly','unmatched','source_error'])
                ->column('asset_id'));
            return array_values(array_diff($good, $bad));
        }
        if ($state === 'pending') {
            $compared = $this->uniqueInts(Db::name('ipa_compare_result')->column('asset_id'));
            $q = Db::name('ipa_asset')->where('status', '=', 'parsed');
            if ($compared) {
                $q->where('id', 'not in', $compared);
            }
            return $this->uniqueInts($q->column('id'));
        }
        return [];
    }

    protected function compareState($assetStatus, array $summary)
    {
        if ($assetStatus !== 'parsed') {
            return '';
        }
        if (empty($summary['sources'])) {
            return 'pending';
        }
        if (!empty($summary['errors'])) {
            return 'source_error';
        }
        if (!empty($summary['unmatched'])) {
            return 'unmatched';
        }
        if (!empty($summary['anomalies'])) {
            return 'anomaly';
        }
        return 'normal';
    }

    protected function uniqueInts($values)
    {
        $out = [];
        foreach ((array)$values as $value) {
            $value = (int)$value;
            if ($value > 0) {
                $out[$value] = $value;
            }
        }
        return array_values($out);
    }
}
