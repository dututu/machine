<?php
namespace app\clientapi\controller;
use think\Controller;
use think\Config;
use \think\Log;
use \think\Request;
use app\clientapi\service\Token;
use think\Loader;
use app\common\model\Goodsorder as GoodsorderModel;
use app\common\model\Goodsorderpay as GoodsorderpayModel;
use app\common\model\Goods as GoodsModel;
use app\common\model\Goodsorderdetail as GoodsOrderDetailModel;
use app\common\model\Outrefund as OutrefundModel;
use app\common\model\Outrefundpics as OutrefundPicsModel;
use app\common\model\Rechargelog as RechargeLogModel;
use app\common\model\Formmessage as MessageModel;
use app\common\model\User as UserModel;
use app\worker\controller\WechatMessage;
use app\lib\enum\GoodsOrderStatusEnum;
use app\lib\enum\GoodsOrderDoorStatusEnum;
use app\lib\enum\WechatTemplate;
use app\lib\enum\OnsaleStatusEnum;
use app\lib\enum\MachineStatusEnum;
use app\lib\enum\MachineBusinessStatusEnum;
use think\Db;
Loader::import('master.RfidApiV2', EXTEND_PATH, '.php');
Loader::import('master.MasterApi', EXTEND_PATH, '.php');

/**
 * 订单相关接口
 */
class Order extends Controller
{

    /**
     * 获取用户未支付订单（一个）
     * @param userid
     * @return 订单
     */
    public function unpayorder() {
        $request = Request::instance();
        $uid = Token::getCurrentUid();
        $orderModel = new GoodsorderModel;
        //获取订单主表数据
        $order = $orderModel->getUnpayOrder($uid);
        if($order){
            //获取订单商品
            $goods = $order->goods;
            $newgoods = [];
            foreach ($goods as $good) {
                $newgood['goodsid'] = $good['goodsid'];
                $newgood['goodsname'] = $good['goodsname'];
                $newgood['picurl'] = Config::get('paths.coshost').$good['picurl'];
                array_push($newgoods,$newgood) ;
            }
            $order['goods'] = $newgoods;
//            $order['goodsnum'] = count($newgoods);
            $orderdetails = GoodsOrderDetailModel::where('orderid', $order['orderid'])->select();
            $goodsnum = 0;
            foreach($orderdetails as $goodsorderdetail){
                $goodsnum = $goodsnum+$goodsorderdetail['amount'];
            }
            $order['goodsnum'] = $goodsnum;
            return result(200,"success",$order);
//        $data = GoodsorderModel::with('goods')->find()->hidden(['doorstatus','isshow','remark',[['goodsname']]]);
        }else{
            //没有欠费订单
            Db::name('user')->where(['userid' => $uid])->update(['havearrears' => 0]);
            return result(201,"success",[]);
        }


    }
    /**
     * 订单详情
     * @param 订单id
     * @return 详情
     */
    public function detail() {
        $orderid = input("orderid");
        $orderModel = new GoodsorderModel;
        //获取订单主表数据
        $order = $orderModel->getOrderDetail($orderid);
        //获取订单商品
        $goods = $order->goods;
        $newgoods = [];
        $totalfee = 0;
        foreach ($goods as $good) {
            $newgood['goodsid'] = $good['goodsid'];
            $newgood['goodsname'] = $good['goodsname'];
            $newgood['picurl'] = Config::get('paths.coshost').$good['picurl'];
            $newgood['unitfee'] = $good['pivot']['unitfee'];
            $newgood['amount'] = $good['pivot']['amount'];
            $newgood['totalfee'] = $good['pivot']['totalfee'];
            $newgood['isrefund'] = $good['pivot']['isrefund'];
            $newgood['spec'] = $good['pivot']['spec'];
            $totalfee+=$good['pivot']['totalfee'];
            array_push($newgoods,$newgood) ;
        }
        $order['totalfee'] = $totalfee;
        $order['goods'] = $newgoods;
        $orderdetails = GoodsOrderDetailModel::where('orderid', $order['orderid'])->select();
        $goodsnum = 0;
        foreach($orderdetails as $goodsorderdetail){
            $goodsnum = $goodsnum+$goodsorderdetail['amount'];
        }
        $order['goodsnum'] = $goodsnum;
        //获取支付类型
        $paytypes = GoodsorderpayModel::where('orderid', $orderid)->select();
        $newpaytypes = [];
        foreach ($paytypes as $paytype) {
            $newpaytype['payfee'] = $paytype['payfee'];
            if($paytype['paytype'] == 0){
                $newpaytype['paytype'] = '储值支付';
            }else if($paytype['paytype'] == 1){
                $newpaytype['paytype'] = '微信免密支付';
            }else if($paytype['paytype'] == 2){
                $newpaytype['paytype'] = '微信支付';
            }else if($paytype['paytype'] == 3){
                $newpaytype['paytype'] = '支付宝支付';
            }else if($paytype['paytype'] == 4){
                $newpaytype['paytype'] = '支付宝免密支付';
            }
            array_push($newpaytypes,$newpaytype) ;
        }
        $order['paytypes'] = $newpaytypes;
        //如果是退款订单，查看退款状态
        if($order['orderstatus'] == GoodsOrderStatusEnum::REFUND){
            $order['refunding'] = 0;//已退款
            $order['refundfee'] = 0;
            $detailorders = GoodsOrderDetailModel::where('orderid', $order['orderid'])->select();
            foreach($detailorders as $goodsorderdetail){
                if($goodsorderdetail['isrefund'] == 1){
                    $outrefund = OutrefundModel::where('refundid',$goodsorderdetail['refundorder'])->find();
                    if($outrefund){
                        $order['refundstatus'] = $outrefund['refundstatus'];
                        if($outrefund['refundstatus'] == 0 || $outrefund['refundstatus'] == 1 ||$outrefund['refundstatus'] == 2 ||$outrefund['refundstatus'] == 4 ){
                            $order['refunding'] = 1;
                            $order['refundfee'] = $outrefund['refundfee'];
                            break;
                        }
                    }
                }
            }
        }
//        $data = GoodsorderModel::with('goods')->find()->hidden(['doorstatus','isshow','remark',[['goodsname']]]);
        return result(200,"success",$order);

    }
    /**
     * 订单列表
     * @param userid  status(订单状态)
     * @return 详情
     */
    public function orderlist() {

        $request = Request::instance();
        $uid = Token::getCurrentUid();
        $page = input("page");
        $rows = input("rows");
        $status = input("status");
        $orderModel = new GoodsorderModel;
        //获取订单列表
        $orderResult = $orderModel->getOrderList($page,$rows,$uid,$status);
        $orderList = $orderResult['result'];
        $total = $orderResult['total'];
        foreach ($orderList as $order){
            //获取订单商品
            $goods = $order->goods;
            $newgoods = [];
            foreach ($goods as $good) {
                $newgood['goodsid'] = $good['goodsid'];
                $newgood['goodsname'] = $good['goodsname'];
                $newgood['picurl'] = Config::get('paths.coshost').$good['picurl'];
                $newgood['unitfee'] = $good['pivot']['unitfee'];
                $newgood['amount'] = $good['pivot']['amount'];
                $newgood['totalfee'] = $good['pivot']['totalfee'];
                $newgood['spec'] = $good['pivot']['spec'];
                array_push($newgoods,$newgood) ;
            }
            $order['goods'] = $newgoods;
            $orderdetails = GoodsOrderDetailModel::where('orderid', $order['orderid'])->select();
            $goodsnum = 0;
            foreach($orderdetails as $goodsorderdetail){
                $goodsnum = $goodsnum+$goodsorderdetail['amount'];
            }
            $order['goodsnum'] = $goodsnum;
            //获取支付类型
            $paytypes = GoodsorderpayModel::where('orderid', $order['orderid'])->select();
            $newpaytypes = [];
            foreach ($paytypes as $paytype) {
                $newpaytype['payfee'] = $paytype['payfee'];
                if($paytype['paytype'] == 0){
                    $newpaytype['paytype'] = '储值支付';
                }else if($paytype['paytype'] == 1){
                    $newpaytype['paytype'] = '微信免密支付';
                }else if($paytype['paytype'] == 2){
                    $newpaytype['paytype'] = '微信支付';
                }else if($paytype['paytype'] == 3){
                    $newpaytype['paytype'] = '支付宝支付';
                }else if($paytype['paytype'] == 4){
                    $newpaytype['paytype'] = '支付宝免密支付';
                }
                array_push($newpaytypes,$newpaytype) ;
            }
            $order['paytypes'] = $newpaytypes;
            //如果是退款订单，查看退款状态
            if($order['orderstatus'] == GoodsOrderStatusEnum::REFUND){
                $order['refunding'] = 0;//已退款
                $detailorders = GoodsOrderDetailModel::where('orderid', $order['orderid'])->select();
                foreach($detailorders as $goodsorderdetail){
                    if($goodsorderdetail['isrefund'] == 1){
                        $outrefund = OutrefundModel::where('refundid',$goodsorderdetail['refundorder'])->find();
                        if($outrefund){
                            if($outrefund['refundstatus'] == 0 || $outrefund['refundstatus'] == 1 ||$outrefund['refundstatus'] == 2 ||$outrefund['refundstatus'] == 4 ){
                                $order['refunding'] = 1;
                                break;
                            }
                        }
                    }
                }
            }
        }
        $hasnext = true;
        if($page*$rows>=$total){
            $hasnext = false;
        }
        $data = [];
        $data['page'] = $page;
        $data['total'] = $total;
        $data['hasnext'] = $hasnext;
//        $data = GoodsorderModel::with('goods')->find()->hidden(['doorstatus','isshow','remark',[['goodsname']]]);
        $data['rows'] = $rows;
        $data['data'] = $orderList;
        return result(200,"success",$data);

    }
    /**
     * 去rfid平台拉取订单信息
     * @param userid
     * @return 订单
     */
    public function requestorder() {

        $orderid = input('get.orderid');
        $wxOrderData = new \RfidApiV2('','');
        $result = $wxOrderData->selOrder($orderid,'');
//        dump($result);
//        $orderModel = new GoodsorderModel;
//        //获取订单主表数据
//        $order = $orderModel->getUnpayOrder('1');
//        //获取订单商品
//        $goods = $order->goods;
//        $newgoods = [];
//        foreach ($goods as $good) {
//            $newgood['goodsid'] = $good['goodsid'];
//            $newgood['goodsname'] = $good['goodsname'];
//            $newgood['picurl'] = $good['picurl'];
//            array_push($newgoods,$newgood) ;
//        }
//        $order['goods'] = $newgoods;
//        $order['goodsnum'] = count($newgoods);
////        $data = GoodsorderModel::with('goods')->find()->hidden(['doorstatus','isshow','remark',[['goodsname']]]);
        return result(200,"success",$result);
    }
    /**
     * 退款信息填写页面的服务类型
     * @param
     * @return list
     */
    public function refundressons(){
        $reasons = array(
            0=>array(
                'id' => 1,
                'reason' => '包装问题',
                'selected' => false
            ),
            1=>array(
                'id' => 2,
                'reason' => '过保质期',
                'selected' => false
            ),
            2=>array(
                'id' => 3,
                'reason' => '标签错误',
                'selected' => false
            ),
            3=>array(
                'id' => 4,
                'reason' => '其它',
                'selected' => false
            ),
        );
        return result(200,"success",$reasons);
    }
    /**
     * 申请退款
     * @param userid 退款商品 服务类型 照片 备注
     * @return show status 1 成功
     */
    public function applyrefund() {
        $uid = Token::getCurrentUid();
        $orderid = input('post.orderid');
        $goodsids = input('post.goodsids');
        $labels = input('post.labels');
        $refundremark = input('post.remark');
        $pics = input('post.pics');
        //新建退款订单
        $refundorder = [];
        $uuid1 = uuid();
        $refundorder['refundid'] = $uuid1;
        $refundorder['orderid'] = $orderid;
        $orderno = makeOrderNo();
        $refundorder['orderno'] = $orderno;
        $serialno = uuid();
        $refundorder['serialno'] = $serialno;
        $refundorder['refundstatus'] = 0;
        $refundorder['labels'] = $labels;
        $refundorder['refundremark'] = $refundremark;
        $refundorder['createtime'] = Date('Y-m-d H:i:s');
        $outrefundModel = new OutrefundModel;
        $outrefundModel->save($refundorder);
        //添加图片
        $picss = explode(';',$pics);
        foreach($picss  as $row){
            if($row!=''){
                $pic = [];
                $uuid2 = uuid();
                $pic['picid'] = $uuid2;
                $pic['refundid'] = $refundorder['refundid'];
                $pic['url'] = $row;
                $pic['createtime'] = Date('Y-m-d H:i:s');
                $picssModel = new OutrefundPicsModel;
                $picssModel->save($pic);
            }
        }
        //订单详情
        $totalrefundfee = 0;
        $goodsids = explode(',',$goodsids);
        foreach($goodsids  as $row){
            $goodsDetail = GoodsOrderDetailModel::where('orderid',$orderid)
                ->where('goodsid',$row)
                ->find();
            if($goodsDetail){
                $goodsDetail['isrefund'] = 1;
                $goodsDetail['refundorder'] = $refundorder['refundid'];
                $goodsDetail->save();
                $totalrefundfee = $totalrefundfee +$goodsDetail['totalfee'];
            }

        }
        model('Outrefund')::where('refundid', '=', $refundorder['refundid'])
            ->update(['refundfee' => $totalrefundfee]);
        //更新订单状态
        $goodsOrderModel = GoodsorderModel::get($orderid);
        $goodsOrderModel->orderstatus = GoodsOrderStatusEnum::REFUND;
        $goodsOrderModel->save();
        //
        $data = [];
        $data['refundid'] = $refundorder['refundid'];
        return result(200,'success',$data);
    }
//    /**
//     * 提交退款信息
//     * @param userid 退款商品 服务类型 照片 备注
//     * @return show status 1 成功
//     */
//    public function submitrefundinfo() {
//        $request = Request::instance();
//        $uid = Token::getCurrentUid();
//        $refundid = input('post.refundid');
//        $labels = input('post.labels');
//        $refundremark = input('post.remark');
//        $pics = input('post.pics');
//        //更新退款订单
//        $outrefundModel = OutrefundModel::get($refundid);
//        $outrefundModel['labels'] = $labels;
//        $outrefundModel['refundremark'] = $refundremark;
//        $outrefundModel->save();
//        //添加图片
//        $picss = explode(';',$pics);
//        foreach($picss  as $row){
//            if($row!=''){
//                $pic = [];
//                $uuid = uuid();
//                $pic['picid'] = $uuid;
//                $pic['refundid'] = $refundid;
//                $pic['url'] = $row;
//                $pic['createtime'] = Date('Y-m-d H:i:s');
//                $picssModel = new OutrefundPicsModel;
//                $picssModel->save($pic);
//            }
//
//        }
//        return status(200,'success');
//    }

    /**
     * 更新订单支付状态
     * @param userid 用户id
     * @return show status 1 成功
     */
    public function orderpaystatus()
    {
        $request = Request::instance();
        $uid = Token::getCurrentUid();
        $orderid = input('post.orderid');
        $paystatus = input('post.status');
        $order = GoodsorderModel::get($orderid);
        if($paystatus == 1){//成功
            $order['orderstatus'] = GoodsOrderStatusEnum::PAID;
            $order['payfee'] = $order['totalfee'];
            $user = UserModel::get($uid);
            $user['havearrears'] = 0;
            $user->save();

            //同步订单状态
            $wxOrderData = new \MasterApi($uid,$user['nickname']);
            $data = array(
                'order_number' => $order['orderno'],
                'pay_result' => 200,
                'timestamp' => time(),
            );
            $machine = model('Machine')::get($order['machineid']);
            $json = $wxOrderData->callbackPay($data,$machine['containerid']);
            $result = json_decode($json,true);
        }else{
            $order['orderstatus'] = GoodsOrderStatusEnum::ARREARAGED;
            $user = UserModel::get($uid);
            $user['havearrears'] = 1;
            $user->save();
            //
            //发送模版消息（由于微信支付回调为失败时，无法获取out_trade_no，所以在此处发送）
            $message = MessageModel::where('orderid',$orderid)
                ->where('userid',$uid)
                ->find();
            if($message){
                $openid = $user['openid'];
                $page = 'pages/index/index';
                $form_id = $message['formid'];
                $keyword1 = $order['orderno'];
                $keyword2 = '微信支付未成功';
                $keyword3 = '商品';
                $keyword4 = '点击进入小程序完成支付';
                //发送模板消息
                $template = array(
                    'cmd' => 1,
                    'data' =>array(
                        'touser' => $openid,
                        'template_id' => WechatTemplate::APPUNPAY,
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
                            )
                        )
                    )
                );
                $re = WechatMessage::add(json_encode($template),"erp_options");
                //
                $message->templateid = WechatTemplate::APPUNPAY;
                $message->sendstatus     = 1;
                $message->templatedata    = json_encode($template['data']);
                $message->save();
            }
            //同步订单状态
            $wxOrderData = new \MasterApi($uid,$user['nickname']);
            $data = array(
                'order_number' => $order['orderno'],
                'pay_result' => 201,
                'timestamp' => time(),
            );
            $machine = model('Machine')::get($order['machineid']);
            $json = $wxOrderData->callbackPay($data,$machine['containerid']);
            $result = json_decode($json,true);
        }
        $order->save();
        return status(200, 'success');
    }
}
