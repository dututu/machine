<?php
/**
 * Created by Caesar.
 */

namespace app\clientapi\controller;

use app\clientapi\service\WxNotify;
use app\clientapi\service\WxNotifyJS;
use app\clientapi\service\WxNotifyPapay;
use app\clientapi\service\WxNotifyRecharge;
use app\clientapi\validate\PayValidate;
use app\clientapi\service\Token;
use think\Controller;
use think\Log;
use \think\Request;
use \think\Loader;
use \think\Config;
use app\common\model\User as UserModel;
use app\common\model\Rechargeorder as RechargeOrderModel;
use app\common\model\Goodsorder as GoodsorderModel;
use app\common\model\Goods as GoodsModel;
use app\common\model\Rfidorder as RfidOrderModel;
use app\common\model\Rfidorderdetail as RfidOrderDetailModel;
use app\common\model\Rfidspec as RfidSpecModel;

Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');

/**
 * 支付相关类
 *
 */
class Pay extends Controller
{
    protected $beforeActionList = [
        'checkExclusiveScope' => ['only' => 'getPreOrder']
    ];
    //
    /**
     * 小程序储值-统一下单
     * @param
     * @return wx
     */
    public function getPreRechargeOrder()
    {
        $orderid = input('post.orderid');
//        (new PayValidate()) -> goCheck();
        $uid = Token::getCurrentUid();
        $user = UserModel::get($uid);
        if(!$user) {
            return status(0,"用户不存在");
        }
        $order = RechargeOrderModel::get($orderid);
        # 配置参数
        $config = Config::get('wx.wxappconfig');
        // 实例支付接口
        $pay = &\Wechat\Loader::get('Pay',$config);
        // 获取预支付ID
        $openid = $user['openid'];
        $body = '知码开门';
        $out_trade_no = $order['orderno'];
        $total_fee = $order['fee'];
        $notify_url = config('wx.recharge_pay_back_url');
        $result = $pay->getPrepayId($openid, $body, $out_trade_no, $total_fee, $notify_url, $trade_type = "JSAPI");

        // 处理创建结果
        if($result===FALSE){
            // 接口失败的处理
            $msg = $pay->errMsg;
            return result(201,$msg);

        }else{
            // 创建JSAPI签名参数包
            $payoptions = $pay->createMchPay($result);//$prepayid
            return result(200,'success',$payoptions);
        }

    }

    /**
     * 小程序获取免密支付参数
     * @param
     * @return wx
     */
    public function getPapayExtraData(){
        $uid = Token::getCurrentUid();
        if($uid){
            $user = UserModel::get($uid);
            $name = $user['nickname'];
            if(!$name){
                $name='小程序用户';
            }
            # 配置参数
            $config = Config::get('wx.wxappconfig');
            // 实例支付接口
            $pay = &\Wechat\Loader::get('Pay',$config);
            $notifyurl = Config::get('wx.host')."admin/wechat/papay";
            $params = $pay->createPaypaParams($name,$notifyurl,Config::get('wx.planid'));
            return $params;
        }else{
            return '';
        }

    }

    /**
     * 小程序商品订单支付参数
     * @param
     * @return wx
     */
    public function getPreGoodsOrder()
    {
        $orderid = input('post.orderid');
//        (new PayValidate()) -> goCheck();
        $request = Request::instance();
        $uid = Token::getCurrentUid();
        $user = UserModel::get($uid);
        if(!$user) {
            return status(0,"用户不存在");
        }
        $order = GoodsorderModel::get($orderid);
        # 配置参数
        $config = Config::get('wx.wxappconfig');
        // 实例支付接口
        $pay = &\Wechat\Loader::get('Pay',$config);
        // 获取预支付ID
        $openid = $user['openid'];
        $body = '知码开门';
        $out_trade_no = $order['orderno'];
        $total_fee = $order['totalfee'];
        $notify_url = config('wx.pay_back_url');
        $result = $pay->getPrepayId($openid, $body, $out_trade_no, $total_fee, $notify_url, $trade_type = "JSAPI");

        // 处理创建结果
        if($result===FALSE){
            // 接口失败的处理
            $msg = $pay->errMsg;
            return result(201,$msg);

        }else{
            // 创建JSAPI签名参数包
            $payoptions = $pay->createMchPay($result);//$prepayid
            return result(200,'success',$payoptions);
        }
    }
    /**
     * 小程序商品订单微信支付回调
     * @param
     * @return wx
     */
    public function receiveNotify()
    {
        # 配置参数
        $config = Config::get('wx.wxappconfig');
        // 实例支付接口
        $pay = &\Wechat\Loader::get('Pay',$config);
        // 获取支付通知
        $notifyInfo = $pay->getNotify();
        // 支付通知数据获取失败
        if($notifyInfo===FALSE){
            // 接口失败的处理
            echo $pay->errMsg;
        }else{
            //支付通知数据获取成功
            if ($notifyInfo['result_code'] == 'SUCCESS' && $notifyInfo['return_code'] == 'SUCCESS') {
                // 支付状态完全成功，可以更新订单的支付状态了
                $notify = new WxNotify();
                $notify->NotifyProcess($notifyInfo);
                // 回复xml，replyXml方法是终态方法
                $pay->replyXml(['return_code' => 'SUCCESS', 'return_msg' => 'DEAL WITH SUCCESS']);
            }
        }


    }
    /**
     * 小程序储值微信支付回调
     * @param
     * @return wx
     */
    public function receiveNotifyRecharge()
    {
        # 配置参数
        $config = Config::get('wx.wxappconfig');
        // 实例支付接口
        $pay = &\Wechat\Loader::get('Pay',$config);
        // 获取支付通知
        $notifyInfo = $pay->getNotify();
        // 支付通知数据获取失败
        if($notifyInfo===FALSE){
            // 接口失败的处理
            echo $pay->errMsg;
        }else{
            //支付通知数据获取成功
            if ($notifyInfo['result_code'] == 'SUCCESS' && $notifyInfo['return_code'] == 'SUCCESS') {
                // 支付状态完全成功，可以更新订单的支付状态了
                $notify = new WxNotifyRecharge();
                $notify->NotifyProcess($notifyInfo);
                // 回复xml，replyXml方法是终态方法
                $pay->replyXml(['return_code' => 'SUCCESS', 'return_msg' => 'DEAL WITH SUCCESS']);
            }
        }
    }
    /**
     * b端rfid订单微信支付回调
     * @param
     * @return wx
     */
    public function receiveNotifyJS()
    {
        # 配置参数
        $config = Config::get('wx.wxconfig');
        // 实例支付接口
        $pay = &\Wechat\Loader::get('Pay',$config);
        // 获取支付通知
        $notifyInfo = $pay->getNotify();
        // 支付通知数据获取失败
        if($notifyInfo===FALSE){
            // 接口失败的处理
            echo $pay->errMsg;
        }else{
            //支付通知数据获取成功
            if ($notifyInfo['result_code'] == 'SUCCESS' && $notifyInfo['return_code'] == 'SUCCESS') {
                // 支付状态完全成功，可以更新订单的支付状态了
                $notify = new WxNotifyJS();
                $notify->NotifyProcess($notifyInfo);
                // 回复xml，replyXml方法是终态方法
                $pay->replyXml(['return_code' => 'SUCCESS', 'return_msg' => 'DEAL WITH SUCCESS']);
            }
        }
    }
    /**
     * 小程序免密支付回调
     * @param
     * @return wx
     */
    public function receiveNotifyPapay()
    {
        # 配置参数
        $config = Config::get('wx.wxappconfig');
        // 实例支付接口
        $pay = &\Wechat\Loader::get('Pay',$config);
        // 获取支付通知
        $notifyInfo = $pay->getNotify();
        // 支付通知数据获取失败
        if($notifyInfo===FALSE){
            // 接口失败的处理
            echo $pay->errMsg;
        }else{
            //支付通知数据获取成功
//            if ($notifyInfo['result_code'] == 'SUCCESS' && $notifyInfo['return_code'] == 'SUCCESS') {
                // 支付状态完全成功，可以更新订单的支付状态了
                $notify = new WxNotifyPapay();
                $notify->NotifyProcess($notifyInfo);
                // 回复xml，replyXml方法是终态方法
                $pay->replyXml(['return_code' => 'SUCCESS', 'return_msg' => 'DEAL WITH SUCCESS']);
//            }
        }
    }

}