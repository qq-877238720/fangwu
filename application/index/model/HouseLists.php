<?php

namespace app\index\model;

use think\Model;

class HouseLists extends Model
{

    // 表名
    protected $table = 'ho_house_lists';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'chuzutype_text'
    ];

    public function getChuzutypeList()
    {
        return ['1' => __('整租'), '0' => __('合租')];
    }

    /**
     * 关联房间表
     * @return [type] [description]
     */
    public function room()
    {

        return $this->hasMany('app\index\model\RoomLists', 'house_id');
    }

    /**
     * 关联小区表
     * @return [type] [description]
     */
    public function community()
    {

        return $this->hasOne('CommunityLists', 'id', 'xiaoquID');
    }

    public function getChuzutypeTextAttr($value, $data)
    {
        $value = $value ? $value : $data['chuzutype'];
        $list = $this->getChuzutypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

}
