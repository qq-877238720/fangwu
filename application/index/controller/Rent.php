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

        if ($this->request->isPost()) {
            $houstData = $this->request->post();
            $roomData = $houstData['room'];

            $isxiaoqu = Db::table('house_lists')->where('xiaoquID',$houstData['xiaoquID'])->where('dong',$houstData['dong'])->where('danyuan',$houstData['danyuan'])->where('ceng',$houstData['ceng'])->find();
            if($isxiaoqu){

                return $this->error('添加失败，已存在该房源！请检查。');
            }

            $uuid = "HE".date("YmdHis",time()).mt_rand(10000,99999);

            $house_id = Db::table('house_lists')->insertGetId([
                'uuid'  => $uuid, // 房屋唯一编号
                'xiaoquID' => $houstData['xiaoquID'],
                'xiangmuID' => $houstData['xiangmuID'],
                'cyfs' => $houstData['cyfs'],   // 持有方式
                'chuzutype' => $houstData['clas'],   // 出租方式
                'dong'  => $houstData['dong'],
                'danyuan'  => $houstData['danyuan'],
                'ceng'  => $houstData['ceng'],
                'mianji'  => $houstData['mianji'],
                'fangwutype'  => $houstData['fangwutype'],
                'beizhu'  => $houstData['beizhu'],
                'user_id'  => USER_ID,
            ]);

            $roomListData = [];

            foreach ($roomData as $key => $value) {
                $value['uuid'] = $uuid;
                $value['house_id'] = $house_id;
                array_push($roomListData, $value);
            }

            $room_state = Db::table('room_lists')->insertAll($roomListData);

            return $this->success('添加成功');
        }

        $res = Db::table('fees_config')->field('id, modelName')->where('uid', USER_ID)->select();
        $xiaoqu = Db::table('ho_community_lists')->field('id,communityName')->where('uid',USER_ID)->select();
        $project = Db::table('ho_pro_lists')->field('id,projectName')->where('uid',USER_ID)->select();
        $rent_source = Db::table('ho_rent_source')->field('id,source')->where('uid',USER_ID)->select();

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
