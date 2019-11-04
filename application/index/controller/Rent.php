<?php
namespace app\index\controller;

use app\common\controller\Frontend;
use think\Config;
use think\Cookie;
use think\Hook;
use think\Session;
use think\Db;
use fast\Random;

class Rent extends Frontend
{
    protected $layout = 'layoutname';
    protected $noNeedLogin = [];
    protected $noNeedRight = ['logout'];

    public function _initialize()
    {
        parent::_initialize();
        define("USER_ID", $this->auth->id);
    }

    /**
     * 添加房源
     */
    public function addHouse()
    {
        $this->view->assign('title', __('添加房源'));


        $res = Db::table('fees_config')->field('id, modelName')->where('uid', USER_ID)->select();
        $xiaoqu = Db::table('ho_community_lists')->field('id,communityName')->where('uid',USER_ID)->select();
        $project = Db::table('ho_pro_lists')->field('id,projectName')->where('uid',USER_ID)->select();
        $rent_source = Db::table('rent_source')->field('id,source')->where('uid',USER_ID)->select();

        $house_state = Db::table('house_state')->field('id,keys')->where('uid',USER_ID)->select();
        
        $listTemplate = Db::table('list_config_template')->field('id, modelName')->where('uid', USER_ID)->select();

        $this->assign("rent_source",$rent_source);
        $this->assign("project",$project);
        $this->assign("xiaoqu",$xiaoqu);
        $this->assign("modelName",$res);
        $this->assign("house_state",$house_state);
        $this->assign("listName",$listTemplate);
        $this->assign("rands",Random::alnum(16));
        return $this->view->fetch();
    }
}
