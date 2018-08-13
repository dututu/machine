<?php
namespace app\wechatservice\controller;
use think\Controller;
use think\Config;
use think\Db;
use think\Log;
use think\Loader;
use app\common\model\Goodsorder as GoodsorderModel;
use app\common\model\Goodsorderpay as GoodsorderpayModel;
use app\common\model\Goods as GoodsModel;
use app\common\model\Goodsorderdetail as GoodsOrderDetailModel;
use app\common\model\Outrefund as OutrefundModel;
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
/**
 * 销售记录
 *
 * @author      Caesar
 * @version     1.0
 */
class Sales extends  Base //Base
{
    protected $beforeActionList = [
        'checkSession'
    ];
    /**
     * 首页
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function statis()
    {

        $openid = session('openid', '', 'wechatservice');
        return $this->salesdashboard();

    }
    public function statismachine()
    {
        return $this->fetch('statis_machine',[
//            'categorys'=>$categorys,
        ]);
    }
    public function statisgoods()
    {
        return $this->fetch('statis_goods',[
//            'categorys'=>$categorys,
        ]);
    }
    public function detail(){
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
//        $order['goodsnum'] = count($newgoods);
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
        if($order['orderstatus'] == 7){
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
        return $this->fetch('detail',[
            'order'=>$order,
        ]);
    }
    /**
     * ajax获取销售明细列表
     *
     * 前端使用jqgrid控件渲染表格
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function saleslist(){
        $openid = session('openid', '', 'wechatservice');
//        $merchantid = $this->getMerchantIdByOpenId($openid);
//        $merchantid = '6dd05bfbe5a047aef3f763c58dff2624';
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $merchantid = $sysuser['merchantid'];
        //search params
        //orderby and page param
        $starttime = input("starttime").' 00:00:00';
        $endtime = input("endtime").' 23:59:59';
        $page = input("page");
        $rows = input("rows");
        $offset = (input("page") - 1) * input("rows");
        $value = model('Goodsorder')->getStatisList($rows,$offset,$merchantid,$starttime,$endtime);
        foreach ($value as $suborder) {
            $goods = model('Goodsorderdetail')->getDetailList($suborder['orderid']);
            foreach ($goods as $subgoods) {
                $subgoods['picurl'] = Config::get('paths.coshost').$subgoods['picurl'];
            }
            $suborder['goods'] = $goods;
            $suborder['goodscount'] = count($goods);
        }
        //
        $records = model('Goodsorder')->getStatisListCount($merchantid,$starttime,$endtime);
        $total = ceil($records/$rows);
        $hasnext = true;
        if($page*$rows>=$records){
            $hasnext = false;
        }
        $data['page'] = $page;
        $data['total'] = $total;
        $data['hasnext'] = $hasnext;
        $data['data'] = $value;
        return result(200,"success",$data);
    }
    /**
     * ajax获取机柜分析列表
     *
     * 前端使用jqgrid控件渲染表格
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function machinesaleslist(){
        $openid = session('openid', '', 'wechatservice');
//        $merchantid = $this->getMerchantIdByOpenId($openid);
//        $merchantid = '6dd05bfbe5a047aef3f763c58dff2624';
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $merchantid = $sysuser['merchantid'];
        //search params
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $starttime = input("starttime").' 00:00:00';
        $endtime = input("endtime").' 23:59:59';
        $offset = (input("page") - 1) * input("rows");
        $sql = 'SELECT a.*,m.dailaddress,sum(a.totalfee) t FROM goodsorder a,machine m where a.machineid = m.machineid and a.machineid in(select machineid from machine where merchantid=?) and a.createtime>\''.$starttime.'\' and a.createtime<\''.$endtime.'\' group by machineid limit ?,?';
        $value = Db::query($sql, [$merchantid,$offset, $rows]);
        $recordsql = 'SELECT COUNT(DISTINCT machineid) c FROM goodsorder a where  a.machineid in(select machineid from machine where merchantid=?) and a.createtime>\''.$starttime.'\' and a . createtime < \''.$endtime.'\'';
        $records =  Db::query($recordsql, [$merchantid]);
        $records =(int)reset($records)['c'];
        $total = ceil($records/$rows);
        $hasnext = true;
        if($page*$rows>=$records){
            $hasnext = false;
        }
        $data['page'] = $page;
        $data['total'] = $total;
        $data['hasnext'] = $hasnext;
        $data['data'] = $value;
        return result(200,"success",$data);
    }
    /**
     * ajax获取商品销售分析列表
     *
     * 前端使用jqgrid控件渲染表格
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function goodssaleslist(){
        $openid = session('openid', '', 'wechatservice');
//        $merchantid = $this->getMerchantIdByOpenId($openid);
//        $merchantid = '6dd05bfbe5a047aef3f763c58dff2624';
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $merchantid = $sysuser['merchantid'];
        //search params
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $starttime = input("starttime").' 00:00:00';
        $endtime = input("endtime").' 23:59:59';
        $offset = (input("page") - 1) * input("rows");
        $subsql1 = 'select machineid from machine where merchantid=?';
        $subsql2 = 'select orderid from goodsorder where machineid in('.$subsql1.')';
        $sql = 'SELECT a.*,sum(a.totalfee) t,g.picurl FROM goodsorderdetail a left join goods g on a.goodsid = g.goodsid where  a.orderid in('.$subsql2.') and a.createtime>\''.$starttime.'\' and a.createtime<\''.$endtime.'\' group by goodsid limit ?,?';
        $value = Db::query($sql, [$merchantid,$offset, $rows]);
        $newgoods = [];
        foreach ($value as $goods){
            $goods['picurl'] = Config::get('paths.coshost').$goods['picurl'];
            array_push($newgoods,$goods);
        }
        $recordsql = 'SELECT COUNT(DISTINCT goodsid) c FROM goodsorderdetail a where  a.orderid in('.$subsql2.') and a.createtime>\''.$starttime.'\' and a.createtime<\''.$endtime.'\'';
        $records =  Db::query($recordsql, [$merchantid]);
        $records =(int)reset($records)['c'];
        $total = ceil($records/$rows);
        $hasnext = true;
        if($page*$rows>=$records){
            $hasnext = false;
        }
        $data['page'] = $page;
        $data['total'] = $total;
        $data['hasnext'] = $hasnext;
        $data['data'] = $newgoods;
        return result(200,"success",$data);
    }

    public function mysales(){

        return $this->fetch('statis_sales',[
//            'categorys'=>$categorys,
        ]);
    }
    public function calufee(){
        $openid = session('openid', '', 'wechatservice');
//        $merchantid = $this->getMerchantIdByOpenId($openid);
//        $merchantid = '6dd05bfbe5a047aef3f763c58dff2624';
        $starttime = input("starttime").' 00:00:00';
        $endtime = input("endtime").' 23:59:59';
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $merchantid = $sysuser['merchantid'];
        $subsql1 = 'select machineid from machine where merchantid=?';
        $sql = 'SELECT sum(a.totalfee) t FROM goodsorder a where  a.machineid in('.$subsql1.') and a.createtime>\''.$starttime.'\' and a.createtime<\''.$endtime.'\'';
        $value = Db::query($sql, [$merchantid]);
        $totalfee = (int)reset($value)['t'];
        $recordsql = 'SELECT COUNT(*) c FROM goodsorder a where  a.machineid in('.$subsql1.') and a.createtime>\''.$starttime.'\' and a.createtime<\''.$endtime.'\'';
        $records =  Db::query($recordsql, [$merchantid]);
        $records =(int)reset($records)['c'];
        $data['totalfee'] = $totalfee/100;
        $data['records'] = $records;
        return result(200,"success",$data);
    }
    //销售概况
    public function salesdashboard($type=0,$starttime=0,$endtime=0)
    {
        $openid = session('openid', '', 'wechatservice');
//        $merchantid = $this->getMerchantIdByOpenId($openid);
//        $merchantid = '6dd05bfbe5a047aef3f763c58dff2624';
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $merchantid = $sysuser['merchantid'];
        if($starttime==0&&$endtime==0){
            $starttime = date("Y-m-d").' 00:00:00';
            $endtime = date("Y-m-d").' 23:59:59';
        }
        $liushuifeesql = "SELECT sum(payfee) as flowfee FROM goodsorder where machineid in(SELECT machineid FROM machine where merchantid = '".$merchantid."') and createtime > '".$starttime."' and  createtime < '".$endtime."'";
        $tradenumsql = "SELECT count(*) as flowfee FROM goodsorder where machineid in(SELECT machineid FROM machine where merchantid = '".$merchantid."') and createtime > '".$starttime."' and  createtime < '".$endtime."'";
        $unpayfeesql = "SELECT sum(totalfee) as flowfee FROM goodsorder where machineid in(SELECT machineid FROM machine where merchantid = '".$merchantid."') and totalfee>0 and payfee=0 and createtime > '".$starttime."' and  createtime < '".$endtime."'";
        $unpaycountsql = "SELECT count(*) as flowfee FROM goodsorder where machineid in(SELECT machineid FROM machine where merchantid = '".$merchantid."') and totalfee>0 and payfee=0 and createtime > '".$starttime."' and  createtime < '".$endtime."'";
        $papayfeesql = "SELECT sum(p.payfee) as flowfee FROM goodsorder g left join goodsorderpay p on g.orderid = p.orderid  where g.machineid in(SELECT machineid FROM machine where merchantid = '".$merchantid."') and p.paytype = 1 and g.createtime > '".$starttime."' and  g.createtime < '".$endtime."'";
        $wepaycountsql = "SELECT count(*) as flowfee FROM goodsorder g left join goodsorderpay p on g.orderid = p.orderid  where g.machineid in(SELECT machineid FROM machine where merchantid = '".$merchantid."') and p.paytype = 2 and g.createtime > '".$starttime."' and  g.createtime < '".$endtime."'";
        $machinessql = "SELECT sum(g.totalfee) as flowfee,m.machinename FROM goodsorder g left join machine m on g.machineid = m.machineid where g.machineid in(SELECT machineid FROM machine where merchantid = '".$merchantid."') and g.createtime > '".$starttime."' and  g.createtime < '".$endtime."' group by g.machineid order by flowfee desc";
        $usercountsql = "SELECT count(distinct userid) as flowfee FROM goodsorder where machineid in(SELECT machineid FROM machine where merchantid = '".$merchantid."') and createtime > '".$starttime."' and  createtime < '".$endtime."'";

        $liushuifee =  Db::query($liushuifeesql);
        $tradenum =  Db::query($tradenumsql);
        $unpayfee =  Db::query($unpayfeesql);
        $unpaycount =  Db::query($unpaycountsql);
        $papayfee =  Db::query($papayfeesql);
        $wepaycount =  Db::query($wepaycountsql);
        $usercount =  Db::query($usercountsql);
        $machines =  Db::query($machinessql);
        $newmachines = [];
        foreach ($machines as $machine){
            if(!$machine['machinename']){
                $machine['machinename'] = '未命名';
            }
            array_push($newmachines,$machine);
        }
        //
        $xs = [];
        $ys = [];
        if($type == 0){
            array_push($xs,date("Y-m-d"));
            if(reset($liushuifee)['flowfee']){
                array_push($ys,(int)reset($liushuifee)['flowfee']);
            }else{
                array_push($ys,0);
            }

        }else if($type == -1){
            array_push($xs,date("Y-m-d",strtotime("-1 day")));
            array_push($ys,(int)reset($liushuifee)['flowfee']);
        }else if($type == 7){
            for($i =0;$i<7;$i++){
                $date = date("Y-m-d",strtotime("-".$i." day"));
                $starttime = $date.' 00:00:00';
                $endtime = $date.' 23:59:59';
                $perfeesql = "SELECT sum(payfee) as flowfee FROM goodsorder where machineid in(SELECT machineid FROM machine where merchantid = '".$merchantid."') and createtime > '".$starttime."' and  createtime < '".$endtime."'";
                $perfee =  Db::query($perfeesql);
                array_push($xs,$date);
                array_push($ys,(int)reset($perfee)['flowfee']/100);
            }

        }else if($type == 30){
            for($i =0;$i<30;$i++){
                $date = date("Y-m-d",strtotime("-".$i." day"));
                $starttime = $date.' 00:00:00';
                $endtime = $date.' 23:59:59';
                $perfeesql = "SELECT sum(payfee) as flowfee FROM goodsorder where machineid in(SELECT machineid FROM machine where merchantid = '".$merchantid."') and createtime > '".$starttime."' and  createtime < '".$endtime."'";
                $perfee =  Db::query($perfeesql);
                array_push($xs,$date);
                array_push($ys,(int)reset($perfee)['flowfee']/100);
            }
        }
        //
        return $this->fetch('salesdashboard',[
            'liushuifee'=>reset($liushuifee)['flowfee']?(int)reset($liushuifee)['flowfee']:0,
            'tradenum'=>reset($tradenum)['flowfee']?(int)reset($tradenum)['flowfee']:0,
            'unpayfee'=>reset($unpayfee)['flowfee']?(int)reset($unpayfee)['flowfee']:0,
            'unpaycount'=>reset($unpaycount)['flowfee']?(int)reset($unpaycount)['flowfee']:0,
            'papayfee'=>reset($papayfee)['flowfee']?(int)reset($papayfee)['flowfee']:0,
            'wepaycount'=>reset($wepaycount)['flowfee']?(int)reset($wepaycount)['flowfee']:0,
            'usercount'=>reset($usercount)['flowfee']?(int)reset($usercount)['flowfee']:0,
            'machinelist2'=>json_encode($newmachines),
            'machinelist'=>$newmachines,
            'xs'=>json_encode($xs),
            'ys'=>json_encode($ys),
            'type'=>$type
        ]);
    }
}
