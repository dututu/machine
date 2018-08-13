<?php
/**
 * Created by Caesar.
 */

namespace app\clientapi\service;

//use app\api\model\Order;
//use app\api\model\Product;
//use app\api\service\Order as OrderService;
use app\lib\enum\GoodsOrderStatusEnum;
use app\common\model\Goodsorder as GoodsorderModel;
use app\common\model\Goodsorderpay as GoodsorderpayModel;
use app\common\model\User as UserModel;
use app\common\model\Formmessage as MessageModel;
use app\worker\controller\WechatMessage;
//use app\lib\order\OrderStatus;
use think\Db;
use think\Exception;
use think\Loader;
use think\Log;


class WxNotifyPapay
{
//<xml>
//<appid><![CDATA[wx2421b1c4370ec43b]]></appid>
//<attach><![CDATA[支付测试]]></attach>
//<bank_type><![CDATA[CFT]]></bank_type>
//<fee_type><![CDATA[CNY]]></fee_type>
//<is_subscribe><![CDATA[Y]]></is_subscribe>
//<mch_id><![CDATA[10000100]]></mch_id>
//<nonce_str><![CDATA[5d2b6c2a8db53831f7eda20af46e531c]]></nonce_str>
//<openid><![CDATA[oUpF8uMEb4qRXf22hE3X68TekukE]]></openid>
//<out_trade_no><![CDATA[1409811653]]></out_trade_no>
//<result_code><![CDATA[SUCCESS]]></result_code>
//<return_code><![CDATA[SUCCESS]]></return_code>
//<sign><![CDATA[B552ED6B279343CB493C5DD0D78AB241]]></sign>
//<sub_mch_id><![CDATA[10000100]]></sub_mch_id>
//<time_end><![CDATA[20140903131540]]></time_end>
//<total_fee>1</total_fee>
//<transaction_id><![CDATA[1004400740201409030005092168]]></transaction_id>
//<contract_id><![CDATA[Wx15463511252015071056489715]]></contract_id>
//</xml>
    //文档 https://pay.weixin.qq.com/wiki/doc/api/pap.php?chapter=18_7&index=9
    public function NotifyProcess($notifyInfo)
    {
        Log::info($notifyInfo);
//        $openid = $notifyInfo['openid'];
        //SUCCESS—支付成功
        //REFUND—转入退款
        //NOTPAY—未支付
        //CLOSED—已关闭
        //ACCEPT—已接收，等待扣款
        //PAY_FAIL--支付失败(其他原因，如银行返回失败)
        $orderno = $notifyInfo['out_trade_no'];//商户订单号
        $order = GoodsorderModel::where('orderno', '=', $orderno)->find();
        if ($notifyInfo['result_code'] == 'SUCCESS' && $notifyInfo['return_code'] == 'SUCCESS') {
            $transaction_id = $notifyInfo['transaction_id'];//微信支付订单号
            $total_fee = $notifyInfo['total_fee'];
            $trade_state = $notifyInfo['trade_state'];//只有resultcode为success时才有这个字段
            if($trade_state == 'SUCCESS'){
                try {
                    $userModel = new UserModel();
                    $user = $userModel->where('userid', $order['userid']) ->find();
                    $user->save([
                        'havearrears'  => 0
                    ],['userid' => $order['userid']]);
                    //更新订单状态
                    GoodsorderModel::where('orderid', '=', $order['orderid'])->update(['orderstatus' => GoodsOrderStatusEnum::PAID,'payfee' => $total_fee]);
                    //
                    $orderPay = new GoodsorderpayModel();
                    $orderPay['orderpayid'] = uuid();
                    $serailno = uuid();
                    $orderPay['serialno'] = $serailno;
                    $orderPay['batchno'] = $transaction_id;
                    $orderPay['orderid'] = $order['orderid'];
                    $orderPay['payfee'] = $total_fee;
                    $orderPay['paytime'] = Date('Y-m-d H:i:s');
                    $orderPay['paystatus'] = 1;
                    $orderPay['paytype'] = 1;
                    $orderPay->save();
                    //发送模板消息
                    //更新模版消息记录
                    $message = MessageModel::where('orderid',$order['orderid'])->where('userid',$order['userid'])->find();
                    if($message){
                        $goodsnames = '';
                        $goods = model('Goodsorderdetail')->where('orderid', $order['orderid'])->select();
                        foreach ($goods as $subgoods) {
                            $goodsnames = $goodsnames.$subgoods['goodsname'].'*'.$subgoods['amount'].';';
                        }
                        $openid = $user['openid'];
                        $template_id = $message['templateid'];
                        $page = 'pages/index/index';
                        $form_id = $message['formid'];
                        $keyword1 = $order['orderno'];
                        $keyword2 = '购买成功';
                        $keyword3 = $goodsnames;
                        $keyword4 = (($order['totalfee'])/100).'元';
                        $keyword5 = $order['createtime'];
                        //发送模板消息
                        $template = array(
                            'cmd' => 1,
                            'data' =>array(
                                'touser' => $openid,
                                'template_id' => $template_id,
                                'page' => $page,
                                'form_id' => $form_id,
                                'data' => array(
                                    'keyword1'=>array(
                                        'value' => $keyword1
                                    ),
                                    'keyword2'=>array(
                                        'value' => $keyword2
                                    ),
                                    'keyword3'=>array(
                                        'value' => $keyword3
                                    ),
                                    'keyword4'=>array(
                                        'value' => $keyword4
                                    ),
                                    'keyword5'=>array(
                                        'value' => $keyword5
                                    )
                                )
                            )
                        );
                        $re = WechatMessage::add(json_encode($template),"erp_options");
                        //
                        $message->sendstatus     = 1;
                        $message->templatedata    = json_encode($template['data']);
                        $message->save();
                        //给商户发送客服消息
                        $machine = model('Machine')->where('machineid',$order['machineid'])->find();
                        $sysuser = model('Sysuser')->where('merchantid',$machine['merchantid'])->find();
                        $user = model('User')->where('userid',$order['userid'])->find();
                        $goodstr = "";
                        $sgoods = model('Goodsorderdetail')->where('orderid', $order['orderid']) ->select();
                        foreach ($sgoods as $subgoods) {
                            $goodstr = $goodstr.$subgoods['goodsname']."(数量:".$subgoods['amount'].")；";
                        }
                        $template = array(
                            'cmd' => 3,
                            'data' =>array(
                                'touser' => $sysuser['openid'],
                                'msgtype' => "text",
                                'text'=>array(
                                    'content' => $machine['location']."购物柜刚刚产生了一笔交易：\n操作时间：".$order['createtime']."\n用户昵称：".$user['nickname']." ".$user['mobile']."\n购买商品：".$goodstr."\n交易金额：".($total_fee/100)."元"
                                )
                            )
                        );
                        $re = WechatMessage::add(json_encode($template),"erp_options");
                    }
                } catch (Exception $ex) {
                    Log::error($ex);
                    // 如果出现异常，向微信返回false，请求重新发送通知
                    Log::info("----免密支付失败 have Exception----");
                    Log::info($notifyInfo);
                    $userModel = new UserModel();
                    $user = $userModel->where('userid', $order['userid']) ->find();
                    $user->save([
                        'havearrears'  => 1
                    ],['userid' => $order['userid']]);
                    GoodsorderModel::where('orderid', '=', $order['orderid'])->update(['orderstatus' => GoodsOrderStatusEnum::ARREARAGED]);
                    return false;
                }
            }else{
                Log::info("----免密支付失败----");
                Log::info($notifyInfo);
                $userModel = new UserModel();
                $user = $userModel->where('userid', $order['userid']) ->find();
                $user->save([
                    'havearrears'  => 1
                ],['userid' => $order['userid']]);
                GoodsorderModel::where('orderid', '=', $order['orderid'])->update(['orderstatus' => GoodsOrderStatusEnum::ARREARAGED]);
            }
        } else {
            Log::info("----免密支付失败----".$notifyInfo['err_code_des']);
            Log::info($notifyInfo);
            $userModel = new UserModel();
            $user = $userModel->where('userid', $order['userid']) ->find();
            $user->save([
                'havearrears'  => 1
            ],['userid' => $order['userid']]);
            GoodsorderModel::where('orderid', '=', $order['orderid'])->update(['orderstatus' => GoodsOrderStatusEnum::ARREARAGED]);

        }


    }

}