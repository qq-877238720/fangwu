<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use think\Config;
use think\Cookie;
use think\Hook;
use think\Session;
use think\Validate;
use app\common\library\Sms;
use think\Db;

/**
 * 会员中心
 */
class User extends Frontend
{
    protected $layout = 'default';
    protected $noNeedLogin = ['login', 'register', 'third'];
    // protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['logout'];

    public function _initialize()
    {
        parent::_initialize();
        $auth = $this->auth;

        if (!Config::get('fastadmin.usercenter')) {
            $this->error(__('User center already closed'));
        }

        //监听注册登录注销的事件
        Hook::add('user_login_successed', function ($user) use ($auth) {
            $expire = input('post.keeplogin') ? 30 * 86400 : 0;
            Cookie::set('uid', $user->id, $expire);
            Cookie::set('username', $user->username, $expire);
            Cookie::set('company_name', $user->company->company_name, $expire);
            Cookie::set('token', $auth->getToken(), $expire);
        });
        Hook::add('user_register_successed', function ($user) use ($auth) {
            Cookie::set('uid', $user->id);
            Cookie::set('username', $user->username);
            Cookie::set('company_name', $user->company->company_name);
            Cookie::set('token', $auth->getToken());
        });
        Hook::add('user_delete_successed', function ($user) use ($auth) {
            Cookie::delete('uid');
            Cookie::delete('username');
            Cookie::delete('company_name');
            Cookie::delete('token');
        });
        Hook::add('user_logout_successed', function ($user) use ($auth) {
            Cookie::delete('uid');
            Cookie::delete('username');
            Cookie::delete('company_name');
            Cookie::delete('token');
        });
    }

    /**
     * 空的请求
     * @param $name
     * @return mixed
     */
    public function _empty($name)
    {
        $data = Hook::listen("user_request_empty", $name);
        foreach ($data as $index => $datum) {
            $this->view->assign($datum);
        }
        return $this->view->fetch('user/' . $name);
    }

    /**
     * 会员中心
     */
    public function index()
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

    public function addYuanGong()
    {
        $this->view->engine->layout('layout/layoutname');

        if ($this->request->isPost()) {
            $username = $this->request->post('username');
            $password = $this->request->post('password');
            $group_id = $this->request->post('group_id');
            $mobile   = $this->request->post('mobile', '');
            
            $rule = [
                'username'  => 'require|length:3,30',
                'password'  => 'require|length:6,30',
                'mobile'    => 'regex:/^1\d{10}$/',
            ];

            $msg = [
                'username.require' => 'Username can not be empty',
                'username.length'  => 'Username must be 3 to 30 characters',
                'password.require' => 'Password can not be empty',
                'password.length'  => 'Password must be 6 to 30 characters',
                'mobile'           => 'Mobile is incorrect',
            ];
            $data = [
                'username'  => $username,
                'password'  => $password,
                'mobile'    => $mobile,
            ];
            $validate = new Validate($rule, $msg);
            $result = $validate->check($data);
            if (!$result) {
                $this->error(__($validate->getError()), null, ['token' => $this->request->token()]);
            }

            $uesrData = $this->auth->getUserinfo();
            
            if ($this->auth->addYuanGong($username, $password, $group_id, $mobile, ['company_id' => $uesrData['company_id'], 'company_addr' => $uesrData['addr']])) {
                $this->success(__('添加员工成功'));
            } else {
                $this->error($this->auth->getError(), null, ['token' => $this->request->token()]);
            }
        }

        $this->view->engine->layout('layout/layoutname');
        $this->view->assign('title', __('Register'));
        $this->view->assign('groupList', \app\admin\model\UserGroup::column('id,name'));
        return $this->view->fetch('addYuanGong');
    }

    /**
     * 注册会员
     */
    public function register()
    {
        $url = $this->request->request('url', '', 'trim');
        if ($this->auth->id) {
            $this->success(__('You\'ve logged in, do not login again'), $url ? $url : url('user/index'));
        }
        if ($this->request->isPost()) {
            $username = $this->request->post('username');
            $password = $this->request->post('password');
            $email = $this->request->post('email');
            $mobile = $this->request->post('mobile', '');
            $captcha = $this->request->post('captcha1');
			
			$code = $this->request->post('captcha'); // 验证码

            $token = $this->request->post('__token__');
            $rule = [
                'username'  => 'require|length:3,30',
                'password'  => 'require|length:6,30',
                // 'email'     => 'require|email',
                'mobile'    => 'regex:/^1\d{10}$/',
                // 'captcha'   => 'require|captcha',
                '__token__' => 'require|token',
            ];

            $msg = [
                'username.require' => 'Username can not be empty',
                'username.length'  => 'Username must be 3 to 30 characters',
                'password.require' => 'Password can not be empty',
                'password.length'  => 'Password must be 6 to 30 characters',
                'captcha.require'  => 'Captcha can not be empty',
                'captcha.captcha'  => 'Captcha is incorrect',
                'email'            => 'Email is incorrect',
                'mobile'           => 'Mobile is incorrect',
            ];
            $data = [
                'username'  => $username,
                'password'  => $password,
                'email'     => $email,
                'mobile'    => $mobile,
                'captcha'   => $captcha,
                '__token__' => $token,
            ];
            $validate = new Validate($rule, $msg);
            $result = $validate->check($data);
            if (!$result) {
                $this->error(__($validate->getError()), null, ['token' => $this->request->token()]);
            }

			$ret = Sms::check($mobile, $code, 'register');
            if (!$ret) {
                $this->error(__('Captcha is incorrect'));
            }
			Sms::flush($mobile, 'register');

            if ($this->auth->register($username, $password, $email, $mobile, [], $username)) {
                $this->success(__('Sign up successful'), $url ? $url : url('index/index'));
            } else {
                $this->error($this->auth->getError(), null, ['token' => $this->request->token()]);
            }
        }
        //判断来源
        $referer = $this->request->server('HTTP_REFERER');
        if (!$url && (strtolower(parse_url($referer, PHP_URL_HOST)) == strtolower($this->request->host()))
            && !preg_match("/(user\/login|user\/register|user\/logout)/i", $referer)) {
            $url = $referer;
        }
        $this->view->assign('url', $url);
        $this->view->assign('title', __('Register'));
        return $this->view->fetch();
    }

    /**
     * 会员登录
     */
    public function login()
    {
        
        $url = '';
        if ($this->auth->id) {
            // $this->success(__('You\'ve logged in, do not login again'), $url ? $url : url('index/main'));
            $this->redirect('index/main');
        }
        if ($this->request->isPost()) {
            $account = $this->request->post('account');
            $password = $this->request->post('password');
            $keeplogin = (int)$this->request->post('keeplogin');
            $token = $this->request->post('__token__');
            $rule = [
                'account'   => 'require|length:3,50',
                'password'  => 'require|length:6,30',
                '__token__' => 'require|token',
            ];

            $msg = [
                'account.require'  => 'Account can not be empty',
                'account.length'   => 'Account must be 3 to 50 characters',
                'password.require' => 'Password can not be empty',
                'password.length'  => 'Password must be 6 to 30 characters',
            ];
            $data = [
                'account'   => $account,
                'password'  => $password,
                '__token__' => $token,
            ];
            $validate = new Validate($rule, $msg);
            $result = $validate->check($data);
            if (!$result) {
                $this->error(__($validate->getError()), null, ['token' => $this->request->token()]);
                return false;
            }
            if ($this->auth->login($account, $password)) {
                $this->success(__('Logged in successful'), $url ? $url : url('index/main'));
            } else {
                $this->error($this->auth->getError(), null, ['token' => $this->request->token()]);
            }
        }
        //判断来源
        $referer = $this->request->server('HTTP_REFERER');
        if (!$url && (strtolower(parse_url($referer, PHP_URL_HOST)) == strtolower($this->request->host()))
            && !preg_match("/(user\/login|user\/register|user\/logout)/i", $referer)) {
            $url = $referer;
        }
        $this->view->assign('url', $url);
        $this->view->assign('title', __('Login'));
        return $this->view->fetch();
    }

    /**
     * 注销登录
     */
    public function logout()
    {
        //注销本站
        $this->auth->logout();
        $this->success(__('Logout successful'), url('user/login'));
    }

    /**
     * 个人信息
     */
    public function profile()
    {
        
        if($this->request->isPost()){
            $postArr = $this->request->post();
            // var_dump($postArr);die;
            $res = $this->auth->getUser()->where('id', $this->auth->id)->update($postArr);
            if($res){
                $this->success('修改成功');
            }else{
                $this->error('修改失败，请稍后再试');
            }
        }

        $this->assign("user", $this->auth->getUser());
        $this->view->engine->layout(false);
        $this->view->assign('title', __('Profile'));
        return $this->view->fetch();
    }

    /**$this->view->engine->layout('layout/layoutname');
     * 修改密码
     */
    public function changepwd()
    {
        $this->view->engine->layout('layout/layoutname');
        if ($this->request->isPost()) {
            $oldpassword = $this->request->post("oldPassword");
            $newpassword = $this->request->post("newpassword");
            $renewpassword = $this->request->post("renewpassword");
            $token = $this->request->post('__token__');
            $rule = [
                'oldpassword'   => 'require|length:6,30',
                'newpassword'   => 'require|length:6,30',
                'renewpassword' => 'require|length:6,30|confirm:newpassword',
                '__token__'     => 'token',
            ];

            $msg = [
            ];
            $data = [
                'oldpassword'   => $oldpassword,
                'newpassword'   => $newpassword,
                'renewpassword' => $renewpassword,
                '__token__'     => $token,
            ];
            $field = [
                'oldpassword'   => __('Old password'),
                'newpassword'   => __('New password'),
                'renewpassword' => __('Renew password')
            ];
            $validate = new Validate($rule, $msg, $field);
            $result = $validate->check($data);
            if (!$result) {
                $this->error(__($validate->getError()), null, ['token' => $this->request->token()]);
                return false;
            }

            $ret = $this->auth->changepwd($newpassword, $oldpassword);
            if ($ret) {
                $this->success(__('Reset password successful'), url('user/login'));
            } else {
                $this->error($this->auth->getError(), null, ['token' => $this->request->token()]);
            }
        }

        $this->view->engine->layout('layout/layoutname');
        $this->view->assign('title', __('Change password'));
        return $this->view->fetch();
    }

    /**
     * 成员管理
     */
    public function userList()
    {

        $this->view->assign('groupList', \app\admin\model\UserGroup::column('id,name'));
        if (empty($this->request->get('page'))) {

            $this->view->engine->layout('layout/layoutname');
            
            $this->view->assign('title', __('成员列表'));
            return $this->view->fetch();
        }

        $where = [];
        $page = $this->request->get('page', 1);
        $limit = $this->request->get('limit', 10);

        if (!empty($this->request->get('username'))) {
            $where['username'] = ['like','%'.$this->request->get('username').'%'];
        }

        if (!empty($this->request->get('mobile'))) {
            $where['mobile'] = ['like','%'.$this->request->get('mobile').'%'];
        }

        if (!empty($this->request->get('email'))) {
            $where['email'] = ['like','%'.$this->request->get('email').'%'];
        }

        if (!empty($this->request->get('group_id'))) {
            $where['group_id'] = ['=', $this->request->get('group_id')];
        }

        $where['company_id'] = ['=', $this->auth->getUserinfo()['company_id']];

        $total = $this->auth->getUser()
            ->where($where)
            ->count();
        $list = $this->auth->getUser()
            ->where($where)
            ->select();

        return json([
            'code' => 0,
            'count' => $total,
            'data' => $list,
            'msg' => ''
        ]);
    }

    
}
