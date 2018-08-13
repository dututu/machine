<?php
/**
 * Created by Caesar.
 */
use think\Config;
return [
    //  +---------------------------------
    //  微信相关配置
    //  +---------------------------------
    'host' => 'https://t.wemall.com.cn/',
//    'host' => 'http://192.168.1.105:7888/',
    //小程序app_id
    'app_id' => 'wx9b0e57d73efd4ba1',
    // 小程序app_secret
    'app_secret' => 'a402ff77ff770303d09349c8d5f85300',
    // 微信使用code换取用户openid及session_key的url地址
    'login_url' => "https://api.weixin.qq.com/sns/jscode2session?"."appid=%s&secret=%s&js_code=%s&grant_type=authorization_code",
    //储值支付回调
    'recharge_pay_back_url' => 'https://t.wemall.com.cn/clientapi/pay/receiveNotifyRecharge',
    //商品支付回调
    'pay_back_url' => 'https://t.wemall.com.cn/clientapi/pay/receiveNotify',
    //js支付回调
    'js_pay_back_url' => 'https://t.wemall.com.cn/clientapi/pay/receiveNotifyJS',
    //免密支付回调
    'pa_pay_back_url' => 'https://t.wemall.com.cn/clientapi/pay/receiveNotifyPapay',
    // 上货调整
    'onsalecheck' => 'wechatservice/onsale/onsalecheck',
    //微信公众平台接口调用配置
    'wxappid' => 'wxdee00ccdb3bec1a6',//公众号appid
    'wxappsecret' => '7e604ff8a49b330238daf5054786d07e',//公众号appsecret
    'planid' => '98808',//免密支付模板/协议id
    'merchanttagid' => '100',//微信个性化菜单对应的tag id
    //小程序参数数组
    'wxappconfig' => array(
        'appid' => 'wx9b0e57d73efd4ba1',
        'appsecret' => 'a402ff77ff770303d09349c8d5f85300',
        'partnerkey' => 'dfhsdjflafhadkjfoiauoieqa983eufi',
        'encodingaeskey' => '',
        'mch_id' => '1273556201'
    ),
    //公众平台参数数组
    'wxconfig' => array(
        'token' => '581a0aa1',
        'appid' => 'wxdee00ccdb3bec1a6',
        'appsecret' => '7e604ff8a49b330238daf5054786d07e',
        'encodingaeskey' => '',
        'mch_id' => '1273556201',
        'partnerkey' => 'dfhsdjflafhadkjfoiauoieqa983eufi',
//        'cachepath' => '/webserver/webdata/html/wshopserver/runtime/log',
//        'ssl_cer' => '/webserver/webdata/html/wshopserver/extend/cert/apiclient_cert.pem',
//        'ssl_key' => '/webserver/webdata/html/wshopserver/extend/cert/apiclient_key.pem',
    //
        'cachepath' => '/Users/caesar/Desktop/project/salecabinet/06代码/wshopserver/runtime/log',
        'ssl_cer' => '/Users/caesar/Desktop/project/salecabinet/06代码/wshopserver/extend/cert/apiclient_cert.pem',
        'ssl_key' => '/Users/caesar/Desktop/project/salecabinet/06代码/wshopserver/extend/cert/apiclient_key.pem',
    ),
];
