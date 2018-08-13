<?php
/**
 * Created by 七月
 * Author: 七月
 * 微信公号: 小楼昨夜又秋风
 * 知乎ID: 七月在夏天
 * Date: 2017/2/21
 * Time: 12:23
 */

namespace app\clientapi\controller;


use app\clientapi\service\UserToken;
use app\clientapi\service\Token as TokenService;
use app\clientapi\validate\TokenGet;
use app\lib\exception\ParameterException;
use think\Controller;

/**
 * 获取令牌，相当于登录
 */
class Token extends Controller
{
    /**
     * 用户获取令牌（登陆）
     * @url /token
     * @POST code
     * @note
     */
    public function getToken($code='',$encryptedData='',$iv='')
    {
        $wx = new UserToken($code,$encryptedData,$iv,'');
        $token = $wx->get();
        return result(200,'success', $token);
    }
    /**
     * 根据openid判断是否存在用户，若有，返回token
     * @url /token
     * @POST code
     * @note
     */
    public function checkUser($code='')
    {
        $wx = new UserToken($code,'','','');
        $token = $wx->checkUser();
        return result(200,'success', $token);
    }

    public function verifyToken($token='')
    {
        if(!$token){
            throw new ParameterException([
                'token不允许为空'
            ]);
        }
        $valid = TokenService::verifyToken($token);
        if($valid){
            $uid = TokenService::getCurrentUid();
            if(empty($uid)){
                $valid = false;
            }else{
                $userModel = model('User')::get($uid);
                if(empty($userModel)){
                    $valid = false;
                }
            }
        }
        return [
            'isValid' => $valid
        ];
    }

}