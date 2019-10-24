<?php
namespace app\index\controller;

use app\common\controller\Frontend;
use think\Config;
use think\Cookie;
use think\Hook;
use think\Session;
use think\Db;

class System extends Frontend
{
    protected $layout = 'default';
    protected $noNeedLogin = ['login', 'register', 'third'];
    protected $noNeedRight = ['logout'];

    const USER_ID = '';

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
            Cookie::set('token', $auth->getToken(), $expire);

            static::USER_ID = $user->id;
        });
        Hook::add('user_register_successed', function ($user) use ($auth) {
            Cookie::set('uid', $user->id);
            Cookie::set('token', $auth->getToken());
        });
        Hook::add('user_delete_successed', function ($user) use ($auth) {
            Cookie::delete('uid');
            Cookie::delete('token');
        });
        Hook::add('user_logout_successed', function ($user) use ($auth) {
            Cookie::delete('uid');
            Cookie::delete('token');
        });
    }

    // 房源配置
    public function housesetting()
    {

        $communityLists = Db::table('community_lists')->field('communityName,id')->where('uid',USER_ID)->select();
        $proLists = Db::table('pro_lists')->field('ProjectName,id')->where('uid',USER_ID)->select();
        
        $this->assign('communityName', $communityLists);
        $this->assign('projectName', $proLists);

        return view();
    }

    // start -===== 小区
    /**
     * 添加小区
     */
    public function addCommunity()
    {

        $area = $this->request->post('area');
        $city = $this->request->post('city');
        $prov = $this->request->post('prov');

        $address = [
            'area' => ['name' => $area['name'], 'value' => $area['value']],
            'city' => ['name' => $city['name'], 'value' => $city['value']],
            'prov' => ['name' => $prov['name'], 'value' => $prov['value']],
        ];
        // 小区名称
        $communityName = $this->request->post('communityName');
        // 小区详细地址
        $detailAddr = $this->request->post('detailAddr');

        $res = Db::table('community_lists')->insert([
            'uid'  => USER_ID,
            'address' => json_encode($address),
            'communityName' => $communityName,
            'detailAddr' => $detailAddr,
        ]);

        return $res ? json(['code' =>200, 'msg' => '添加'.$communityName.'成功']) : json(['code' =>400, 'msg' => '添加失败']);
    }

    public function delCommunity()
    {

        $id = $this->request->post('id');

        Db::table('community_lists')->where('uid', USER_ID)->where('id', $id)->delete();

        return json(['code' =>200, 'msg' => '删除成功'.$id]);
    }
    // end -===== 小区


    /**
     * 添加房源-项目
     */
    // start -===== 项目
    public function addProject()
    {

        $projectName = $this->request->post('projectName');

        $res = Db::table('pro_lists')->insert([
            'uid'  => USER_ID,
            'ProjectName' => $projectName,
        ]);

        return json(['code' =>200, 'msg' => '添加'.$projectName.'成功']);
    }

    public function delproject()
    {

        $id = $this->request->post('id');
        Db::table('pro_lists')->where('uid', USER_ID)->where('id', $id)->delete();
        return json(['code' =>200, 'msg' => '删除成功']);
    }
    // end -===== 项目


    // start -===== 费用模板
    // 费用模板配置
    public function costtemplatesetting()
    {

        $res = Db::table('fees_config')->field('modelName,id')->where('uid',USER_ID)->select();

        $this->assign('modelName', $res);

        return view();
    }

    public function delTeamplate()
    {

        $id = $this->request->post('id');

        Db::table('fees_config')->where('uid', USER_ID)->where('id', $id)->delete();

        return json(['code' =>200, 'msg' => '删除成功']);
    }

    public function addCostTemplateSetting()
    {
        if ($this->request->isPost()) {

            $data = $this->request->param();
            // todo 验证
            if (empty($data['afixFees'])) {
                return json(['code' =>400, 'msg' => '费用不能为空']);
            }

            // if (empty($data['computedFees'])) {
            //     return json(['code' =>400, 'msg' => '计量费用不能为空']);
            // }

            $afixFees = $data['afixFees'];
            $computedFees = [];
            $modelName = $data['modelName'];
            $id = USER_ID;
            $arr = [
                'afixFees'     => $afixFees,
                'computedFees' => $computedFees
            ];

            $res = Db::table('fees_config')->insert([
                'uid'       => $id,
                'model'     => json_encode($arr),
                'modelName' => $modelName
            ]);

            return $res ? json(['code' =>200, 'msg' => $arr]) :json($arr);
        }

        return view('add_template');
    }


    public function editCostTemplateSetting()
    {

        if ($this->request->isPost()) {

            $data = $this->request->param();
            // todo 验证
            if (empty($data['afixFees'])) {
                return json(['code' =>400, 'msg' => '费用不能为空']);
            }


            $afixFees = $data['afixFees'];
            $computedFees = [];
            $modelName = $data['modelName'];
            $id = USER_ID;
            $arr = [
                'afixFees'     => $afixFees,
                'computedFees' => $computedFees
            ];

            $res = Db::table('fees_config')->where('id', $data['id'])->update([
                'model'     => json_encode($arr),
                'modelName' => $modelName
            ]);

            return $res ? json(['code' =>200, 'msg' => $arr]) :json($arr);
        }

        // 费用
        $feesConfig = Db::table('fees_config')->field('id, modelName,model')->where('id', $this->request->get('id'))->find();
        $result = [
            'modelName' => $feesConfig['modelName'],
            'model' => json_decode($feesConfig['model'], true),
            'id'    => $feesConfig['id'],
        ];
        // echo "<pre>";
        // var_dump($result);die;
        $this->assign('info', $result);
        return view('detail_template');
    }

    // end -===== 费用模板

    // 租客来源配置
    public function TenantsSetting()
    {

        $res = Db::table('rent_source')->field('source,id')->where('uid',USER_ID)->select();

        $res2 = Db::table('house_state')->field('keys,id')->where('uid',USER_ID)->select();

        $this->assign('rentSourceName', $res);

        $this->assign('keysName', $res2);

        return view('rent_source_setting');
    }

    public function addRentSource()
    {

        $sourceName = $this->request->post('sourceName');

        $res = Db::table('rent_source')->insert([
            'uid'  => USER_ID,
            'source' => $sourceName,
        ]);

        return json(['code' =>200, 'msg' => '添加'.$sourceName.'成功']);
    }

    public function delRentSource()
    {

        $id = $this->request->post('id');
        Db::table('rent_source')->where('uid', USER_ID)->where('id', $id)->delete();
        return json(['code' =>200, 'msg' => '删除成功']);
    }

    public function addRentHouse()
    {

        $keysName = $this->request->post('keysName');
        $isres = Db::table('house_state')->where('uid',USER_ID)->where('keys',$keysName)->find();
        if($isres){
            return json(['code' =>0, 'msg' => '已存在改房屋类型，请检查']);
        }
        $res = Db::table('house_state')->insert([
            'uid'  => USER_ID,
            'keys' => $keysName,
        ]);

        return json(['code' =>200, 'msg' => '添加'.$keysName.'成功']);
    }

    public function delHouseState()
    {

        $id = $this->request->post('id');
        Db::table('house_state')->where('uid', USER_ID)->where('id', $id)->delete();
        return json(['code' =>200, 'msg' => '删除成功']);
    }



    // start -===== 入住结算清单模板
    // 入住结算清单模板
    public function listTemplateSetting()
    {

        $res = Db::table('list_config_template')->field('modelName,id')->where('uid',USER_ID)->select();

        $this->assign('modelName', $res);

        return view();
    }

    public function delListTeamplate()
    {

        $id = $this->request->post('id');

        Db::table('list_config_template')->where('uid', USER_ID)->where('id', $id)->delete();

        return json(['code' =>200, 'msg' => '删除成功']);
    }

    public function addListTemplateSetting()
    {
        if ($this->request->isPost()) {

            $data = $this->request->param();
            // todo 验证
            if (empty($data['afixFees'])) {
                return json(['code' =>400, 'msg' => '清单项值不能为空']);
            }

            // if (empty($data['computedFees'])) {
            //     return json(['code' =>400, 'msg' => '计量费用不能为空']);
            // }

            $afixFees = $data['afixFees'];
            $computedFees = [];
            $modelName = $data['modelName'];
            $id = USER_ID;
            $arr = [
                'afixFees'     => $afixFees,
                'computedFees' => $computedFees
            ];

            $res = Db::table('list_config_template')->insert([
                'uid'       => $id,
                'model'     => json_encode($arr),
                'modelName' => $modelName
            ]);

            return $res ? json(['code' =>200, 'msg' => $arr]) :json($arr);
        }

        return view('add_template');
    }

    // end -===== 入住结算清单模板
}
