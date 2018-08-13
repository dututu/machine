<?php
/**
 * Created by PhpStorm.
 * User: caesar
 * Date: 2018/1/1
 * Time: 下午5:46
 */

namespace app\worker\controller;


use think\Config;
use think\Exception;
use think\Log;
use app\admin\service\Templatemessage;
use app\common\model\Machine as MachineModel;
use app\common\model\Merchant as MerchantModel;
use app\common\model\Onsalehistory as OnsaleHistoryModel;
use app\common\model\Sysuser as SysuserModel;
use app\common\model\Formmessage as MessageModel;
use app\lib\enum\OnsaleStatusEnum;
use app\lib\enum\MachineStatusEnum;
use app\lib\enum\MerchantStatusEnum;
use think\Loader;
use app\common\model\Interfacelog as InterfaceLogModel;

Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
Loader::import('master.MasterApi', EXTEND_PATH, '.php');
Loader::import('master.GoboxApi', EXTEND_PATH, '.php');

/**
 * Redis队列发送微信模版消息
 */
class WechatMessage extends Base
{

    protected $options = [];

    protected $wechat;
    protected $listName;

    public function __construct($options = [])
    {
//        $this->options = empty($options) ? Config::get("wechat.erp_options") : $options;
        parent::__construct($options);

//        $this->message=Wechat::message($this->options);
        $this->listName = md5($this->workerName);
        # 配置参数
        $config = Config::get('wx.wxconfig');
        # 加载对应操作接口
        $this->wechat = &\Wechat\Loader::get('Receive', $config);
    }


    /**
     * 检测命令行是否执行中
     * Power: Mikkle
     * @return bool
     */
    static public function checkCommandRun()
    {
        return self::redis()->get("command") ? true : false;
    }

    /**
     * 快速添加模版消息任务
     *
     * 当命令行未运行 直接执行
     * Power: Mikkle
     * @param $data
     * @param array $options
     */
    static public function add($data, $options = [])
    {
        $instance = self::instance($options);
        switch (true) {
            case (self::checkCommandRun()):
                $instance->redis->lpush($instance->listName, $data);
                $instance->runWorker();
                break;
            default:
//                $instance->message->sendTemplateMessage($data);
        }
    }

    /**
     * 命令行执行的方法
     * Power: Mikkle
     */
    static public function run()
    {
        $instance = self::instance();
        try {
            $i = 0;
            while (true) {
                $data = $instance->redis->rpop($instance->listName);

                if ($data) {
                    //获取cmd命令类型并决定去向
                    $data = json_decode($data, true);
                    if ($data['cmd'] == 0) {//发送微信公众平台模板消息
                        $re = $instance->sendMessage($data['data']);
                    } else if ($data['cmd'] == 1) {//发送微信小程序模板消息
                        $re = $instance->sendAppMessage($data['data']);
                    } else if ($data['cmd'] == 2) {//请求开门
                        $re = $instance->requestopen($data['machineid'],$data['userid']);
                    } else if ($data['cmd'] == 3) {//发送客服消息
                        $re = $instance->sendCustomMessage($data['data']);
                    } else if ($data['cmd'] == 4) {//重力柜请求开门
                        $re = $instance->requestopenweight($data['machineid'],$data['userid'],$data['lockid']);
                    }

                } else {
                    break;
                }
                $i++;
                sleep(1);
            }
            $instance->clearWorker();
            echo "执行了{$i}次任务" . PHP_EOL;
        } catch (Exception $e) {
            Log::error($e);
            $instance->clearWorker();
            die($e->getMessage());
        }
    }

    /**
     * 发送公众号模版消息的方法
     * Power: Mikkle
     * @param $data
     * @return bool
     */
    protected function sendMessage($data)
    {

        $no = true;
        $no = $this->wechat->sendTemplateMessage($data);
        if ($no) {
            return true;
        } else {
            $this->failed($data);
        };

    }

    /**
     * 发送小程序模版消息的方法
     * Power: Mikkle
     * @param $messagedata
     * @return bool
     */
    protected function sendAppMessage($messagedata)
    {
        $AppID = Config::get('wx.app_id');
        $AppSecret = Config::get('wx.app_secret');
        $tokenurl = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $AppID . "&secret=" . $AppSecret;
        $tokenresult = doCurl($tokenurl, 0, []);
        $tokenarray = json_decode($tokenresult, true);
        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $tokenarray['access_token'];
        $ch = api_notice_increment($url, json_encode($messagedata));
        if ($ch) {
            return true;
        } else {
            $this->failed($messagedata);
        };
    }
    /**
     * 发送客服消息
     * Power: Mikkle
     * @param $data
     * @return bool
     */
    protected function sendCustomMessage($data)
    {

        # 配置参数
        $config = Config::get('wx.wxconfig');
        $wechatreceive = &\Wechat\Loader::get('Receive', $config);
        // 发送客服消息（$data为数组，具体请参数微信官方文档）
        $replytext = $data;
        $wechatreceive->sendCustomMessage($replytext);

    }
    /**
     * 理货请求开门
     * Power: Mikkle
     * @param $data
     * @return bool
     */
    protected function requestopen($machineid,$userid)
    {
        $machine = MachineModel::where('machineid', $machineid)->find();
        $sysuser =  SysuserModel::where('userid', $userid)->find();
        if($sysuser){
            $merchantid = $sysuser['merchantid'];
            $merchnat = MerchantModel::where('merchantid', $merchantid)->find();
            if($merchnat&&$merchnat['status'] == 2){
                if($sysuser['merchantid'] == $machine['merchantid']){
                    //新建订单
                    $order = [];
                    $uuid = uuid();
                    $order['historyid'] = $uuid;
                    $orderno = makeOrderNo();
                    $order['orderno'] = $orderno;
                    $serialno = uuid();
                    $order['serialno'] = $serialno;
                    $order['machineid'] = $machineid;
                    $order['operateuserid'] = $userid;
                    $onsaleorder = new OnsaleHistoryModel;
                    $onsaleorder->createorder($order);
                    //请求开门
                    $requestdata = array(
                        'serialnumber' => $orderno,
                        'containerid' => $machine['containerid'],
                        'opencommand' => 1,
                        'callbackurl' => Config::get('wx.host').'callbackapi/device/bopen',
                        'timestamp' => time(),
                        'type' => 2,
                    );
                    $wxOrderData = new \MasterApi($sysuser['userid'],$sysuser['nickname']);
                    $json = $wxOrderData->deviceOpenS($requestdata);
                    $openresult = json_decode($json,true);
                    if($openresult['code'] == 200){
                        OnsaleHistoryModel::where('historyid', '=', $uuid)
                            ->update(['status' => OnsaleStatusEnum::PREOPEN]);
                        MachineModel::where('machineid', '=', $machineid)
                            ->update(['status' => MachineStatusEnum::PREPAREFOROPEN]);
                        //
                        # 配置参数
                        $config = Config::get('wx.wxconfig');
                        $wechatreceive = &\Wechat\Loader::get('Receive', $config);
                        // 发送客服消息（$data为数组，具体请参数微信官方文档）
                        $replytext = array(
                            'touser' => $sysuser['openid'],
                            'msgtype' => "text",
                            'text'=>array(
                                'content' => '开柜成功'
                            )
                        );
                        $wechatreceive->sendCustomMessage($replytext);
                    }else{
                        OnsaleHistoryModel::where('historyid', '=', $uuid)
                            ->update(['status' => OnsaleStatusEnum::CANCELED]);
                        MachineModel::where('machineid', '=', $machineid)
                            ->update(['status' => MachineStatusEnum::UNOPENED]);
                        //
                        # 配置参数
                        $config = Config::get('wx.wxconfig');
                        $wechatreceive = &\Wechat\Loader::get('Receive', $config);
                        // 发送客服消息（$data为数组，具体请参数微信官方文档）
                        $replytext = array(
                            'touser' => $sysuser['openid'],
                            'msgtype' => "text",
                            'text'=>array(
                                'content' => $openresult['msg']
                            )
                        );
                        $wechatreceive->sendCustomMessage($replytext);
                    }
                }else{
                    # 配置参数
                    $config = Config::get('wx.wxconfig');
                    $wechatreceive = &\Wechat\Loader::get('Receive', $config);
                    // 发送客服消息（$data为数组，具体请参数微信官方文档）
                    $replytext = array(
                        'touser' => $sysuser['openid'],
                        'msgtype' => "text",
                        'text'=>array(
                            'content' => '机柜属于其它商户，您没有开门权限'
                        )
                    );
                    $wechatreceive->sendCustomMessage($replytext);
                }
            }else{
                # 配置参数
                $config = Config::get('wx.wxconfig');
                $wechatreceive = &\Wechat\Loader::get('Receive', $config);
                // 发送客服消息（$data为数组，具体请参数微信官方文档）
                $replytext = array(
                    'touser' => $sysuser['openid'],
                    'msgtype' => "text",
                    'text'=>array(
                        'content' => '商户审核中或停用'
                    )
                );
                $wechatreceive->sendCustomMessage($replytext);
            }

        }else{
            # 配置参数
            $config = Config::get('wx.wxconfig');
            $wechatreceive = &\Wechat\Loader::get('Receive', $config);
            // 发送客服消息（$data为数组，具体请参数微信官方文档）
            $replytext = array(
                'touser' => $sysuser['openid'],
                'msgtype' => "text",
                'text'=>array(
                    'content' => '用户不存在'
                )
            );
            $wechatreceive->sendCustomMessage($replytext);
        }





    }
    /**
     * 理货请求开门-重力柜
     * Power: Mikkle
     * @param $data
     * @return bool
     */
    protected function requestopenweight($machineid,$userid,$lockid)
    {

        //
        $machine = MachineModel::where('machineid', $machineid)->find();
        $sysuser =  SysuserModel::where('userid', $userid)->find();
        if($sysuser){
            $merchantid = $sysuser['merchantid'];
            $merchnat = MerchantModel::where('merchantid', $merchantid)->find();
            if($merchnat&&$merchnat['status'] == MerchantStatusEnum::NORMAL){
                //log
                $interfaceLogModel = new InterfaceLogModel;
                $interfaceLogModel['logid'] = uuid();
                $interfaceLogModel['operaterid'] = $userid;
                $interfaceLogModel['detailinfo'] = ' test 理货请求开门-重力柜';
                $interfaceLogModel['operatername'] = '';
                $interfaceLogModel['orderno'] = '';
                $interfaceLogModel['containerid'] = $machine['containerid'];
                $interfaceLogModel['createtime'] = Date('Y-m-d H:i:s');
                $interfaceLogModel['requesttype'] = 0;
                $interfaceLogModel['operateresult'] = 0;
                $interfaceLogModel['url'] = 'requestopenweight';
                $interfaceLogModel['requestparams'] = json_encode($sysuser);
                $interfaceLogModel['encryptrequestparams'] = '';
                $interfaceLogModel['encryptresponseparams'] = '';
                $interfaceLogModel['responseparams'] = json_encode($machine);
                $interfaceLogModel->save();
                if($sysuser['merchantid'] == $machine['merchantid']){
                    //新建订单
                    $order = [];
                    $uuid = uuid();
                    $order['historyid'] = $uuid;
                    $orderno = makeOrderNo();
                    $order['orderno'] = $orderno;
                    $serialno = uuid();
                    $order['serialno'] = $serialno;
                    $order['machineid'] = $machineid;
                    $order['operateuserid'] = $userid;
                    $onsaleorder = new OnsaleHistoryModel;
                    $onsaleorder->createorder($order);

                    //请求开门
                    $gboxApi = new \GoboxApi('','');
                    $option = [];
                    $option['dev_id'] = $machine['boxdevid'];
                    $option['lockid'] = $lockid;
                    $option['user_type'] = 2;//2:上货人员
                    $option['transid'] = $orderno;
                    $masterresult = $gboxApi->openDoor($option);

                    if($masterresult['code'] == 0){
                        OnsaleHistoryModel::where('historyid', '=', $uuid)
                            ->update(['status' => OnsaleStatusEnum::OPENED]);
//                        MachineModel::where('machineid', '=', $machineid)
//                            ->update(['status' => MachineStatusEnum::OPENED]);
                        //
                        # 配置参数
                        $config = Config::get('wx.wxconfig');
                        $wechatreceive = &\Wechat\Loader::get('Receive', $config);
                        $onsalecheckurl = Config::get('wx.host').Config::get('wx.onsalecheck').'?machineid='.$machineid.'&userid='.$userid.'&historyid='.$uuid;
                        $weighturl = Config::get('wx.host').'wechatservice/machine/weight'.'?machineid='.$machineid;
                        $clearurl = Config::get('wx.host').'wechatservice/machine/clearpos'.'?machineid='.$machineid;
                        // 发送客服消息（$data为数组，具体请参数微信官方文档）
//                        {
//                            "touser":"OPENID",
//    "msgtype":"news",
//    "news":{
//                            "articles": [
//         {
//             "title":"Happy Day",
//             "description":"Is Really A Happy Day",
//             "url":"URL",
//             "picurl":"PIC_URL"
//         },
//         {
//             "title":"Happy Day",
//             "description":"Is Really A Happy Day",
//             "url":"URL",
//             "picurl":"PIC_URL"
//         }
//         ]
//    }
//}
                        $pic0 = Config::get('wx.host').'static/admin/assets/images/pic0.png';
                        $pic1 = Config::get('wx.host').'static/admin/assets/images/inbox.jpg';
                        $pic2 = Config::get('wx.host').'static/admin/assets/images/dashboard-24.jpg';
                        $pic3 = Config::get('wx.host').'static/admin/assets/images/recycle.jpg';
                        //,{"title":"机柜台秤实时监控","description":"","url":"","picurl":"'.$pic2.'"},{"title":"机柜内商品清空","description":"","url":"","picurl":"'.$pic3.'"}
                        $jdata = '{"touser":"'.$sysuser['openid'].'","msgtype":"news","news":{"articles":[{"title":"'.$machine['containerid'].'号机柜现场调试","description":"","url":"","picurl":"'.$pic0.'"},{"title":"上货商品调整","description":"","url":"'.$onsalecheckurl.'","picurl":"'.$pic1.'"},{"title":"机柜台秤实时监控","description":"","url":"'.$weighturl.'","picurl":"'.$pic2.'"},{"title":"机柜内商品清空","description":"","url":"'.$clearurl.'","picurl":"'.$pic3.'"}]}}';
//                        $replytext = array(
//                            'touser' => $sysuser['openid'],
//                            'msgtype' => "news",
//                            'text'=>array(
//                                'content' => '<a href="'.$onsalecheckurl.'" >上货成功,点击进行上货调整</a>'
//                            )
//                        );
                        $wechatreceive->sendCustomMessage(json_decode($jdata,true));
                    }else{
                        OnsaleHistoryModel::where('historyid', '=', $uuid)
                            ->update(['status' => OnsaleStatusEnum::CANCELED]);
                        MachineModel::where('machineid', '=', $machineid)
                            ->update(['status' => MachineStatusEnum::UNOPENED]);
                        //
                        # 配置参数
                        $config = Config::get('wx.wxconfig');
                        $wechatreceive = &\Wechat\Loader::get('Receive', $config);
                        // 发送客服消息（$data为数组，具体请参数微信官方文档）
                        $replytext = array(
                            'touser' => $sysuser['openid'],
                            'msgtype' => "text",
                            'text'=>array(
                                'content' => $masterresult['msg']
                            )
                        );
                        $wechatreceive->sendCustomMessage($replytext);
                    }
                    //log
                    $interfaceLogModel = new InterfaceLogModel;
                    $interfaceLogModel['logid'] = uuid();
                    $interfaceLogModel['operaterid'] = $userid;
                    $interfaceLogModel['detailinfo'] = $orderno.' 理货请求开门-重力柜';
                    $interfaceLogModel['operatername'] = '';
                    $interfaceLogModel['orderno'] = $orderno;
                    $interfaceLogModel['containerid'] = $machine['containerid'];
                    $interfaceLogModel['createtime'] = Date('Y-m-d H:i:s');
                    $interfaceLogModel['requesttype'] = 0;
                    $interfaceLogModel['operateresult'] = 0;
                    $interfaceLogModel['url'] = 'requestopenweight';
                    $interfaceLogModel['requestparams'] = json_encode($option);
                    $interfaceLogModel['encryptrequestparams'] = '';
                    $interfaceLogModel['encryptresponseparams'] = '';
                    $interfaceLogModel['responseparams'] = json_encode($masterresult);
                    $interfaceLogModel->save();
                }else{
                    # 配置参数
                    $config = Config::get('wx.wxconfig');
                    $wechatreceive = &\Wechat\Loader::get('Receive', $config);
                    // 发送客服消息（$data为数组，具体请参数微信官方文档）
                    $replytext = array(
                        'touser' => $sysuser['openid'],
                        'msgtype' => "text",
                        'text'=>array(
                            'content' => '机柜属于其它商户，您没有开门权限'
                        )
                    );
                    $wechatreceive->sendCustomMessage($replytext);
                }
            }else{
                # 配置参数
                $config = Config::get('wx.wxconfig');
                $wechatreceive = &\Wechat\Loader::get('Receive', $config);
                // 发送客服消息（$data为数组，具体请参数微信官方文档）
                $replytext = array(
                    'touser' => $sysuser['openid'],
                    'msgtype' => "text",
                    'text'=>array(
                        'content' => '商户审核中或停用'
                    )
                );
                $wechatreceive->sendCustomMessage($replytext);
            }

        }else{
            # 配置参数
            $config = Config::get('wx.wxconfig');
            $wechatreceive = &\Wechat\Loader::get('Receive', $config);
            // 发送客服消息（$data为数组，具体请参数微信官方文档）
            $replytext = array(
                'touser' => $sysuser['openid'],
                'msgtype' => "text",
                'text'=>array(
                    'content' => '用户不存在'
                )
            );
            $wechatreceive->sendCustomMessage($replytext);
        }





    }
    /**
     * 出错执行的回调方法
     * Power: Mikkle
     * @param $data
     */
    protected function failed($data)
    {
    }

}