<?php
namespace app\mobile\controller;

use think\Controller as BaseController;

class Controller extends BaseController
{
    /**
     * 构造方法
     */
    public function __construct()
    {
        
        // 执行父类构造方法
        parent::__construct();

        $this->assign('now', date('Y-m-d H:i'));
        
        $this->initAdminConst();

        // 初始化后台模块信息
        // $this->initAdminInfo();

        // 手动刷新超时时间
        // $sid = $_COOKIE['PHPSESSID'];
        // $expire = time()+config('session.expire');
        // setcookie('PHPSESSID', $sid, $expire,'/');
        
        // 后台控制器钩子
        // Hook::listen('hook_controller_admin_base', $this->request);
    }

     /**
     * 初始化后台模块常量
     */
    final private function initAdminConst()
    {
        
        // 用户ID
        defined('USER_ID')  or  define('USER_ID',     is_login());
        
        // 是否为超级管理员
        // defined('IS_ROOT')      or  define('IS_ROOT',       is_administrator());
    }

    /**
     * 初始化后台模块信息
     */
    final private function initAdminInfo()
    {
        $module = $this->request->module();
        $controller = $this->request->controller();
        $action = $this->request->action();
        if ($controller === 'Index' && $module == 'index' && $action == 'index') {
            return;
        }
        // 验证登录
        !USER_ID && $this->redirect('user/login');
    }
}
