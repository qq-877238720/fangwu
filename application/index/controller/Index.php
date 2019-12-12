<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use think\Db;

class Index extends Frontend
{

    protected $noNeedLogin = ['index'];
    protected $noNeedRight = '*';
    protected $layout = 'layoutname';

    public function _initialize()
    {
        parent::_initialize();
        $auth = $this->auth;
        define("USER_ID", $this->auth->id);
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
        $houseResult = (new \app\index\model\HouseLists)->where('user_id', USER_ID)->with('room')->select();

        $houseLists = collection($houseResult)->toArray();
        $kong      = $houseLists;
        $yuding    = $houseLists;
        $zhuangxiu = $houseLists;
        
        foreach ($kong as $key => &$value) {
            $value['room'] = $this->dealRoom($value['room'], 11);
        }

        foreach ($yuding as $ydk => &$ydv) {
            $ydv['room'] = $this->dealRoom($ydv['room'], 13);
        }

        foreach ($zhuangxiu as $zxk => &$zxv) {
            $zxv['room'] = $this->dealRoom($zxv['room'], 12);
        }

        $rent = Db::table('ho_rent_lists')
                ->alias('r')
                ->where('user_id',USER_ID)
                ->where('rentstatus','在租')
                ->join('payment_cycle p', 'p.rent_id = r.rent_id')
                ->select();
        foreach ($rent as $k => &$v) {

            $v['qianfei_state'] = 0;// 不欠费

            $v['payment_cycle_time'] = json_decode($v['payment_cycle_time'], true);
            for ($i = 0; $i < count($v['payment_cycle_time']); $i++) {
                if ($v['payment_cycle_time'][$i]['paymentJson']['jiaofei_state'] == '已缴费') {
                    if ( !empty($v['payment_cycle_time'][$i+1]) && ($v['payment_cycle_time'][$i+1]['paymentJson']['jiaofei_state'] == '' || $v['payment_cycle_time'][$i+1]['paymentJson']['jiaofei_state'] == '未缴费') ) {

                        $times = strtotime($v['payment_cycle_time'][$i+1]['paymentJson']['paydate_start']) - time();
                        $diff  = floor($times/3600/24);

                        if ($diff <= 7) {
                            $v['diff'] = $diff;
                        }
                    }
                    // if ( $paymentCycle['payment_cycle_time'][$i]['paymentJson']['paydate_start'] <= date('Y-m-d') ) {
                    //     $v['qianfei_state'] = 1; // 欠费

                    // }
                } else {
                    if ($i == 0 && ($v['payment_cycle_time'][$i]['paymentJson']['jiaofei_state'] == '' || $v['payment_cycle_time'][$i]['paymentJson']['jiaofei_state'] == '未缴费')) {
                        $times = strtotime($v['payment_cycle_time'][$i]['paymentJson']['paydate_start']) - time();
                        $diff  = floor($times/3600/24);
                        
                        if ($diff <= 7) {
                            $v['diff'] = $diff;
                        }
                    }
                    if ( $v['payment_cycle_time'][$i]['paymentJson']['paydate_start'] <= date('Y-m-d') ) {
                        $v['qianfei_state'] = 1; // 欠费

                    }
                    break;
                }
            }

            if ($v['qianfei_state'] == 0 ) {
                unset($rent[$k]);
                continue;
            }

            if (!empty($rent)) {
                foreach($v['payment_cycle_time'] as $kk=>$vv):
                    if ($vv['paymentJson']['jiaofei_state'] == '已缴费') {
                        continue;
                    } else {
                        $key = $kk;
                        break;
                    }
                endforeach;
            }

            $v['key'] = $key;
        }
        // echo "<pre>";
        // var_dump($rent);die;
        $result = [
            'count' => $this->countRoom($houseLists),
            'kong'  => $this->countRoom($kong),
            'yuding'  => $this->countRoom($yuding),
            'zhuangxiu'  => $this->countRoom($zhuangxiu),
            'rent'  => $rent,
        ];

        $this->assign('info', $result);

        $this->view->assign('title', __('面板'));
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


    protected function countRoom($house_lists)
    {

        $sum = 0;
        foreach ($house_lists as $key => $value) {

            if (empty($value['room']))  continue;

            if ($value['chuzutype'] == 1) {
                $sum += 1;
            } else {
                $sum += count($value['room']);
            }
        }
        return $sum;
    }

}
