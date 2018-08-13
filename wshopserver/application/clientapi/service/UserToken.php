<?php

namespace app\clientapi\service;

use app\common\model\User as UserModel;
use app\lib\enum\ScopeEnum;
use app\lib\exception\TokenException;
use app\lib\exception\WeChatException;
use think\Exception;
use think\Model;
use think\Db;
use think\Request;
use think\Log;
use think\Loader;
use app\worker\controller\WechatMessage;
use app\lib\enum\WechatTemplate;

Loader::import('wechat.WxBizDataCrypt', EXTEND_PATH, '.php');
/**
 * 微信登录
 * 如果担心频繁被恶意调用，请限制ip
 * 以及访问频率
 */
class UserToken extends Token
{
    protected $code;
    protected $wxLoginUrl;
    protected $wxAppID;
    protected $wxAppSecret;
    protected $encryptedData;
    protected $iv;
    protected $mobile;

    function __construct($code,$encryptedData,$iv,$mobile)
    {
        $this->code = $code;
        $this->encryptedData = $encryptedData;
        $this->mobile = $mobile;
        $this->iv = $iv;
        $this->wxAppID = config('wx.app_id');
        $this->wxAppSecret = config('wx.app_secret');
        $this->wxLoginUrl = sprintf(
            config('wx.login_url'), $this->wxAppID, $this->wxAppSecret, $this->code);
    }

    
    /**
     * 登陆
     * 思路1：每次调用登录接口都去微信刷新一次session_key，生成新的Token，不删除久的Token
     * 思路2：检查Token有没有过期，没有过期则直接返回当前Token
     * 思路3：重新去微信刷新session_key并删除当前Token，返回新的Token
     */
    public function get()
    {
        $result = curl_get($this->wxLoginUrl);

        // 注意json_decode的第一个参数true
        // 这将使字符串被转化为数组而非对象

        $wxResult = json_decode($result, true);
        Log::info($wxResult);
        if (empty($wxResult)) {
            // 为什么以empty判断是否错误，这是根据微信返回
            // 规则摸索出来的
            // 这种情况通常是由于传入不合法的code
            throw new Exception('获取session_key及openID时异常，微信内部错误');
        }
        else {
            // 建议用明确的变量来表示是否成功
            // 微信服务器并不会将错误标记为400，无论成功还是失败都标记成200
            // 这样非常不好判断，只能使用errcode是否存在来判断
            $loginFail = array_key_exists('errcode', $wxResult);
            if ($loginFail) {
                $this->processLoginError($wxResult);
            }
            else {
                return $this->grantToken($wxResult);
            }
        }
    }
    public function checkUser()
    {
        $result = curl_get($this->wxLoginUrl);

        // 注意json_decode的第一个参数true
        // 这将使字符串被转化为数组而非对象

        $wxResult = json_decode($result, true);
        Log::info($wxResult);
        if (empty($wxResult)) {
            // 为什么以empty判断是否错误，这是根据微信返回
            // 规则摸索出来的
            // 这种情况通常是由于传入不合法的code
            throw new Exception('获取session_key及openID时异常，微信内部错误');
        }
        else {
            // 建议用明确的变量来表示是否成功
            // 微信服务器并不会将错误标记为400，无论成功还是失败都标记成200
            // 这样非常不好判断，只能使用errcode是否存在来判断
            $loginFail = array_key_exists('errcode', $wxResult);
            if ($loginFail) {
                $this->processLoginError($wxResult);
            }
            else {
                return $this->grantToken2($wxResult);
            }
        }
    }
    // 判断是否重复获取
    private function duplicateFetch(){
       //TODO:目前无法简单的判断是否重复获取，还是需要去微信服务器去openid
        //TODO: 这有可能导致失效行为 
    }

    // 处理微信登陆异常
    // 那些异常应该返回客户端，那些异常不应该返回客户端
    // 需要认真思考
    private function processLoginError($wxResult)
    {
        throw new WeChatException(
            [
                'msg' => $wxResult['errmsg'],
                'errorCode' => $wxResult['errcode']
            ]);
    }

    // 写入缓存
    private function saveToCache($wxResult)
    {
        $key = self::generateToken();
        $value = json_encode($wxResult);
        $expire_in = 0;
        $result = cache($key, $value, $expire_in);

        if (!$result){
            throw new TokenException([
                'msg' => '服务器缓存异常',
                'errorCode' => 10005
            ]);
        }
        return $key;
    }

    // 颁发令牌
    // 只要调用登陆就颁发新令牌
    // 但旧的令牌依然可以使用
    // 所以通常令牌的有效时间比较短
    // 目前微信的express_in时间是7200秒
    // 在不设置刷新令牌（refresh_token）的情况下
    // 只能延迟自有token的过期时间超过7200秒（目前还无法确定，在express_in时间到期后
    // 还能否进行微信支付
    // 没有刷新令牌会有一个问题，就是用户的操作有可能会被突然中断
    private function grantToken($wxResult)
    {
        // 此处生成令牌使用的是TP5自带的令牌
        //        $token = Request::instance()->token('token', 'md5');
        $openid = $wxResult['openid'];
        $session_key = $wxResult['session_key'];
        $unionid = '';
        if(array_key_exists('unionid',$wxResult)){
            $unionid = $wxResult['unionid'];
            Log::info($unionid);
        }
        if(empty($this->mobile)){
            //get mobile
            $da = new \WXBizDataCrypt($this->wxAppID,$session_key);
            $errCode = $da->decryptData($this->encryptedData, $this->iv, $resultdata);
            Log::info($errCode);
            if ($errCode != 0) {
                //'解密数据失败！'
                throw new TokenException([
                    'msg' => '解密数据失败',
                    'errorCode' => 10005
                ]);
            }
            $resultjson = json_decode($resultdata,true);
            Log::info($resultjson);
            $mobile = $resultjson['phoneNumber'];
            //get mobile end
        }else{
            $mobile = $this->mobile;
        }

        $userModel = new UserModel();
        $user = $userModel->where('openid', $openid) ->find();
        if (!$user){
            $uid = $this->newUser($openid,$unionid,$mobile);

        }
        else {
            $uid = $user->userid;
            $user['mobile'] = $mobile;
            $user->save();
        }
        $cachedValue = $this->prepareCachedValue($wxResult, $uid);
        $token = $this->saveToCache($cachedValue);
        //
        $request = Request::instance();
        $logdata = [];
        $logdata['logid'] = uuid();
        $logdata['typeid'] = '1';
        $logdata['loginip'] = $request->ip();
        $logdata['logintime'] = Date('Y-m-d H:i:s');
        $logdata['userid'] = $uid;
        $logdata['username'] = $user['nickname'];
        Db::name('customloginlogs')->insert($logdata);
        return $token;
    }

    private function grantToken2($wxResult)
    {
        // 此处生成令牌使用的是TP5自带的令牌
        //        $token = Request::instance()->token('token', 'md5');
        $openid = $wxResult['openid'];

        $userModel = new UserModel();
        $user = $userModel->where('openid', $openid) ->find();
        if ($user){
            $uid = $user['userid'];
            $cachedValue = $this->prepareCachedValue($wxResult, $uid);
            $token = $this->saveToCache($cachedValue);
            //
            $request = Request::instance();
            $logdata = [];
            $logdata['logid'] = uuid();
            $logdata['typeid'] = '1';
            $logdata['loginip'] = $request->ip();
            $logdata['logintime'] = Date('Y-m-d H:i:s');
            $logdata['userid'] = $uid;
            $logdata['username'] = $user['nickname'];
            Db::name('customloginlogs')->insert($logdata);
            return $token;
        }else{
            return "";
        }

    }
    private function prepareCachedValue($wxResult, $uid)
    {
        $cachedValue = $wxResult;
        $cachedValue['uid'] = $uid;
        $cachedValue['scope'] = ScopeEnum::User;
        return $cachedValue;
    }

    // 创建新用户
    private function newUser($openid,$unionid,$mobile)
    {
        // 有可能会有异常，如果没有特别处理
        // 这里不需要try——catch
        // 全局异常处理会记录日志
        // 并且这样的异常属于服务器异常
        // 也不应该定义BaseException返回到客户端
        $data = [];
        $uid = uuid();
        $data['userid'] = $uid;
        $data['openid'] = $openid;
        $data['unionid'] = $unionid;
        $data['appid'] = $this->wxAppID;
        $data['mobile'] = $mobile;
        model('User')->add($data);
        //
        //
//        $new_tel2 = '';
//        if($mobile){
//            $new_tel2 = substr_replace($mobile, '****', 3, 4);
//        }
//
//        $template = array(
//            'cmd' => 1,
//            'data' =>array(
//                'touser' => $openid,
//                'template_id' => WechatTemplate::REGSUCCESS,
//                'data' => array(
//                    'first'=>array(
//                        'value' => '您好，您已成功注册芝麻开门购物柜会员',
//                        'color' => '#173177'
//                    ),
//                    'keyword1'=>array(
//                        'value' => '',
//                        'color' => '#173177'
//                    ),
//                    'keyword2'=>array(
//                        'value' => $new_tel2,
//                        'color' => '#173177'
//                    ),
////                    'remark'=>array(
////                        'value' => '感谢您的支持，欢迎再次光临',
////                        'color' => '#173177'
////                    ),
//                )
//            )
//        );
//        WechatMessage::add(json_encode($template),"erp_options");
        //
        return $uid;
    }
}
