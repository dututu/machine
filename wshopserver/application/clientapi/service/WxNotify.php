<?php
/**
 * Created by Caesar.
 * 小程序订单微信支付回调
 */

namespace app\clientapi\service;


use app\lib\enum\GoodsOrderStatusEnum;
use app\common\model\Goodsorder as GoodsorderModel;
use app\common\model\Formmessage as MessageModel;
use app\worker\controller\WechatMessage;
use app\common\model\User as UserModel;
use app\common\model\Goodsorderpay as GoodsorderpayModel;
//use app\lib\order\OrderStatus;
use think\Db;
use think\Exception;
use think\Loader;
use think\Log;


class WxNotify
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
//1. 检查订单是否存在
//2. 检查金额是否正确(not)
//3. 检查订单是否已经处理过（防止重复通知）
//4. 更新订单
    public function NotifyProcess($notifyInfo)
    {
        $result_code = $notifyInfo['result_code'];//
        $transaction_id = $notifyInfo['transaction_id'];//微信支付订单号
        $orderno = $notifyInfo['out_trade_no'];//商户订单号
        $total_fee = $notifyInfo['total_fee'];//订单金额
        Log::info("----微信支付回调----");
        Log::info($notifyInfo);
        try {
            $order = GoodsorderModel::where('orderno', '=', $orderno)->find();
            if(empty($order)){
                Log::info("----微信支付回调-没有此订单号---");
                return false;
            }
//            if($order['orderstatus'] == GoodsOrderStatusEnum::PAID){
//                Log::info("----微信支付回调-订单已支付---");
//                return false;
//            }
            if($result_code == 'SUCCESS'){
                $userModel = new UserModel();
                $user = $userModel->where('userid', $order['userid'])->find();
                $user->save([
                    'havearrears' => 0
                ], ['userid' => $order['userid']]);
                $orderstatus = $order['orderstatus'];
                GoodsorderModel::where('orderid', '=', $order['orderid'])->update(['orderstatus' => GoodsOrderStatusEnum::PAID,'payfee' => $total_fee]);
                //
                if($orderstatus == GoodsOrderStatusEnum::ARREARAGED){
                    $orderPayModel = new GoodsorderpayModel();
                    $orderPay = $orderPayModel->where('orderid', $order['orderid'])->find();
                    if($orderPay){
                        $orderPay['batchno'] = $transaction_id;
                        $orderPay['payfee'] = $total_fee;
                        $orderPay['paytime'] = Date('Y-m-d H:i:s');
                        $orderPay['paystatus'] = 1;
                        $orderPay['paytype'] = 2;
                        $orderPay->save();
                    }else{
                        $orderPay = new GoodsorderpayModel();
                        $orderPay['orderpayid'] = uuid();
                        $serailno = uuid();
                        $orderPay['serialno'] = $serailno;
                        $orderPay['batchno'] = $transaction_id;
                        $orderPay['orderid'] = $order['orderid'];
                        $orderPay['payfee'] = $total_fee;
                        $orderPay['paytime'] = Date('Y-m-d H:i:s');
                        $orderPay['paystatus'] = 1;
                        $orderPay['paytype'] = 2;
                        $orderPay->save();
                    }

                }else{
                    $orderPay = new GoodsorderpayModel();
                    $orderPay['orderpayid'] = uuid();
                    $serailno = uuid();
                    $orderPay['serialno'] = $serailno;
                    $orderPay['batchno'] = $transaction_id;
                    $orderPay['orderid'] = $order['orderid'];
                    $orderPay['payfee'] = $total_fee;
                    $orderPay['paytime'] = Date('Y-m-d H:i:s');
                    $orderPay['paystatus'] = 1;
                    $orderPay['paytype'] = 2;
                    $orderPay->save();
                }
                //发送模板消息
                //更新模版消息记录
                $message = MessageModel::where('orderid', $order['orderid'])->where('userid', $order['userid'])->find();
                if ($message) {
                    $goodsnames = '';
                    $goods = model('Goodsorderdetail')->where('orderid', $order['orderid'])->select();
                    foreach ($goods as $subgoods) {
                        $goodsnames = $goodsnames . $subgoods['goodsname'] . '*' . $subgoods['amount'] . ';';
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
                        'data' => array(
                            'touser' => $openid,
                            'template_id' => $template_id,
                            'page' => $page,
                            'form_id' => $form_id,
                            'data' => array(
                                'keyword1' => array(
                                    'value' => $keyword1
                                ),
                                'keyword2' => array(
                                    'value' => $keyword2
                                ),
                                'keyword3' => array(
                                    'value' => $keyword3
                                ),
                                'keyword4' => array(
                                    'value' => $keyword4
                                ),
                                'keyword5' => array(
                                    'value' => $keyword5
                                )
                            )
                        )
                    );
                    $re = WechatMessage::add(json_encode($template), "erp_options");
                    //
                    $message->sendstatus = 1;
                    $message->templatedata = json_encode($template['data']);
                    $message->save();
                    //
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
            }else{
                Log::info("----微信支付失败----");
                Log::info($notifyInfo);
                $userModel = new UserModel();
                $user = $userModel->where('userid', $order['userid']) ->find();
                $user->save([
                    'havearrears'  => 1
                ],['userid' => $order['userid']]);
                GoodsorderModel::where('orderid', '=', $order['orderid'])->update(['orderstatus' => GoodsOrderStatusEnum::ARREARAGED]);
            }

        } catch (Exception $ex) {
            // 如果出现异常，向微信返回false，请求重新发送通知
            Log::info("----微信支付失败 have Exception----");
            return false;
        }
    }


}