<?php
namespace app\callbackapi\controller;
use think\Controller;
use app\rfidapi\controller\Order;
use app\rfidapi\controller\Kus;
class Test extends Controller
{
	// 订单信息查询
	public function testorder()
    {
    	$time = time();
    	$param = array(
    		"orderId"=>"ok151862423865",
    		"timestamp" => $time
    		);
        $ordeobj = new Order();
        $order = $ordeobj->selOrder($param);
        print_r($order);
    }

    // 库存信息查询
    public function testku()
    {
    	$time = time();
    	$param = array(
    		"serialsnumber"=>"ok151862423865",
    		"containerids" => array('QT2111803080002','QT2111803080004'),
    		"timestamp" => $time
    		);
        $kusobj = new Kus();
        $kus = $kusobj->list($param);
        print_r($kus);
    }

}