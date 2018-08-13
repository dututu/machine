<?php

namespace app\admin\service;

use think\Exception;
use think\Model;
use think\Db;
use think\Config;
use think\Log;
use think\Loader;
use app\worker\controller\WechatMessage;
use app\lib\enum\WechatTemplate;
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');

/**
 * 模板消息发送
 */
class Templatemessage
{
    //----------服务号消息-普通用户-------------
    //交易成功通知
    public function tradesuccess($data){
        # 配置参数
        $config = Config::get('wx.wxconfig');
        # 加载对应操作接口
        $wechat = &\Wechat\Loader::get('Receive', $config);
        $postArr = array(
            'touser' => 'OPENID',
            'template_id' => 'template_id',
            'url' => 'url',
            'miniprogram' => array(
                'appid' => 'wxe8eac4281b3a65d7',
                'pagepath' => 'pages/index/index',
            ),
            'data' => array(
                'first'=>array(
                    'value' => '恭喜你购买成功！',
                    'color' => '#173177'
                ),
                'keynote1'=>array(
                    'value' => '巧克力',
                    'color' => '#173177'
                ),
                'keynote2'=>array(
                    'value' => '39.8元',
                    'color' => '#173177'
                ),
                'keynote3'=>array(
                    'value' => '2014年9月22日',
                    'color' => '#173177'
                ),
                'remark'=>array(
                    'value' => '欢迎再次购买！',
                    'color' => '#173177'
                ),
            )
        );
        $wechat->sendTemplateMessage($postArr);
    }
    //订单未支付通知
    //储值余额变动通知
    //注册成功通知
    //操作提醒
    //----------服务号消息-商户-------------
    //审批结果通知 DONE
    public function sendappplyresult($openid,$applyusername){
//        # 配置参数
//        $config = Config::get('wx.wxconfig');
//        # 加载对应操作接口
//        $wechat = &\Wechat\Loader::get('Receive', $config);

        $template = array(
            'cmd' => 0,
            'data' =>array(
                'touser' => $openid,
                'template_id' => WechatTemplate::MERCHANTAPPLY,
                'data' => array(
                    'first'=>array(
                        'value' => '您好，您已经通过审批，正式成为知码开门购物柜运营商',
                        'color' => '#173177'
                    ),
                    'keyword1'=>array(
                        'value' => $applyusername,
                        'color' => '#173177'
                    ),
                    'keyword2'=>array(
                        'value' => '同意成为运营商',
                        'color' => '#173177'
                    ),
                    'keyword3'=>array(
                        'value' => Date('Y-m-d H:i:s'),
                        'color' => '#173177'
                    ),
                    'remark'=>array(
                        'value' => '',
                        'color' => '#173177'
                    ),
                )
            )
        );
        WechatMessage::add(json_encode($template),"erp_options");
//        $wechat->sendTemplateMessage($postArr);
    }

    //机柜上线通知
    //商品上架通知
    //销售通知

}
