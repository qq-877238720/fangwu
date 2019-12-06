<?php

namespace app\index\model;

use think\Model;

class CommunityLists extends Model
{

    // 表名
    protected $table = 'ho_community_lists';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;

}
