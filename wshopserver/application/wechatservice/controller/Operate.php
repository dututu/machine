<?php
namespace app\wechatservice\controller;
use think\Controller;

use think\Loader;
use think\Config;
use think\Log;
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
/**
 * 运营申请
 *
 * @author      Caesar
 * @version     1.0
 */
class Operate extends  Controller //Base
{
    protected $beforeActionList = [
        'checkSession'
    ];
    /**
     * 首页
     * @access public
     * @throws
     */
    public function index()
    {
        $openid = session('openid', '', 'wechatservice');
        $sysuser = model('Sysuser')->where('openid', $openid)->find();
        if($sysuser&&(!empty($sysuser['merchantid']))){
            return $this->redirect('applystatus',["merchantid"=>$sysuser['merchantid']]);
        }else{
            return $this->fetch('operation_request',[

            ]);
        }
    }
    public function applystatus($merchantid=''){
        $merchant = model('Merchant')->where('merchantid', $merchantid)->find();
        if($merchant){
            return $this->fetch('operation_request_status',[
                'status'=>$merchant['status'],
            ]);
        }else{
            return $this->fetch('operation_request',[
//            'categorys'=>$categorys,
            ]);
        }

    }
    /**
     * 申请成为商户
     */
    public function merchantapply()
    {
        if(!request()->isPost()){
            $this ->error('请求失败');
        }
        $openid = session('openid', '', 'wechatservice');
        if($openid){
            $formdata = input("post.");
            $username = $formdata['username'];
            $mobile = $formdata['mobile'];
            $merchantname = $formdata['merchantname'];
            $merchantaddress = $formdata['merchantaddress'];
            $remark = $formdata['remark'];
            $sysuser = model('Sysuser')->where('openid', $openid)->find();
            $merchantid = uuid();
            if($sysuser){
                $data['merchantid'] = $merchantid;
                $data['creater'] = $sysuser['userid'];
                $data['createtime'] = Date('Y-m-d H:i:s');
                $data['location'] = $merchantaddress;
                $data['merchantname'] = $merchantname;
                $data['remark'] = $remark;
                $data['mobile'] = $mobile;
                $data['status'] = 1;
                $res = model('Merchant') ->save($data);
                //更新sysuser merchantid
                model('Sysuser')::where('userid', '=', $sysuser['userid'])
                    ->update(['merchantid' => $merchantid,'username' => $username,'mobile' => $mobile]);
            }else{
                $userid = uuid();
                $data['merchantid'] = $merchantid;
                $data['creater'] = $userid;
                $data['createtime'] = Date('Y-m-d H:i:s');
                $data['location'] = $merchantaddress;
                $data['merchantname'] = $merchantname;
                $data['remark'] = $remark;
                $data['mobile'] = $mobile;
                $data['status'] = 1;
                $res = model('Merchant') ->save($data);
                //
                $userdata['userid'] = $userid;
                $userdata['username'] = $username;
                $userdata['mobile'] = $mobile;
                $userdata['merchantid'] = $merchantid;
                $userdata['openid'] = $openid;

                $userdata['unionid'] = session('unionid', '', 'wechatservice');
                $userdata['avatar'] = session('avatar', '', 'wechatservice');
                $userdata['gender'] = session('gender', '', 'wechatservice');
                $userdata['nickname'] = session('nickname', '', 'wechatservice');
                $userdata['location'] = session('location', '', 'wechatservice');

                $userdata['creater'] = $userid;
                $userdata['createtime'] = Date('Y-m-d H:i:s');
                $userdata['status'] = 0;
                model('Sysuser') ->save($userdata);
            }
            return $this->redirect('applystatus',["merchantid"=>$merchantid]);
        }else{
            $this -> result($_SERVER['HTTP_REFERER'],0,'fail');
        }

    }



}
//array (
//    'openid' => 'o45AfwTPzvlkREEihhId8gLaw9Dg',
//    'nickname' => 'Caesar',
//    'sex' => 1,
//    'language' => 'zh_CN',
//    'city' => 'Chaoyang',
//    'province' => 'Beijing',
//    'country' => 'CN',
//    'headimgurl' => 'http://wx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTJ1DF8Z5QtcVNF2eAqPoCC7QqxLM1oictrNiaicPGAJdCMXswcw6LxnicpUq8R3pZPibib9PJ1jA0xuO8XQ/0',
//    'privilege' =>
//        array (
//        ),
//    'unionid' => 'o2dvSt2lunVFYLf4jOR-ua5cc39I',
//)