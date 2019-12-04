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

    /**
     * 房源小详情
     * @return [type] [description]
     */
    public function detail()
    {
        $uuid = $this->request->get('uuid');
        $this->assign('uuid', $uuid);
        return view();
    }

    /**
     * 编辑房源
     * @return [type] [description]
     */
    public function editHousedetail()
    {
        $uuid = $this->request->get('uuid');
        $this->assign('uuid', $uuid);
        return view();
    }


    /**
     * 查看房源预定信息
     * @return [type] [description]
     */
    public function chakanYd()
    {
        $uuid = $this->request->get('uuid');
        $this->assign('uuid', $uuid);
        return view();
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
            $arr['uid'] = USER_ID;// 用户ID
            $arr['updated_at'] = date('Y-m-d h:i:s', time()); //修改时间
            $arr['roomState'] = $postArr['housestate'];     //房屋状态 入住 和 预定


            $arr['modelId'] = $postArr['pricemodel']; //费用模板
            if(!isset($postArr['model'])){
                $arr['fees'] = "";
            }else{
                $postArr['model']['computedFees'] = [];
                $arr['fees'] = json_encode($postArr['model']); //费用配置
            }

            if(!isset($postArr['listtemplate'])){
                $arr['listTemplate'] = "";
            }else{
                $arr['listTemplate'] = json_encode($postArr['listtemplate']); //清单配置
            }

            //===========区分是在租还是预定==========
            if($arr['roomState'] == "预定"){
                $arr['houseKeepingStart'] = ""; //租赁开始时间
                $arr['houseKeepingEnd'] = ""; //租赁结束时间
                $arr['paymentWeeks'] = ""; //付款周期
            }else{
                $arr['houseKeepingStart'] = strtotime($postArr['startimes']); //租赁开始时间
                $arr['houseKeepingEnd'] = strtotime($postArr['endtimes']); //租赁结束时间
                $arr['paymentWeeks'] = $postArr['paymenttimes']; //付款周期
            }     
            //===========end 区分是在租还是预定==========

            $arr['userOrigin'] = $postArr['userorigin']; //租客来源
            
            //租客信息
            $arruser['house_uuid'] = $postArr['uuid']; //房屋唯一ID
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
            // var_dump($userlist[0]['sex']);die;
            // echo json_encode($imgArr);die;
            for ($i=0; $i < count($userlist); $i++) { 
                $arruser['name'] = $userlist[$i]['username'];
                $arruser['sex'] = $userlist[$i]['sex'];//租客性别 1： 男 2 女
                $arruser['phone'] = $userlist[$i]['userphone'];
                if ( isset($userlist[$i]['usercard']) ) {
                    $arruser['card'] = $userlist[$i]['usercard'];
                }
                if ( isset($userlist[$i]['huzhao']) ) {
                    $arruser['huzhao'] = $userlist[$i]['huzhao'];//租客护照号
                }
                $arruser['uid'] = USER_ID; //管理用户ID
                if($postArr['housestate'] == "在租"){
                    $arruser['juzhu_state'] = "在租";
                }else if($postArr['housestate'] == "预定"){
                    $arruser['juzhu_state'] = "预定";
                }else{
                    $arruser['juzhu_state'] = "";
                }
                $arruser['ruzhutime'] = $arr['houseKeepingStart'];
                if($i==0){
                    $arruser['cardimg'] = json_encode($imgArr);//租客证件照片 

                    $arruser['nation'] = $userlist[$i]['nation'];//籍贯
                    $arruser['address'] = $userlist[$i]['address'];//籍贯地址
                    $arruser['validdate'] = $userlist[$i]['validdate'];//证件有效期
                    $arruser['authority'] = $userlist[$i]['authority'];//签发机关
                }else{
                    $arruser['cardimg'] = "";//租客证件照片

                    $arruser['nation'] = "";//籍贯
                    $arruser['address'] = "";//籍贯地址
                    $arruser['validdate'] = "";//证件有效期
                    $arruser['authority'] = "";//签发机关
                }
                $resuser = Db::table('rent_user')->insert($arruser);
            }
            //租客信息end
            
            // start 租客付款周期表
            if($postArr['housestate'] == "预定"){
                $cycle['payment_cycle_time'] = "";//付款周期
                $cycle['deposit'] = ""; //租房押金
                $cycle['dingjinprice'] = $postArr['yudingpri']; //租房定金
                $cycle['house_uuid'] = $postArr['uuid']; //房屋唯一ID
                $cycle['rent_state'] = "预定";
                $cycle['uid'] = USER_ID; //管理用户ID
            }else{
                $qujian = $this->get_ld_times($postArr['startimes'],$postArr['endtimes'],$arr['paymentWeeks']);
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
                        'paymentModel'      => $arr['paymentWeeks'], //付款模式
                        'beizhu'            => "", //备注
                    );
                    $paymentJson_data[$i]['paymentJson'] = $paymentJson;
                    $paymentJson_data[$i]['paymentModel'] = isset($postArr['model'])?$postArr['model']:'';
                }
                // var_dump($paymentJson_data);die;
                $cycle['payment_cycle_time'] = json_encode($paymentJson_data);//付款周期
                $cycle['deposit'] = $postArr['deposit']; //租房押金
                $cycle['dingjinprice'] = ""; //租房定金
                $cycle['house_uuid'] = $postArr['uuid']; //房屋唯一ID
                $cycle['rent_state'] = "在租";
    
                $cycle['uid'] = USER_ID; //管理用户ID
            }
            

            $respaydate = Db::table('payment_cycle')->insert($cycle);
            // end   租客付款周期

            $res = Db::table('house_lists')->where('uuid', $postArr['uuid'])->update($arr);
            if($res){
                $msg = ['code'=>1,'data'=>$postArr['uuid'],'msg'=>"添加成功",'url'=>""];
                return json_encode($msg);
            }else{
                $msg = ['code'=>0,'data'=>"",'msg'=>"添加失败",'url'=>""];
                return json_encode($msg);
            }
        }

        // 房子信息
        $result = Db::table('house_lists')->where('uuid', $this->request->param('uuid'))->find();
        // 费用模板
        $feesConfig = Db::table('fees_config')->field('id, modelName')->where('uid', USER_ID)->select();
        // 租客来源
        $rent_source = Db::table('rent_source')->field('id,source')->where('uid',USER_ID)->select();
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
