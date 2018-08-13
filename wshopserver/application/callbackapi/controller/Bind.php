<?php
namespace app\callbackapi\controller;
use think\Controller;
use think\Db;
use \think\Log;
use \think\Request;
use think\Loader;
use app\common\model\Machine as MachineModel;
use app\common\model\Interfacelog as InterfaceLogModel;

//加载GatewayClient。关于GatewayClient参见本页面底部介绍
// GatewayClient 3.0.0版本开始要使用命名空间
require_once 'Gateway.php';
use GatewayClient\Gateway;
/**
 * TODO
* bind todo
 */
class Bind extends Controller
{

    public function bind($orderno,$client_id) {
        // 设置GatewayWorker服务的Register服务ip和端口，请根据实际情况改成实际值
        Gateway::$registerAddress = '10.141.180.245:1238';
        // 假设用户已经登录，用户uid和群组id在session中
//        $uid = $_SESSION['uid'];
        // client_id与uid绑定
        $result =  Gateway::bindUid($client_id, $orderno);
        return $result;
    }


}
