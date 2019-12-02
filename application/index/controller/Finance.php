<?php
namespace app\index\controller;

use app\common\controller\Frontend;
use think\Config;
use think\Cookie;
use think\Hook;
use think\Session;
use think\Db;
use fast\Random;

class Finance extends Frontend
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
     * 账目核销
     */
    public function hexiao()
    {

        $this->view->assign('title', __('账目核销'));

        return $this->view->fetch();
    }

    /**
     * 流水记账
     */
    public function liushui()
    {

        $this->view->assign('title', __('流水记账'));

        return $this->view->fetch();
    }

    /**
     * 账单查看
     */
    public function zhangdan()
    {

        $this->view->assign('title', __('账单查看'));

        return $this->view->fetch();
    }

    /**
     * 账单记录详情
     */
    public function detail()
    {

        $this->view->assign('title', __('详情'));

        return $this->view->fetch();
    }
}
