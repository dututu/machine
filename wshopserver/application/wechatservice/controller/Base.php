<?php
namespace app\wechatservice\controller;
use think\Controller;
use think\Log;
use think\Loader;
use think\Config;
use think\Request;
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');

/**
 * 基类base
 *
 * @author      Caesar
 * @version     1.0
 */
class Base extends  Controller
{
    public function _initialize() {


    }
    /**
     * 验证商户
     */
    protected function checkMerchant()
    {
        $openid = session('openidtest', '', 'wechatservice');
        if($openid) {
            $sysuser = model('Sysuser')::where('openid', $openid)->find();
            if($sysuser){
                $merchantid = $sysuser['merchantid'];
                $merchnat = model('Merchant')::where('merchantid', $merchantid)->find();
                if($merchnat&&$merchnat['status'] == 2){

                }else{
                    $this->error('商户审核中或停用');
                }

            }else{
                $this->error('用户不存在');
            }

        }
    }
    public function checkSession(){
        $request = Request::instance();
        $url = $request->url(true);
        $openid = session('openid', '', 'wechatservice');
//        $openid = false;
//        session('openid',null);
//        return;
        if(!$openid){
            Log::info('-----check session not exist-----'.$url);
            $code = input("code");
            if($code){
                # 配置参数
                $config = Config::get('wx.wxconfig');
                $oauth = &\Wechat\Loader::get('Oauth', $config);
                $accresult = $oauth->getOauthAccessToken();
                Log::info($accresult);
                if($accresult===FALSE){
                    // 接口失败的处理
                    $this->error('获取验证信息失败，请重试');
//                    return false;
                }else{
                    // 接口成功的处理
                    $access_token = $accresult['access_token'];
                    $openid = $accresult['openid'];
                    $unionid = $accresult['unionid'];
                    session('openid', $openid, 'wechatservice');
                    // 获取用户信息
                    $inforesult = $oauth->getOauthUserinfo($access_token, $openid);
                    // 处理返回结果
                    if($inforesult===FALSE){
                        // 接口失败的处理
                        $this->error('获取用户信息失败，请重试');
//                        return $this->redirect('index');
                    }else{
                        // 接口成功的处理
                        $sysuser = model('Sysuser')->where('openid', $openid)->find();
                        if($sysuser){
                            $sysuser->unionid     = $unionid;
                            $sysuser->avatar    = $inforesult['headimgurl'];
                            $sysuser->gender    = $inforesult['sex'];
                            $sysuser->nickname    = removeEmoji($inforesult['nickname']);
                            $sysuser->location    = $inforesult['country'].'-'.$inforesult['province'].'-'.$inforesult['city'];
                            $sysuser->save();
                        }else{
                            $location = $inforesult['country'].'-'.$inforesult['province'].'-'.$inforesult['city'];
                            session('unionid', $unionid, 'wechatservice');
                            session('avatar', $inforesult['headimgurl'], 'wechatservice');
                            session('gender', $inforesult['sex'], 'wechatservice');
                            session('nickname', removeEmoji($inforesult['nickname']), 'wechatservice');
                            session('location', $location, 'wechatservice');
                        }
                    }
//                    return $this->redirect('index');
                    return $this->redirect($url);
                }
            }else{
                # 配置参数
                $config = Config::get('wx.wxconfig');
                $oauth = &\Wechat\Loader::get('Oauth', $config);
                // 执行接口操作
//                $callback = Config::get('wx.host').'wechatservice/goods';
                $callback = $url;
                $state = 12345;
                $scope = 'snsapi_userinfo';//snsapi_userinfo
                $result = $oauth->getOauthRedirect($callback, $state, $scope);
                Log::info($result);
                // 处理返回结果
                if($result===FALSE){
                    // 接口失败的处理
                    return false;
                }else{
                    // 接口成功的处理
//            array (
//                'code' => '061BlsM01nfrj02ozuN011dcM01BlsMC',
//                'state' => '1234',
//            )
                    return $this->redirect($result);
                }
            }
        }else{
            $this->checkMerchant();
        }

    }
    protected function getMerchantIdByOpenId($openid){
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        return $sysuser['merchantid'];
    }
    protected function getUserIdByOpenId($openid){
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        return $sysuser['userid'];
    }
}
