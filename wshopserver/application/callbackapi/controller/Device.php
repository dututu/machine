<?php
namespace app\callbackapi\controller;
use think\Controller;
use think\Db;
use \think\Log;
use \think\Request;
use think\Loader;
use app\lib\enum\GoodsOrderStatusEnum;
use app\lib\enum\GoodsOrderDoorStatusEnum;
use app\lib\enum\OnsaleStatusEnum;
use app\lib\enum\MachineStatusEnum;
use app\lib\enum\MachineBusinessStatusEnum;
use app\common\model\Machine as MachineModel;
use app\common\model\Goodsorder as GoodsorderModel;
use app\common\model\Onsalehistory as OnsaleModel;
use app\common\model\Interfacelog as InterfaceLogModel;
Loader::import('master.MasterApi', EXTEND_PATH, '.php');
/**
* 设备相关回调
 */
class Device extends Controller
{

    /**
     * 开门回调地址返回开门结果（购物）
     * @param 成功 {"deviceOpenRes": "success", "code": 200, "order_id": "ABCDEFGABCDEFGABCDE1511485667", "time": "2017-11-24 09:07:40", "msg": "deviceOpenRes success", "type": "1"}
     * @param 失败 {"order_id": "201711061342",”code”:201, "msg": "deviceOpenRes fail", "time": "2017-11-06 13:46:39", "deviceOpenRes": "fail",”type”:1}
     * @return echo result
     * @throws
     */
    public function open() {
        Log::info('开门回调地址返回开门结果（购物）--start---');

        Log::info("***********请求开门回调开门***********".Date('Y-m-d H:i:s'));
        $param = file_get_contents('php://input');
        $jdecode = json_decode($param,true);
        $masterApi = new \MasterApi('','');
        $dparam = $masterApi->decrypt($jdecode['param']);
        $dparam2 = json_decode($dparam,true);
        $orderno = $dparam2['order_id'];
//        $deviceOpenRes = $dparam2['deviceOpenRes'];
        $code = $dparam2['code'];
//        $time = $dparam2['time'];
//        $msg = $dparam2['msg'];
//        $type = $dparam2['type'];
        $res = [];
        $userid = '';
        $containerid = '';
        //return
        $res['code'] = '200';
        $res['msg'] = 'success';
        $ress = $masterApi->encrypt($res);
        $array['param'] = $ress;
        echo json_encode($array);
        if($code == 200){
            //update order
            $orderModel = GoodsorderModel::where('orderno', '=', $orderno)->find();
            $orderModel['orderstatus'] = GoodsOrderStatusEnum::SHOPPING;
            $orderModel['doorstatus'] = GoodsOrderDoorStatusEnum::OPENED;
            $orderModel->save();
            $userid = $orderModel['userid'];
            //update machine
            $machine = MachineModel::get($orderModel['machineid']);
            $machine['status'] = MachineStatusEnum::OPENED;
            $machine ->save();
            $containerid = $machine['containerid'];
        }else if($code == 201){
            //update order
            $orderModel = GoodsorderModel::where('orderno', '=', $orderno)->find();
            $orderModel['orderstatus'] = GoodsOrderStatusEnum::CANCELED;
            $orderModel['doorstatus'] = GoodsOrderDoorStatusEnum::UNPENED;
            $orderModel->save();
            //update machine
            $machine = MachineModel::get($orderModel['machineid']);
            $machine['status'] = MachineStatusEnum::UNOPENED;
            $machine ->save();
            $containerid = $machine['containerid'];
        }
        //log
        $interfaceLogModel = new InterfaceLogModel;
        $interfaceLogModel['logid'] = uuid();
        $interfaceLogModel['operaterid'] = $userid;
        $interfaceLogModel['detailinfo'] = $orderno.' 开门回调地址返回开门结果（购物）';
        $interfaceLogModel['orderno'] = $orderno;
        $interfaceLogModel['containerid'] = $containerid;
        $interfaceLogModel['operatername'] = '';
        $interfaceLogModel['createtime'] = Date('Y-m-d H:i:s');
        $interfaceLogModel['requesttype'] = 1;
        $interfaceLogModel['operateresult'] = 0;
        $interfaceLogModel['url'] = 'callbackapi/device/open';
        $interfaceLogModel['requestparams'] = $dparam;
        $interfaceLogModel['encryptrequestparams'] = $jdecode;
        $interfaceLogModel['encryptresponseparams'] = $ress;
        $interfaceLogModel['responseparams'] = json_encode($res);
        $interfaceLogModel->save();
    }
    /**
     * 主控云平台推送关门结果（购物）
     * @param userid 用户id
     * @return echo
     * @throws
     */
    public function close() {
        Log::info('主控云平台推送关门结果（购物）--start---');
        Log::info("***********请求开门回调关门***********".Date('Y-m-d H:i:s'));
        $param = file_get_contents('php://input');
        $jdecode = json_decode($param,true);
        $masterApi = new \MasterApi('','');
        $dparam = $masterApi->decrypt($jdecode['param']);
        $dparam2 = json_decode($dparam,true);
        $res = [];
        $orderno = $dparam2['order_id'];
        $containerid = $dparam2['containerid'];
//        $time = $dparam2['time'];
//        $msg = $dparam2['msg'];
//        $type = $dparam2['type'];
//        $deviceCloseres = $dparam2['deviceCloseres'];
        //return
        $res['code'] = '200';
        $res['msg'] = 'success';
        $ress = $masterApi->encrypt($res);
        $array['param'] = $ress;
        echo json_encode($array);
        //update order
        $orderModel = GoodsorderModel::where('orderno', '=', $orderno)->find();
        if($orderModel){
            GoodsorderModel::where('orderno', '=', $orderno)
                ->update(['doorstatus' => GoodsOrderDoorStatusEnum::CLOSED]);
            //        $orderModel['orderstatus'] = GoodsOrderStatusEnum::PREPAREFORPAY;
            //update machine
            $machine = MachineModel::get($orderModel['machineid']);
            $machine['status'] = MachineStatusEnum::CLOSED;
            $machine ->save();
            //log
            $interfaceLogModel = new InterfaceLogModel;
            $interfaceLogModel['logid'] = uuid();
            $interfaceLogModel['operaterid'] = $orderModel['userid'];
            $interfaceLogModel['detailinfo'] = $orderno.' 主控云平台推送关门结果（购物）';
            $interfaceLogModel['orderno'] = $orderno;
            $interfaceLogModel['containerid'] = $containerid;
            $interfaceLogModel['operatername'] = '';
            $interfaceLogModel['createtime'] = Date('Y-m-d H:i:s');
            $interfaceLogModel['requesttype'] = 1;
            $interfaceLogModel['operateresult'] = 0;
            $interfaceLogModel['url'] = 'callbackapi/device/close';
            $interfaceLogModel['requestparams'] = $dparam;
            $interfaceLogModel['encryptrequestparams'] = $jdecode;
            $interfaceLogModel['encryptresponseparams'] = $ress;
            $interfaceLogModel['responseparams'] = json_encode($res);
            $interfaceLogModel->save();
        }else{
            //return
            $res['code'] = '200';
            $res['msg'] = 'success';
            $ress = $masterApi->encrypt($res);
            //log
            $interfaceLogModel = new InterfaceLogModel;
            $interfaceLogModel['logid'] = uuid();
            $interfaceLogModel['operaterid'] = '';
            $interfaceLogModel['detailinfo'] = $orderno.'没有此订单 主控云平台推送关门结果（购物）';
            $interfaceLogModel['orderno'] = $orderno;
            $interfaceLogModel['containerid'] = $containerid;
            $interfaceLogModel['operatername'] = '';
            $interfaceLogModel['createtime'] = Date('Y-m-d H:i:s');
            $interfaceLogModel['requesttype'] = 1;
            $interfaceLogModel['operateresult'] = 0;
            $interfaceLogModel['url'] = 'callbackapi/device/close';
            $interfaceLogModel['requestparams'] = $dparam;
            $interfaceLogModel['encryptrequestparams'] = $jdecode;
            $interfaceLogModel['encryptresponseparams'] = $ress;
            $interfaceLogModel['responseparams'] = json_encode($res);
            $interfaceLogModel->save();
        }

    }
    /**
     * 开门回调地址返回开门结果（理货）
     * @param userid 用户id
     * @return echo
     * @throws
     */
    public function bopen() {
        Log::info('开门回调地址返回开门结果（理货）--start---');
        $param = file_get_contents('php://input');
        $jdecode = json_decode($param,true);
        $masterApi = new \MasterApi('','');
        $dparam = $masterApi->decrypt($jdecode['param']);
        $dparam2 = json_decode($dparam,true);
        $orderno = $dparam2['order_id'];
//        $deviceOpenRes = $dparam2['deviceOpenRes'];
//        $time = $dparam2['time'];
//        $msg = $dparam2['msg'];
//        $type = $dparam2['type'];
        $res = [];
        $orderModel = [];
        $containerid = '';
        //return
        $res['code'] = '200';
        $res['msg'] = 'success';
        $ress = $masterApi->encrypt($res);
        $array['param'] = $ress;
        echo json_encode($array);
        if($dparam2['code'] == 200){
            //update order
            $orderModel = OnsaleModel::where('orderno', '=', $orderno)->find();
            $orderModel['status'] = OnsaleStatusEnum::OPENED;
            $orderModel->save();
            //update machine
            $machine = MachineModel::get($orderModel['machineid']);
            $machine['status'] = MachineStatusEnum::OPENED;
            $machine ->save();
            $containerid = $machine['containerid'];
        }else if($dparam2['code'] == 201){
            //update order
            $orderModel = OnsaleModel::where('orderno', '=', $orderno)->find();
            $orderModel['status'] = OnsaleStatusEnum::CANCELED;//门没开，当前有订单没完成
            $orderModel->save();
            //update machine
            $machine = MachineModel::get($orderModel['machineid']);
            $machine['status'] = MachineStatusEnum::UNOPENED;
            $machine ->save();
            $containerid = $machine['containerid'];
        }
        //log
        $interfaceLogModel = new InterfaceLogModel;
        $interfaceLogModel['logid'] = uuid();
        $interfaceLogModel['operaterid'] = $orderModel['operateuserid'];
        $interfaceLogModel['detailinfo'] = $orderno.' 开门回调地址返回开门结果（理货）';
        $interfaceLogModel['operatername'] = '';
        $interfaceLogModel['orderno'] = $orderno;
        $interfaceLogModel['containerid'] = $containerid;
        $interfaceLogModel['createtime'] = Date('Y-m-d H:i:s');
        $interfaceLogModel['requesttype'] = 1;
        $interfaceLogModel['operateresult'] = 0;
        $interfaceLogModel['url'] = 'callbackapi/device/bopen';
        $interfaceLogModel['requestparams'] = $dparam;
        $interfaceLogModel['encryptrequestparams'] = $jdecode;
        $interfaceLogModel['encryptresponseparams'] = $ress;
        $interfaceLogModel['responseparams'] = json_encode($res);
        $interfaceLogModel->save();
    }
    /**
     * 主控云平台推送关门结果（理货）
     * @param userid 用户id
     * @return echo
     * @throws
     */
    public function bclose() {
        Log::info('主控云平台推送关门结果（理货）');
        $param = file_get_contents('php://input');
        $jdecode = json_decode($param,true);
        $masterApi = new \MasterApi('','');
        $dparam = $masterApi->decrypt($jdecode['param']);
        $dparam2 = json_decode($dparam,true);
        $res = [];
        $orderno = $dparam2['order_id'];
        $containerid = $dparam2['containerid'];
        //return
        $res['code'] = '200';
        $res['msg'] = 'success';
        $ress = $masterApi->encrypt($res);
        $array['param'] = $ress;
        echo json_encode($array);
//        $time = $dparam2['time'];
//        $msg = $dparam2['msg'];
//        $type = $dparam2['type'];
//        $deviceCloseres = $dparam2['deviceCloseres'];
        //update order
        $orderModel = OnsaleModel::where('orderno', '=', $orderno)->find();
        if($orderModel){
            $orderModel['status'] = OnsaleStatusEnum::CLOSED;
            $orderModel->save();
            //update machine
            $machine = MachineModel::get($orderModel['machineid']);
            $machine['status'] = MachineStatusEnum::CLOSED;
            $machine ->save();
            //log
            $interfaceLogModel = new InterfaceLogModel;
            $interfaceLogModel['logid'] = uuid();
            $interfaceLogModel['operaterid'] = $orderModel['operateuserid'];
            $interfaceLogModel['detailinfo'] = $orderno.' 主控云平台推送关门结果（理货）';
            $interfaceLogModel['operatername'] = '';
            $interfaceLogModel['orderno'] = $orderno;
            $interfaceLogModel['containerid'] = $containerid;
            $interfaceLogModel['createtime'] = Date('Y-m-d H:i:s');
            $interfaceLogModel['requesttype'] = 1;
            $interfaceLogModel['operateresult'] = 0;
            $interfaceLogModel['url'] = 'callbackapi/device/bclose';
            $interfaceLogModel['requestparams'] = $dparam;
            $interfaceLogModel['encryptrequestparams'] = $jdecode;
            $interfaceLogModel['encryptresponseparams'] = $ress;
            $interfaceLogModel['responseparams'] = $res;
            $interfaceLogModel->save();
        }else{
            //return
        }

    }
    /**
     * 主控云平台推送 机柜注册结果
     * @param {"containerid": 货柜id, "code": 200, "time": 1153434343}
     * @return echo
     * @throws
     */
    public function regis() {
        Log::info('主控云平台推送 机柜注册结果 --start--');
        $param = file_get_contents('php://input');
        $jdecode = json_decode($param,true);
        $masterApi = new \MasterApi('','');
        $dparam = $masterApi->decrypt($jdecode['param']);
        $dparam2 = json_decode($dparam,true);
        $res = [];
        $code = $dparam2['code'];
        $containerid = $dparam2['containerid'];
//        $time = $param['time'];
        if($code == 200){
            //update machine
            $machine = model('Machine')->where('containerid',$containerid)->find();
            $machine['status'] = MachineStatusEnum::PRODUCE;
            $machine['businessstatus'] = MachineBusinessStatusEnum::NOTCONNECTED;
            $machine ->save();
        }
        //return
        $res['code'] = '200';
        $res['msg'] = 'success';
        $ress = $masterApi->encrypt($res);
        //log
        $interfaceLogModel = new InterfaceLogModel;
        $interfaceLogModel['logid'] = uuid();
        $interfaceLogModel['operaterid'] = '';
        $interfaceLogModel['operatername'] = '';
        $interfaceLogModel['detailinfo'] = '主控云平台推送 机柜注册结果 containerid:'.$containerid;
        $interfaceLogModel['createtime'] = Date('Y-m-d H:i:s');
        $interfaceLogModel['requesttype'] = 1;
        $interfaceLogModel['url'] = 'callbackapi/device/regis';
        $interfaceLogModel['requestparams'] = $dparam;
        $interfaceLogModel['operateresult'] = 0;
        $interfaceLogModel['encryptrequestparams'] = $jdecode;
        $interfaceLogModel['encryptresponseparams'] = $ress;
        $interfaceLogModel['responseparams'] = json_encode($res);
        $interfaceLogModel->save();
        $array['param'] = $ress;
        echo json_encode($array);
    }
}
