<?php
namespace app\clientapi\controller;
use app\clientapi\validate\IDMustBePositiveInt;
use think\Controller;
use think\Db;
use \think\Log;
use \think\Loader;
use app\clientapi\service\Token;
use app\common\model\User as UserModel;
use app\clientapi\service\UserToken;
use app\clientapi\service\Token as TokenService;

Loader::import('sms.simple-sms', EXTEND_PATH, '.php');
/**
 * 用户相关接口
 */
class User extends BaseController
{

    /**
     * 获取用户详细信息
     * @return 详情
     */
    public function detail() {
        $uid = Token::getCurrentUid();
        $ret = UserModel::get($uid);
        if(!$ret) {
            return status(201,"用户不存在");
        }
        $data = [];
        $data['userid'] = $ret['userid'];
        $data['avatar'] = $ret['avater'];
        $data['nickname'] = $ret['nickname'];
        $data['isnopasspay'] = $ret['isnopasspay'];
        $data['havearrears'] = $ret['havearrears'];
        $data['mobile'] = $ret['mobile'];
        $data['fee'] = $ret['fee'];

        return result(200,"success",$data);
    }
    /**
     * 更新用户详细信息
     * @return 详情
     */
    public function updateuserinfo(){
        $uid = Token::getCurrentUid();
        $avater = input('post.avatarUrl');
        $city = input('post.city');
        $country = input('post.country');
//        $gender = input('post.gender');
//        $language = input('post.language');
        $nickName = input('post.nickName');
        $province = input('post.province');
        $ret = UserModel::get($uid);
        $ret['nickname'] = removeEmoji($nickName);
        $ret['avater'] = $avater;
        $ret['location'] = $country.'-'.$province.'-'.$city;
        $ret->save();
        return status(200,'success');
    }
    /**
     * 发送短信验证码
     * @return 详情
     */
    public function sendverifycode(){
        $mobile = input('post.mobile');
        $rand = rand(1000,9999);
        $content = '【知码开门】您的短信验证码为 '.$rand;
        $mobole = $mobile;
        $smsid = uuid();
        $result = sendSMS($content,$mobole,$smsid);
        Log::info($result);
        if($result['code'] == 'SUCCESS'){
            $data = ['smsid' => $smsid, 'createtime' => Date('Y-m-d H:i:s'),'code' =>$rand,'verifystatus' => 0 ];
            Db::name('smsverify')
                ->data($data)
                ->insert();
            return result(200,'success', $smsid);
        }else{
            return result(201,'发送失败', $smsid);
        }
    }
    /**
     * 验证短信验证码
     * @return 详情
     */
    public function verifysmscode(){
        $mobile = input('post.mobile');
        $verifycode = input('post.verifycode');
        $smsid = input('post.smsid');
        $code = input('post.code');
        $sms = Db::table('smsverify')->where('smsid',$smsid)->find();
        if($sms['code'] == $verifycode){
            $wx = new UserToken($code,'','',$mobile);
            $token = $wx->get();
            Db::name('smsverify')
                ->where('smsid', $smsid)
                ->update(['verifystatus' => 1]);
            return result(200,'success', $token);
        }else{
            return result(201,'短信验证失败', '');
        }

    }
}
