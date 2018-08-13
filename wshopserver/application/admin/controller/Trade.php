<?php
namespace app\admin\controller;
use think\Config;
use think\Log;
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

Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
Loader::import('master.GoboxApi', EXTEND_PATH, '.php');
/**
 * 交易管理
 *
 * @author      Caesar
 * @version     1.0
 */
class Trade extends  Adminbase //Base
{
    private  $obj,$orderdetailobj,$userobj,$merchantobj,$machineobj,$outrefund,$outrefundpics,$goodsorderpay;
    public function _initialize() {
        parent::_initialize();
        $this->obj = model("Goodsorder");
        $this->orderdetailobj = model("Goodsorderdetail");
        $this->merchantobj = model("Merchant");
        $this->userobj = model("User");
        $this->machineobj = model("Machine");
        $this->outrefund = model("Outrefund");
        $this->outrefundpics = model("Outrefundpics");
        $this->goodsorderpay = model("Goodsorderpay");
    }
    protected $beforeActionList = [
        'checkAuth' => ['only' => 'index']
    ];
    /**
     * 交易管理页
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function index()
    {
        //
        $prerefundcount = model("Outrefund")->where('refundstatus',0)->count();
        $nopaycount = model("Goodsorder")->where('orderstatus',6)->count();
        //
        $datasuccess = 'orderstatus = 5 or orderstatus = 8';
        $successcount = model("Goodsorder")->where($datasuccess)->count();
        $refundcount = model("Outrefund")->where('refundstatus',3)->count();
        return $this->fetch('index',[
            'prerefundcount'=>$prerefundcount,
            'nopaycount'=>$nopaycount,
            'successcount'=>$successcount,
            'refundcount'=>$refundcount,
        ]);
    }

    /**
     * ajax获取订单列表
     *
     * 前端使用jqgrid控件渲染表格
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function tradelist(){
        //search params
        $s_name = input("s_name");
        $s_select = input("s_select");
        $s_time = input("s_time");
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $status = input("status");
        $suserid = input("userid");
        $offset = (input("page") - 1) * input("rows");
        if($status!=null&&$status!=''){
            $value = $this->obj->getOrderListByStatus($rows,$offset,$status);
            $records = $this->obj->getOrderListCountByStatus($status);
        }else if($suserid!=null&&$suserid!=''){
            $value = $this->obj->getOrderListByUserid($rows,$offset,$suserid);
            $records = $this->obj->getOrderListCountByUserid($suserid);
        }else{
            $value = $this->obj->getList($rows,$sidx,$sord,$offset,$s_name,$s_select,$s_time);
            $records = $this->obj->getListCount($s_name,$s_select,$s_time);
        }

        $total = ceil($records/$rows);
//        $this->recordlog('4',0,'获取交易订单列表');
        return pagedata($page,$total,$records, $value);
    }
    public function exporttradelist(){
        //search params
        $s_name = input("s_name");
        $s_select = input("s_select");
        $s_time = input("s_time");
        //orderby and page param
        $page = input("page");
        $rows = 99999;
        $sidx = input("sidx");
        $sord = input("sord");
        $status = input("status");
        $suserid = input("userid");
        $offset = 0;
        if($status!=null&&$status!=''){
            $value = $this->obj->getOrderListByStatus($rows,$offset,$status);
        }else if($suserid!=null&&$suserid!=''){
            $value = $this->obj->getOrderListByUserid($rows,$offset,$suserid);
        }else{
            $value = $this->obj->getList($rows,$sidx,$sord,$offset,$s_name,$s_select,$s_time);
        }
        $newlist = [];
        $newitem = [];
        foreach ($value as $item){
            $newitem['orderno'] = $item['orderno'];
            $goodsorderpays = model('Goodsorderpay')->where('orderid', $item['orderid']) ->select();
            $batchnos = [];
            foreach ($goodsorderpays as $pays){
                array_push($batchnos,$pays['batchno']);
            }
            $newitem['batchno'] = $batchnos;
            $newitem['merchantname'] = $item['merchantname'];
            $user = model('User')::get($item['userid']);
            $newitem['mobile'] = $user['mobile'];
            $newitem['containerid'] = $item['containerid'];
            $newitem['totalfee'] = $item['totalfee']/100;
            $newitem['payfee'] = $item['payfee']/100;
            $newitem['orderstatus'] = $item['orderstatus'];
            if($item['orderstatus'] == 1){
                $newitem['orderstatus'] = '购物中';
            }else if($item['orderstatus'] == 2){
                $newitem['orderstatus'] = '待结账';
            }else if($item['orderstatus'] == 3){
                $newitem['orderstatus'] = '已取消';
            }else if($item['orderstatus'] == 4){
                $newitem['orderstatus'] = '待支付';
            }else if($item['orderstatus'] == 5){
                $newitem['orderstatus'] = '已付款';
            }else if($item['orderstatus'] == 6){
                $newitem['orderstatus'] = '已欠费';
            }else if($item['orderstatus'] == 7){
                $newitem['orderstatus'] = '转退款';
            }else if($item['orderstatus'] == 8){
                $newitem['orderstatus'] = '已完成';
            }


            $newitem['createtime'] = $item['createtime'];
            array_push($newlist,$newitem);

        }
        //
        $excel=new Excel();
        $table_name="goodsorder";
        $field=["orderno"=>"订单编号","batchno"=>"流水号","merchantname"=>"商户名称","mobile"=>"买家联系方式","containerid"=>"机柜编号","totalfee"=>"总金额","payfee"=>"付款金额","status"=>"状态","createtime"=>"时间"];
        $map=[];

        $excel->setExcelName("交易订单".Date('Y-m-d H:i:s'))
            ->createOrderSheet("订单数据",$table_name,$field,$newlist)
            ->downloadExcel();
    }
    /**
     * 订单详情页
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function detail($page=1,$orderid='',$status='',$userid='')
    {
        return $this->fetch('order_detail',[
            'orderid'=>$orderid,
            'page'=>$page,
            'status'=>$status,
            'userid'=>$userid
        ]);
    }
    /**
     * ajax获取订单详情
     *
     * @access public
     * @return tp5
     */
    public function detaildata($orderid=''){
        $goodsorder = $this->obj->where('orderid', $orderid)->find();
        $goods = $this->orderdetailobj->where('orderid', $orderid)->select();
        $user = $this->userobj->where('userid',$goodsorder['userid'])->find();
        $machine = $this->machineobj->where('machineid',$goodsorder['machineid'])->find();
        $merchant = $this->merchantobj->where('merchantid',$machine['merchantid'])->find();
        $goodsorderpay = $this->goodsorderpay->where('orderid', $orderid)->select();
        $paytype = '';
        $batchno = '';
        foreach ($goodsorderpay as $orderpay){
            if($orderpay['paytype'] == 0){
                $paytype = $paytype.' 储值支付';
            }else if($orderpay['paytype'] == 1){
                $paytype = $paytype.' 微信免密支付';
                $batchno = $orderpay['batchno'].' ';
            }else if($orderpay['paytype'] == 2){
                $paytype = $paytype.' 微信支付';
                $batchno = $batchno.' '.$orderpay['batchno'].' ';
            }else if($orderpay['paytype'] == 3){
                $paytype = $paytype.' 支付宝支付';
                $batchno = $batchno.' '.$orderpay['batchno'].' ';
            }else if($orderpay['paytype'] == 4){
                $paytype = $paytype.' 支付宝免密支付';
                $batchno = $batchno.' '.$orderpay['batchno'].' ';
            }
        }
        $goodsorder['paytype'] = $paytype;
        $goodsorder['batchno'] = $batchno;
        //查询退款订单
        $refunds = $this->outrefund->where('orderid', $orderid)->select();
        if(count($refunds)>0){
            foreach ($refunds as $refund){
                $refundpics = $this->outrefundpics->where('refundid', $refund['refundid'])->select();
                $refundgoods = $this->orderdetailobj->where('refundorder', $refund['refundid'])->select();
                $refund['refundpics'] = $refundpics;
                $refund['refundgoods'] = $refundgoods;
                if($refund['refundstatus'] == 1||$refund['refundstatus'] == 2||$refund['refundstatus'] == 3||$refund['refundstatus'] == 4){
                    if($refund['opter']){
                        $suser = model('Sysuser')->where('userid',$refund['opter'])->find();
                        $refund['username'] = $suser['username'];
                    }
                }
            }

        }
        return result(200,'success',[
            'orderid'=>$orderid,
            'goodsorder'=>$goodsorder,
            'goods'=>$goods,
            'user'=>$user,
            'machine'=>$machine,
            'merchant'=>$merchant,
            'refunds'=>$refunds
        ]);
    }
    /**
     * 退款
     *
     * @access public
     * @return tp5
     */
    public function applyrefund()
    {
        $loginuserid = $this->getLoginUser()['userid'];
        $orderid = input("orderid");
        $refundid = input("refundid/a");
        $refundfee = input("refundfee/a");
        $remark = input("remark/a");
        $goodsorder = $this->obj->where('orderid', $orderid)->find();
        $totalrefundfee = 0;
        foreach ($refundfee as $subrefundfee){
            $totalrefundfee = $totalrefundfee+$subrefundfee;
        }
        for ($i=0; $i<count($refundid); $i++) {
            Log::info('------:'.$refundid[$i].'-----'.$refundfee[$i].'-----'.$remark[$i]);
            if(empty($remark[$i])){
                $remark[$i] = "";
            }
            $refund = $this->outrefund->where('refundid', $refundid[$i])->find();
            $goodsorderpay = model('Goodsorderpay')->where('orderid', $orderid)->find();
            if($goodsorderpay){
                if($goodsorderpay['paytype'] == 0){//储值支付
                    model('Goodsorder')::where('orderid', '=', $orderid)
                        ->update(['orderstatus' => 7]);
                    $user = model('User')->where('userid', $goodsorder['userid'])->find();
                    model('User')::where('userid', '=', $goodsorder['userid'])
                        ->update(['fee' => ($user['fee']+$totalrefundfee*100)]);
                    model('Outrefund')::where('refundid', '=', $refund['refundid'])
                        ->update(['refundstatus' => 3,'realfee' => $refundfee[$i]*100,'remarks' => $remark[$i],'fefundtime'=>Date('Y-m-d H:i:s'),'opter'=>$loginuserid]);
                    //记录日志
                    $log = [];
                    $uuid = uuid();
                    $log['logid'] = $uuid;
                    $log['userid'] = $goodsorder['userid'];
                    $log['logtype'] = 3;
                    $log['fee'] = $refundfee[$i]*100;
                    $log['serialno'] = $orderid;
                    $log['createtime'] = date("Y-m-d H:i:s", time());
                    model('Rechargelog')::create($log);
                }else if($goodsorderpay['paytype'] == 1){//微信免密支付
                    # 配置参数
                    $config = Config::get('wx.wxconfig');
                    // 实例支付接口
                    $pay = &\Wechat\Loader::get('Pay',$config);
                    // 调用退款接口
                    $out_trade_no = $goodsorder['orderno'];
                    $transaction_id = $goodsorderpay['batchno'];
                    $out_refund_no = $refund['orderno'];
                    $total_fee = $goodsorder['totalfee'];
                    $refund_fee = $totalrefundfee*100;
                    $result = $pay->refund($out_trade_no, $transaction_id, $out_refund_no, $total_fee, $refund_fee, $op_user_id = null);

                    // 处理创建结果
                    if($result===FALSE){
                        // 接口失败的处理
                        Log::info('--免密支付 pay failure--'.$pay->errMsg);
                        model('Outrefund')::where('refundid', '=', $refund['refundid'])
                            ->update(['refundstatus' => 4,'fefundtime'=>Date('Y-m-d H:i:s')]);
                    }else{
                        // 接口成功的处理
                        model('Goodsorder')::where('orderid', '=', $orderid)
                            ->update(['orderstatus' => 7]);
                        model('Outrefund')::where('refundid', '=', $refund['refundid'])
                            ->update(['refundstatus' => 3,'realfee' => $refundfee[$i]*100,'remarks' => $remark[$i],'fefundtime'=>Date('Y-m-d H:i:s'),'opter'=>$loginuserid]);
                    }
                }else if($goodsorderpay['paytype'] == 2){//微信支付
                    # 配置参数
                    $config = Config::get('wx.wxconfig');
                    // 实例支付接口
                    $pay = &\Wechat\Loader::get('Pay',$config);
                    // 调用退款接口
                    $out_trade_no = $goodsorder['orderno'];
                    $transaction_id = $goodsorderpay['batchno'];
                    $out_refund_no = $refund['orderno'];
                    $total_fee = $goodsorder['totalfee'];
                    $refund_fee = $totalrefundfee*100;
                    $result = $pay->refund($out_trade_no, $transaction_id, $out_refund_no, $total_fee, $refund_fee, $op_user_id = null);

                    // 处理创建结果
                    if($result===FALSE){
                        // 接口失败的处理
                        Log::info('--微信支付 pay failure--'.$pay->errMsg);
                        model('Outrefund')::where('refundid', '=', $refund['refundid'])
                            ->update(['refundstatus' => 4,'fefundtime'=>Date('Y-m-d H:i:s')]);
                    }else{
                        // 接口成功的处理
                        model('Goodsorder')::where('orderid', '=', $orderid)
                            ->update(['orderstatus' => 7]);
                        model('Outrefund')::where('refundid', '=', $refund['refundid'])
                            ->update(['refundstatus' => 3,'realfee' => $refundfee[$i]*100,'remarks' => $remark[$i],'fefundtime'=>Date('Y-m-d H:i:s'),'opter'=>$loginuserid]);
                    }
                }
            }
        }
        return result(200,'','');
//        return $this->redirect('detail',["orderid"=>$orderid]);
    }

    /**
     * 刷新订单
     *
     * @access public
     * @return tp5
     */
    public function refreshorder($orderid){
//        $result = model('Goodsorder')::where('orderid', '=', $orderid)
//            ->update(['businessstatus' => MachineBusinessStatusEnum::CANCELED]);//已作废
        $goodsorder = model('Goodsorder')->where('orderid',$orderid)->find();
        if($goodsorder&&($goodsorder['orderstatus'] == 1)){//购物中
            $machine = model('Machine')->where('machineid',$goodsorder['machineid'])->find();
            $gboxApi = new \GoboxApi('','');
            $option = [];
            $option['dev_id'] = $machine['boxdevid'];
            $option['transid'] = $goodsorder['orderno'];
            $masterresult = $gboxApi->GetTransEvt($option);
            if($masterresult['code'] == 0 && isset($masterresult['output']) && isset($masterresult['output']['evt_type'])){
                if($masterresult['output']['evt_type'] == 'Order'){
                    $evt_data = $masterresult['output']['evt_data'];//一次事件的具体数据
                    $transid = $masterresult['output']['transid'];
                    $orderModel = GoodsorderModel::where('orderno', '=', $transid)->find();
                    $machine = MachineModel::get($orderModel['machineid']);
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
                            model('Goodsorderdetail')->where('orderid',$orderModel['orderid'])->delete();
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
                                                'value' => '微信支付充值',
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


                            }else if($result == -1){
                                $orderModel['orderstatus'] = GoodsOrderStatusEnum::COMPLETE;
                                $orderModel['payfee'] = $need2pay;
                                $orderModel->save();

                                return result(201,'该订单已支付','');
                            } else {
                                return result(201,'操作失败，支付失败','');
                            }
                        } else {
                            return result(201,'操作失败，need2pay<0','');
                        }
                    }
                }else{
                    //return shopping
                    return result(201,'操作失败，购物中','');
                }
            }else{
                //return error
                return result(201,'操作失败，星星返回失败','');
            }
        }
        //
        return result(200,'','');
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
            $orderModel['orderstatus'] = GoodsOrderStatusEnum::PAID;//已支付
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

            Log::info('储值余额不足');
            if ($user['isnopasspay'] == 1) {//已经开通微信代扣
                Log::info('已经开通微信代扣');
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
                Log::info('$result');
                Log::info($result);
                // 处理创建结果
                if (($result['return_code'] == 'SUCCESS') && ($result['result_code'] == 'SUCCESS')) {
                    //申请扣费成功，等待回调
                    $orderModel['orderstatus'] = GoodsOrderStatusEnum::UNPAID;//待付款
                    $orderModel['payfee'] = $need2pay;
                    $orderModel->save();
                    $user->save([
                        'havearrears' => 0
                    ], ['userid' => $user['userid']]);

                    return 200;
                } else if($result['result_code'] == 'FAIL'&&$result['err_code'] == 'ORDERPAID'){
                    return -1;
                } else {
                    $orderModel['orderstatus'] = GoodsOrderStatusEnum::ARREARAGED;//已欠费
                    $orderModel->save();
                    $user->save([
                        'havearrears' => 1
                    ], ['userid' => $user['userid']]);
                    return 0;
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
