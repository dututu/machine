<?php
namespace app\callbackapi\controller;
use think\Controller;
use think\Config;
use \think\Log;
use \think\Request;
use app\lib\enum\GoodsOrderStatusEnum;
use app\lib\enum\OnsaleStatusEnum;
use app\lib\enum\GoodsOrderDoorStatusEnum;
use app\lib\enum\MachineStatusEnum;
use think\Loader;
use app\common\model\Machine as MachineModel;
use app\common\model\Goodsorder as GoodsorderModel;
use app\common\model\Goods as GoodsModel;
use app\common\model\Goodsorderdetail as GoodsOrderDetailModel;
use app\common\model\Goodsorderpay as GoodsorderpayModel;
use app\common\model\User as UserModel;
use app\common\model\Sysuser as SysUserModel;
use app\common\model\Onsalehistory as OnsaleModel;
use app\common\model\Onsaledetail as OnsaleDetailModel;
use app\common\model\Interfacelog as InterfaceLogModel;
use app\worker\controller\WechatMessage;
use app\common\model\Formmessage as MessageModel;
use app\lib\enum\WechatTemplate;
Loader::import('master.MasterApi', EXTEND_PATH, '.php');
Loader::import('master.RfidApiV2', EXTEND_PATH, '.php');
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
/**
* 订单相关回调
 */
class Order extends Controller
{
//

    /**
     * 主控云平台推送 订单结果（购物）
     */
    public function order() {
        Log::info('主控云平台推送 订单结果（购物） --start--');
        Log::info("***********请求开门回调订单结果***********".Date('Y-m-d H:i:s'));
        //
        $param = file_get_contents('php://input');
        $jdecode = json_decode($param,true);
        $masterApi= new \MasterApi('1','admin');
        $dparam = $masterApi->decrypt($jdecode['param']);
        $dparam2 = json_decode($dparam,true);
        //
        $preres = [];
        $order_status = $dparam2['order_status'];
        $orderno = $dparam2['order_id'];
        $userid = '';
        $preres['code'] = '200';
        $preres['msg'] = 'success';
        $res = $masterApi->encrypt($preres);
        $array['param'] = $res;
        echo json_encode($array);

        $containerid = '';
        if($order_status == '200'){
            //update order
            $orderModel = GoodsorderModel::where('orderno', '=', $orderno)->find();
            $machine = MachineModel::get($orderModel['machineid']);
            $containerid = $machine['containerid'];
            if($orderModel['orderstatus'] == GoodsOrderStatusEnum::SHOPPING){
                $orderModel['orderstatus'] = GoodsOrderStatusEnum::PREPAREFORPAY;
                $orderModel['doorstatus'] = GoodsOrderDoorStatusEnum::CLOSED;
                $orderModel->save();
                $userid = $orderModel['userid'];
                //update machine
                $machine['status'] = MachineStatusEnum::CLOSED;
                $machine ->save();
                //拉取订单信息
                Log::info('主控云平台推送 订单结果（购物） 拉取订单信息'.$order_status);
                $need2pay = $this->requestOrder($orderModel['orderid'],$orderModel['orderno']);
                if($need2pay != -1){
                    //更新订单金额
                    $orderModel['totalfee'] = $need2pay;
                    $orderModel['payfee'] = 0;
                    $orderModel->save();
                    //扣费
                    if($need2pay >0){
                        $result =  $this->charge($orderModel['orderid'],$orderModel['userid'],$need2pay);
                        if($result == 200){
                            //给商户发送客服消息
                            $sysuser = model('Sysuser')->where('merchantid',$machine['merchantid'])->find();
                            $user = model('User')->where('userid',$userid)->find();
                            $goodstr = "";
                            $sgoods = model('Goodsorderdetail')->where('orderid', $orderModel['orderid']) ->select();
                            foreach ($sgoods as $subgoods) {
                                $goodstr = $goodstr.$subgoods['goodsname']."(数量:".$subgoods['amount'].")；";
                            }
                            $template = array(
                                'cmd' => 3,
                                'data' =>array(
                                    'touser' => $sysuser['openid'],
                                    'msgtype' => "text",
                                    'text'=>array(
                                        'content' => $machine['location']."购物柜刚刚产生了一笔交易：\n操作时间：".$orderModel['createtime']."\n用户昵称：".$user['nickname']." ".$user['mobile']."\n购买商品：".$goodstr."\n交易金额：".($need2pay/100)."元"
                                    )
                                )
                            );
                            $re = WechatMessage::add(json_encode($template),"erp_options");
                            //发送储值余额变动通知
                            $template = array(
                                'cmd' => 0,
                                'data' =>array(
                                    'touser' => $user['openid'],
                                    'template_id' => WechatTemplate::REHARGECHANGE,
                                    'data' => array(
                                        'first'=>array(
                                            'value' => '尊敬的用户，您的储值余额产生了变动：',
                                            'color' => '#173177'
                                        ),
                                        'keyword1'=>array(
                                            'value' => '微信支付充值',
                                            'color' => '#173177'
                                        ),
                                        'keyword2'=>array(
                                            'value' => '-'.($need2pay/100).'元',
                                            'color' => '#173177'
                                        ),
                                        'keyword3'=>array(
                                            'value' => (($user['fee']-$need2pay)/100).'元',
                                            'color' => '#173177'
                                        ),
                                        'remark'=>array(
                                            'value' => '感谢您的支持，欢迎再次光临',
                                            'color' => '#173177'
                                        ),
                                    )
                                )
                            );
                            WechatMessage::add(json_encode($template),"erp_options");
                        }
                    }else{
                        //同步订单状态
                        $wxOrderData = new \MasterApi($userid,'');
                        $data = array(
                            'order_number' => $orderModel['orderno'],
                            'pay_result' => 200,
                            'timestamp' => time(),
                        );
                        $machine = model('Machine')::get($orderModel['machineid']);
                        $json = $wxOrderData->callbackPay($data,$machine['containerid']);
                        $result = json_decode($json,true);
                    }
                }
            }else{
                Log::info('主控云平台推送 订单结果（购物） 订单不是SHOPPING状态'.$order_status);
            }

        }else{
            //TODO 推送订单失败处理
            Log::info('主控云平台推送 订单结果（购物） --$order_status!=200'.$order_status);
        }


        //log
        $interfaceLogModel = new InterfaceLogModel;
        $interfaceLogModel['logid'] = uuid();
        $interfaceLogModel['operaterid'] = $userid;
        $interfaceLogModel['detailinfo'] = $orderno.' 主控云平台推送订单结果（购物）';
        $interfaceLogModel['operatername'] = '';
        $interfaceLogModel['orderno'] = $orderno;
        $interfaceLogModel['containerid'] = $containerid;
        $interfaceLogModel['createtime'] = Date('Y-m-d H:i:s');
        $interfaceLogModel['requesttype'] = 1;
        $interfaceLogModel['operateresult'] = 0;
        $interfaceLogModel['url'] = 'callbackapi/order/order';
        $interfaceLogModel['requestparams'] = $dparam;
        $interfaceLogModel['encryptrequestparams'] = $jdecode;
        $interfaceLogModel['encryptresponseparams'] = $res;
        $interfaceLogModel['responseparams'] = json_encode($preres);
        $interfaceLogModel->save();

    }
    /**
     * 主控云平台推送 订单结果（理货）
     */
    public function orderb() {
        Log::info('主控云平台推送 订单结果（理货） --start--');
        $param = file_get_contents('php://input');
        $jdecode = json_decode($param,true);
        $masterApi = new \MasterApi('1','admin');
        $dparam = $masterApi->decrypt($jdecode['param']);
        $dparam2 = json_decode($dparam,true);
        //
        $preres = [];
        $order_status = $dparam2['order_status'];
        $orderno = $dparam2['order_id'];
//        $time = $dparam2['time'];
//        $msg = $dparam2['msg'];
//        $type = $dparam2['type'];
        $userid='';
        $preres['code'] = "200";
        $preres['msg'] = 'success';
        $res = $masterApi->encrypt($preres);
        //return
        $array['param'] = $res;
        echo json_encode($array);

        if($order_status == 200){
            //update order
            $orderModel = OnsaleModel::where('orderno', '=', $orderno)->find();
            if($orderModel&&($orderModel['status'] == OnsaleStatusEnum::CLOSED)){
                OnsaleModel::where('orderno', '=', $orderno)
                    ->update(['status' => OnsaleStatusEnum::COMPLETED]);
                $userid = $orderModel['operateuserid'];
                //拉取订单信息
                $this->requestOrderb($orderModel['historyid'],$orderModel['orderno']);
            }


        }else{
            Log::info('主控云平台推送 订单结果（理货） --$order_status!=200'.$order_status);
        }


        //log
        if($orderModel){
            $machine = model('Machine')::get($orderModel['machineid']);
            $interfaceLogModel = new InterfaceLogModel;
            $interfaceLogModel['logid'] = uuid();
            $interfaceLogModel['operaterid'] = $userid;
            $interfaceLogModel['detailinfo'] = $orderno.' 主控云平台推送订单结果（理货）';
            $interfaceLogModel['operatername'] = '';
            $interfaceLogModel['orderno'] = $orderno;
            $interfaceLogModel['containerid'] = $machine['containerid'];
            $interfaceLogModel['createtime'] = Date('Y-m-d H:i:s');
            $interfaceLogModel['requesttype'] = 1;
            $interfaceLogModel['operateresult'] = 0;
            $interfaceLogModel['url'] = 'callbackapi/order/orderb';
            $interfaceLogModel['requestparams'] = $dparam;
            $interfaceLogModel['encryptrequestparams'] = $jdecode;
            $interfaceLogModel['encryptresponseparams'] = $res;
            $interfaceLogModel['responseparams'] = json_encode($preres);
            $interfaceLogModel->save();
        }

//        return json_encode($array);
    }
    /**
     * 拉取订单信息
     */
    protected function requestOrder($orderid,$orderno)
    {
        $orderModel = GoodsorderModel::where('orderno', '=', $orderno)->find();
        $userModel = new UserModel();
        $user = $userModel->where('userid', $orderModel['userid']) ->find();
        $rfidApi = new \RfidApiV2($user['userid'],$user['nickname']);
        //
        $machine = model('Machine')::get($orderModel['machineid']);
        $result = $rfidApi->selOrder($orderno,$machine['containerid']);
        $orderresult = json_decode($result,true);
        Log::info("--------拉取到订单信息---------");
        Log::info($orderresult);
        $need2pay = -1;
        if(isset($orderresult['type'])){
            $type = $orderresult['type'];
            if($type == 1){//购物
                $goods = $orderresult['datas'];
                if($goods&&(count($goods)>0)){
                    $need2pay = 0;
                    foreach ($goods as $good) {
                        $amount = $good['amount'];
                        $barCode = $good['barCode'];
                        $goodsModel = GoodsModel::get(['goodsid' => $barCode]);
                        if($goodsModel){
                            $detailModel = new GoodsOrderDetailModel;
                            $detailModel['detailid'] = uuid();
                            $detailModel['orderid'] = $orderid;
                            $detailModel['goodsid'] = $goodsModel['goodsid'];
                            $detailModel['goodsname'] = $goodsModel['goodsname'];
                            $detailModel['spec'] = $goodsModel['spec'];
                            $detailModel['unitfee'] = $goodsModel['salefee'];
                            $detailModel['amount'] = $amount;
                            $detailModel['totalfee'] = $amount*$goodsModel['salefee'];
                            $detailModel['createtime'] = Date('Y-m-d H:i:s');
                            $detailModel['isrefund'] = 0;
                            $detailModel->save();
                            $need2pay+= $detailModel['totalfee'];
                        }else{
                            Log::info('主控云平台推送 订单结果（购物） --商品不存在--barcode:'.$barCode);
                        }
                    }
                    $orderModel['orderstatus'] = GoodsOrderStatusEnum::UNPAID;
                    $orderModel->save();
                }else{
                    $need2pay = 0;
                    $orderModel['orderstatus'] = GoodsOrderStatusEnum::COMPLETE;
                    $orderModel->save();
                }
            }
        }


        return $need2pay;
    }
    /**
     * 拉取订单信息- 理货
     */
    protected function requestOrderb($historyid,$orderno)
    {
        Log::info('拉取订单信息- 理货'.$orderno);
        $onsaleModel = OnsaleModel::where('orderno', '=', $orderno)->find();
        $sysuserModel = new SysUserModel();
        $sysuser = $sysuserModel->where('userid', $onsaleModel['operateuserid']) ->find();

        $rfidApi = new \RfidApiV2($sysuser['userid'],$sysuser['username']);
//        $result = '{"orderId":"20171031000","type":1,"datas":[{"amount":1,"barCode":"E280681000000039019EB3B7B3B70004"},{"amount":2,"barCode":"E280681000000039019EB3B7B3B70005"}],"timestamp":1509417170}';

        $machine = model('Machine')::get($onsaleModel['machineid']);
        $result = $rfidApi->selOrder($orderno,$machine['containerid']);
        $orderresult = json_decode($result,true);
        if($orderresult){
            $type = $orderresult['type'];
            if($type == 2){
                $adds = $orderresult['adds'];
                $down = $orderresult['down'];
                $onsalecount = 0;
                $offsalecount = 0;
                if($adds){
                    foreach ($adds as $good) {
                        $amount = $good['amount'];
                        $barCode = $good['barCode'];
                        $onsalecount = $onsalecount + $amount;
                        $goodsModel = GoodsModel::get(['goodsid' => $barCode]);
                        if($goodsModel){
                            $detailModel = new OnsaleDetailModel;
                            $detailModel['detailid'] = uuid();
                            $detailModel['onsaleid'] = $historyid;
                            $detailModel['goodsid'] = $goodsModel['goodsid'];
                            $detailModel['goodsname'] = $goodsModel['goodsname'];
                            $detailModel['spec'] = $goodsModel['spec'];
                            $detailModel['unitfee'] = $goodsModel['salefee'];
                            $detailModel['amount'] = $amount;
                            $detailModel['flag'] = 0;
                            $detailModel->save();
                        }else{
                            Log::info('主控云平台推送 订单结果（理货） --商品不存在--barcode:'.$barCode);
                        }
                    }
                }
                if($down){
                    foreach ($down as $good) {
                        $amount = $good['amount'];
                        $barCode = $good['barCode'];
                        $offsalecount = $offsalecount + $amount;
                        $goodsModel = GoodsModel::get(['goodsid' => $barCode]);
                        if($goodsModel){
                            $detailModel = new OnsaleDetailModel;
                            $detailModel['detailid'] = uuid();
                            $detailModel['onsaleid'] = $historyid;
                            $detailModel['goodsid'] = $goodsModel['goodsid'];
                            $detailModel['goodsname'] = $goodsModel['goodsname'];
                            $detailModel['spec'] = $goodsModel['spec'];
                            $detailModel['unitfee'] = $goodsModel['salefee'];
                            $detailModel['amount'] = $amount;
                            $detailModel['flag'] = 1;
                            $detailModel->save();
                        }else{
                            Log::info('主控云平台推送 订单结果（理货） --商品不存在--barcode:'.$barCode);
                        }
                    }
                }
                $onsaleModel['status'] = OnsaleStatusEnum::COMPLETED;//理货完成
                $onsaleModel['offsalecount'] = $offsalecount;
                $onsaleModel['onsalecount'] = $onsalecount;
                $onsaleModel->save();
                //发送客服消息
                $goodstr = "";
                $goodstr2 = "";
                $sgoods = model('Onsaledetail')->where('onsaleid', $onsaleModel['historyid']) ->select();
                foreach ($sgoods as $subgoods) {
                    if($subgoods['flag'] == 0){//上架
                        $goodstr = $goodstr.$subgoods['goodsname']."(数量:".$subgoods['amount'].")；";
                    }else{
                        $goodstr2 = $goodstr2.$subgoods['goodsname']."(数量:".$subgoods['amount'].")；";
                    }
                }
                $template = array(
                    'cmd' => 3,
                    'data' =>array(
                        'touser' => $sysuser['openid'],
                        'msgtype' => "text",
                        'text'=>array(
                            'content' => $machine['location']."购物柜刚刚完成了上货：\n操作时间：".$onsaleModel['operatetime']."\n操作用户：".$sysuser['username']." ".$sysuser['mobile']."\n上货商品：".$goodstr."\n下架商品：".$goodstr2
                        )
                    )
                );
                $re = WechatMessage::add(json_encode($template),"erp_options");
            }
        }else{
            Log::info("requestorderb is null");
        }


        return [];
    }
    /**
     * 付费
     * @return res
     */
    protected function charge($orderid,$userid,$need2pay){
        $userModel = new UserModel();
        $orderModel = GoodsorderModel::get(['orderid' => $orderid]);
        $user = $userModel->where('userid', $userid) ->find();

        if($user['fee']>=$need2pay){
            $newfee = $user['fee'] - $need2pay;
            $user->save([
                'fee'  => $newfee
            ],['userid' => $user['userid']]);
            $orderModel['orderstatus'] = 5;//已支付
            $orderModel['payfee'] = $need2pay;
            $orderModel->save();
            $user->save([
                'havearrears'  => 0
            ],['userid' => $user['userid']]);
            //
            $orderPay = new GoodsorderpayModel();
            $orderPay['orderpayid'] = uuid();
            $serailno = uuid();
            $orderPay['serialno'] = $serailno;
            $orderPay['batchno'] = '0';
            $orderPay['orderid'] = $orderModel['orderid'];
            $orderPay['payfee'] = $need2pay;
            $orderPay['paytime'] = Date('Y-m-d H:i:s');
            $orderPay['paystatus'] = 1;
            $orderPay['paytype'] = 0;
            $orderPay->save();
            //
            //记录日志
            $log = [];
            $uuid = uuid();
            $log['logid'] = $uuid;
            $log['userid'] = $userid;
            $log['logtype'] = 2;
            $log['fee'] = $need2pay;
            $log['serialno'] = $orderid;
            $log['createtime'] = date("Y-m-d H:i:s", time());
            model('Rechargelog')::create($log);
            //
            //发送模板消息
            //更新模版消息记录
            $message = MessageModel::where('orderid',$orderid)->where('userid',$orderModel['userid'])->find();
            if($message){
                $goodsnames = '';
                $goods = model('Goodsorderdetail')->where('orderid', $orderModel['orderid'])->select();
                foreach ($goods as $subgoods) {
                    $goodsnames = $goodsnames.$subgoods['goodsname'].'*'.$subgoods['amount'].';';
                }
                $openid = $user['openid'];
                $template_id = $message['templateid'];
                $page = 'pages/index/index';
                $form_id = $message['formid'];
                $keyword1 = $orderModel['orderno'];
                $keyword2 = '购买成功';
                $keyword3 = $goodsnames;
                $keyword4 = (($orderModel['totalfee'])/100).'元';
                $keyword5 = $orderModel['createtime'];
                //发送模板消息
                $template = array(
                    'cmd' => 1,
                    'data' =>array(
                        'touser' => $openid,
                        'template_id' => $template_id,
                        'page' => $page,
                        'form_id' => $form_id,
                        'data' => array(
                            'keyword1'=>array(
                                'value' => $keyword1
                            ),
                            'keyword2'=>array(
                                'value' => $keyword2
                            ),
                            'keyword3'=>array(
                                'value' => $keyword3
                            ),
                            'keyword4'=>array(
                                'value' => $keyword4
                            ),
                            'keyword5'=>array(
                                'value' => $keyword5
                            )
                        )
                    )
                );
                $re = WechatMessage::add(json_encode($template),"erp_options");
                //
                $message->sendstatus     = 1;
                $message->templatedata    = json_encode($template['data']);
                $message->save();
            }
            //同步订单状态
            $wxOrderData = new \MasterApi($user['userid'],$user['nickname']);
            $data = array(
                'order_number' => $orderModel['orderno'],
                'pay_result' => 200,
                'timestamp' => time(),
            );
            $machine = model('Machine')::get($orderModel['machineid']);
            $json = $wxOrderData->callbackPay($data,$machine['containerid']);
            $result = json_decode($json,true);
            return 200;
        }else{//储值余额不足
            if($user['isnopasspay'] == 1){//已经开通微信代扣
                # 配置参数
                $config = Config::get('wx.wxconfig');
                // 实例支付接口
                $pay = &\Wechat\Loader::get('Pay',$config);
                // 获取预支付ID
                $contract_id = $user['contractid'];
                $body = '订单支付';
                $out_trade_no = $orderModel['orderno'];
                $total_fee = $need2pay;
                $notify_url = config('wx.pa_pay_back_url');
                $result = $pay->pappayapply($out_trade_no, $total_fee, $body,$notify_url,$contract_id);
                Log::info($result);
                // 处理创建结果
                if($result===FALSE){
                    // 接口失败的处理
                    $msg = $pay->errMsg;
                    $orderModel['orderstatus'] = GoodsOrderStatusEnum::ARREARAGED;//已欠费
                    $orderModel->save();
                    $user->save([
                        'havearrears'  => 1
                    ],['userid' => $user['userid']]);
                    return 0;
                }else{
                    if(($result['return_code']=='SUCCESS') && ($result['result_code']=='SUCCESS')){
                        //申请扣费成功，等待回调
                        //同步订单状态
                        $wxOrderData = new \MasterApi($user['userid'],$user['nickname']);
                        $data = array(
                            'order_number' => $orderModel['orderno'],
                            'pay_result' => 200,
                            'timestamp' => time(),
                        );
                        $machine = model('Machine')::get($orderModel['machineid']);
                        $json = $wxOrderData->callbackPay($data,$machine['containerid']);
                        $result = json_decode($json,true);
                        return 1;
                    }else{
                        $orderModel['orderstatus'] = GoodsOrderStatusEnum::ARREARAGED;//已欠费
                        $orderModel->save();
                        $user->save([
                            'havearrears'  => 1
                        ],['userid' => $user['userid']]);
                        return 0;
                    }
                }
            }else{
                Log::info('主控云平台推送 订单结果（购物） --储值余额不足');
                $orderModel['orderstatus'] = GoodsOrderStatusEnum::ARREARAGED;//已欠费
                $orderModel->save();
                $user->save([
                    'havearrears'  => 1
                ],['userid' => $user['userid']]);
                return 0;
            }

        }
    }
}
