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
    protected $noNeedLogin = ['*'];
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
     * 账目核销数据
     */
    public function hexiaoDetail()
    {

        $page  = $this->request->get('page') - 1;
        $limit = $this->request->get('limit');

        $res = Db::table('ho_finance')
                ->alias('f')
                ->join('rent_lists r', 'r.rent_id = f.rent_id')
                ->join('house_lists h', 'h.uuid = f.uuid')
                ->join('community_lists c', 'c.id = h.xiaoquID')
                ->where('f.user_id', USER_ID)
                ->where('f.status', 0)
                ->limit($page*$limit, $limit)
                ->select();

        $total = Db::table('ho_finance')
                ->alias('f')
                ->join('rent_lists r', 'r.rent_id = f.rent_id')
                ->join('house_lists h', 'h.uuid = f.uuid')
                ->join('community_lists c', 'c.id = h.xiaoquID')
                ->where('f.user_id', USER_ID)
                ->where('f.status', 0)
                ->limit($page*$limit, $limit)
                ->count();

        return json([
            'status' => 200,
            'total'  => $total,
            'rows'   => $res,
        ]);
    }

    /**
     * 核销(仅限财务人员可以调用)
     * @return [type] [description]
     */
    public function change($id)
    {

        $res = Db::table('ho_finance')
                ->where('finance_id', $id)
                ->update([
                    'status' => 1,
                    'updatetime' => time(),
                ]);
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
     * 账单查看
     */
    public function zhangdanDetail()
    {

        $page  = $this->request->get('page') - 1;
        $limit = $this->request->get('limit');

        $res = Db::table('ho_finance')
                ->alias('f')
                ->join('rent_lists r', 'r.rent_id = f.rent_id')
                ->join('house_lists h', 'h.uuid = f.uuid')
                ->join('community_lists c', 'c.id = h.xiaoquID')
                ->where('f.user_id', USER_ID)
                ->where('f.status', 1)
                ->limit($page*$limit, $limit)
                ->select();

        $total = Db::table('ho_finance')
                ->alias('f')
                ->join('rent_lists r', 'r.rent_id = f.rent_id')
                ->join('house_lists h', 'h.uuid = f.uuid')
                ->join('community_lists c', 'c.id = h.xiaoquID')
                ->where('f.user_id', USER_ID)
                ->where('f.status', 1)
                ->limit($page*$limit, $limit)
                ->count();

        return json([
            'status' => 200,
            'total'  => $total,
            'rows'   => $res,
        ]);
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
