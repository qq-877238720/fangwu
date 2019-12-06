<?php

namespace app\index\model;

use think\Model;

class RoomLists extends Model
{

    // 表名
    protected $table = 'room_lists';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'status_text'
    ];

    public function getFjstatusList()
    {
        return ['10' => __('在租'), '11' => __('空置'), '12' => __('装修'), '13' => __('预定')];
    }

    public function getFjstatusNum($value)
    {
        $fjstatusList = ['在租' => 10, '空置' => 11, '装修' => 12, '预定' => 13];
        return $fjstatusList[$value];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : $data['fjstatus'];
        $list = $this->getFjstatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

}
