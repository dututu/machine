<?php

namespace app\admin\service;

use think\Exception;
use think\Model;
use think\Db;
use think\Config;
use think\Log;
use think\Loader;
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');

/**
 * 公共service
 */
class Common
{
    /**
     * 生成带参数二维码，用于登录或绑定用户
     * @param sceneid 100 用户绑定 200 登录
     * @access public
     * @return tp5
     */
    public function generateParamQRCode($sceneid){
        $AppID = Config::get('wx.wxappid');
        $AppSecret = Config::get('wx.wxappsecret');
        $tokenurl = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$AppID."&secret=".$AppSecret;
        $tokenresult = doCurl($tokenurl,0,[]);
//        $tokenresult = "{\"access_token\":\"ACCESS_TOKEN\",\"expires_in\":7200}";
        Log::info($tokenresult);
        $tokenarray = json_decode($tokenresult,true);
        $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=".$tokenarray['access_token'];
//        //微信登录
//        session_start();
        $qrcode = '{"expire_seconds": 1800, "action_name": "QR_SCENE", "action_info": {"scene": {"scene_id": '.$sceneid.'}}}';
        $ch = api_notice_increment($url,$qrcode);
        Log::info($ch);
        $chresult = json_decode($ch,true);
        $url = urldecode($chresult["url"]);
        ob_start();
        $pic = \QRcode::png($url,false);
        $imageString = base64_encode(ob_get_contents());
        ob_end_clean();
        Log::info($ch);
        return [
            'imageString' =>$imageString,
            'ticket' =>$chresult['ticket']
        ];
    }
    /**
     * 将某个用户设置商户标签
     * @access public
     * @return tp5
     */
    public function addUser2WechatGroup($openid){
        # 配置参数
        $config = Config::get('wx.wxconfig');
        $user = &\Wechat\Loader::get('User', $config);
        $openid_list = array(
            0=>$openid
        );
        //批量为粉丝打标签 商户的标签id是100
        $result = $user->batchAddUserTag(Config::get('wx.merchanttagid'), $openid_list);

        //处理结果
        if($result===FALSE){
            //接口失败的处理
            echo $user->errMsg;
        }else{
            //接口成功的处理
        }
    }
    /**
     * 移除某个用户的商户标签
     * @access public
     * @return tp5
     */
    public function removeUser2WechatGroup($openid){
        # 配置参数
        $config = Config::get('wx.wxconfig');
        $user = &\Wechat\Loader::get('User', $config);
        $openid_list = array(
            0=>$openid
        );
        //批量为粉丝打标签 商户的标签id是100
        $result = $user->batchDeleteUserTag(Config::get('wx.merchanttagid'), $openid_list);

        //处理结果
        if($result===FALSE){
            //接口失败的处理
            echo $user->errMsg;
        }else{
            //接口成功的处理
        }
    }
}
