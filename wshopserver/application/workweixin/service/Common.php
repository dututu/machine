<?php

namespace app\workweixin\service;

use think\Exception;
use think\Model;
use think\Db;
use think\Config;
use think\Log;
use think\Loader;
use app\base\controller\Redis;
use Wechat\Lib\Tools;

/**
 * 公共service
 */
class Common
{
    protected $token;
    protected $ep_secret;

    public function __construct( $ep_secret )
    {
        $this->ep_secret = $ep_secret;
    }

    /**
     * 获取token 每个应用的secret不一样 获取的token不一样
     *
     * @param sceneid 100 用户绑定 200 登录
     *
     * @access public
     * @return tp5
     */
    public function getToken()
    {
        //应用secret
        $ep_secret = $this->ep_secret;
        $redis = new Redis();
        //redis
        $redis_key = "work_token_" . $ep_secret;
        $this->token = $redis->get( $redis_key );
        if( $this->token )
            return $this->token;

        $ep_cropid = Config::get( 'cropid' );
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=' . $ep_cropid . '&corpsecret=' . $ep_secret;
        $res = doCurl( $url,0 );
//        $res = '{
//                "errcode": 0,
//                "errmsg": "ok",
//                "access_token": "wQakJyd27Cbp5PJ8FP82z9cVVPm-AX4eJqor1Se4mjKeEGsGu5ObMiAGcGy3OSh7epGFipnmbiYA6IOG6r44Y5ee9co0qzFydoTDvjwEesikl-ktUUaFn-VoDSGN-wjSUOulIj6QaVBWk_fz8OZuo4gp-Q2q4Bw5nP2-hTRd9IhBWBteZNSZUxIvjWgwSiQumi-rgSZyJo6-sLKSu0QFfQ",
//                "expires_in": 7200
//                }';
        if( $res ) {
            $result = json_decode( $res,true );
            if( $result['errcode'] == 0 ) {
                $redis->set( $redis_key,$result['access_token'],$result['expires_in'] - 100 );
                $this->token = $result['access_token'];
            }

        } else {
            Log::error( '获取token失败:' . $res );
        }
        return $this->token;
    }


    public function getUserInfo( $userid )
    {
        $token = $this->getToken();
        $url = "https://qyapi.weixin.qq.com/cgi-bin/user/get?access_token=$token&userid=$userid";
        $res = Tools::httpGet( $url );
        if( $res ) {
            $result = json_decode( $res,true );
        } else {
            $this->loglog( $res );
            throw new Exception( '获取用户信息失败' );
        }

        return $result;
    }

    /**
     * @param $code
     *
     * @return mixed
     */
    public function getUserTicketByCode( $code )
    {
        $token = $this->getToken();
        $url = "https://qyapi.weixin.qq.com/cgi-bin/user/getuserinfo?access_token=$token&code=$code";
        $res = Tools::httpGet($url);
        if( $res )
            $result = json_decode( $res,true );
        return $result;
    }

    /**
     * @param $code
     *
     * @return mixed
     */
    public function getUserIdByTicket( $userTicket )
    {
        $token = $this->getToken();
        $url = "https://qyapi.weixin.qq.com/cgi-bin/user/getuserdetail?access_token=$token";
        $data['user_ticket'] = $userTicket;
        $data = json_encode( $data,JSON_UNESCAPED_UNICODE );
        $res = Tools::httpsPost( $url,$data );
        if( $res ) {
            $result = json_decode( $res,true );
        } else {
            $this->loglog( $res );
            throw new Exception( '获取信息失败' );
        }
        return $result;
    }

    public function getTagByTagId($tagid){
        $token = $this->getToken();
        $url = "https://qyapi.weixin.qq.com/cgi-bin/tag/get?access_token=$token&tagid=$tagid";
        $res = Tools::httpGet($url);
        if( $res ) {
            $result = json_decode( $res,true );
        } else {
            $this->loglog( $res );
            throw new Exception( '获取标签信息失败' );
        }
        return $result;
    }




    /**
     * 记录错误日志
     * @param $res
     */
    public function loglog( $res )
    {
        Log::error( __CLASS__ . '方法' . __FUNCTION__ . ' 接口返回错误' . $res );
    }


}
