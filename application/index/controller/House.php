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

        // fjstatus
        $fjstatus = $this->request->param('roomState') ?? "0";
        // communityId
        $communityId = $this->request->param('communityId') ?? "0";
        // 房型 fangwutype
        $fangwutype = $this->request->param('fangwutype') ?? "0";

        $arr = [];

        if ( $communityId == '0' ) {

            $communityLists = Db::table('ho_community_lists')->where('uid', USER_ID)->select();
        } else {

            $communityLists = Db::table('ho_community_lists')->where('uid', USER_ID)->where('id', $communityId)->select();
        }
        
        foreach ($communityLists as $key => $value) {

            $houseListsModel = (new \app\index\model\HouseLists)->where('user_id', USER_ID)->where('xiaoquID', $value['id']);
    
            if ( $fangwutype == '0' ) {

                $houseLists = $houseListsModel->with('room')->select();
            } else {

                $houseLists = $houseListsModel->where('fangwutype', $fangwutype)->with('room')->select();
            }
            
            $houseLists = collection($houseLists)->toArray();
            
            foreach ($houseLists as $k => &$v) {

                $v['room'] = $this->dealRoom($v['room'], $fjstatus);

                $v['qianfei_state'] = 0;// 不欠费

                // 房屋租金
                $money = 0;
                
                $v['money'] = sprintf("%.2f",$money);

                if(!empty($v['room'])) {
                    foreach ($v['room'] as $room_key => $room_value) {
                        $v['status_text'] = $room_value['status_text'];
                    }
                    // 楼栋分组
                    if ( empty($floorArr[$v['dong']]) ) {
                        if ($fjstatus === '欠费' && $v['qianfei_state'] == 1) {
                            $floorArr[$v['dong']][] = $v;
                        } else if ($fjstatus !== '欠费') {
                            $floorArr[$v['dong']][] = $v;
                        }
                    } else {
                        if ($fjstatus === '欠费' && $v['qianfei_state'] == 1) {
                            array_push($floorArr[$v['dong']], $v);
                        } else if ($fjstatus !== '欠费'){
                            array_push($floorArr[$v['dong']], $v);
                        }
                    }
                }
            }
            
            if ($houseLists) {

                if (!empty($floorArr) ) {

                    $array = [
                        'name' => $value['communityName'],
                        'info' => $floorArr
                    ];

                    array_push($arr, $array);

                    $floorArr = array();
                }
            }
        }

        $this->view->assign('title', __('房源中心'));

        // 小区
        $communityList = Db::table('ho_community_lists')->where('uid', USER_ID)->select();
        $this->assign('communityLists', $communityList);
        // 房型
        $houseStateLists = Db::table('house_state')->where('uid', USER_ID)->select();
        $this->assign('houseStateLists', $houseStateLists);
        $this->assign('fangwutype', $fangwutype);

        $this->assign('communityId', $communityId);
        $this->assign('roomState', $fjstatus);

        $this->assign('info', $arr);

        // echo '<pre>';
        // var_dump($arr);die;

        return $this->view->fetch();
    }


    /**
     * 晒选出需要的room
     * @param  array  $roomArray 房间数组
     * @param  string $fjstatus  需要留下的房间状态,0表示都留下
     * @return [type]            [description]
     */
    protected function dealRoom($roomArray, $fjstatus = 0)
    {

        if ($fjstatus == 0)  return $roomArray;

        foreach ($roomArray as $k => $v) {
            
            if ($v['fjstatus'] != $fjstatus) {
                unset($roomArray[$k]);
            }
        }

        return $roomArray;
    }

}
