<?php

namespace app\admin\model;

use think\Db;
use think\Model;


class Kami extends Model
{

    // 表名
    protected $name = 'kami';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'addtime_text',
        'usetime_text',
        'endtime_text'
    ];

    protected static function init()
    {
        // fa_kami_app 是“指定 App 卡 -> App”的附属映射。
        // 卡密仅到期时不会触发 delete，因此映射会保留；只有真正删除卡密时才同步删除。
        self::beforeDelete(function ($row) {
            $id = isset($row['id']) ? (int)$row['id'] : 0;
            if ($id > 0) {
                Db::table('fa_kami_app')->where('kami_id', $id)->delete();
            }
        });
    }

    public function getAddtimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['addtime']) ? $data['addtime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getUsetimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['usetime']) ? $data['usetime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getEndtimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['endtime']) ? $data['endtime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setAddtimeAttr($value)
    {
        return $value === '' ? 0 : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setUsetimeAttr($value)
    {
        return $value === '' ? 0 : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setEndtimeAttr($value)
    {
        return $value === '' ? 0 : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

}
