<?php
namespace app\admin\controller;
use think\Controller;
use think\Log;
use think\Db;
use think\Request;
use app\common\model\Sysuser as SysUserModel;
use think\Loader;
use think\Config;
use app\admin\service\Common;
use app\common\model\Machine as MachineModel;
use app\common\model\Onsalehistory as OnsaleHistoryModel;
use app\common\model\Formmessage as MessageModel;
use app\worker\controller\WechatMessage;
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
Loader::import('master.MasterApi', EXTEND_PATH, '.php');
Loader::import('master.GoboxApi', EXTEND_PATH, '.php');
define("TOKEN", "f8a69593");
/**
 * 公众号事件推送回调
 *
 * @author      Caesar
 * @version     1.0
 */
class Wechat extends  Controller
{
    protected $wechat;
    protected $openid;
    /***
     *seneid:100 pc绑定 200 pc登录
     *
     */
	public function callback(){
        /* 创建接口操作对象 */
        # 配置参数
        $config = Config::get('wx.wxconfig');

        # 加载对应操作接口
        $this->wechat = &\Wechat\Loader::get('Receive', $config);

        /* 验证接口 */
        if ($this->wechat->valid() === FALSE) {
            // 接口验证错误，记录错误日志
            // log_message('ERROR', "微信被动接口验证失败，{$wechat->errMsg}[{$wechat->errCode}]");
            // 退出程序
            exit($this->wechat->errMsg);
        }


        /* 获取粉丝的openid */
        $openid = $this->wechat->getRev()->getRevFrom();
        $this->openid = $openid;
        /* 记录接口日志，具体方法根据实际需要去完善 */
        // _logs();

        /* 分别执行对应类型的操作 */
        switch ($this->wechat->getRev()->getRevType()) {
            // 文本类型处理
            case \Wechat\WechatReceive::MSGTYPE_TEXT:
                $keys = $this->wechat->getRevContent();
                return $this->_keys($keys);
            // 事件类型处理
            case \Wechat\WechatReceive::MSGTYPE_EVENT:
                $event = $this->wechat->getRevEvent();
                return $this->_event(strtolower($event['event']));
//                if(strtolower($event['event']) == 'scancode_waitmsg' || strtolower($event['event']) == 'scancode_push'){
//                    $this->wechat->text("请求已发送")->reply();
//                    $this->_event(strtolower($event['event']));
//                }else{
//                    return $this->_event(strtolower($event['event']));
//                }

            // 图片类型处理
            case \Wechat\WechatReceive::MSGTYPE_IMAGE:
                return $this->_image();
            // 发送位置类的处理
            case \Wechat\WechatReceive::MSGTYPE_LOCATION:
                return $this->_location();
            // 其它类型的处理，比如卡卷领取、卡卷转赠
            default:
                return $this->_default();
        }



    }
    public function valid()
    {
        $echoStr = $_GET["echostr"];

        //valid signature , option
        if($this->checkSignature()){
            echo $echoStr;
            exit;
        }
    }



    private function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];

        $token = TOKEN;
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );

        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }


    //new sdk
    function _keys($keys){
        // 这里直接原样回复给微信(当然你需要根据业务需求来定制的)
        return $this->wechat->text($keys)->reply();
    }

    function _event($event)
    {
        switch ($event) {
            // 粉丝关注事件
            case 'subscribe':// 扫码关注公众号事件
            case 'scan':
                $rev = $this->wechat->getRev()->getRevData();
                if(isset($rev['Ticket'])){//扫描带参数二维码时如果用户未关注公众号会携带此参数
                    //--------start------
                    $fromUsername = $rev['FromUserName'];
                    $Ticket = $rev['Ticket'];
                    $EventKey = $rev['EventKey'];
                    $keyArray = explode("_", $EventKey);
                    if (count($keyArray) == 1) {//已关注者扫描
                        $openid = $fromUsername;
                        $sceneid = $EventKey;
                    } else {//未关注者关注后推送事件
                        $openid = $fromUsername;
                        $sceneid = $keyArray[1];
                    }
                    if ($sceneid == 100) {//pc绑定
                        Log::info('-----------pc bind-------------');
                        $accountbind = Db::name('wechataccountbind')->where(['ticket' => $Ticket])->find();
                        if ($accountbind) {
                            $sysuser = Db::name('sysuser')->where(['openid' => $openid])->find();
                            if ($sysuser) {//已经绑定
                                Db::name('wechataccountbind')
                                    ->where('bindid', $accountbind['bindid'])
                                    ->update(['status' => 2, 'openid' => $openid]);
                                return $this->wechat->text('已经绑定')->reply();
                            } else {
                                $newsysuser = model('Sysuser')->where('userid', $accountbind['userid'])->find();
                                if ($newsysuser) {
                                    Db::name('sysuser')
                                        ->where('userid', $accountbind['userid'])
                                        ->update(['status' => 1, 'openid' => $openid]);
                                    Db::name('wechataccountbind')
                                        ->where('bindid', $accountbind['bindid'])
                                        ->update(['status' => 1, 'openid' => $openid]);
                                    //
                                    # 配置参数
                                    $config = Config::get('wx.wxconfig');
                                    $this->wechat = &\Wechat\Loader::get('User', $config);
                                    //
                                    $result = $this->wechat->getUserInfo($openid);
                                    // 处理创建结果
                                    if($result===FALSE){
                                        // 接口失败的处理
                                        return $this->wechat->text('获取用户信息失败')->reply();
                                    }else{
                                        // 接口成功的处理
                                        $newsysuser->unionid     = $result['unionid'];
                                        $newsysuser->avatar    = $result['headimgurl'];
                                        $newsysuser->gender    = $result['sex'];
                                        $newsysuser->nickname    = $result['nickname'];
                                        $newsysuser->location    = $result['country'].'-'.$result['province'].'-'.$result['city'];
                                        $newsysuser->save();
                                        return $this->wechat->text('绑定成功')->reply();
                                    }
                                }
                            }
                        } else {
                            return $this->wechat->text('您没有绑定，请联系管理员绑定')->reply();
                        }

//                    if (!$sysuser) {
//                        $user = new SysUserModel;
//                        $user['userid'] = uuid();
//                        $user['openid'] = $openid;
//                        $user['creater'] = '1';
//                        $user['createtime'] = Date('Y-m-d H:i:s');
//                        $user['status'] = 0;
//                        $user->save();
//                    }
                    } else if ($sceneid == 200) {//pc登录
                        Log::info('-----------pc login-------------');
                        $account = Db::name('wechataccountverify')->where(['ticket' => $Ticket])->find();
                        if ($account) {
                            $sysuser = Db::name('sysuser')->where(['openid' => $openid])->find();
                            if ($sysuser) {//存在用户
                                $upuser['logintime'] = Date('Y-m-d H:i:s');
                                $result = Db::name('sysuser')->where(['userid' => $sysuser['userid']])->update($upuser);
                                Db::name('wechataccountverify')
                                    ->where('verifyid', $account['verifyid'])
                                    ->update(['status' => 1, 'openid' => $openid]);
                                //登录日志
                                $request = Request::instance();
                                $logdata = [];
                                $logdata['logid'] = uuid();
                                $logdata['typeid'] = '1';
                                $logdata['logintime'] = Date('Y-m-d H:i:s');
                                $logdata['userid'] = $sysuser['userid'];
                                $logdata['loginip'] = $request->ip();
                                $logdata['username'] = $sysuser['username'];
                                Db::name('loginlog')->insert($logdata);
                                return $this->wechat->text('已登录运营平台,请等待浏览器响应')->reply();
                            } else {
                                Db::name('wechataccountverify')
                                    ->where('verifyid', $account['verifyid'])
                                    ->update(['status' => 2, 'openid' => $openid]);
                                return $this->wechat->text('您没有注册运营平台账户，请联系管理员绑定')->reply();
                            }
                        }

                    }
                    //--------end--------
                    return $this->wechat->text('')->reply();
                }else{
                    $openid = $this->openid;
                    if($openid){
                        $sysuser = model('Sysuser')::where('openid', $openid)->find();
                        if($sysuser&&(!empty($sysuser['merchantid']))){
                            //打商户标签
                            $commonservice = new Common();
                            $commonservice->addUser2WechatGroup($openid);
                            return $this->wechat->text('欢迎商户关注公众号！')->reply();
                        }else{
                            return $this->wechat->text('欢迎关注公众号！')->reply();
                        }

                    }else{
                        return $this->wechat->text('欢迎关注公众号！')->reply();
                    }
                }

            // 粉丝取消关注
            case 'unsubscribe':
                exit("success");
            // 点击微信菜单的链接
            case 'click':
                return $this->wechat->text('你点了菜单链接！')->reply();
            // 微信扫码推事件
            case 'scancode_push':
            case 'scancode_waitmsg':
                $openid = $this->wechat->getRev()->getRevFrom();
                $scanInfo = $this->wechat->getRev()->getRevScanInfo();
                $scanurl = $scanInfo['ScanResult'];
                $pos  =  strpos ($scanurl,Config::get('wx.host').'clientapi?id=');



                if ($pos  ===  false) {
                    return $this->wechat->text("此二维码不是机柜二维码")->reply();
                }else{
                    $purl = parse_url($scanurl);
                    $query = $purl['query'];
                    $querys = explode('&',$query);
                    $lockid='';
                    if(count($querys)==1){
                        $containerid = explode('=',$querys[0])[1];
                    }else if(count($querys)==2){
                        $containerid = explode('=',$querys[0])[1];
                        $lockid = explode('=',$querys[1])[1];
                    }

                    $sysuser =  Db::name('sysuser')->where(['openid' => $openid])->find();
                    //更新机柜状态 1 待开柜（其它状态请查看数据库）
                    $machine = model('Machine')::where('containerid', '=', $containerid)->find();
                    if($machine){
                        $machineid = $machine['machineid'];
                        if($machine['rfidtypecode'] == 3){//重力感应
                            $template = array(
                                'cmd' => 4,
                                'machineid' => $machineid,
                                'userid' => $sysuser['userid'],
                                'lockid' => $lockid,
                            );
                            $re = WechatMessage::add(json_encode($template), "erp_options");
                            return $this->wechat->text("已通知机柜开门:\n机柜编号:".$machine['containerid']."\n机柜类型:智能称重柜\n操作时间:".Date('Y-m-d H:i:s')."\n操作人:".$sysuser['username'])->reply();
                        }else{
                            if($machine['status'] == 3||$machine['status'] == 4||$machine['status'] == 10){
                                $template = array(
                                    'cmd' => 2,
                                    'machineid' => $machineid,
                                    'userid' => $sysuser['userid'],
                                );
                                $re = WechatMessage::add(json_encode($template), "erp_options");
                                return $this->wechat->text("已通知机柜开门:\n机柜编号:".$machine['containerid']."\n机柜类型:射频柜\n操作时间:".Date('Y-m-d H:i:s')."\n操作人:".$sysuser['username'])->reply();

                            }else{
                                return $this->wechat->text("机柜已经打开")->reply();
                            }
                        }

                    }else{
                        return $this->wechat->text("开柜失败，没有此机柜")->reply();
                    }


                }
                //
                return '';

        }

    }

    function _image()
    {
        // $wechat 中有获取图片的方法
        return $this->wechat->text('您发送了一张图片过来')->reply();
    }
    //签约解约回调
    public function papay(){
        /* 创建接口操作对象 */
        # 配置参数
        $config = Config::get('wx.wxconfig');
        # 加载对应操作接口
        $this->wechat = &\Wechat\Loader::get('Receive', $config);
        $rev = $this->wechat->getRev()->getRevData();
        //签约返回
//        array (
//            'change_type' => 'ADD',
//            'contract_code' => '947c6ae1a43250de16d3319651369910',
//            'contract_expired_time' => '2027-12-17 16:14:01',
//            'contract_id' => '201712190391337792',
//            'mch_id' => '1273556201',
//            'openid' => 'oIu0D0VHGJn5GWTjWnP3OmpXk694',
//            'operate_time' => '2017-12-19 16:14:01',
//            'plan_id' => '98808',
//            'request_serial' => '5',
//            'result_code' => 'SUCCESS',
//            'return_code' => 'SUCCESS',
//            'return_msg' => 'OK',
//            'sign' => '76C6F5F48A35EC5DF5637FF1A2A8B2A0',
//        )
        Log::info($rev);

        if (!$rev || $rev['return_code'] !== 'SUCCESS') {//失败
            $return_code = $rev['return_code'];
            $return_msg = $rev['return_msg'];
            return false;
        }else{
            if ($rev['result_code'] == 'SUCCESS') {
                $openid = $rev['openid'];
                $change_type = $rev['change_type'];//ADD--签约 DELETE--解约
                $contract_id = $rev['contract_id'];//委托代扣协议id
                if($change_type == 'ADD'){//签约
                    Db::name('user')
                        ->where('openid', $openid)
                        ->update(['isnopasspay' => 1,'contractid' => $contract_id]);
                }else if($change_type == 'DELETE'){//解约
                    Db::name('user')
                        ->where('openid', $openid)
                        ->update(['isnopasspay' => 0]);
                }
            }
        }
        exit('<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>');
    }
}