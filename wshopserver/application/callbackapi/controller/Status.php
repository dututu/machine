<?php
namespace app\callbackapi\controller;
use think\Controller;
use think\Db;
use \think\Log;
use \think\Request;
use think\Loader;
use app\common\model\Machine as MachineModel;
use app\common\model\Interfacelog as InterfaceLogModel;
Loader::import('master.MasterApi', EXTEND_PATH, '.php');
/**
 * TODO
* 状态相关接口
 */
class Status extends Controller
{
    /**
     * 主控ping运营平台
     * @param userid 用户id
     * @return show status 1 成功
     */
    public function ping() {
        $param = file_get_contents('php://input');
        $jdecode = json_decode($param,true);
        $masterApi = new \MasterApi('','');
        $dparam = $masterApi->decrypt($jdecode['param']);
        $dparam2 = json_decode($dparam,true);
        Log::info($dparam2);
        $res = [];
        $timestamp = $dparam2['timestamp'];
        $ping = $dparam2['ping'];

        //

        //

        //return
        $res['code'] = 200;
        $res['msg'] = 'success';
        $res['send'] = $ping;
        $res['time'] = date("Y-m-d H:i:s", time());
        $ress = $masterApi->encrypt($res);
        //log
        $interfaceLogModel = new InterfaceLogModel;
        $interfaceLogModel['logid'] = uuid();
        $interfaceLogModel['operaterid'] = '';
        $interfaceLogModel['operatername'] = '';
        $interfaceLogModel['createtime'] = Date('Y-m-d H:i:s');
        $interfaceLogModel['requesttype'] = 1;
        $interfaceLogModel['url'] = 'callbackapi/status/ping';
        $interfaceLogModel['requestparams'] = $dparam;
        $interfaceLogModel['encryptrequestparams'] = $jdecode;
        $interfaceLogModel['encryptresponseparams'] = $ress;
        $interfaceLogModel['responseparams'] = $res;
        $interfaceLogModel->save();
        $array['param'] = $ress;
        echo json_encode($array);
    }
    /**
     * RFID平台ping运营平台
     * @param userid 用户id
     * @return show status 1 成功
     */
    public function pings() {
        $param = file_get_contents('php://input');
        $jdecode = json_decode($param,true);
        $masterApi = new \MasterApi('','');
        $dparam = $masterApi->decrypt($jdecode['param']);
        $dparam2 = json_decode($dparam,true);
        $res = [];
        $timestamp = $dparam2['timestamp'];
        $ping = $dparam2['ping'];

        //return
        $res['code'] = 200;
        $res['msg'] = 'success';
        $res['send'] = $ping;
        $res['time'] = date("Y-m-d H:i:s", time());
        $ress = $masterApi->encrypt($res);
        //log
        $interfaceLogModel = new InterfaceLogModel;
        $interfaceLogModel['logid'] = uuid();
        $interfaceLogModel['operaterid'] = '';
        $interfaceLogModel['operatername'] = '';
        $interfaceLogModel['createtime'] = Date('Y-m-d H:i:s');
        $interfaceLogModel['requesttype'] = 1;
        $interfaceLogModel['url'] = 'callbackapi/status/pings';
        $interfaceLogModel['requestparams'] = $dparam;
        $interfaceLogModel['encryptrequestparams'] = $jdecode;
        $interfaceLogModel['encryptresponseparams'] = $ress;
        $interfaceLogModel['responseparams'] = $res;
        $interfaceLogModel->save();
        $array['param'] = $ress;
        echo json_encode($array);
    }

}
