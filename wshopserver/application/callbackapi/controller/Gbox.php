<?php

namespace app\callbackapi\controller;

use app\common\model\Cart;
use app\common\model\Cartitem;
use think\Controller;
use think\controller\Rest;
use think\Db;
use \think\Log;
use \think\Request;
use \think\Response;
use think\Loader;
use app\common\model\Machine as MachineModel;
use app\common\model\Interfacelog as InterfaceLogModel;
use app\lib\enum\GoodsOrderStatusEnum;
use app\lib\enum\OnsaleStatusEnum;
use app\lib\enum\GoodsOrderDoorStatusEnum;
use app\lib\enum\MachineStatusEnum;
use app\common\model\Goodsorder as GoodsorderModel;
use app\common\model\Goods as GoodsModel;
use app\common\model\Cartitem as CartItemModel;
use app\common\model\Cart as CartModel;
use app\common\model\Goodsorderdetail as GoodsOrderDetailModel;
use app\common\model\Goodsorderpay as GoodsorderpayModel;
use app\common\model\User as UserModel;
use app\worker\controller\WechatMessage;
use app\common\model\Formmessage as MessageModel;
use app\lib\enum\WechatTemplate;
use think\Config;
require_once 'Gateway.php';
use GatewayClient\Gateway;

Loader::import('master.MasterApi', EXTEND_PATH, '.php');
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
Loader::import('master.GoboxApi', EXTEND_PATH, '.php');

/**
 *
 * 状态相关接口
 */
class Gbox extends Controller
{
    /**
     * gbox回调
     */
    public function notify()
    {
        $param = file_get_contents('php://input');
        Log::info('gbox推送购物车');
        Log::info($param);
//        $param = '{"transid":"201801312021488291","dev_uid":"02c000815ec25674","time":1517401440,"evt_type":"Cart","evt_data":[{"op_type":1,"ori_pos":"","pos":"010201","barcode":"11d9d58455b905b7e946868ab6134cf8","count":1},{"op_type":3,"ori_pos":"","pos":"010201","barcode":"57a93b663cb5e93c390d6492fe4e3d3b","count":2},{"op_type":4,"ori_pos":"","pos":"010201","barcode":"6d90879fe93c44261c3532243b90fefe","count":1},{"op_type":2,"ori_pos":"","pos":"010201","barcode":"7c37fcff85e4c485e5e82ab28bae3967","count":1},{"op_type":1,"ori_pos":"","pos":"010201","barcode":"11d9d58455b905b7e946868ab6134cf8","count":1},{"op_type":3,"ori_pos":"","pos":"010201","barcode":"57a93b663cb5e93c390d6492fe4e3d3b","count":2},{"op_type":4,"ori_pos":"","pos":"010201","barcode":"6d90879fe93c44261c3532243b90fefe","count":1},{"op_type":2,"ori_pos":"","pos":"010201","barcode":"7c37fcff85e4c485e5e82ab28bae3967","count":1},{"op_type":1,"ori_pos":"","pos":"010201","barcode":"11d9d58455b905b7e946868ab6134cf8","count":1},{"op_type":3,"ori_pos":"","pos":"010201","barcode":"57a93b663cb5e93c390d6492fe4e3d3b","count":2},{"op_type":4,"ori_pos":"","pos":"010201","barcode":"6d90879fe93c44261c3532243b90fefe","count":1},{"op_type":2,"ori_pos":"","pos":"010201","barcode":"7c37fcff85e4c485e5e82ab28bae3967","count":1}],"sign":"04c23d87bd8c32b83feddfe1a296f3e2"}';
//        $paramorder = '{"transid":"201802011305406927","dev_id":"02c000815ec25674","time":1517401440,"evt_type":"Order","user_type":1,"evt_data":[{"op_type":1,"ori_pos":"","pos":"010201","barcode":"11d9d58455b905b7e946868ab6134cf8","count":1},{"op_type":1,"ori_pos":"","pos":"010201","barcode":"57a93b663cb5e93c390d6492fe4e3d3b","count":1"count":1},{"op_type":1,"ori_pos":"","pos":"010201","barcode":"6d90879fe93c44261c3532243b90fefe","count":1}],"sign":"7e772f85c767601a5c6e740d001da9b3"}';
        //关门事件 {"evt_type":"DoorStatus","transid":"B405962674506597","dev_uid":"02c00081083032d3","dev_id":1038,"time":1522896182,"user_type":0,"status":0,"sign":"121406af7e6872fac7be0c7bfc8b8f83"}
        try {
            $jdecode = json_decode($param, true);
            $evt_type = $jdecode['evt_type'];
            if ($evt_type == 'Cart') {
                $transid = $jdecode['transid'];//交易ID orderno
                Log::info('gbox推送购物车' . $transid);
//                $dev_uid = $jdecode['dev_uid'];//管理设备的 union id
                $dev_id = $jdecode['dev_id'];
                $evt_data = $jdecode['evt_data'];//一次事件的具体数据
                $response = [];
                $response['op'] = "cart";
                $array = array();
                //购物车持久化
                $cartModel = Cart::where('orderno', '=', $transid)->find();
                if ($cartModel) {
                    $cartid = $cartModel['cartid'];
                } else {
                    $machine = MachineModel::where('boxdevid', '=', $dev_id)->find();
                    $cartModel = new Cart();
                    $cartid = uuid();
                    $cartModel['cartid'] = $cartid;
                    $cartModel['orderno'] = $transid;
                    $cartModel['machineid'] = $machine['machineid'];
                    $cartModel['createtime'] = Date('Y-m-d H:i:s');
                    $cartModel->save();
                }
//            //删除原先的cartitem
//            Db::table('cartitem')->where('cartid','=',$cartModel['cartid'])->delete();
                //
                $weights = [];
                $weightdata = [];
                foreach ($evt_data as $item) {
                    $op_type = $item['op_type'];// 1 货架的商品拿出到购物车 2 购物车商品放回正确的货架 3 购物车商品未放回正确的货架 4 被错误放置的商品重新被取出
                    $goodsid = $item['barcode'];
                    $count = $item['count'];//商品数量
                    $pos = $item['pos'];//货架号
                    $weight = $item['weight'];//weight
                    $weightdata['weight'] = $weight;
                    $weightdata['pos'] = $pos;
                    $weightdata['op_type'] = $op_type;
                    array_push($weights,$weightdata);
//                $ori_pos = $item['ori_pos'];//商品原先属于哪个货架号
                    if ($op_type == 1 || $op_type == 4) {
                        $currentItem = Cartitem::where('cartid', $cartid)->where('goodsid', $goodsid)->find();
                        if ($currentItem) {
                            $newcount = $count + $currentItem['goodscount'];
                            Db::name('cartitem')
                                ->where('cartid', $cartid)
                                ->where('goodsid', $goodsid)
                                ->update(['goodscount' => $newcount]);
                        } else {
                            //添加cartitem
                            $cartItem = new Cartitem();
                            $cartItem['cartitemid'] = uuid();
                            $cartItem['cartid'] = $cartModel['cartid'];
                            $cartItem['goodsid'] = $goodsid;
                            $cartItem['goodscount'] = $count;
                            $cartItem['pos'] = $pos;
//                $cartItem['oripos'] = $ori_pos;
                            $cartItem['createtime'] = Date('Y-m-d H:i:s');
                            $cartItem->save();
                        }
                    } else {
                        $currentItem = Cartitem::where('cartid', $cartid)->where('goodsid', $goodsid)->find();
                        if ($currentItem) {
                            if ($currentItem['goodscount'] > $count) {
                                $newcount = $currentItem['goodscount'] - $count;
                                Db::name('cartitem')
                                    ->where('cartid', $cartid)
                                    ->where('goodsid', $goodsid)
                                    ->update(['goodscount' => $newcount]);
                            } else {
                                Db::table('cartitem')->where('cartid', '=', $cartid)
                                    ->where('goodsid', '=', $goodsid)->delete();
                            }
                        }
                    }
                }

                $currentItems = Cartitem::where('cartid', $cartid)->select();
                $totalfee = 0;
                foreach ($currentItems as $item) {
                    $goodsModel = GoodsModel::where('goodsid', '=', $item['goodsid'])->find();
                    if ($goodsModel) {
                        $goodsModel['count'] = $item['goodscount'];
                        $goodsModel['picurl'] = Config::get('paths.coshost') . $goodsModel['picurl'];
                        $totalfee = $totalfee + $goodsModel['salefee'] * $goodsModel['count'];
                        array_push($array, $goodsModel);
                    }
                }

                $response['weights'] = $weights;
                $response['totalfee'] = $totalfee;
                $response['data'] = $array;

                //
                Gateway::$registerAddress = '192.168.10.55:1238';// 10.141.180.245:1238
                Gateway::sendToUid($transid, json_encode($response));
                // 建立socket连接到内部推送端口
//                $client = stream_socket_client('tcp://127.0.0.1:5678', $errno, $errmsg, 15);
//                $data = array('orderno' => $transid, 'data' => json_encode($response));
//                // 发送数据，注意5678端口是Text协议的端口，Text协议需要在数据末尾加上换行符
//                fwrite($client, json_encode($data) . "\n");
                // 读取推送结果
//                echo fread($client, 8192);

            } else if ($evt_type == 'Order') {
                $transid = $jdecode['transid'];
                Log::info('gbox推送订单结果' . $transid);
                $dev_id = $jdecode['dev_id'];
                $evt_data = $jdecode['evt_data'];//一次事件的具体数据
                $orderModel = GoodsorderModel::where('orderno', '=', $transid)->find();
                $machine = MachineModel::get($orderModel['machineid']);
                $containerid = $machine['containerid'];
                if ($orderModel['orderstatus'] == GoodsOrderStatusEnum::SHOPPING) {
                    $orderModel['orderstatus'] = GoodsOrderStatusEnum::PREPAREFORPAY;
                    $orderModel['doorstatus'] = GoodsOrderDoorStatusEnum::CLOSED;
                    $orderModel->save();
                    $userid = $orderModel['userid'];
                    //update machine
                    $machine['status'] = MachineStatusEnum::CLOSED;
                    $machine->save();
                    //计算金额
                    if ($evt_data && (count($evt_data) > 0)) {
                        $need2pay = 0;
                        foreach ($evt_data as $good) {
                            $goodsid = $good['barcode'];
                            $op_type = $good['op_type'];//只关注1
                            $count = $good['count'];//商品数量
                            $pos = $good['pos'];//货架号
//                        $ori_pos = $good['ori_pos'];//商品原先属于哪个货架号
                            if ($op_type == 1) {
                                $goodsModel = GoodsModel::where('goodsid', '=', $goodsid)->find();
                                if ($goodsModel) {
                                    $detailModel = new GoodsOrderDetailModel;
                                    $detailModel['detailid'] = uuid();
                                    $detailModel['orderid'] = $orderModel['orderid'];
                                    $detailModel['goodsid'] = $goodsModel['goodsid'];
                                    $detailModel['goodsname'] = $goodsModel['goodsname'];
                                    $detailModel['spec'] = $goodsModel['spec'];
                                    $detailModel['unitfee'] = $goodsModel['salefee'];
                                    $detailModel['amount'] = $count;
                                    $detailModel['totalfee'] = $count * $goodsModel['salefee'];
                                    $detailModel['createtime'] = Date('Y-m-d H:i:s');
                                    $detailModel['isrefund'] = 0;
                                    $detailModel->save();
                                    $need2pay = $need2pay + $detailModel['totalfee'];
                                    //
//                                $option = [];
//                                $option['time'] = time();
//                                $option['option'] = 1;
//                                $option['dev_id'] = $machine['boxdevid'];
//                                $option['pos'] = $pos;
//                                $option['barcode'] = $goodsid;
//                                $option['num'] = $count;
//                                $option['memo'] = '';
//                                $gboxApi = new \GoboxApi('','');
//                                $masterresult = $gboxApi->updateSkuCount($option);
                                    //
                                } else {
                                    Log::info('gbox推送订单结果--商品不存在--goodsid:' . $goodsid);
                                }
                            }

                        }
                        if($need2pay == 0){
                            $orderModel['orderstatus'] = GoodsOrderStatusEnum::COMPLETE;
                            $orderModel->save();
                        }else{
                            $orderModel['orderstatus'] = GoodsOrderStatusEnum::UNPAID;
                            $orderModel->save();
                        }

                    } else {
                        $need2pay = 0;
                        $orderModel['orderstatus'] = GoodsOrderStatusEnum::COMPLETE;
                        $orderModel->save();
                    }

                    //----------
                    //更新订单金额
                    $orderModel['totalfee'] = $need2pay;
                    $orderModel['payfee'] = 0;
                    $orderModel->save();
                    //扣费
                    if ($need2pay > 0) {
                        $result = $this->charge($orderModel['orderid'], $orderModel['userid'], $need2pay);
                        if ($result == 200) {
                            //给商户发送客服消息
                            $sysuser = model('Sysuser')->where('merchantid', $machine['merchantid'])->find();
                            $user = model('User')->where('userid', $userid)->find();
                            $goodstr = "";
                            $sgoods = model('Goodsorderdetail')->where('orderid', $orderModel['orderid'])->select();
                            foreach ($sgoods as $subgoods) {
                                $goodstr = $goodstr . $subgoods['goodsname'] . "(数量:" . $subgoods['amount'] . ")；";
                            }
                            $template = array(
                                'cmd' => 3,
                                'data' => array(
                                    'touser' => $sysuser['openid'],
                                    'msgtype' => "text",
                                    'text' => array(
                                        'content' => $machine['location'] . "购物柜刚刚产生了一笔交易：\n操作时间：" . $orderModel['createtime'] . "\n用户昵称：" . $user['nickname'] . " " . $user['mobile'] . "\n购买商品：" . $goodstr . "\n交易金额：" . ($need2pay / 100) . "元"
                                    )
                                )
                            );
                            $re = WechatMessage::add(json_encode($template), "erp_options");
                            //发送储值余额变动通知
                            $template = array(
                                'cmd' => 0,
                                'data' => array(
                                    'touser' => $user['openid'],
                                    'template_id' => WechatTemplate::REHARGECHANGE,
                                    'data' => array(
                                        'first' => array(
                                            'value' => '尊敬的用户，您的储值余额产生了变动：',
                                            'color' => '#173177'
                                        ),
                                        'keyword1' => array(
                                            'value' => '储值支付',
                                            'color' => '#173177'
                                        ),
                                        'keyword2' => array(
                                            'value' => '-' . ($need2pay / 100) . '元',
                                            'color' => '#173177'
                                        ),
                                        'keyword3' => array(
                                            'value' => (($user['fee'] - $need2pay) / 100) . '元',
                                            'color' => '#173177'
                                        ),
                                        'remark' => array(
                                            'value' => '感谢您的支持，欢迎再次光临',
                                            'color' => '#173177'
                                        ),
                                    )
                                )
                            );
                            WechatMessage::add(json_encode($template), "erp_options");



                        }
                    }
                    $newOrderModel = GoodsorderModel::where('orderno', '=', $transid)->find();
                    $response = [];
                    $response['op'] = "order";
                    $response['orderstatus'] = $newOrderModel['orderstatus'];
                    //
                    Gateway::$registerAddress = '192.168.10.55:1238';//10.141.180.245:1238
                    Gateway::sendToUid($transid, json_encode($response));
                    // 建立socket连接到内部推送端口
//                            $client = stream_socket_client('tcp://127.0.0.1:5678', $errno, $errmsg, 15);
//                            $data = array('orderno' => $transid, 'data' => json_encode($response));
//                            // 发送数据，注意5678端口是Text协议的端口，Text协议需要在数据末尾加上换行符
//                            fwrite($client, json_encode($data) . "\n");
                    // 读取推送结果
//                            echo fread($client, 8192);
                } else {
                    Log::info('gbox推送订单结果 订单不是SHOPPING状态' . $transid);
                }

                $interfaceLogModel = new InterfaceLogModel;
                $interfaceLogModel['logid'] = uuid();
                $interfaceLogModel['operaterid'] = $orderModel['userid'];
                $interfaceLogModel['operatername'] = '';
                $interfaceLogModel['createtime'] = Date('Y-m-d H:i:s');
                $interfaceLogModel['requesttype'] = 0;
                $interfaceLogModel['url'] = 'order';
                $interfaceLogModel['requestparams'] = $param;
                $interfaceLogModel['encryptrequestparams'] = '';
                $interfaceLogModel['detailinfo'] = $transid . ' gbox回调订单信息';
                $interfaceLogModel['operateresult'] = 0;
                $interfaceLogModel['orderno'] = $transid;
                $interfaceLogModel['containerid'] = $containerid;
                $interfaceLogModel['encryptresponseparams'] = '';
                $interfaceLogModel['responseparams'] = '';
                $interfaceLogModel->save();
            } else if ($evt_type == 'DoorStatus') {
                $transid = $jdecode['transid'];//交易ID orderno
                $dev_id = $jdecode['dev_id'];
                $user_type = $jdecode['user_type'];
                $status = $jdecode['status'];
                if ($user_type == 1 && $status == 1) {//开门
                    $orderModel = GoodsorderModel::where('orderno', '=', $transid)->find();
                    $orderModel['doorstatus'] = GoodsOrderDoorStatusEnum::OPENED;
                    $orderModel->save();
                    $machine = MachineModel::get($orderModel['machineid']);
                    $machine['status'] = MachineStatusEnum::OPENED;
                    $machine->save();
                } else if ($user_type == 1 && $status == 0) {//关门
                    $orderModel = GoodsorderModel::where('orderno', '=', $transid)->find();
                    $orderModel['doorstatus'] = GoodsOrderDoorStatusEnum::CLOSED;
                    $orderModel->save();
                    $machine = MachineModel::get($orderModel['machineid']);
                    $machine['status'] = MachineStatusEnum::CLOSED;
                    $machine->save();
                } else if ($user_type == 2 && $status == 1) {//理货开门
                    $machine = MachineModel::where('boxdevid', '=', $dev_id)->find();
                    $machine['status'] = MachineStatusEnum::OPENED;
                    $machine->save();
                } else if ($user_type == 2 && $status == 0) {//理货关门
                    $machine = MachineModel::where('boxdevid', '=', $dev_id)->find();
                    $machine['status'] = MachineStatusEnum::CLOSED;
                    $machine->save();
                }

            }
            header('HTTP/1.1 204 SUCCESS');
            exit();
        } catch (\Exception $e) {
//            $this->error('执行错误');
            Log::info('执行错误');
            Log::info($e);
            header('HTTP/1.1 500 FAIL');
            exit();

        }
//        Response::create('', 'json')->code(204);
//        json('')->code(204);

    }

    /**
     * 付费
     * @return res
     */
    protected function charge($orderid, $userid, $need2pay)
    {
        $userModel = new UserModel();
        $orderModel = GoodsorderModel::get(['orderid' => $orderid]);
        $user = $userModel->where('userid', $userid)->find();

        if ($user['fee'] >= $need2pay) {
            $newfee = $user['fee'] - $need2pay;
            $user->save([
                'fee' => $newfee
            ], ['userid' => $user['userid']]);
            $orderModel['orderstatus'] = 5;//已支付
            $orderModel['payfee'] = $need2pay;
            $orderModel->save();
            $user->save([
                'havearrears' => 0
            ], ['userid' => $user['userid']]);
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
            $message = MessageModel::where('orderid', $orderid)->where('userid', $orderModel['userid'])->find();
            if ($message) {
                $goodsnames = '';
                $goods = model('Goodsorderdetail')->where('orderid', $orderModel['orderid'])->select();
                foreach ($goods as $subgoods) {
                    $goodsnames = $goodsnames . $subgoods['goodsname'] . '*' . $subgoods['amount'] . ';';
                }
                $openid = $user['openid'];
                $template_id = $message['templateid'];
                $page = 'pages/index/index';
                $form_id = $message['formid'];
                $keyword1 = $orderModel['orderno'];
                $keyword2 = '购买成功';
                $keyword3 = $goodsnames;
                $keyword4 = (($orderModel['totalfee']) / 100) . '元';
                $keyword5 = $orderModel['createtime'];
                //发送模板消息
                $template = array(
                    'cmd' => 1,
                    'data' => array(
                        'touser' => $openid,
                        'template_id' => $template_id,
                        'page' => $page,
                        'form_id' => $form_id,
                        'data' => array(
                            'keyword1' => array(
                                'value' => $keyword1
                            ),
                            'keyword2' => array(
                                'value' => $keyword2
                            ),
                            'keyword3' => array(
                                'value' => $keyword3
                            ),
                            'keyword4' => array(
                                'value' => $keyword4
                            ),
                            'keyword5' => array(
                                'value' => $keyword5
                            )
                        )
                    )
                );
                $re = WechatMessage::add(json_encode($template), "erp_options");
                //
                $message->sendstatus = 1;
                $message->templatedata = json_encode($template['data']);
                $message->save();
            }
            return 200;
        } else {//储值余额不足
            if ($user['isnopasspay'] == 1) {//已经开通微信代扣
                # 配置参数
                $config = Config::get('wx.wxconfig');
                // 实例支付接口
                $pay = &\Wechat\Loader::get('Pay', $config);
                // 获取预支付ID
                $contract_id = $user['contractid'];
                $body = '订单支付';
                $out_trade_no = $orderModel['orderno'];
                $total_fee = $need2pay;
                $notify_url = config('wx.pa_pay_back_url');
                $result = $pay->pappayapply($out_trade_no, $total_fee, $body, $notify_url, $contract_id);
                Log::info($result);
                // 处理创建结果
                if ($result === FALSE) {
                    // 接口失败的处理
//                    $msg = $pay->errMsg;
                    $orderModel['orderstatus'] = GoodsOrderStatusEnum::ARREARAGED;//已欠费
                    $orderModel->save();
                    $user->save([
                        'havearrears' => 1
                    ], ['userid' => $user['userid']]);
                    return 0;
                } else {
                    if (($result['return_code'] == 'SUCCESS') && ($result['result_code'] == 'SUCCESS')) {
                        //扣款接口请求成功，返回success仅代表扣款申请受理成功，不代表扣款成功。扣款是否成功以支付通知的结果为准。
                        $orderModel['orderstatus'] = GoodsOrderStatusEnum::UNPAID;//待支付
                        $orderModel['payfee'] = $need2pay;
                        $orderModel->save();
                        $user->save([
                            'havearrears' => 0
                        ], ['userid' => $user['userid']]);

                        return 2000;
                    } else {
                        $orderModel['orderstatus'] = GoodsOrderStatusEnum::ARREARAGED;//已欠费
                        $orderModel->save();
                        $user->save([
                            'havearrears' => 1
                        ], ['userid' => $user['userid']]);
                        return 0;
                    }
                }
            } else {
                Log::info('主控云平台推送 订单结果（购物） --储值余额不足');
                $orderModel['orderstatus'] = GoodsOrderStatusEnum::ARREARAGED;//已欠费
                $orderModel->save();
                $user->save([
                    'havearrears' => 1
                ], ['userid' => $user['userid']]);
                return 0;
            }

        }
    }

}
