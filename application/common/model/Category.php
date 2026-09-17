<?php

namespace app\common\model;

use app\common\library\SourceAppRepository;
use app\common\library\SourceChangeLog;
use think\Db;
use think\Model;

/**
 * 分类模型
 */
class Category extends Model
{

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'type_text',
        'flag_text',
    ];

    // afterInsert 内部会再次 save() 设置 weigh，避免把一次新增记录成 add+update 两次。
    protected static $suppressSourceChange = false;

    protected static function init()
    {
        self::afterInsert(function ($row) {
            self::$suppressSourceChange = true;
            try {
                $row->save(['weigh' => $row['id']]);
            } finally {
                self::$suppressSourceChange = false;
            }
            SourceAppRepository::forget();
            SourceChangeLog::record(isset($row['id']) ? (int)$row['id'] : 0, 'add');
        });

        self::afterUpdate(function ($row) {
            if (self::$suppressSourceChange) {
                return;
            }
            SourceAppRepository::forget();
            SourceChangeLog::record(isset($row['id']) ? (int)$row['id'] : 0, 'update');
        });

        // 真正删除 App 时同步删除“指定 App 卡 -> App”映射。
        // App 仅隐藏、卡密仅到期等状态变化不会触发这里，因此历史映射会保留。
        self::beforeDelete(function ($row) {
            $id = isset($row['id']) ? (int)$row['id'] : 0;
            if ($id > 0) {
                Db::table('fa_kami_app')->where('app_id', $id)->delete();
            }
            SourceAppRepository::forget();
        });

        self::afterDelete(function ($row) {
            SourceChangeLog::record(isset($row['id']) ? (int)$row['id'] : 0, 'delete');
        });
    }

    public function setFlagAttr($value, $data)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }

    /**
     * 读取分类类型
     * @return array
     */
    public static function getTypeList()
    {
        $typeList = config('site.categorytype');
        foreach ($typeList as $k => &$v) {
            $v = __($v);
        }
        return $typeList;
    }

    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : $data['type'];
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getFlagList()
    {
        return ['hot' => __('Hot'), 'index' => __('Index'), 'recommend' => __('Recommend')];
    }

    public function getFlagTextAttr($value, $data)
    {
        $value = $value ? $value : $data['flag'];
        $valueArr = explode(',', $value);
        $list = $this->getFlagList();
        return implode(',', array_intersect_key($list, array_flip($valueArr)));
    }

    /**
     * 读取分类列表
     * @param string $type   指定类型
     * @param string $status 指定状态
     * @return array
     */
    public static function getCategoryArray($type = null, $status = null)
    {
        $list = collection(self::where(function ($query) use ($type, $status) {
            if (!is_null($type)) {
                $query->where('type', '=', $type);
            }
            if (!is_null($status)) {
                $query->where('status', '=', $status);
            }
        })->order('weigh', 'desc')->select())->toArray();
        return $list;
    }
}
