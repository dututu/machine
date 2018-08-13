<?php
namespace app\common\model;

use think\Db;
use think\Model;
/**
 * 商品订单表
 *
 * @author      alvin
 * @version     1.0
 */
class Goodsorder extends Model
{
    protected $autoWriteTimestamp = false;


    //订单状态：1 购物中，2待结账，3已取消，4待支付，5已付款，6已欠费，7转退款，8已完成
    static public $orderstatus = [
        1 => '购物中',
        2 => '待结账',
        3 => '已取消',
        4 => '待支付',
        5 => '已付款',
        6 => '已欠费',
        7 => '转退款',
        8 => '已完成',
    ];
    public function goods()
    {
        return $this->belongsToMany('Goods','goodsorderdetail','goodsid','orderid');
    }
    //c端
    public function createorder($data = []) {
        if(!is_array($data)) {
            exception('传递的数据不是数组');
        }

        $data['isshow'] = 1;
        $data['doorstatus'] = 1;
        $data['orderstatus'] = 1;
        $data['createtime'] = Date('Y-m-d H:i:s');

        return $this->data($data)->allowField(true)
            ->save();
    }

//    public static function getSummaryByPage($page=1, $size=20){
//        $pagingData = self::order('create_time desc')
//            ->paginate($size, true, ['page' => $page]);
//        return $pagingData ;
//    }
    public function getUnpayOrder($userid)
    {
        $data = [];
        $data['a.userid'] = $userid;
        $data['a.orderstatus'] = 6; //array(['=',4],['=',6],'or');
        $join = [
            ['machine m','a.machineid=m.machineid']
        ];
        $result = $this
            ->alias('a')->where($data)
            ->join($join)
            ->field('a.orderid,a.orderno,a.serialno,a.userid,a.machineid,a.createtime,a.orderstatus,a.totalfee,a.payfee,m.location,m.dailaddress,m.machinename')
            ->find();
//        echo $this->getLastSql();
        return $result;
    }

    public function getOrderDetail($orderid)
    {
        $data = [];
        $data['orderid'] = $orderid;
        $join = [
            ['machine m','a.machineid=m.machineid']
        ];
        $result = $this->where($data)
            ->alias('a')
            ->join($join)
            ->field('a.orderid,a.orderno,a.serialno,a.userid,a.machineid,a.createtime,a.orderstatus,a.totalfee,a.payfee,m.machinename,m.location,m.dailaddress')
            ->find();
//        echo $this->getLastSql();
        return $result;
    }
    public function getOrderList($page,$rows,$userid,$status)
    {
        $offset = ($page - 1) * $rows;
        $data = [];
        $order = [
            'a.createtime'=>'desc',
        ];
        $data['a.userid'] = $userid;
        if($status == 0){//recent orders
            $data['a.orderstatus'] = array(['=',3],['=',5],['=',6],['=',7],['=',8],'or');;
        }else if($status == 8){//all orders
            $data['a.orderstatus'] = array(['=',3],['=',5],['=',8],'or');;
        }else{
            $data['a.orderstatus'] = $status;
        }

        $join = [
            ['machine m','a.machineid=m.machineid'],
            ['user u','a.userid=u.userid']
        ];
        $result = $this
            ->alias('a')->where($data)
            ->order($order)
            ->limit($offset,$rows)
            ->join($join)
            ->field('a.orderid,a.orderno,a.serialno,a.userid,a.machineid,a.createtime,a.orderstatus,a.doorstatus,a.totalfee,a.payfee,m.machinename,m.location,m.dailaddress,u.nickname')
            ->select();
        $total = $this->where($data)
            ->alias('a')
            ->join($join)
            ->count();

        return [
            'result' => $result,
            'total' => $total
        ];
    }

    //admin
    public function getList($rows,$sidx,$sord,$offset,$s_name,$s_select,$s_time)
    {
        $data = [];
        $data3 = [];
        if(!empty($s_name)){
//            $data['u.nickname'] = ['like','%'.$s_name.'%'];
            $data3 = 'mt.merchantname like \'%'.$s_name.'%\' or m.containerid=\''.$s_name.'\'';
        }
        if(!empty($s_select)){
            $data['a.orderstatus'] = $s_select;
        }
        $data2 = '';
        if(!empty($s_time)){
            $timerange = explode('~',$s_time);
            $data2 = 'a.createtime >= \''.$timerange[0].' 00:00:00\' and a.createtime<=\''.$timerange[1].' 23:59:59\'';
        }
        $order = [];
        if(!empty($sidx)){
            $order[$sidx] = $sord;
        }else{
            $order['createtime'] = 'desc';
        }
        $join = [
            ['machine m','a.machineid=m.machineid','LEFT'],
            ['user u','a.userid=u.userid','LEFT'],
            ['merchant mt','m.merchantid=mt.merchantid','LEFT']
        ];
        $result = $this
            ->alias('a')
            ->join($join)->where($data)->where($data2)->where($data3)
            ->order($order)
            ->limit($offset,$rows)
            ->field('a.*,m.location,m.containerid,m.merchantid,u.nickname,u.mobile,mt.merchantname')
            ->select();
//        echo $this->getLastSql();
//        foreach ($result as $order){
//            $merchantid = $order['merchantid'];
//            $merchant = Db::table('merchant')->where('merchantid',$merchantid)->find();
//            $order['merchantname'] = $merchant['merchantname'];
//        }
        return $result;
    }
    public function getListCount($s_name,$s_select,$s_time)
    {
        $data = [];
        $data3 = [];
        if(!empty($s_name)){
//            $data['u.nickname'] = ['like','%'.$s_name.'%'];
            $data3 = 'mt.merchantname like \'%'.$s_name.'%\' or m.containerid=\''.$s_name.'\'';
        }
        if(!empty($s_select)){
            $data['a.orderstatus'] = $s_select;
        }
        $data2 = '';
        if(!empty($s_time)){
            $timerange = explode('~',$s_time);
            $data2 = 'a.createtime >= \''.$timerange[0].' 00:00:00\' and a.createtime<=\''.$timerange[1].' 23:59:59\'';
        }
        $join = [
            ['machine m','a.machineid=m.machineid','LEFT'],
            ['user u','a.userid=u.userid','LEFT'],
            ['merchant mt','m.merchantid=mt.merchantid','LEFT']
        ];
        $result = $this
            ->alias('a')
            ->join($join)->where($data)->where($data2)->where($data3)->count();
        return $result;
    }
    public function getOrderListByStatus($rows,$offset,$status)
    {
        //status 100 待退款 101 扣费未成功 102 交易成功 103已退款
        $data = [];
        $order = [
            'a.createtime'=>'desc',
        ];
        if($status == 100){
            $data['a.orderid'] = ['IN',function($query){
                $query->table('outrefund')->where('refundstatus',0)->field('orderid');
            }];
        }else if($status == 103){
            $data['a.orderid'] = ['IN',function($query){
                $query->table('outrefund')->where('refundstatus',3)->field('orderid');
            }];
        }else if($status == 101){
            $data['a.orderstatus'] = 6;
        }else if($status == 102){
            $data['a.orderstatus'] = array(['=',5],['=',8],'or');
        }

        $join = [
            ['machine m','a.machineid=m.machineid'],
            ['user u','a.userid=u.userid']
        ];
        $result = $this
            ->alias('a')
            ->where($data)
            ->order($order)
            ->limit($offset,$rows)
            ->join($join)
            ->field('a.*,m.location,m.containerid,m.merchantid,u.nickname,u.mobile')
            ->select();
        foreach ($result as $order){
            $merchantid = $order['merchantid'];
            $merchant = Db::table('merchant')->where('merchantid',$merchantid)->find();
            $order['merchantname'] = $merchant['merchantname'];
        }

        return $result;
    }
    public function getOrderListCountByStatus($status)
    {
        $data = [];
        if($status == 100){
            $data['a.orderid'] = ['IN',function($query){
                $query->table('outrefund')->where('refundstatus',0)->field('orderid');
            }];
        }else if($status == 103){
            $data['a.orderid'] = ['IN',function($query){
                $query->table('outrefund')->where('refundstatus',3)->field('orderid');
            }];
        }else if($status == 101){
            $data['a.orderstatus'] = 6;
        }else if($status == 102){
            $data['a.orderstatus'] = array(['=',5],['=',8],'or');
        }

        $join = [
            ['machine m','a.machineid=m.machineid'],
            ['user u','a.userid=u.userid']
        ];
        $result = $this
            ->alias('a')
            ->join($join)->where($data)->count();
        return $result;
    }
    public function getOrderListByUserid($rows,$offset,$userid)
    {
        //status 100 待退款 101 扣费未成功 102 交易成功 103已退款
        $data = [];
        $order = [
            'a.createtime'=>'desc',
        ];
        $data['a.userid'] = $userid;

        $join = [
            ['machine m','a.machineid=m.machineid'],
            ['user u','a.userid=u.userid']
        ];
        $result = $this
            ->alias('a')
            ->where($data)
            ->order($order)
            ->limit($offset,$rows)
            ->join($join)
            ->field('a.*,m.location,m.containerid,m.merchantid,u.nickname,u.mobile')
            ->select();
        foreach ($result as $order){
            $merchantid = $order['merchantid'];
            $merchant = Db::table('merchant')->where('merchantid',$merchantid)->find();
            $order['merchantname'] = $merchant['merchantname'];
        }

        return $result;
    }
    public function getOrderListCountByUserid($userid)
    {
        $data = [];
        $data['a.userid'] = $userid;

        $join = [
            ['machine m','a.machineid=m.machineid'],
            ['user u','a.userid=u.userid']
        ];
        $result = $this
            ->alias('a')
            ->join($join)->where($data)->count();
        return $result;
    }
    //TODO TEST
    public function getListTest($rows,$offset)
    {
        $data = [];
        $order = [];
        $order['createtime'] = 'desc';
        $join = [
            ['machine m','a.machineid=m.machineid'],
            ['user u','a.userid=u.userid']
        ];
        $result = $this->where($data)
            ->order($order)
            ->limit($offset,$rows)
            ->alias('a')
            ->join($join)
            ->field('a.*,m.location,m.containerid,m.merchantid,u.nickname,u.mobile')
            ->select();
//        echo $this->getLastSql();
        foreach ($result as $order){
            $merchantid = $order['merchantid'];
            $merchant = Db::table('merchant')->where('merchantid',$merchantid)->find();
            $order['merchantname'] = $merchant['merchantname'];
        }
        return $result;
    }
    //B 端
    //
    public function getStatisList($rows,$offset,$merchantid,$starttime,$endtime)
    {
        $data = [];
        $data['m.merchantid'] = $merchantid;
        $order = [];
        $order['createtime'] = 'desc';
        $data2 = " a.createtime > '".$starttime."' and a.createtime <'".$endtime."'";
        $join = [
            ['machine m','a.machineid=m.machineid','LEFT']
        ];
        $result = $this
            ->alias('a')
            ->join($join)->where($data)->where($data2)
            ->order($order)
            ->limit($offset,$rows)
            ->field('a.*,m.location,m.merchantid')
            ->select();
        return $result;
    }
    public function getStatisListCount($merchantid,$starttime,$endtime)
    {
        $data = [];
        $data['m.merchantid'] = $merchantid;
        $data2 = " a.createtime > '".$starttime."' and a.createtime <'".$endtime."'";
        $join = [
            ['machine m','a.machineid=m.machineid','LEFT']
        ];
        $result = $this
            ->alias('a')
            ->join($join)->where($data)->where($data2)->count();
        return $result;
    }
    public function getmachineid($orderid) {
        $result = $this->where('orderid',$orderid)->find();
        if($result)
            return $result['machineid'];
        else 
            return 0;
    }
}