<?php
/**
 * Created by Caesar.
 */

namespace app\clientapi\service;

use app\common\model\Rechargeorder as RechargeorderModel;
use think\Db;
use think\Exception;
use think\Loader;
use think\Log;
use app\worker\controller\WechatMessage;
use app\lib\enum\WechatTemplate;


class WxNotifyRecharge
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
            $order = RechargeorderModel::where('orderno', '=', $orderno)->lock(true)->find();
            $this->updateOrderStatus($total_fee,$transaction_id,$order->orderid, true);
            //发送储值余额变动通知
            $user = model('User')->where('userid',$order['userid'])->find();
            $template = array(
                'cmd' => 0,
                'data' =>array(
                    'touser' => $user['openid'],
                    'template_id' => WechatTemplate::REHARGECHANGE,
                    'data' => array(
                        'first'=>array(
                            'value' => '尊敬的用户，您的储值余额产生了变动：',
                            'color' => '#173177'
                        ),
                        'keyword1'=>array(
                            'value' => '微信支付充值',
                            'color' => '#173177'
                        ),
                        'keyword2'=>array(
                            'value' => '+'.($order['realfee']/100).'元',
                            'color' => '#173177'
                        ),
                        'keyword3'=>array(
                            'value' => ($user['fee']/100).'元',
                            'color' => '#173177'
                        ),
                        'remark'=>array(
                            'value' => '感谢您的支持，欢迎再次光临',
                            'color' => '#173177'
                        ),
                    )
                )
            );
            WechatMessage::add(json_encode($template),"erp_options");
            Db::commit();
        } catch (Exception $ex) {
            Db::rollback();
            Log::error($ex);
            // 如果出现异常，向微信返回false，请求重新发送通知
            return false;
        }
    }


    private function updateOrderStatus($total_fee,$transaction_id,$orderID, $success)
    {

        RechargeorderModel::where('orderid', '=', $orderID)
            ->update(['status' => 2,'batchno' => $transaction_id]);
        //
    }
}