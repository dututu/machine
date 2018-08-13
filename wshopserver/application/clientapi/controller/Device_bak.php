<?php

namespace app\clientapi\controller;

use think\Controller;
use think\Config;
use \think\Log;
use \think\Request;
use think\Loader;
use app\lib\enum\GoodsOrderStatusEnum;
use app\lib\enum\GoodsOrderDoorStatusEnum;
use app\lib\enum\WechatTemplate;
use app\lib\enum\OnsaleStatusEnum;
use app\lib\enum\MachineStatusEnum;
use app\lib\enum\MachineBusinessStatusEnum;
use app\clientapi\service\Token;
use app\common\model\Machine as MachineModel;
use app\common\model\Goodsorder as GoodsorderModel;
use app\common\model\User as UserModel;
use app\common\model\Formmessage as MessageModel;

Loader::import('master.MasterApi', EXTEND_PATH, '.php');
Loader::import('master.RfidApiV2', EXTEND_PATH, '.php');
Loader::import('master.GoboxApi', EXTEND_PATH, '.php');

/**
 * 设备操作相关接口
 */
class Device extends BaseController
{

    /**
     * 判断是否可以扫码
     * @param machineid 机柜id
     * @return show status 200 成功
     */
    public function checkscan()
    {
        $uid = Token::getCurrentUid();
        $userModel = UserModel::get($uid);
        if(!$userModel){
            $code = 205;
            $msg = '用户未登录';
            return status($code, $msg);
        }
        if($userModel['havearrears'] == 1){
            $code = 201;
            $msg = '您有未结订单';
            return status($code, $msg);
        }
        if($userModel['isblack'] == 1){
            $code = 202;
            $msg = '您已被加入黑名单';
            return status($code, $msg);
        }
        //储值小于30元，弹出储值提示，如果已经开通免密支付，则不弹出。
        if ($userModel['isnopasspay'] == 0) {//未开通免密支付
            if ($userModel['fee'] / 100 < 30) {
                $code = 206;
                $msg = '请开通免密支付或储值';
                return status($code, $msg);
            }
        }
        $code = 200;
        return status($code, '');
    }
    /**
     * 判断是否可以开门
     * @param machineid 机柜id
     * @return show status 200 成功
     */
    public function requestopen()
    {
        $uid = Token::getCurrentUid();
        $userModel = UserModel::get($uid);
        $qrurl = input('post.qrurl');
        $pos  =  strpos ($qrurl,Config::get('wx.host').'clientapi?id=');
        if ($pos  ===  false) {
            return status(404, "此二维码不是机柜二维码");
        }else {
            $lockid='';
            $purl = parse_url($qrurl);
            $query = $purl['query'];
            $querys = explode('&',$query);
            if(count($querys)==1){
                $containerid = explode('=',$querys[0])[1];
            }else if(count($querys)==2){
                $containerid = explode('=',$querys[0])[1];
                $lockid = explode('=',$querys[1])[1];
            }
            $machine = model('Machine')::where('containerid', '=', $containerid)->find();
            $machineid = $machine['machineid'];
            $msg = 'success';
            //获取用户状态 是否有未支付订单 是否黑名单等
            if(!$userModel){
                $code = 205;
                $msg = '用户未登录';
                return status($code, $msg);
            }
            if(!$machine){
                $code = 204;
                $msg = '机柜不存在';
                return status($code, $msg);
            }
            if($userModel['havearrears'] == 1){
                $code = 201;
                $msg = '您有未结订单';
                return status($code, $msg);
            }
            if($userModel['isblack'] == 1){
                $code = 202;
                $msg = '您已被加入黑名单';
                return status($code, $msg);
            }
            //储值小于30元，弹出储值提示，如果已经开通免密支付，则不弹出。
            if ($userModel['isnopasspay'] == 0) {//未开通免密支付
                if ($userModel['fee'] / 100 < 30) {
                    $code = 206;
                    $msg = '请开通免密支付或储值';
                    return status($code, $msg);
              }
            }
            if($machine['businessstatus'] == MachineBusinessStatusEnum::NORMAL && ($machine['status'] == MachineStatusEnum::UNOPENED || $machine['status']== MachineStatusEnum::CLOSED|| $machine['status']== MachineStatusEnum::ONLINED)){
                $code = 200;
                $returndata = [];
                $returndata['machineid'] = $machineid;
                $returndata['lockid'] = $lockid;
                return result($code, 'success',$returndata);
            }else{
                if($machine['status'] == MachineStatusEnum::PREPRODUCE && $machine['businessstatus'] == MachineBusinessStatusEnum::NOTCONNECTED ){
                    $code = 203;
                    $msg = '您好，该机柜还未开始运营，请重新选择机柜';
                    return status($code, $msg);
                }else if($machine['status'] == MachineStatusEnum::PRODUCE && $machine['businessstatus'] == MachineBusinessStatusEnum::NOTCONNECTED ){
                    $code = 203;
                    $msg = '您好，该机柜还未开始运营，请重新选择机柜';
                    return status($code, $msg);
                }else if($machine['status'] == MachineStatusEnum::ONLINED && $machine['businessstatus'] == MachineBusinessStatusEnum::INITED ){
                    $code = 203;
                    $msg = '您好，该机柜还未开始运营，请重新选择机柜';
                    return status($code, $msg);
                }else if($machine['status'] == MachineStatusEnum::ONLINED && $machine['businessstatus'] == MachineBusinessStatusEnum::PREDISPATCH ){
                    $code = 203;
                    $msg = '您好，该机柜还未开始运营，请重新选择机柜';
                    return status($code, $msg);
                }else if($machine['status'] == MachineStatusEnum::PREPAREFOROPEN && $machine['businessstatus'] == MachineBusinessStatusEnum::NORMAL ){
                    $code = 203;
                    $msg = '您好，该机柜正在购物中，请重新选择机柜';
                    return status($code, $msg);
                }else if($machine['status'] == MachineStatusEnum::OPENED && $machine['businessstatus'] == MachineBusinessStatusEnum::NORMAL ){
                    $code = 203;
                    $msg = '您好，该机柜正在购物中，请重新选择机柜';
                    return status($code, $msg);
                }else if($machine['businessstatus'] == MachineBusinessStatusEnum::STOPPED ){
                    $code = 203;
                    $msg = '您好，该机柜还未开始运营，请重新选择机柜';
                    return status($code, $msg);
                }else if($machine['businessstatus'] == MachineBusinessStatusEnum::CANCELED ){
                    $code = 203;
                    $msg = '您好，该机柜已作废，请重新选择机柜';
                    return status($code, $msg);
                }else{
                    $code = 203;
                    $msg = '机柜未知故障';
                    return status($code, $msg);
                }

            }
        }


    }

    /**
     * 请求开门
     * @param machineid 机柜id
     * @param formid formid
     * @return show status 1 成功
     */
    public function open()
    {
        $date = input('post.date');
        Log::info("***********小程序请求开门***********".$date);
        $uid = Token::getCurrentUid();
        $machineid = input('post.machineid');
        $formid = input('post.formid');
        $lockid = input('post.lockid');
        $userModel = UserModel::get($uid);
        //更新机柜状态 1 待开柜（其它状态请查看数据库）
        $machine = MachineModel::get($machineid);

        if(!$machine){
            return result(404, "机柜不存在", []);
        }else if($machine['businessstatus'] == MachineBusinessStatusEnum::NORMAL && ($machine['status'] == MachineStatusEnum::UNOPENED || $machine['status']== MachineStatusEnum::CLOSED|| $machine['status']== MachineStatusEnum::ONLINED)){
            $machine->status = MachineStatusEnum::PREPAREFOROPEN;
            $machine->save();
            //新建订单
            $order = [];
            $uuid = uuid();
            $order['orderid'] = $uuid;
            $orderno = makeOrderNo();
            $order['orderno'] = $orderno;
            $serialno = uuid();
            $order['serialno'] = $serialno;
            $order['machineid'] = $machineid;
            $order['orderstatus'] = GoodsOrderStatusEnum::SHOPPING;
            $order['doorstatus'] = GoodsOrderDoorStatusEnum::PREPAREFOROPEN;
            $order['userid'] = $uid;
            $order['totalfee'] = 0;
            $order['payfee'] = 0;
            $goodsModel = new GoodsorderModel;
            $goodsModel->createorder($order);
            //新建模版消息记录
            if($formid){
                $message = [];
                $muuid = uuid();
                $message['fmid'] = $muuid;
                $message['formid'] = $formid;
                $message['orderid'] = $uuid;
                $message['templateid'] = WechatTemplate::APPPAYSUCCESS;
                $message['userid'] = $uid;
                $message['sendstatus'] = 0;
                $message['createtime'] = date("Y-m-d H:i:s", time());
                $message['templatedata'] = '';
                MessageModel::create($message);
            }
            //
            if($machine['rfidtypecode'] == 3){//重力感应柜
                $gboxApi = new \GoboxApi('','');
                $option = [];
                $option['dev_id'] = $machine['boxdevid'];
                $option['lockid'] = $lockid;
                $option['user_type'] = 1;//1:消费者
                $option['transid'] = $orderno;
                $masterresult = $gboxApi->openDoor($option);
                $data = [];
                $data['orderid'] = $uuid;
                $data['orderno'] = $order['orderno'];
                $data['recogmode'] = 3;//重力柜
                if($masterresult['code'] == 0){
                    $order['orderstatus'] = GoodsOrderStatusEnum::SHOPPING;
                    $order['doorstatus'] = GoodsOrderDoorStatusEnum::OPENED;
                    $goodsModel->save($order);
                    $machine['status'] = MachineStatusEnum::OPENED;
                    $machine->save();
                    return result(200, $masterresult['msg'], $data);
                }else{
                    $order['orderstatus'] = GoodsOrderStatusEnum::CANCELED;
                    $order['doorstatus'] = GoodsOrderDoorStatusEnum::UNPENED;
                    $goodsModel->save($order);
                    $machine['status'] = MachineStatusEnum::UNOPENED;
                    $machine->save();
                    return result(201, $masterresult['msg'], $data);
                }
            }else{
                $data = [];
                $data['orderid'] = $uuid;
                $data['orderno'] = $order['orderno'];
                $data['recogmode'] = 2;//超高频
                //请求开门
                $requestdata = array(
                    'serialnumber' => $order['orderno'],
                    'containerid' => $machine['containerid'],
                    'opencommand' => 1,
                    'callbackurl' => Config::get('wx.host').'callbackapi/device/open',
                    'timestamp' => time(),
                    'type' => 1,
                );
                $wxOrderData = new \MasterApi($uid,$userModel['nickname']);
                $json = $wxOrderData->deviceOpen($requestdata);

                Log::info("***********小程序请求开门结束***********".Date('Y-m-d H:i:s'));
                $result = json_decode($json,true);
                if($result['code'] == 200){
                    return result(200, $result['msg'], $data);
                }else{
                    $order['orderstatus'] = GoodsOrderStatusEnum::CANCELED;
                    $order['doorstatus'] = GoodsOrderDoorStatusEnum::UNPENED;
                    $goodsModel->save($order);
                    //
                    $machine['status'] = MachineStatusEnum::UNOPENED;
                    $machine->save();
                    if($result['code'] == 100 || $result['code'] == 101){
                        return result(202, '您好，该机柜工作异常，请重新选择机柜', $data);
                    }else if($result['code'] == 102 || $result['code'] == 103 || $result['code'] == 105){
                        return result(202, '您好，该机柜正在购物中，请重新选择机柜', $data);
                    }else if($result['code'] == 104 || $result['code'] == 106 || $result['code'] == 107){
                        return result(202, '网络异常，请重新扫码开门', $data);
                    }else{
                        return result(202, $result['msg'], $data);
                    }

                }
            }

        }else{
            return result(201, "机柜开柜中", []);
        }


    }

    /**
     * 查询开门状态
     * @param orderid
     * @return show status 1 成功
     */
    public function openstatus()
    {

        $uid = Token::getCurrentUid();
        $data = [];
        $orderid = input('post.orderid');
        $model = GoodsorderModel::get($orderid);
        $data['orderstatus'] = $model->orderstatus;
        $data['doorstatus'] = $model->doorstatus;
        if ($data['doorstatus'] == GoodsOrderDoorStatusEnum::OPENED) {
            return result(200, '', $data);
        }else if ($data['doorstatus'] == GoodsOrderDoorStatusEnum::UNPENED) {
            return result(201, '开柜失败', $data);
        }else{
            return result(202, '', $data);
        }

    }

    /**
     * 主动查询开门状态（超时或异常时）
     * @param orderid
     * @return show status 1 成功
     */
    public function requestopenstatus()
    {
        $uid = Token::getCurrentUid();
        $userModel = UserModel::get($uid);
        $orderid = input('post.orderid');
        $model = GoodsorderModel::get($orderid);
        $wxOrderData = new \MasterApi($uid,$userModel['nickname']);
        $requestdata = array(
            'serialnumber' => $model['orderno'],
            'timestamp' => time(),
        );
        //TODO 处理主动查询的返回
//        $json = $wxOrderData->deviceOpenStatus($requestdata);
//        $result = json_decode($json,true);
        $data = [];
        $data['orderstatus'] = $model->orderstatus;
        $data['doorstatus'] = $model->doorstatus;
        return result(200, 'success', $data);
    }

    /**
     * 订单状态
     * @param orderid
     * @return show status 1 成功
     */
    public function orderstatus()
    {

        $uid = Token::getCurrentUid();
        $data = [];
        $orderid = input('post.orderid');
        $status = 201;
        $model = GoodsorderModel::get($orderid);
        $data['orderstatus'] = $model->orderstatus;
        $data['doorstatus'] = $model->doorstatus;
        if ($data['orderstatus'] == GoodsOrderStatusEnum::PAID || $data['orderstatus'] == GoodsOrderStatusEnum::ARREARAGED|| $data['orderstatus'] == GoodsOrderStatusEnum::CANCELED|| $data['orderstatus'] == GoodsOrderStatusEnum::COMPLETE) {
            $status = 200;
        }
        return result($status, '', $data);
    }
}
