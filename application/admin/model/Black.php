<?php

namespace app\admin\model;

use think\Model;

class Black extends Model
{
    protected $name = 'black';
    protected $autoWriteTimestamp = false;
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    protected $append = [
        'addtime_text',
        'usetime_text',
        'endtime_text'
    ];

    public function getAddtimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['addtime']) ? $data['addtime'] : '');
        return is_numeric($value) && (int)$value > 0 ? date('Y-m-d H:i:s', $value) : $value;
    }

    public function getUsetimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['usetime']) ? $data['usetime'] : '');
        return is_numeric($value) && (int)$value > 0 ? date('Y-m-d H:i:s', $value) : '';
    }

    public function getEndtimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['endtime']) ? $data['endtime'] : '');
        return is_numeric($value) && (int)$value > 0 ? date('Y-m-d H:i:s', $value) : '';
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
