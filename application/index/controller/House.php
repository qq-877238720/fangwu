<?php
namespace app\index\controller;

use app\common\controller\Frontend;
use think\Config;
use think\Cookie;
use think\Hook;
use think\Session;
use think\Db;
use fast\Random;

class House extends Frontend
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
     * 房源中心
     */
    public function center()
    {

        $this->view->assign('title', __('房源中心'));

        return $this->view->fetch();
    }
}
