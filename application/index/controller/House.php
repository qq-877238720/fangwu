<?php
namespace app\index\controller;

use app\common\controller\Frontend;
use think\Config;
use think\Cookie;
use think\Hook;
use think\Session;
use think\Db;
use Endroid\QrCode\QrCode;
use fast\Random;
use app\common\services\IDCardOCR;
use think\Response;

class House extends Frontend
{
    protected $layout = 'layoutname';
    protected $noNeedLogin = ['*'];
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

            // 判断房屋的类型 1整租 0合租
            // 整租，需要把room的状态提取到 house 下, 处理周期，需要根据 uuid 查询到 rent 再去周期表
            // 合租，循环房屋表, 处理周期，需要根据 room_id 查询到 rent 再去周期表
            
            foreach ($houseLists as $k => &$v) {
                if ($fjstatus !== '欠费'){
                    $v['room'] = $this->dealRoom($v['room'], $fjstatus);
                }

                $v['qianfei_state'] = 0;// 不欠费
                if ($v['chuzutype'] == 1) {

                    foreach ($v['room'] as $room_key => &$room_value) {
                        $v['status_text'] = $room_value['status_text'];
                    }

                    $rent = Db::table('ho_rent_lists')->where('uuid', $v['uuid'])->where('rentstatus','在租')->find();
                    $paymentCycle = Db::table('ho_payment_cycle')->where('rent_id', $rent['rent_id'])->find();

                    if ($paymentCycle) {
                        
                        $paymentCycle['payment_cycle_time'] = json_decode($paymentCycle['payment_cycle_time'], true);
                        for ($i = 0; $i < count($paymentCycle['payment_cycle_time']); $i++) {
                            if ($paymentCycle['payment_cycle_time'][$i]['paymentJson']['jiaofei_state'] == '已缴费') {
                                if ( !empty($paymentCycle['payment_cycle_time'][$i+1]) && ($paymentCycle['payment_cycle_time'][$i+1]['paymentJson']['jiaofei_state'] == '' || $paymentCycle['payment_cycle_time'][$i+1]['paymentJson']['jiaofei_state'] == '未缴费') ) {

                                    $times = strtotime($paymentCycle['payment_cycle_time'][$i+1]['paymentJson']['paydate_start']) - time();
                                    $diff  = floor($times/3600/24);

                                    if ($diff <= 7) {
                                        $v['diff'] = $diff;
                                    }
                                }
                                // if ( $paymentCycle['payment_cycle_time'][$i]['paymentJson']['paydate_start'] <= date('Y-m-d') ) {
                                //     $v['qianfei_state'] = 1; // 欠费

                                // }
                            } else {
                                if ($i == 0 && ($paymentCycle['payment_cycle_time'][$i]['paymentJson']['jiaofei_state'] == '' || $paymentCycle['payment_cycle_time'][$i]['paymentJson']['jiaofei_state'] == '未缴费')) {
                                    $times = strtotime($paymentCycle['payment_cycle_time'][$i]['paymentJson']['paydate_start']) - time();
                                    $diff  = floor($times/3600/24);
                                    
                                    if ($diff <= 7) {
                                        $v['diff'] = $diff;
                                    }
                                }
                                if ( $paymentCycle['payment_cycle_time'][$i]['paymentJson']['paydate_start'] <= date('Y-m-d') ) {
                                    $v['qianfei_state'] = 1; // 欠费

                                }
                                break;
                            }
                        }
                    }

                    // 房屋租金
                    $money = 0;
                    if ( $rent['rentstatus'] === '在租') {

                        $fees = json_decode($rent['feemodeljson'], true);
                        foreach ($fees['afixFees'] as $afixV) {
                            $money += $afixV['money'];
                        }
                        
                    } else {

                    }
                    
                    $v['money'] = sprintf("%.2f",$money);

                } else {
                    foreach ($v['room'] as $room_key => &$room_value) {

                        $v['status_text'] = $room_value['status_text'];
                        $room_value['qianfei_state'] = 0;

                        $rent = Db::table('ho_rent_lists')->where('room_id', $room_value['room_id'])->where('rentstatus','在租')->find();
                        $paymentCycle = Db::table('ho_payment_cycle')->where('rent_id', $rent['rent_id'])->find();

                        if ($paymentCycle) {
                            
                            $paymentCycle['payment_cycle_time'] = json_decode($paymentCycle['payment_cycle_time'], true);
                            for ($i = 0; $i < count($paymentCycle['payment_cycle_time']); $i++) {
                                if ($paymentCycle['payment_cycle_time'][$i]['paymentJson']['jiaofei_state'] == '已缴费') {
                                    if ( !empty($paymentCycle['payment_cycle_time'][$i+1]) && ($paymentCycle['payment_cycle_time'][$i+1]['paymentJson']['jiaofei_state'] == '' || $paymentCycle['payment_cycle_time'][$i+1]['paymentJson']['jiaofei_state'] == '未缴费') ) {

                                        $times = strtotime($paymentCycle['payment_cycle_time'][$i+1]['paymentJson']['paydate_start']) - time();
                                        $diff  = floor($times/3600/24);

                                        if ($diff <= 7) {
                                            $room_value['diff'] = $diff;
                                        }
                                    }

                                } else {
                                    if ($i == 0 && ($paymentCycle['payment_cycle_time'][$i]['paymentJson']['jiaofei_state'] == '' || $paymentCycle['payment_cycle_time'][$i]['paymentJson']['jiaofei_state'] == '未缴费')) {

                                        $times = strtotime($paymentCycle['payment_cycle_time'][$i]['paymentJson']['paydate_start']) - time();
                                        $diff  = floor($times/3600/24);
                                        
                                        if ($diff <= 7) {
                                            $room_value['diff'] = $diff;
                                        }
                                    }
                                    if ( $paymentCycle['payment_cycle_time'][$i]['paymentJson']['paydate_start'] <= date('Y-m-d') ) {
                                        $room_value['qianfei_state'] = 1; // 欠费
                                    }
                                    break;
                                }
                            }
                        }

                        // 处理 房源下的房间 的欠费状态
                        if ($fjstatus === '欠费' && $room_value['qianfei_state'] == 1) {
                            $floorArr[$v['dong']][] = $v;
                        } else if ($fjstatus !== '欠费'){
                            
                        }else {
                            unset($v['room'][$room_key]);
                        }

                        // 房屋租金
                        $money = 0;
                        if ( $rent['rentstatus'] === '在租') {

                            $fees = json_decode($rent['feemodeljson'], true);
                            foreach ( $fees['afixFees'] as $afixV) {
                                $money += $afixV['money'];
                            }
                            
                        } else {

                        }
                        
                        $room_value['money'] = sprintf("%.2f",$money);
                    }

                }

                if(!empty($v['room'])) {
                    
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

    /**
     * 房源小详情
     * @return [type] [description]
     */
    public function detail()
    {
        $uuid = $this->request->get('uuid');
        $roomId = $this->request->get('room_id');

        $houseResult = (new \app\index\model\HouseLists)->where('user_id', USER_ID)->where('uuid', $uuid)->with('community')->find();

        if ($roomId == "0") {

            $roomListsModel = (new \app\index\model\RoomLists)->where('uuid', $uuid)->find(); // 整租
            $rent = Db::table('ho_rent_lists')
                    ->alias('r')
                    ->where('r.uuid', $uuid)
                    ->where('r.rentstatus','在租')
                    ->join('payment_cycle p','r.rent_id = p.rent_id')
                    ->find();
        } else {

            $roomListsModel = (new \app\index\model\RoomLists)->where('room_id', $roomId)->find(); // 合租
            $rent = Db::table('ho_rent_lists')
                    ->alias('r')
                    ->where('r.room_id', $roomId)
                    ->where('r.rentstatus','在租')
                    ->join('payment_cycle p','r.rent_id = p.rent_id')
                    ->find();
        }

        // 房屋租金
        $money = 0;
        if (!empty($rent)) {
            $fees = json_decode($rent['feemodeljson'], true);
            foreach ($fees['afixFees'] as $afixV) {
                $money += $afixV['money'];
            }
        }

        // 租客付款周期表
        // if ($roomId == "0") {
        //     $rentRes = Db::table('ho_rent_lists')->where('uuid', $uuid)->where('rentstatus','在租')->find(); // 整租
        //     $paymentCycle = Db::table('ho_payment_cycle')->where('rent_id', $rentRes['rent_id'])->find();
        // } else {

        //     $rentRes = Db::table('ho_rent_lists')->where('room_id', $roomId)->where('rentstatus','在租')->find();
        //     $paymentCycle = Db::table('ho_payment_cycle')->where('rent_id', $rentRes['rent_id'])->find();
        // }
        // echo "<pre>";
        // var_dump($rent);die;
        $key = null;
        if (!empty($rent)) {
            foreach(json_decode($rent['payment_cycle_time'], true) as $k=>$vo):
                if ($vo['paymentJson']['jiaofei_state'] == '已缴费') {
                    continue;
                } else {
                    $key = $k;
                    break;
                }
            endforeach;
        }
        
        $result = [];

        // if ($houseResult['chuzutype'] == 0) {

            $result = [
                'status_text' => $roomListsModel['status_text'],
                'uuid'        => $roomListsModel['uuid'],
                'fjarea'      => $roomListsModel['fjarea'],
                'beizhu'      => $roomListsModel['beizhu'],
                'room_id'     => $roomId,
                'money'       => sprintf("%.2f",$money),
                'payment_weeks'=> $rent['payment_weeks'],
                'start_time'       => $rent['payment_cycle_start_time'],
                'end_time'       => $rent['payment_cycle_end_time'],
                'key'         => $key,
            ];
        // }

        $this->assign('info', $result);

        return view();
    }

    /**
     * 租约详情
     * @return [type] [description]
     */
    public function editHousedetail()
    {

        if ( !empty($this->request->param('watch')) ) {
            $this->assign('watch', 1);
        }

        $uuid = $this->request->param('uuid');
        $roomId = $this->request->get('room_id');

        $houseResult = (new \app\index\model\HouseLists)->where('user_id', USER_ID)->where('uuid', $uuid)->with('community')->find();

        if ($roomId == "0") {

            $roomListsModel = (new \app\index\model\RoomLists)->where('uuid', $uuid)->find(); // 整租
            $rent = Db::table('ho_rent_lists')
                    ->alias('r')
                    ->where('r.uuid', $uuid)
                    ->where('r.rentstatus','在租')
                    ->join('payment_cycle p','r.rent_id = p.rent_id')
                    ->join('rent_source rs', 'rs.id = r.laiyuan')
                    ->find();
        } else {

            $roomListsModel = (new \app\index\model\RoomLists)->where('room_id', $roomId)->find(); // 合租
            $rent = Db::table('ho_rent_lists')
                    ->alias('r')
                    ->where('r.room_id', $roomId)
                    ->where('r.rentstatus','在租')
                    ->join('payment_cycle p','r.rent_id = p.rent_id')
                    ->join('rent_source rs', 'rs.id = r.laiyuan')
                    ->find();
        }

        $result = [
            'uuid'        => $uuid,
            'room_id'     => $roomId,

            'cyfs'        => $houseResult['cyfs'],
            'communityName' => $houseResult['community']['communityName'],
            'dong'        => $houseResult['dong'],
            'ceng'        => $houseResult['ceng'],
            'fangwutype'  => $houseResult['fangwutype'],

            'status_text' => $roomListsModel['status_text'],
            'fjarea'      => $roomListsModel['fjarea'],
            'beizhu'      => $roomListsModel['beizhu'],
            'room_id'     => $roomId,

            'rentSource'  => $rent['source'],
            // 'money'       => sprintf("%.2f",$money),
            'start_time'       => $rent['payment_cycle_start_time'],
            'end_time'       => $rent['payment_cycle_end_time'],
            'payment_cycle_time' => $rent['payment_cycle_time']
        ];

        // 退租
        // $tuizu = Db::table('tuizu')->field('ruzhu_at, tuizu_at')->where('house_uuid', $this->request->param('uuid'))->order('id asc')->select();

        if ($result['status_text'] == '预定') {
            $this->assign('rent_user', Db::table('ho_rent_lists')->where('uuid', $uuid)->where('rentstatus', '预定')->select());
        }

        if ($result['status_text'] == '在租') {
            $this->assign('rent_user', Db::table('ho_rent_lists')->where('uuid', $uuid)->where('rentstatus', '在租')->select());
        }
        
        // $this->assign('tuizu', $tuizu);
        // $this->assign('rentSource', $rentSource);
        // $this->assign('paymentCycle', $paymentCycle);
        // $this->assign('communityName', $communityName);
        
        $this->assign('info', $result);

        return view();


        $uuid = $this->request->get('uuid');
        // 费用模板
        $feesConfig = Db::table('fees_config')->field('id, modelName')->where('uid', USER_ID)->select();
        // 租客来源
        $rent_source = Db::table('ho_rent_source')->field('id,source')->where('uid',USER_ID)->select();
        // 入住清单：包含固定资产内容和数量及水、气初始度数，电卡余额
        $listTemplate = Db::table('list_config_template')->field('id, modelName')->where('uid', USER_ID)->select();

        $this->assign('uuid', $uuid);
        $this->assign('info', (new \app\index\model\HouseLists)->where('user_id', USER_ID)->where('uuid', $uuid)->with('room')->find());
        $this->assign("rent_source",$rent_source);
        $this->assign("modelName",$feesConfig);
        $this->assign("listName",$listTemplate);
        return view();
    }


    /**
     * 查看房源预定信息
     * @return [type] [description]
     */
    public function chakanYd()
    {

        $uuid = $this->request->get('uuid');
        $roomId = $this->request->get('room_id');

        $houseResult = (new \app\index\model\HouseLists)->where('user_id', USER_ID)->where('uuid', $uuid)->with('community')->find();

        if ($roomId == "0") {

            $roomListsModel = (new \app\index\model\RoomLists)->where('uuid', $uuid)->find(); // 整租
            $rent = Db::table('ho_rent_lists')
                    ->alias('r')
                    ->where('r.uuid', $uuid)
                    ->where('r.rentstatus','预定')
                    ->join('rent_source rs', 'rs.id = r.laiyuan')
                    ->find();
        } else {

            $roomListsModel = (new \app\index\model\RoomLists)->where('room_id', $roomId)->find(); // 合租
            $rent = Db::table('ho_rent_lists')
                    ->alias('r')
                    ->where('r.room_id', $roomId)
                    ->where('r.rentstatus','预定')
                    ->join('rent_source rs', 'rs.id = r.laiyuan')
                    ->find();
        }

        $result = [
            'uuid' => $uuid,
            // 'room_id'     => $roomId,

            'cyfs'        => $houseResult['cyfs'],
            'communityName' => $houseResult['community']['communityName'],
            'dong'        => $houseResult['dong'],
            'ceng'        => $houseResult['ceng'],
            'fangwutype'  => $houseResult['fangwutype'],

            'rentSource'  => $rent['source'],
            'yudingpri'   => $rent['dingjinprice'], // 预定金
            'createtime'  => $rent['createtime'],  // 预定时间
        ];


        $this->assign('rent_user', Db::table('ho_rent_lists')->where('uuid', $uuid)->where('rentstatus', '预定')->select());

        $this->assign('info', $result);
        return view();
    }

    /**
     * 预定到 确认入住
     * @return [type] [description]
     */
    public function querenRZ()
    {

        if ($this->request->isPost()) {
            

            $postArr = $this->request->post();

            $houseResult = (new \app\index\model\HouseLists)->where('user_id', USER_ID)->where('uuid', $postArr['uuid'])->find();
            
            if ($postArr['room_id'] == "0") {
                $roomListsModel = (new \app\index\model\RoomLists)->where('uuid', $postArr['uuid'])->find();
            } else {
                $roomListsModel = (new \app\index\model\RoomLists)->where('room_id', $postArr['room_id'])->find();
            }

            if($roomListsModel['fjstatus'] != 13) {
                return;
            }

            // 判断租约时间
            if(strtotime($postArr['startimes']) > strtotime($postArr['endtimes'])) {
                $msg = ['code'=>0,'data'=>"",'msg'=>"添加失败,请检查租赁时间",'url'=>""];
                return json_encode($msg);
            }

            // 1.添加租客信息
            $userArr = [
                'room_id'   => $postArr['room_id'],
                'uuid'      => $postArr['uuid'],
                'laiyuan'   => $postArr['userorigin'],
                'user_id'   => USER_ID
            ];
            
            $userArr['yajin'] = $postArr['deposit'];  // 押金

            $userlist = $postArr['user'];

            $imgArr = array ( 
                array ( 
                    'tag'=>'FRONT',
                    'url'=>$postArr['chengzhuren_zhengmian']
                ), array  ( 
                    'tag'=>'BACK',
                    'url'=>$postArr['chengzhuren_fanmian']
                )
            );
            
             //费用模板
            if(!isset($postArr['model'])){
                $userArr['feemodeljson'] = "";
            }else{
                $postArr['model']['computedFees'] = [];
                $userArr['feemodeljson'] = json_encode($postArr['model']); //费用配置
            }

            if(!isset($postArr['listtemplate'])){
                $userArr['qingdanmodeljson'] = "";
            }else{
                $userArr['qingdanmodeljson'] = json_encode($postArr['listtemplate']); //清单配置
            }


            for ($i=0; $i < count($userlist); $i++) {

                $userArr['xingming'] = $userlist[$i]['username'];
                $userArr['sex'] = $userlist[$i]['sex'];//租客性别 1： 男 2 女
                $userArr['phone'] = $userlist[$i]['userphone'];
                $userArr['cardtype'] = $userlist[$i]['cardtype'];
                if ( isset($userlist[$i]['usercard']) ) {
                    $userArr['card'] = $userlist[$i]['usercard'];
                }

                // if ( isset($userlist[$i]['huzhao']) ) {
                //     $userArr['huzhao'] = $userlist[$i]['huzhao'];//租客护照号
                // }

                $userArr['rentstatus'] = "在租";
                $userArr['createtime'] = time();

                if($i==0){
                    $userArr['chengzurentag'] = 1;
                    $userArr['headimgjson'] = json_encode($imgArr);//租客证件照片 
                    $userArr['nation'] = $userlist[$i]['nation'];//籍贯
                    $userArr['address'] = $userlist[$i]['address'];//籍贯地址
                    // $userArr['validdate'] = $userlist[$i]['validdate'];//证件有效期
                    // $userArr['authority'] = $userlist[$i]['authority'];//签发机关
                }else{
                    $userArr['chengzurentag'] = 0;  // 是否为承租人
                    $userArr['headimgjson'] = "";//租客证件照片
                    $userArr['nation'] = "";//籍贯
                    $userArr['address'] = "";//籍贯地址
                    // $userArr['validdate'] = "";//证件有效期
                    // $userArr['authority'] = "";//签发机关
                }

                if ($i == 0) {
                    $rent_id = Db::table('ho_rent_lists')->where('rent_id', $userlist[$i]['rent_id'])->update($userArr);
                } else {
                    $rent_id = Db::table('ho_rent_lists')->insertGetId($userArr);
                }
                
            }
            //租客信息end
            
            // 2.添加周期
            $paymentArr = [
                'rent_id'   => $userlist[0]['rent_id'],
                'uuid'      => $postArr['uuid'],
                'payment_weeks' => $postArr['paymenttimes']
            ];

            //===========区分是在租还是预定==========
            if($postArr['housestate'] == "预定"){
                $paymentArr['payment_cycle_start_time'] = ""; //租赁开始时间
                $paymentArr['payment_cycle_end_time'] = ""; //租赁结束时间
                // $paymentArr['paymentWeeks'] = ""; //付款周期
                $paymentArr['payment_cycle_time'] = "";//付款周期
            }else{
                $paymentArr['payment_cycle_start_time'] = strtotime($postArr['startimes']); //租赁开始时间
                $paymentArr['payment_cycle_end_time'] = strtotime($postArr['endtimes']); //租赁结束时间
                // $paymentArr['paymentWeeks'] = $postArr['paymenttimes']; // 付款周期

                $qujian = $this->get_ld_times($postArr['startimes'],$postArr['endtimes'],$postArr['paymenttimes']);
                $paymentJson_data = [];

                for ($i=0; $i < count($qujian); $i++) { 
                    list($paydate_start, $paydate_end)  = explode(",", $qujian[$i]);
                    $paymentJson = array(
                        'paydate_start'     => $paydate_start, // 付款开始时间
                        'paydate_end'       => $paydate_end, // 付款结束时间
                        'rebate'            => "", //优惠金额
                        'rent_price'        => "", //实际租金
                        'jiaofei_state'     => "", //缴费状态
                        'skfs'              => "", //收款方式
                        'paymentWeeks'      => $postArr['paymenttimes'], //付款模式
                        'beizhu'            => "", //备注
                    );
                    $paymentJson_data[$i]['paymentJson'] = $paymentJson;
                    $paymentJson_data[$i]['paymentModel'] = isset($postArr['model'])?$postArr['model']:'';
                }
                $paymentArr['payment_cycle_time'] = json_encode($paymentJson_data);//付款周期
            }
            //===========end 区分是在租还是预定==========
            
            $respaydate = Db::table('ho_payment_cycle')->where('rent_id', $userlist[0]['rent_id'])->update($paymentArr);

            // 3, 更新房屋状态
            $statusNum = (new \app\index\model\RoomLists)->getFjstatusNum($postArr['housestate']);
            
            if ($postArr['room_id'] == "0") {
                $res = Db::table('ho_room_lists')->where('uuid', $postArr['uuid'])->update([
                    'fjstatus' => $statusNum
                ]);

            } else {
                $res = Db::table('ho_room_lists')->where('room_id', $postArr['room_id'])->update([
                    'fjstatus' => $statusNum
                ]);

            }

            if($res){
                $msg = ['code'=>1,'data'=>['uuid' => $postArr['uuid'], 'room_id' => $postArr['room_id']],'msg'=>"添加成功",'url'=>""];
                return json_encode($msg);
            }else{
                $msg = ['code'=>0,'data'=>"",'msg'=>"添加失败",'url'=>""];
                return json_encode($msg);
            }
        }


        $result = [
            'room_id' => $this->request->get('room_id'),
            'uuid'    => $this->request->get('uuid')
        ];

        $houseResult = (new \app\index\model\HouseLists)->where('user_id', USER_ID)->where('uuid', $result['uuid'])->with('community')->find();

        if ($result['room_id'] == "0") {

            $roomListsModel = (new \app\index\model\RoomLists)->where('uuid', $result['uuid'])->find(); // 整租
            $rent = Db::table('ho_rent_lists')
                    ->alias('r')
                    ->where('r.uuid', $result['uuid'])
                    ->where('r.rentstatus','预定')
                    ->join('rent_source rs', 'rs.id = r.laiyuan')
                    ->find();
        } else {

            $roomListsModel = (new \app\index\model\RoomLists)->where('room_id', $result['room_id'])->find(); // 合租
            $rent = Db::table('ho_rent_lists')
                    ->alias('r')
                    ->where('r.room_id', $result['room_id'])
                    ->where('r.rentstatus','预定')
                    ->join('rent_source rs', 'rs.id = r.laiyuan')
                    ->find();
        }

        $result = array_merge($result, [
            'xingming' => $rent['xingming'],
            'sex'      => $rent['sex'],
            'phone'    => $rent['phone'],
            'cardtype'      => $rent['cardtype'],
            'card'      => $rent['card'],
            'nation'      => $rent['nation'],
            'address'      => $rent['address'],
            'rent_id'      => $rent['rent_id'],
        ]);

        // 费用模板
        $feesConfig = Db::table('fees_config')->field('id, modelName')->where('uid', USER_ID)->select();
        // 租客来源
        $rent_source = Db::table('ho_rent_source')->field('id,source')->where('uid',USER_ID)->select();
        // 入住清单：包含固定资产内容和数量及水、气初始度数，电卡余额
        $listTemplate = Db::table('list_config_template')->field('id, modelName')->where('uid', USER_ID)->select();

        $this->assign("rent_source",$rent_source);
        $this->assign("modelName",$feesConfig);
        $this->assign("listName",$listTemplate);
        $this->assign("info", $result);
        $this->assign("rands",Random::alnum(16));
        return view('queren_rz');
    }

    /**
     * 添加用户房源预定信息 
     * 
     * （添加预定暂未使用，直接在租客登记处操作）
     * 
     * @return [type] [description]
     */
    public function addYd()
    {
        $uuid = $this->request->get('uuid');
        $this->assign('uuid', $uuid);
        return view();
    }

    /**
     * 添加新租客(入住)
     * @return [type] [description]
     */
    public function addRent()
    {

        if ($this->request->isPost()) {

            $postArr = $this->request->post();

            $houseResult = (new \app\index\model\HouseLists)->where('user_id', USER_ID)->where('uuid', $postArr['uuid'])->find();

            if ($postArr['room_id'] == "0") {
                $roomListsModel = (new \app\index\model\RoomLists)->where('uuid', $postArr['uuid'])->find();
                $renttype = '整租';
            } else {
                $roomListsModel = (new \app\index\model\RoomLists)->where('room_id', $postArr['room_id'])->find();
                $renttype = '合租';
            }

            if($roomListsModel['fjstatus'] != 11) {
                return;
            }

            // 判断租约时间
            if(strtotime($postArr['startimes']) > strtotime($postArr['endtimes'])) {
                $msg = ['code'=>0,'data'=>"",'msg'=>"添加失败,请检查租赁时间",'url'=>""];
                return json_encode($msg);
            }

            // 1.添加租客信息
            $userArr = [
                'room_id'   => $postArr['room_id'],
                'uuid'      => $postArr['uuid'],
                'laiyuan'   => $postArr['userorigin'],
                'user_id'   => USER_ID
            ];
            if($postArr['housestate'] == "预定"){
                $userArr['dingjinprice'] = $postArr['yudingpri'];  // 预定金
            }else{
                $userArr['yajin'] = $postArr['deposit'];  // 押金
            }

            
            $userlist = $postArr['user'];

            $imgArr = array ( 
                array ( 
                    'tag'=>'FRONT',
                    'url'=>$postArr['chengzhuren_zhengmian']
                ), array  ( 
                    'tag'=>'BACK',
                    'url'=>$postArr['chengzhuren_fanmian']
                )
            );

             //费用模板
            if(!isset($postArr['model'])){
                $userArr['feemodeljson'] = "";
            }else{
                $postArr['model']['computedFees'] = [];
                $userArr['feemodeljson'] = json_encode($postArr['model']); //费用配置
            }

            if(!isset($postArr['listtemplate'])){
                $userArr['qingdanmodeljson'] = "";
            }else{
                $userArr['qingdanmodeljson'] = json_encode($postArr['listtemplate']); //清单配置
            }


            for ($i=0; $i < count($userlist); $i++) {

                $userArr['xingming'] = $userlist[$i]['username'];
                $userArr['sex'] = $userlist[$i]['sex'];//租客性别 1： 男 2 女
                $userArr['phone'] = $userlist[$i]['userphone'];
                $userArr['cardtype'] = $userlist[$i]['cardtype'];
                if ( isset($userlist[$i]['usercard']) ) {
                    $userArr['card'] = $userlist[$i]['usercard'];
                }

                // if ( isset($userlist[$i]['huzhao']) ) {
                //     $userArr['huzhao'] = $userlist[$i]['huzhao'];//租客护照号
                // }

                if($postArr['housestate'] == "在租"){
                    $userArr['rentstatus'] = "在租";
                }else if($postArr['housestate'] == "预定"){
                    $userArr['rentstatus'] = "预定";
                }else{
                    $userArr['rentstatus'] = "";
                }

                $userArr['createtime'] = time();

                if($i==0){
                    $userArr['chengzurentag'] = 1;
                    $userArr['headimgjson'] = json_encode($imgArr);//租客证件照片 
                    $userArr['nation'] = $userlist[$i]['nation'];//籍贯
                    $userArr['address'] = $userlist[$i]['address'];//籍贯地址
                    // $userArr['validdate'] = $userlist[$i]['validdate'];//证件有效期
                    // $userArr['authority'] = $userlist[$i]['authority'];//签发机关
                }else{
                    $userArr['chengzurentag'] = 0;  // 是否为承租人
                    $userArr['headimgjson'] = "";//租客证件照片
                    $userArr['nation'] = "";//籍贯
                    $userArr['address'] = "";//籍贯地址
                    // $userArr['validdate'] = "";//证件有效期
                    // $userArr['authority'] = "";//签发机关
                }
                if ($i==0) {
                    $rent_id = Db::table('ho_rent_lists')->insertGetId($userArr);
                } else {
                    $rent    = Db::table('ho_rent_lists')->insertGetId($userArr);
                }
            }
            //租客信息end
            
            // 2.添加周期
            $paymentArr = [
                'rent_id'   => $rent_id,
                'uuid'      => $postArr['uuid'],
                'payment_weeks' => $postArr['paymenttimes']
            ];

            //===========区分是在租还是预定==========
            if($postArr['housestate'] == "预定"){
                $paymentArr['payment_cycle_start_time'] = ""; //租赁开始时间
                $paymentArr['payment_cycle_end_time'] = ""; //租赁结束时间
                // $paymentArr['paymentWeeks'] = ""; //付款周期
                $paymentArr['payment_cycle_time'] = "";//付款周期
                Db::table('ho_finance')->insert([
                    'user_id' => USER_ID,
                    'rent_id' => $rent_id,
                    'uuid'    => $postArr['uuid'],
                    'room_id' => $postArr['room_id'],
                    'money'   => $postArr['yudingpri'],
                    'moneytype' => '预定金',
                    'way'     => '收入',
                    'renttype' => $renttype,
                    'createtime' => time(),
                    'updatetime' => time(),
                ]);
            }else{
                $paymentArr['payment_cycle_start_time'] = strtotime($postArr['startimes']); //租赁开始时间
                $paymentArr['payment_cycle_end_time'] = strtotime($postArr['endtimes']); //租赁结束时间
                // $paymentArr['paymentWeeks'] = $postArr['paymenttimes']; // 付款周期

                $qujian = $this->get_ld_times($postArr['startimes'],$postArr['endtimes'],$postArr['paymenttimes']);
                $paymentJson_data = [];

                for ($i=0; $i < count($qujian); $i++) { 
                    list($paydate_start, $paydate_end)  = explode(",", $qujian[$i]);
                    $paymentJson = array(
                        'paydate_start'     => $paydate_start, // 付款开始时间
                        'paydate_end'       => $paydate_end, // 付款结束时间
                        'rebate'            => "", //优惠金额
                        'rent_price'        => "", //实际租金
                        'jiaofei_state'     => "", //缴费状态
                        'skfs'              => "", //收款方式
                        'paymentWeeks'      => $postArr['paymenttimes'], //付款模式
                        'beizhu'            => "", //备注
                    );
                    $paymentJson_data[$i]['paymentJson'] = $paymentJson;
                    $paymentJson_data[$i]['paymentModel'] = isset($postArr['model'])?$postArr['model']:'';
                }
                // var_dump($paymentJson_data);die;
                $paymentArr['payment_cycle_time'] = json_encode($paymentJson_data);//付款周期
            }
            //===========end 区分是在租还是预定==========
            
            $respaydate = Db::table('ho_payment_cycle')->insert($paymentArr);

            // 3, 更新房屋状态
            $statusNum = (new \app\index\model\RoomLists)->getFjstatusNum($postArr['housestate']);
            
            if ($postArr['room_id'] == "0") {
                $res = Db::table('ho_room_lists')->where('uuid', $postArr['uuid'])->update([
                    'fjstatus' => $statusNum
                ]);

            } else {
                $res = Db::table('ho_room_lists')->where('room_id', $postArr['room_id'])->update([
                    'fjstatus' => $statusNum
                ]);

            }

            if($res){
                $msg = ['code'=>1,'data'=>['uuid' => $postArr['uuid'], 'room_id' => $postArr['room_id']],'msg'=>"添加成功",'url'=>""];
                return json_encode($msg);
            }else{
                $msg = ['code'=>0,'data'=>"",'msg'=>"添加失败",'url'=>""];
                return json_encode($msg);
            }
        }

        $result = [
            'room_id' => $this->request->get('room_id'),
            'uuid'    => $this->request->get('uuid')
        ];
        // 费用模板
        $feesConfig = Db::table('fees_config')->field('id, modelName')->where('uid', USER_ID)->select();
        // 租客来源
        $rent_source = Db::table('ho_rent_source')->field('id,source')->where('uid',USER_ID)->select();
        // 入住清单：包含固定资产内容和数量及水、气初始度数，电卡余额
        $listTemplate = Db::table('list_config_template')->field('id, modelName')->where('uid', USER_ID)->select();

        $this->assign("rent_source",$rent_source);
        $this->assign("modelName",$feesConfig);
        $this->assign("listName",$listTemplate);
        $this->assign("info",$result);
        $this->assign("rands",Random::alnum(16));
        return view();
    }

    /**
     * 修改房屋信息(编辑房源)
     * @return [type] [description]
     */
    public function edit()
    {

        if ($this->request->isPost()) {
            $houstData = $this->request->post();
            $roomData = $houstData['room'];

            $houseResult = (new \app\index\model\HouseLists)->where('user_id', USER_ID)->where('uuid', $houstData['uuid'])->with('room')->find();

            // 如果房屋有一间已出租，不能修改状态
            if ( ($houseResult['chuzutype'] == '0' && $houstData['clas'] == 1) ||  
                  ($houseResult['chuzutype'] == '1' && $houstData['clas'] == 0) ) {

                foreach ($houseResult['room'] as $key => $value) {
                    if ($value['fjstatus'] == 10) {
                        return;
                    }
                }
            }

            // 房屋详情
            // $houseResult = (new \app\index\model\HouseLists)->where('user_id', USER_ID)->where('uuid', $uuid)->with('room')->find();
            $house_id = Db::table('ho_house_lists')->where('uuid', $houstData['uuid'])->update([
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
            ]);

            foreach ($roomData as $key => $value) {
                $room_state = Db::table('ho_room_lists')->where('room_id', $value['room_id'])->update([
                    'fjbh'    => $value['fjbh'],
                    'fjarea'  => $value['fjarea'],
                    'beizhu'  => $value['beizhu'],
                    'fjstatus'  => $value['fjstatus'],
                ]);

                // TODO: 房屋增减情况
            }

            return $this->success('修改成功');
        }

        $uuid = $this->request->param('uuid');

        // 房屋详情
        $houseResult = (new \app\index\model\HouseLists)->where('user_id', USER_ID)->where('uuid', $uuid)->with('room')->find();

        // 项目
        $project = Db::table('ho_pro_lists')->field('id,projectName')->where('uid',USER_ID)->select();
        // 小区
        $communityLists = Db::table('ho_community_lists')->field('id, communityName')->where('uid', USER_ID)->select(); 
        // 费用
        $feesConfig = Db::table('fees_config')->field('id, modelName')->where('uid', USER_ID)->select();  
        // 房屋类型
        $house_state = Db::table('house_state')->field('id,keys')->where('uid',USER_ID)->select();

        // 判断房屋是否已经出租，或者预定，
        // 如果已经出租 预定 则不能修改 房屋出租类型(整租 合租)
        $watch = 0;
        foreach ($houseResult['room'] as $key => $value) {
            if ($value['fjstatus'] == 10 || $value['fjstatus'] == 12) {
                $watch = 1;
            }
        }

        $this->assign("project",$project);
        $this->assign("xiaoqu",$communityLists);
        $this->assign("modelName",$feesConfig);
        $this->assign("house_state",$house_state);

        $this->assign("info",$houseResult);
        $this->assign("rands",Random::alnum(16));
        $this->assign("watch", $watch);

        return view('edit_house');
    }

    /**
     * 收房租
     * @return [type] [description]
     */
    public function rentCollection()
    {

        if ($this->request->isPost()) {

            $postArr = $this->request->post();
            // $paymentCycle = Db::table('payment_cycle')->where('house_uuid', $postArr['uuid'])->where('rent_state', '在租')->find();

            $uuid = $this->request->param('uuid');
            $roomId = $this->request->param('room_id');

            if ($roomId == "0") {
                $rentRes = Db::table('ho_rent_lists')->where('uuid', $uuid)->where('rentstatus','在租')->find(); // 整租
                $paymentCycle = Db::table('ho_payment_cycle')->where('rent_id', $rentRes['rent_id'])->find();
                $renttype = '整租';
            } else {

                $rentRes = Db::table('ho_rent_lists')->where('room_id', $roomId)->where('rentstatus','在租')->find();
                $paymentCycle = Db::table('ho_payment_cycle')->where('rent_id', $rentRes['rent_id'])->find();
                $renttype = '合租';
            }

            $paymentCycle['payment_cycle_time'] = json_decode($paymentCycle['payment_cycle_time'], true);
            $paymentCycle['payment_cycle_time'][$postArr['key']]['paymentJson']['beizhu'] = $postArr['beizhu'];
            $paymentCycle['payment_cycle_time'][$postArr['key']]['paymentJson']['rebate'] = $postArr['rebate'];
            $paymentCycle['payment_cycle_time'][$postArr['key']]['paymentJson']['rent_price'] = $postArr['rent_price'];
            $paymentCycle['payment_cycle_time'][$postArr['key']]['paymentJson']['skfs'] = $postArr['skfs'];
            $paymentCycle['payment_cycle_time'][$postArr['key']]['paymentJson']['jiaofei_state'] = '已缴费';

            $financeArr = [];
            // 表示第一次收租，需要添加押金
            if ($postArr['key'] == 0) {

                Db::table('ho_finance')->insert([
                    'user_id' => USER_ID,
                    'rent_id' => $rentRes['rent_id'],
                    'uuid'    => $uuid,
                    'room_id' => $roomId,
                    'money'   => $rentRes['yajin'],
                    'moneytype' => '押金',
                    'way'     => '收入',
                    'renttype' => $renttype,
                    'createtime' => time(),
                    'updatetime' => time(),
                ]);
            }

            // 循环导入 每项费用模板
            foreach ($paymentCycle['payment_cycle_time'][$postArr['key']]['paymentModel']['afixFees'] as $key => $value) {

                Db::table('ho_finance')->insert([
                    'user_id' => USER_ID,
                    'rent_id' => $rentRes['rent_id'],
                    'uuid'    => $uuid,
                    'room_id' => $roomId,
                    'money'   => $value['money'],
                    'moneytype' => $value['name'],
                    'way'     => '收入',
                    'renttype' => $renttype,
                    'createtime' => time(),
                    'updatetime' => time(),
                ]);
            }


            $cycle['payment_cycle_time'] = json_encode($paymentCycle['payment_cycle_time']);

            if ($roomId == "0") {
                $respaydate = Db::table('ho_payment_cycle')->where('rent_id', $rentRes['rent_id'])->update($cycle);
            } else {
                $respaydate = Db::table('ho_payment_cycle')->where('rent_id', $rentRes['rent_id'])->update($cycle);
            }

            // $respaydate = Db::table('payment_cycle')->where('house_uuid', $postArr['uuid'])->update($cycle);
            if($respaydate){
                $msg = ['code'=>1,'data'=>"",'msg'=>"修改成功",'url'=>""];
                return json_encode($msg);
            }else{
                $msg = ['code'=>0,'data'=>"",'msg'=>"修改失败",'url'=>""];
                return json_encode($msg);
            }
        }

        $uuid = $this->request->param('uuid');
        $roomId = $this->request->param('room_id');

        $houseResult = (new \app\index\model\HouseLists)->where('user_id', USER_ID)->where('uuid', $uuid)->with('community')->find();

        if ($roomId == "0") {

            $roomListsModel = (new \app\index\model\RoomLists)->where('uuid', $uuid)->find(); // 整租
            $rent = Db::table('ho_rent_lists')
                    ->alias('r')
                    ->where('r.uuid', $uuid)
                    ->where('r.rentstatus','在租')
                    ->join('payment_cycle p','r.rent_id = p.rent_id')
                    ->find();
        } else {

            $roomListsModel = (new \app\index\model\RoomLists)->where('room_id', $roomId)->find(); // 合租
            $rent = Db::table('ho_rent_lists')
                    ->alias('r')
                    ->where('r.room_id', $roomId)
                    ->where('r.rentstatus','在租')
                    ->join('payment_cycle p','r.rent_id = p.rent_id')
                    ->find();
        }

        $key = $this->request->get('key');
        $this->assign('id', $key);

        $result = [
            'payment' => json_decode($rent['payment_cycle_time'], true)[$key],
            'key'     => $key,
            'deposit' => $rent['yajin'],
            'stage'     => '第'. ($key+1) .'期',
            // 'paymentWeeks'     => $houseInfo['paymentWeeks'],
            'uuid'     => $uuid,
            'room_id'     => $roomId,
            'roomNumber' => $roomListsModel['fjbh'],
            'houseKeepingStart' => $rent['payment_cycle_start_time'],
            'houseKeepingEnd' => $rent['payment_cycle_end_time'],
        ];
        $money = 0;
        if($key == 0) {
            $money += $rent['yajin'];
        }
        // echo "<pre>";
        // var_dump($result);die;
        foreach ($result['payment']['paymentModel']['afixFees'] as $value) {
            switch ($houseInfo['paymentWeeks']) {
                case '季付':
                    $money += $value['money']*3;
                    break;
                case '年付':
                    $money += $value['money']*12;
                    break;
                case '半年付':
                    $money += $value['money']*6;
                    break;
                default:
                    $money += $value['money'];
                    break;
            }
        }
        $this->assign('money', $money);
        $this->assign('info', $result);
        return view();
    }

    /**
     * 退租详情 根据 uuid
     * @return [view] [description]
     */
    public function tuizu()
    {

        $uuid = $this->request->param('uuid');
        $roomId = $this->request->param('room_id');

        if ($this->request->isPost()) {

            // TODO需要算金额
            // $payment_cycle_time = Db::table('payment_cycle')->where('house_uuid', $uuid)->where('rent_state', '在租')->find()['payment_cycle_time'];
            // $ruzhu_at = json_decode($payment_cycle_time, true)[0]['paymentJson']['paydate_start'];

            // Db::table('tuizu')->insert([
            //     'tui' => $this->request->param('tui'),
            //     'shou' => $this->request->param('shou'),
            //     'tuizu_at' => strtotime($this->request->param('tuizu_time')), // 退租日期
            //     'ruzhu_at' => strtotime($ruzhu_at), // 入住日期
            //     'beizhu'    => $this->request->param('beizhu'),
            //     'uid'    => USER_ID,
            //     'house_uuid'    => $uuid,
            // ]);
            $roomModel = (new \app\index\model\RoomLists);

            if ($roomId == "0") {
                $rent_id = Db::table('ho_rent_lists')->where('uuid', $uuid)->where('rentstatus','在租')->find()['rent_id'];
                $rentRes = Db::table('ho_rent_lists')->where('uuid', $uuid)->where('rentstatus','在租')->update([
                    'rentstatus' => '退租'
                ]); // 整租
                $roomListsModel = $roomModel->where('uuid', $uuid)->update([
                    'fjstatus'  => $roomModel->getFjstatusNum('空置')
                ]); // 整租
                $renttype = '整租';
            } else {

                $rent_id = Db::table('ho_rent_lists')->where('room_id', $roomId)->where('rentstatus','在租')->find()['rent_id'];
                $rentRes = Db::table('ho_rent_lists')->where('room_id', $roomId)->where('rentstatus','在租')->update([
                    'rentstatus' => '退租'
                ]);
                

                $roomListsModel = $roomModel->where('room_id', $roomId)->update([
                    'fjstatus'  => $roomModel->getFjstatusNum('空置')
                ]); // 合租
                $renttype = '合租';
            }

            $shou = $this->request->post('shou');
            $tui  = $this->request->post('tui');
            $data = [];
            if ($shou > 0) {
                $data[] = [
                    'user_id' => USER_ID,
                    'rent_id' => $rent_id,
                    'uuid'    => $uuid,
                    'room_id' => $roomId,
                    'money'   => $shou,
                    'moneytype' => '退租时收入',
                    'way'     => '收入',
                    'finance_beizhu'  => $this->request->post('beizhu'),
                    'renttype' => $renttype,
                    'createtime' => time(),
                    'updatetime' => time(),
                ];
            }

            if ($tui > 0) {
                $data[] = [
                    'user_id' => USER_ID,
                    'rent_id' => $rent_id,
                    'uuid'    => $uuid,
                    'room_id' => $roomId,
                    'money'   => $tui,
                    'moneytype' => '退租时退款',
                    'way'     => '支出',
                    'finance_beizhu'  => $this->request->post('beizhu'),
                    'renttype' => $renttype,
                    'createtime' => time(),
                    'updatetime' => time(),
                ];
            }

            Db::table('ho_finance')->insertAll($data);

            if($roomListsModel){
                $msg = ['code'=>1,'data'=>"",'msg'=>"退租成功",'url'=>""];
                return json_encode($msg);
            }else{
                $msg = ['code'=>0,'data'=>"",'msg'=>"退租失败",'url'=>""];
                return json_encode($msg);
            }

        }
        

        $houseResult = (new \app\index\model\HouseLists)->where('user_id', USER_ID)->where('uuid', $uuid)->find();

        if ($roomId == "0") {

            $roomListsModel = (new \app\index\model\RoomLists)->where('uuid', $uuid)->find(); // 整租
            $rent = Db::table('ho_rent_lists')
                    ->alias('r')
                    ->where('r.uuid', $uuid)
                    ->where('r.rentstatus','在租')
                    ->join('payment_cycle p','r.rent_id = p.rent_id')
                    ->find();
        } else {

            $roomListsModel = (new \app\index\model\RoomLists)->where('room_id', $roomId)->find(); // 合租
            $rent = Db::table('ho_rent_lists')
                    ->alias('r')
                    ->where('r.room_id', $roomId)
                    ->where('r.rentstatus','在租')
                    ->join('payment_cycle p','r.rent_id = p.rent_id')
                    ->find();
        }

        // $result = Db::table('ho_house_lists')->where('uid', USER_ID)->where('uuid',$uuid)->find();

        $result = [
            'uuid'        => $uuid,
            'room_id'     => $roomId,

            'roomNumber' => $roomListsModel['fjbh'],

            // 'money'       => sprintf("%.2f",$money),
            'houseKeepingStart' => $rent['payment_cycle_start_time'],
            'houseKeepingEnd' => $rent['payment_cycle_end_time'],

            'listTemplate' => $rent['feemodeljson']
        ];
        // echo "<pre>";
        // var_dump($result);die;
        $this->assign('info', $result);

        return view();
    }


    /**
     * 装修完毕
     * @return [type] [description]
     */
    public function zhuangxQD()
    {

        if ($this->request->isPost()) {

            $uuid = $this->request->post('uuid');
            $roomId = $this->request->post('room_id');

            $this->editHouseStatus($uuid, $roomId) ? $this->success('修改成功') : $this->error('fail');
        }
    }

    /**
     * 撤销预定
     * @return [type] [description]
     */
    public function cexiaoYD()
    {

        if ($this->request->isPost()) {

            $uuid = $this->request->post('uuid');
            $roomId = $this->request->post('room_id');

            $this->editHouseStatus($uuid, $roomId) ? $this->success('修改成功') : $this->error('fail');
        }
    }

    /**
     * 修改房屋状态
     */
    public function editHouseStatus($uuid, $roomId, $status = '空置')
    {
        
        $houseResult = (new \app\index\model\HouseLists)->where('user_id', USER_ID)->where('uuid', $uuid)->find();
        $roomModel = (new \app\index\model\RoomLists);
        $fjstatus = $roomModel->getFjstatusNum($status);

        if ($roomId == "0") {
            $res = $roomModel->where('uuid', $uuid)->update([
                'fjstatus'  => $fjstatus
            ]);
        } else {
            $res = $roomModel->where('room_id', $roomId)->update([
                'fjstatus'  => $fjstatus
            ]);
        }

        if ($res) {
            return true;
        }

        return false;
    }
    

    /**
     * 查询当前上传的身份证件照
     */
    public function select_idcard(){
        $id = $this->request->post('id');
        $idcard = Db::table('attachment')->field('id,url,tag')->where('user_id',USER_ID)->where('admin_id',$id)->select();
        $msg = ['code'=>1,'data'=>$idcard,'msg'=>"查询成功".$id,'url'=>""];
        return json_encode($msg);
    }

    /**
     * 通过身份证件识别承租人信息
     */
    public function idCard()
    {
        $url = 'http://'.$_SERVER["HTTP_HOST"] . $this->request->post('url');
        $tag = $this->request->post('tag');
        // echo $url."=======".$tag;
        $res = IDCardOCR::getIDCard($url, $tag);//FRONT   BACK
        // $res = IDCardOCR::getIDCard('https://www.vipzu.cn/static/01.png', 'BACK');
        // $msg = ['code'=>1,'data'=>$res,'msg'=>"识别成功",'url'=>""];
        return json($res);
    }
    /**
     * 直接输出二维码 + 生成二维码图片文件
     */
    public function create(){
        $rands = $this->request->param('rands');
        $qr_url = url('mobile/index/index', ['id'=>$rands,'uid'=>USER_ID,'up'=>'keys'], 'htm', 'http://uooko.cn');

        $text = $this->request->get('text', $qr_url);
        $size = $this->request->get('size', 300);
        $padding = $this->request->get('padding', 10);
        $errorcorrection = $this->request->get('errorcorrection', 'medium');
        $foreground = $this->request->get('foreground', "#ffffff");
        $background = $this->request->get('background', "#000000");
        $logo = $this->request->get('0');
        $logosize = $this->request->get('50');
        $label = $this->request->get('手机扫描二维码上传身份证件');
        $labelfontsize = $this->request->get('14');
        $labelhalign = $this->request->get('5');
        $labelvalign = $this->request->get('4');

        // 前景色
        list($r, $g, $b) = sscanf($foreground, "#%02x%02x%02x");
        $foregroundcolor = ['r' => $r, 'g' => $g, 'b' => $b];

        // 背景色
        list($r, $g, $b) = sscanf($background, "#%02x%02x%02x");
        $backgroundcolor = ['r' => $r, 'g' => $g, 'b' => $b];

        $qrCode = new QrCode();
        $qrCode
            ->setText($text)
            ->setSize($size)
            ->setPadding($padding)
            ->setErrorCorrection($errorcorrection)
            ->setForegroundColor($foregroundcolor)
            ->setBackgroundColor($backgroundcolor)
            ->setLogoSize($logosize)
            ->setLabel($label)
            ->setLabelFontSize($labelfontsize)
            ->setLabelHalign($labelhalign)
            ->setLabelValign($labelvalign)
            ->setImageType(QrCode::IMAGE_TYPE_PNG);
        $fontPath = ROOT_PATH . 'public/assets/fonts/SourceHanSansK-Regular.ttf';
        if (file_exists($fontPath)) {
            $qrCode->setLabelFontPath($fontPath);
        }
        if ($logo) {
            $qrCode->setLogo(ROOT_PATH . 'public/assets/img/qrcode.png');
        }
        //也可以直接使用render方法输出结果
        //$qrCode->render();
        return new Response($qrCode->get(), 200, ['Content-Type' => $qrCode->getContentType()]);

        exit;
    }


    // 获取收费模板内容
    public function findmodelName(){
        if(request()->isPost() && request()->param('id')){
            $id = request()->param('id');
            $res = Db::table('fees_config')->field('model')->where('id', $id)->find()['model'];
            // var_dump(json_decode($res, true));
            if($res){
                return $res;
            }else{
                return false;
            }
            // echo $res;
        }
    }

    // 获取入住清单模板内容
    public function findlisttemplateName(){
        if(request()->isPost() && request()->param('id')){
            $id = request()->param('id');
            $res = Db::table('list_config_template')->field('model')->where('id', $id)->find()['model'];
            // var_dump(json_decode($res, true));
            if($res){
                return $res;
            }else{
                return false;
            }
            // echo $res;
        }
    }

    /**
     * 输入开始时间，结束时间，粒度（周，月，季度）
     * @param 参数一：开始时间
     * @param 参数二：结束时间
     * @param 参数三：粒度（周，月，季度）
     * @return 时间段字符串数组
     */
    protected function get_ld_times($st,$et,$ld){    
        if($ld=='周付'){
            $timeArr=array();
            $t1=$st;
            $t2=getdays($t1)['1'];
            while($t2<$et || $t1<=$et){//周为粒度的时间数组
                $timeArr[]=$t1.','.$t2;
                $t1=date('Y-m-d',strtotime("$t2 +1 day"));
                $t2=getdays($t1)['1'];
                $t2=$t2>$et?$et:$t2;
            }
            return $timeArr;
        }else if($ld=='月付'){
            $t1=$st;
            $t2=date('Y-m-d',strtotime("$t1 +1 month -1 day"));;
            // $timeArr=array();
            while($t2<$et || $t1<=$et){//月为粒度的时间数组
                $timeArr[]=$t1.','.$t2;
                $t1=date('Y-m-d',strtotime("$t2 +1 day"));
                $t2=date('Y-m-d',strtotime("$t1 +1 month -1 day"));
                $t2=$t2>$et?$et:$t2;
            }
            return $timeArr;
        }else if($ld=='季付'){
            $t1=$st;
            $t2=date('Y-m-d',strtotime("$t1 +3 month -1 day"));;
            // $timeArr=array();
            while($t2<$et || $t1<=$et){//月为粒度的时间数组
                $timeArr[]=$t1.','.$t2;
                $t1=date('Y-m-d',strtotime("$t2 +1 day"));
                $t2=date('Y-m-d',strtotime("$t1 +3 month -1 day"));
                $t2=$t2>$et?$et:$t2;
            }
            return $timeArr;
        }else if($ld=='半年付'){
            $t1=$st;
            $t2=date('Y-m-d',strtotime("$t1 +6 month -1 day"));;
            // $timeArr=array();
            while($t2<$et || $t1<=$et){//月为粒度的时间数组
                $timeArr[]=$t1.','.$t2;
                $t1=date('Y-m-d',strtotime("$t2 +1 day"));
                $t2=date('Y-m-d',strtotime("$t1 +6 month -1 day"));
                $t2=$t2>$et?$et:$t2;
            }
            return $timeArr;
        }else if($ld=='年付'){
            $t1=$st;
            $t2=date('Y-m-d',strtotime("$t1 +1 year -1 day"));;
            // $timeArr=array();
            while($t2<$et || $t1<=$et){//月为粒度的时间数组
                $timeArr[]=$t1.','.$t2;
                $t1=date('Y-m-d',strtotime("$t2 +1 day"));
                $t2=date('Y-m-d',strtotime("$t1 +1 year -1 day"));
                $t2=$t2>$et?$et:$t2;
            }
            return $timeArr;
        }else{
            return array('参数错误!');
        }
    }
    protected function getdays($day){//指定天的周一和周天
        $lastday=date('Y-m-d',strtotime("$day Sunday"));
        $firstday=date('Y-m-d',strtotime("$lastday -6 days"));
        return array($firstday,$lastday);
    }
}
