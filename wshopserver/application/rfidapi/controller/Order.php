<?php
namespace app\rfidapi\controller;
use think\Controller;
use think\Db;
use \think\Log;
use \think\Request;
use think\Loader;
use traits\model\Transaction;
use app\common\model\Taglib as TaglibModel;
use app\common\model\Orderbase as OrderbaseModel;
use app\common\model\Orderepc as OrderepcModel;
use app\common\model\Orderdetail as OrderdetailModel;
use app\common\model\Salegoods as SalegoodsModel;
class Order extends Controller
{
    public function selOrder($dparam2){
        // 订单号信息验证
        $orderid = isset($dparam2['orderId']) ? $dparam2['orderId'] : '' ;
        if($orderid == "" || $orderid == "null")
        {
            $result['code'] = "203";
            $result['msg'] = "订单号参数错误";
            echo json_encode($result);exit;
        }

        // sql防注入
        if (!get_magic_quotes_gpc())   
        {    
          $orderid = addslashes($orderid);
        }   
        $sql = "SELECT orderbase.orderid,orderbase.containerid,orderbase.type,orderdetail.barcode,orderdetail.amount,orderdetail.type as types from orderbase LEFT JOIN orderdetail on orderbase.orderid = orderdetail.orderid WHERE orderbase.orderid = '$orderid'";
        $info = Db::query($sql);
        // 订单号存在验证
        if(count($info) == 0)
        {
            $result['code'] = "204";
            $result['msg'] = "订单号不存在";
            echo json_encode($result);
            exit;
        }
        // type 1购物2理货 types1入2出
        $result = $data = $adds = $down = array();
        $result['orderid'] = $info[0]['orderid'];
        $result['containerid'] = $info[0]['containerid'];
        $result['type'] = $type = $info[0]['type'];
        
        // 订单为空，开门无操作
        if(isset($info[0]) && is_null($info[0]['barcode']))
        {
           if($type == 1){ //购物
                $result['datas'] = array();
           
           }else{   //理货
                 $result['adds'] = $result['down'] = array();
           }
            echo json_encode($result);
            exit;
        }

        // 开门有操作
        foreach ($info as $key => $value) 
        {
            $types = $value['types'];
            $barcode = $value['barcode'];
            $amount = $value['amount'];
            if($type == 1) //1购物
            {
                $data[] = array(
                    'barCode'=>$barcode,
                    'amount'=>$amount
                    );
            } else { //2理货
                if($types == 1)  //1入库
                {
                    $adds[] = array(
                        'barCode'=>$barcode,
                        'amount'=>$amount
                        );
                } else if($types == 2) {    //2出库
                    $down[] = array(
                        'barCode'=>$barcode,
                        'amount'=>$amount
                        );
                }
            }
        }
        if($type == 1)
        {
            $result['datas'] = $data;
        } else {
            $result['adds'] = $adds;
            $result['down'] = $down;
        }
        $result['timestamp'] = time();
        echo json_encode($result,JSON_UNESCAPED_SLASHES); 
    }
}