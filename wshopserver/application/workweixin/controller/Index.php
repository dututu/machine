<?php
namespace app\workweixin\controller;

use app\common\model\Goodsorder;
use think\Controller;
use think\Db;
use think\Config;
use think\Log;
use think\Loader;
use think\Request;

class Index extends Base
{

    /**
     * 首页
     * @access public
     * @return tp5
     */
    public function index()
    {

        return $this->fetch();

    }

    /**
     * 销售报表
     * @return mixed
     */
    public function saleDetail()
    {
        return $this->fetch('saledetail');
    }

    /**
     * 机柜报表
     * @return mixed
     */
    public function machineDetail()
    {
        $start = input('start',$this->start);
        $end = input('end',$this->end);
        $order = new Order();
        $data = $order->getDayOrder($start,$end);
        if( $data['errcode'] != 0)
            return $this->error($data['errmsg']);
        $this->assign('data',$data['data']);
        return $this->fetch();
    }

    /**
     * 商户报表
     * @return mixed
     */
    public function agentDetail()
    {
        $start = input( 'start',$this->start );
        $end = input( 'end',$this->end );

        $merchant = new Merchant();
        $data = $merchant->getMerchantData($start,$end);
        if( $data['errcode'] != 0)
            return $this->error($data['errmsg']);
        $this->assign('data',$data['data']);
        return $this->fetch('agentdetail');
    }

    /**
     * 用户报表
     * @return mixed
     */
    public function userDetail()
    {
        return $this->fetch('userdetail');
    }

}
