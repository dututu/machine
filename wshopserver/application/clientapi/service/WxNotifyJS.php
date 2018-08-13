<?php
/**
 * Created by Caesar.
 */

namespace app\clientapi\service;

use app\common\model\Rfidorder as RfidorderModel;
use think\Db;
use think\Exception;
use think\Loader;
use think\Log;
use app\common\model\Formmessage as MessageModel;
use app\common\model\Sysuser as SysUserModel;
use app\worker\controller\WechatMessage;
use app\lib\enum\GoodsOrderStatusEnum;
use app\lib\enum\GoodsOrderDoorStatusEnum;
use app\lib\enum\WechatTemplate;
use app\lib\enum\OnsaleStatusEnum;
use app\lib\enum\RfidOrderStatusEnum;
use app\lib\enum\MachineBusinessStatusEnum;

Loader::import('master.RfidApiV2', EXTEND_PATH, '.php');

class WxNotifyJS
{
//    protected $data = <<<EOD
//<xml><appid><![CDATA[wxaaf1c852597e365b]]></appid>
//<bank_type><![CDATA[CFT]]></bank_type>
//<cash_fee><![CDATA[1]]></cash_fee>
//<fee_type><![CDATA[CNY]]></fee_type>
//<is_subscribe><![CDATA[N]]></is_subscribe>
//<mch_id><![CDATA[1392378802]]></mch_id>
//<nonce_str><![CDATA[k66j676kzd3tqq2sr3023ogeqrg4np9z]]></nonce_str>
//<openid><![CDATA[ojID50G-cjUsFMJ0PjgDXt9iqoOo]]></openid>
//<out_trade_no><![CDATA[A301089188132321]]></out_trade_no>
//<result_code><![CDATA[SUCCESS]]></result_code>
//<return_code><![CDATA[SUCCESS]]></return_code>
//<sign><![CDATA[944E2F9AF80204201177B91CEADD5AEC]]></sign>
//<time_end><![CDATA[20170301030852]]></time_end>
//<total_fee>1</total_fee>
//<trade_type><![CDATA[JSAPI]]></trade_type>
//<transaction_id><![CDATA[4004312001201703011727741547]]></transaction_id>
//</xml>
//EOD;

    public function NotifyProcess($notifyInfo)
    {
        $transaction_id = $notifyInfo['transaction_id'];//微信支付订单号
        $orderno = $notifyInfo['out_trade_no'];//商户订单号
        $total_fee = $notifyInfo['total_fee'];
        Db::startTrans();
        try {
            $order = RfidorderModel::where('orderno', '=', $orderno)->lock(true)->find();
            $userModel = new SysUserModel();
            $user = $userModel->where('userid', $order['sysuserid'])->find();
            RfidorderModel::where('orderid', '=', $order->orderid)
                ->update(['orderstatus' => RfidOrderStatusEnum::PAID,'payfee' => $total_fee,'batchno' => $transaction_id]);//2已支付
//            $orderdetail = model('Rfidorderdetail')::where('orderid', '=', $order['orderid'])->select();
//            //同步电子标签到rfid平台
//            $goodsarray = [];
//            foreach ($orderdetail as $detail){
//                $goodsmodel = model('Goods')::where('goodsid', '=', $detail['goodsid'])->find();
//                $goods1 = array(
//                    "commodity_name" => $goodsmodel['goodsname'],
//                    "commodity_grades" => $goodsmodel['spec'],
//                    "commodity_barcode" => $goodsmodel['goodsid'],
//                    "commodity_number" => $detail['rfidcount'].'',
//                    "commodity_type" => $goodsmodel['rfidtypeid']
//                );
//                array_push($goodsarray, $goods1);
//            }
//            $data = array(
//                'order_number' => $orderno,
//                'timestamp' => time(),
//                'commoditys' => $goodsarray,
//            );
//            $wxOrderData = new \RfidApi('','');
//            $result = $wxOrderData->addLabelBuyOrder($data);
//            Log::info('--------------------addLabelBuyOrder---start------------');
//            Log::info($result);
//            $decresult = json_decode($result,true);
//            if($decresult&&$decresult['code'] == 200){
//                RfidorderModel::where('orderid', '=', $order->orderid)
//                    ->update(['orderstatus' => RfidOrderStatusEnum::RECEIVED,'payfee' => $total_fee,'batchno' => $transaction_id]);//4已接收（同步到RFID）
//
//            }
//            Log::info('--------------------addLabelBuyOrder---end------------');
            //添加异步命令行发送模版消息
            $template = array(
                'cmd' => 0,
                'data' =>array(
                    'touser' => $user['openid'],
                    'template_id' => WechatTemplate::RFIDORDER,
                    'data' => array(
                        'first'=>array(
                            'value' => '下单成功，电子标签服务商正在备货请等待',
                            'color' => '#173177'
                        ),
                        'keyword1'=>array(
                            'value' => $order['createtime'],
                            'color' => '#173177'
                        ),
                        'keyword2'=>array(
                            'value' => $order['orderno'],
                            'color' => '#173177'
                        ),
                        'keyword3'=>array(
                            'value' => 'rfid订单',
                            'color' => '#173177'
                        ),
                        'keyword4'=>array(
                            'value' => $order['receiver'],
                            'color' => '#173177'
                        ),
                        'keyword5'=>array(
                            'value' => $order['mobile'],
                            'color' => '#173177'
                        ),
                        'remark'=>array(
                            'value' => '总价：'.($order['totalfee']/100).'元',
                            'color' => '#173177'
                        ),
                    )
                )
            );
            $re = WechatMessage::add(json_encode($template),"erp_options");
            //
            Db::commit();
        } catch (Exception $ex) {
            Db::rollback();
            Log::error($ex);
            // 如果出现异常，向微信返回false，请求重新发送通知
            return false;
        }
    }


}