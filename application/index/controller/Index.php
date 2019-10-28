<?php

namespace app\index\controller;

use app\common\controller\Frontend;

class Index extends Frontend
{

    protected $noNeedLogin = ['index'];
    protected $noNeedRight = '*';
    protected $layout = 'layoutname';

    public function _initialize()
    {
        parent::_initialize();
        $auth = $this->auth;
    }

    public function index()
    {
        return $this->view->fetch();
    }

    /**
     * 会员中心
     */
    public function main()
    {

        $this->view->assign('title', __('管理中心'));
        return $this->view->fetch();
    }

    /**
     * dashbord
     */
    public function dashbord()
    {
        $this->view->assign('title', __('面板'));
        return $this->view->fetch();
    }

}
