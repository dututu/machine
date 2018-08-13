<?php
namespace app\admin\controller;
use think\Controller;

/**
 * 会员管理
 *
 * @author      Caesar
 * @version     1.0
 */
class User extends  Adminbase //Base
{
    private  $obj;
    public function _initialize() {
        parent::_initialize();
        $this->obj = model("User");
    }
    protected $beforeActionList = [
        'checkAuth' => ['only' => 'index']
    ];
    /**
     * 会员管理页
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function index()
    {
        $havearrearscount = $this->obj->where('havearrears',1)->count();
        $nopaypasscount = $this->obj->where('isnopasspay',1)->count();
        $rechargecount = $this->obj->where('fee','>',0)->count();
        $stopcount = $this->obj->where('status',1)->count();
        return $this->fetch('index',[
            'havearrearscount'=>$havearrearscount,
            'nopaypasscount'=>$nopaypasscount,
            'rechargecount'=>$rechargecount,
            'stopcount'=>$stopcount,
        ]);
    }



    /**
     * ajax获取用户列表
     *
     * 前端使用jqgrid控件渲染表格
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function userlist(){
        //search params
        $s_name = input("s_name");
        $s_select = input("s_select");
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $offset = (input("page") - 1) * input("rows");
        $value = $this->obj->getList($rows,$sidx,$sord,$offset,$s_name,$s_select);
        $records = $this->obj->getListCount($s_name,$s_select);
        $total = ceil($records/$rows);
//        $this->recordlog('4',0,'获取会员列表');
        return pagedata($page,$total,$records, $value);
    }
    /**
     * 会员详情页
     *
     * @access public
     * @return tp5
     */
    public function detail($userid='')
    {
        $usermodel = $this->obj->where('userid',$userid)->find();
        $data = 'orderstatus =5 or orderstatus=8';
        $orders = model('Goodsorder')->where('userid', $userid)->where($data)->select();
        $totalfee = 0;
        foreach ($orders as $order){
            $totalfee = $totalfee+$order['payfee'];
        }
        return $this->fetch('user_detail',[
            'user'=>$usermodel,
            'totalfee'=>$totalfee/100,
            'totalcount'=>count($orders)
        ]);
    }

    /**
     * 交易明细列表
     * @param
     * @return 详情
     */
    public function rechargelogs() {
        //search params
        $userid = input("userid");
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $offset = (input("page") - 1) * input("rows");
        $value = model('Rechargelog')->getLogList($rows,$sidx,$sord,$offset,$userid);
        $records = model('Rechargelog')->getListCount($userid);
        $total = ceil($records/$rows);
        return pagedata($page,$total,$records, $value);
    }
    /**
     * ajax获取交易详情
     *
     * @access public
     * @return tp5
     */
    public function logdetail($page=1){
        $logid = input("logid");
        $userid = input("userid");
        $rechargeorder = [];
        $goodsorder = [];
        $goods = [];
        $user = model('User')->where('userid',$userid)->find();
        if(empty($logid)){
            $log = model('Rechargelog')->where('userid',$userid)->order('createtime','desc')->find();
        }else{
            $log = model('Rechargelog')->where('logid',$logid)->find();
        }
        $logid = $log['logid'];
        //1储值2消费3退款 4取现
        if($log['logtype'] == 1){
            $rechargeorder = model('Rechargeorder')->where('orderid',$log['serialno'])->find();
            $activitymodel = model('Rechargeactivity')->where('activityid',$rechargeorder['activityid'])->find();
            $rechargeorder['activityno'] = $activitymodel['activityno'];
            $rechargeorder['statustext'] = '';
            if($rechargeorder['status'] == 2){
                $rechargeorder['statustext'] = '已支付';
            }else if($rechargeorder['status'] == 3){
                $rechargeorder['statustext'] = '已关闭';
            }else if($rechargeorder['status'] == 4){
                $rechargeorder['statustext'] = '已完成';
            }

        }else if($log['logtype'] == 2){
            $goodsorder = model('Goodsorder')->where('orderid',$log['serialno'])->find();
            $goods = model('Goodsorderdetail')->where('orderid', $log['serialno'])->select();
            $goodsorderpay = model('Goodsorderpay')->where('orderid', $log['serialno'])->select();
            $paytype = '微信支付';
            foreach ($goodsorderpay as $orderpay){
                if($orderpay['paytype'] == 0){
                    $paytype = $paytype.' 储值支付';
                }else if($orderpay['paytype'] == 1){
                    $paytype = $paytype.' 微信免密支付';
                }else if($orderpay['paytype'] == 2){
                    $paytype = $paytype.' 微信支付';
                }else if($orderpay['paytype'] == 3){
                    $paytype = $paytype.' 支付宝支付';
                }else if($orderpay['paytype'] == 4){
                    $paytype = $paytype.' 支付宝免密支付';
                }
            }
            $goodsorder['paytype'] = $paytype;
            $machine = model('Machine')->where('machineid',$goodsorder['machineid'])->find();
            $merchant = model('Merchant')->where('merchantid',$machine['merchantid'])->find();
            $goodsorder['machinename'] = $machine['machinename'];
            $goodsorder['merchantname'] = $merchant['merchantname'];
            $goodsorder['statustext'] = '';
            //1 购物中，2待结账，3已取消，4待支付，5已付款，6已欠费，7转退款，8已完成
            if($goodsorder['orderstatus'] == 5){
                $goodsorder['statustext'] = '已付款';
            }else if($goodsorder['orderstatus'] == 7){
                $goodsorder['statustext'] = '转退款';
            }else if($goodsorder['orderstatus'] == 8){
                $goodsorder['statustext'] = '已完成';
            }
        }else if($log['logtype'] == 3){

        }else if($log['logtype'] == 4){

        }

        return $this->fetch('recharge_log',[
            'userid'=>$userid,
            'logid'=>$logid,
            'user'=>$user,
            'log'=>$log,
            'rechargeorder'=>$rechargeorder,
            'goodsorder'=>$goodsorder,
            'goods'=>$goods,
            'page'=>$page
        ]);
    }
}
