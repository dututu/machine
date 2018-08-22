<?php
namespace app\workweixin\controller;

use think\Controller;
use think\Db;
use think\Log;
use think\Model;

class Dailyreport extends Controller
{
    /**
     * 每日商户统计
     */
    public function dailyMerchantold( $date = '' )
    {
        $date = $date ? : date( "Y-m-d",strtotime( '-1 day' ) );
        $now = date( "Y-m-d H:i:s" );
        //机柜总数
        $res_1 = model( 'Merchant' )->alias( 'me' )
            ->join( 'machine ma','me.merchantid = ma.merchantid','left' )
            ->where( 'ma.status in( 1,2,3,4,10) and ma.businessstatus=4 ')
            ->group( 'me.merchantid' )
            ->column( 'me.merchantid,me.merchantname,count(ma.merchantid) totalnummachine' );

        //商品数量 总销售额
        $res_2 = model( 'Merchant' )->alias( 'me' )
        ->join( 'machine ma','me.merchantid = ma.merchantid','left' )
        ->join( 'goodsorderpay p',"p.orderid = g.orderid and p.paystatus=1 and DATE_FORMAT(g.createtime, '%Y-%m-%d') = '$date'",'left' )
        ->join( 'goodsorder g',"g.machineid = ma.machineid and g.orderstatus in (4,5,6,7,8) and DATE_FORMAT(g.createtime, '%Y-%m-%d') = '$date'",'right' )

        ->join( 'goodsorderdetail gd','gd.orderid = g.orderid','left' )
        ->where( 'me.status',2 )
        ->group( 'me.merchantid' )
        ->column( 'me.merchantid,ifnull(me.agencyid,0) agentid,count(gd.goodsid) quantitygoods' );
        
        
        $res_4 = model( 'Merchant' )->alias( 'me' )
            ->join( 'machine ma','me.merchantid = ma.merchantid','left' )
            ->join( 'goodsorder g',"g.machineid = ma.machineid and g.orderstatus in (4,5,6,7,8) and DATE_FORMAT(g.createtime, '%Y-%m-%d') = '$date'",'left' )
            ->where( 'me.status',2 )
            ->group( 'me.merchantid' )
            ->column( 'me.merchantid,me.merchantname,sum(ifnull(g.payfee,0)) totalsales' );

        //新增机柜数

        $res_3 = model( 'Merchant' )->alias( 'me' )
            ->join( 'machine ma',"me.merchantid = ma.merchantid and ma.status != 7 and DATE_FORMAT(ma.createtime, '%Y-%m-%d') = '$date'",'left' )
            ->where( 'me.status',2 )
            ->group( 'me.merchantid' )
            ->column( 'me.merchantid,me.mobile,count(ma.machineid) numnewmachine' );
        $res = array_replace_recursive( $res_1,$res_2,$res_3,$res_4);
        foreach( $res as $k => $v ) {
            $res[$k]['dailymerchantid'] = uuid();
            $res[$k]['merchantid'] = $k;
            $res[$k]['statdate'] = $date;
            $res[$k]['exetime'] = $now;
            unset( $res[$k]['merchantname'] );
            unset( $res[$k]['mobile'] );
        }
        $res = array_values( $res );
        Db::table( 'statdailymerchant' )->insertAll( $res );
    }

    /**
     * 订单每日统计
     */
    public function dailyOrder( $date = '' )
    {
        $date = $date ? : date( "Y-m-d",strtotime( '-1 day' ) );
        $now = date( "Y-m-d H:i:s" );
        $res_1 = model( 'Machine' )->alias( 'ma' )
            ->join( "
                (select goo.*
from goodsorderpay pay
INNER JOIN goodsorder goo
on pay.orderid=goo.orderid
where pay.paystatus=1
                and
                DATE_FORMAT(goo.createtime, '%Y-%m-%d') = '$date'
                )

                g","g.machineid = ma.machineid and g.orderstatus in (4,5,6,7,8) and DATE_FORMAT(g.createtime, '%Y-%m-%d') = '$date'",'left' )
            ->join( 'goodsorderdetail gd',"g.orderid = gd.orderid",'left' )
            ->whereIn( 'ma.status',[ 1,2,3,4,10 ] )
            ->group( 'ma.machineid' )
            ->column( 'ma.machineid,ifnull(ma.merchantid,0) merchantid,ifnull(count(DISTINCT g.orderid),0) totalpens,ifnull(sum(gd.amount),0) totalnumgoods,count(DISTINCT g.userid) numpurchased' );
        
        //支付表
        

        $res_2 = model( 'Machine' )->alias( 'ma' )
            ->join( 'goodsorder g',"g.machineid = ma.machineid and g.orderstatus in (4,5,6,7,8) and DATE_FORMAT(g.createtime, '%Y-%m-%d') = '$date' and g.payfee>0",'left' )
            ->join( 'merchant me',"ma.merchantid = me.merchantid",'left' )
            ->whereIn( 'ma.status',[ 1,2,3,4,10 ] )
            ->group( 'ma.machineid' )
            ->column( 'ma.machineid, count(g.machineid) totaltorderspens,ifnull(SUM(g.payfee),0) totalsales,ifnull(me.agencyid,0) agentid' );

        //退款总金额与退款总笔数
        $refund_sql= "
            select 
            ma.machineid,ifnull(sum(refundorder.realfee),0) amountrefund, ifnull(count(refundorder.realfee),0) pensrefund
             from 
             machine ma
            LEFT JOIN (
            select goodsorder.orderid as orderid,machineid,refund.realfee as realfee,
            refund.fefundtime as fefundtime,refund.refundstatus as refundstatus
            from outrefund refund  LEFT JOIN goodsorder
            on refund.orderid = goodsorder.orderid
            where 
            DATE_FORMAT(refund.fefundtime, '%Y-%m-%d') = '$date'
            and refund.refundstatus =3 
            ) as refundorder
            on refundorder.machineid = ma.machineid
            where ma.status in(1,2,3,4,10)
            GROUP BY ma.machineid
            ";
        $res_refund = model( 'Merchant' )->query($refund_sql);
        $res_refund_new =array();
        foreach( $res_refund as $k => $v ) {
           
            $res_refund_new[$v["machineid"]]=$v;

        }
        
        //总收入 笔数
        $income_sql="
            select 
            ma.machineid,ifnull(sum(payorder.payfee),0) amountincome, ifnull(count(payorder.payfee),0) pensincome
             from 
             machine ma
            LEFT JOIN (
            select goodsorder.orderid as orderid,machineid,pay.payfee as payfee,
            pay.paytime as paytime,pay.paystatus as paystatus
            from goodsorderpay pay  LEFT JOIN goodsorder
            on pay.orderid = goodsorder.orderid
            where 
            DATE_FORMAT(pay.paytime, '%Y-%m-%d') = '$date'
            and pay.paystatus =1
            
            ) as payorder
            on payorder.machineid = ma.machineid
            where ma.status in(1,2,3,4,10)
            GROUP BY ma.machineid

            ";
        
        $res_income = model( 'Merchant' )->query($income_sql);
        $res_income_new =array();
        foreach( $res_income as $k => $v ) {
           
            $res_income_new[$v["machineid"]]=$v;

        }
        $res = array_replace_recursive( $res_1,$res_2,$res_refund_new,$res_income_new );
        unset($res_income,$res_refund);
        
        foreach( $res as $k => $v ) {
            $res[$k]['dailyorderid'] = uuid();
            $res[$k]['machineid'] = $k;
            $res[$k]['statdate'] = $date;
            $res[$k]['exetime'] = $now;
        }
//        $res = array_values( $res );

        //补缴 微信支付 欠费没有支付详情 订单金额大于0  支付状态为1 成功
        $res_3 = model( 'Machine' )->alias( 'ma' )
            ->join( 'goodsorder g',"g.machineid = ma.machineid and g.orderstatus in (4,5,6,7,8) and DATE_FORMAT(g.createtime, '%Y-%m-%d') = '$date' and g.payfee > 0",'left' )
            ->join( 'goodsorderpay gp','gp.orderid=g.orderid and gp.paystatus != 4 and gp.paystatus = 1 ','left' )
            ->field( "ma.machineid,ma.machinename,g.createtime,g.orderid,g.payfee,gp.orderpayid,gp.serialno,gp.paystatus,gp.paytype,gp.payfee gpayfee" )
            ->whereIn( 'ma.status',[ 1,2,3,4,10 ] )
            ->order( 'g.createtime desc' )
            ->select();
//        var_dump($res_3);die;
        $arr = [ ];
        foreach( $res_3 as $k => $v ) {
            $val = $v->toArray();
            $arr[$val['machineid']]['wxpayment'] += 0;
            $arr[$val['machineid']]['numarrears'] += 0;
            $arr[$val['machineid']]['amountstoredvalue'] += 0;
            $arr[$val['machineid']]['numstoredvalues'] += 0;
            $arr[$val['machineid']]['wxdeductedamount'] += 0;

            //微信补缴
            if( $val['paytype'] == 2 )
                $arr[$val['machineid']]['wxpayment'] += $val['gpayfee'];
            //欠费数量
            if( $val['orderid'] && !$val['orderpayid'] )
                $arr[$val['machineid']]['numarrears']++;

            //储值金额
            if( $val['paytype'] == 0 && $val['paytype'] != null )
                $arr[$val['machineid']]['amountstoredvalue'] += $val['gpayfee'];

            //储值数量
            if( $val['paytype'] == 0 && $val['paytype'] != null )
                $arr[$val['machineid']]['numstoredvalues']++;

            //微信代扣
            if( $val['paytype'] == 1 )
                $arr[$val['machineid']]['wxdeductedamount'] += $val['gpayfee'];

        }
        $result = array_replace_recursive( $res,$arr );
        $result = array_values( $result );


        Db::table( 'statdailyorder' )->insertAll( $result );


    }

    //每日机柜统计
    public function dailyMachine( $date = '' )
    {
        $date = $date ? : date( "Y-m-d",strtotime( '-1 day' ) );
        $now = date( "Y-m-d H:i:s" );
        //runing运营中的
        $res_running = model( 'Machine' )
        ->column( '*' );
        $data['dailymachineid'] = uuid();
        $data['totalnumber'] = 0;
        $data['totalnumproduced'] = 0;
        $data['totalnuminproduction'] = 0;
        $data['totalnumonline'] = 0;
        foreach( $res_running as $k => $v ) {

            if( in_array( $v["status"],[ 1,2,3,4,10 ] ) )
                $data['totalnumber'] += 1;
            if( $v["status"]== 8 )//待生产
                $data['totalnumproduced'] += 1;
            if( $v["status"] == 9 )//生产中
                $data['totalnuminproduction'] += 1;
            if( in_array( $v["status"],[ 1,2,3,4,10 ] ) && $v["businessstatus"]==4)//生产中
                $data['totalnumonline'] += 1;
            
        }
        $data['statdate'] = $date;
        $data['exetime'] = $now;
        $res = Db::table( 'statdailymachine' )->insert( $data );
    }

    public function getAllDailyMerchant()
    {
//        $start = model( 'Merchant' )->min( 'createtime' );
        $start = '2018-03-30';
        $end = date( 'Y-m-d',strtotime( '-1 day' ) );
        $data_list = getDateList( $start,$end );
        foreach( $data_list as $k => $v ) {
            $this->dailyMerchant( $v );
        }
    }

    public function getAllDailyOrder()
    {
        $start = '2018-03-09';
        $end = date( 'Y-m-d',strtotime( '-1 day' ) );
//        $end = '2018-03-09';
        $data_list = getDateList( $start,$end );

        foreach( $data_list as $k => $v ) {
            $this->dailyMachine( $v );
            $this->dailyOrder( $v );
            $this->dailyUser( $v );
            $this->dailygoods( $v );
            Log::write('111');
        }
    }

    /**
     * 用户每日统计
     */
    public function dailyUser( $date = '' )
    {
        $date = $date ? : date( "Y-m-d",strtotime( '-1 day' ) );
        $now = date( "Y-m-d H:i:s" );
        $result = Db::query("
select 
-- 储值总额(元)
( 
select ifnull(sum(fee),0) from `user`
where status=0
) as totalstoredvalue,
-- 新开通储值用户数
(
select ifnull(count(DISTINCT(r.userid)),0) from user u INNER JOIN rechargeorder r
on u.userid= r.userid
where DATE_FORMAT(u.createTime, '%Y-%m-%d') ='".$date."'
and r.`status` in (2,4)
) as newstoredvalueusers,
-- 新储值金额(元)
(
select ifnull(sum(realfee),0) from rechargeorder 
where 
 DATE_FORMAT(createtime, '%Y-%m-%d')='".$date."'
and `status` in (2,4)
) as newstoredvalue,
-- 开通免密用户数
(
select ifnull(count(*),0) from `user`
where status=0 and contractid is not null
) as opennumsecretusers,
-- 用户总数
(
select ifnull(count(*),0) from `user`
where status=0
) as totalnumusers,
-- newusers新增用户
(
select ifnull(count(*),0) from `user`
where status=0 
and  DATE_FORMAT(createTime, '%Y-%m-%d')='".$date."'
)as newusers,
-- 欠费用户数
(
select ifnull(count(*),0) from `user`
where status=0 and havearrears=1

) as numusersarrears
            
            ");
        foreach( $result as $k => $v ) {
            $result[$k]['dailyuserid'] = uuid();
            $result[$k]['statdate'] = $date;
            $result[$k]['exetime'] = $now;
        }

       Db::table( 'statdailyuser' )->insertAll( $result );

    }
    
    
    /**
     * 商品每日统计
     */
    public function dailygoods( $date = '' )
    {
        $date = $date ? : date( "Y-m-d",strtotime( '-1 day' ) );
        $now = date( "Y-m-d H:i:s" );
        $result = Db::query("
            


select d.goodsid as goodsid,ca.categoryname as goodsclass,sum(d.amount) as purchasequantity ,
ifnull(
(
select sum(newd.amount)
from goodsorderdetail newd
where newd.detailid=d.detailid
and isrefund=1
)
,0) as returnquantity
, sum(d.totalfee) as totalamount 

 from goodsorderpay p INNER JOIN  goodsorderdetail d
on p.orderid=d.orderid 
INNER JOIN goods g
on d.goodsid=g.goodsid
INNER JOIN goodscategory ca
on g.goodscategoryid=ca.categoryid
where 
DATE_FORMAT(p.paytime, '%Y-%m-%d') ='".$date."'
and p.paystatus=1 and isrefund=0
GROUP BY DATE_FORMAT(p.paytime, '%Y-%m-%d') ,goodsclass ;   

    
            ");
        foreach( $result as $k => $v ) {
            $result[$k]['dailygoodsid'] = uuid();
            $result[$k]['statdate'] = $date;
            $result[$k]['exetime'] = $now;
        }
    
        Db::table( 'statdailygoods' )->insertAll( $result );
    
    }
    
    /**
     * 商户每日统计
     */
    public function dailymerchant( $date = '' )
    {
        $date = $date ? : date( "Y-m-d",strtotime( '-1 day' ) );
        $now = date( "Y-m-d H:i:s" );
        $result = Db::query("

            
select 
ifnull(me.agencyid,'') as agentid,
me.merchantid as merchantid,
-- 机柜总数量
(
select count(*) from machine ma
where ma.merchantid=me.merchantid 
and ma.businessstatus=4 and ma.status in( 1,2,3,4,10) 
and DATE_FORMAT(ma.createtime, '%Y-%m-%d')<='".$date."'
)
as totalnummachine,
-- 商品数量 
ifnull(

(
select sum(d.amount) 
from goodsorderpay p INNER JOIN goodsorder o 
on p.orderid=o.orderid
INNER JOIN goodsorderdetail d
on d.orderid=p.orderid
where p.paystatus=1
and   DATE_FORMAT(p.paytime, '%Y-%m-%d')<='".$date."'
and o.orderstatus in (4,5,6,7,8)
and o.machineid in(

select ma.machineid from machine ma
where ma.merchantid=me.merchantid 
and ma.businessstatus=4
and  DATE_FORMAT(ma.createtime, '%Y-%m-%d')<='".$date."'
)
)

,0)
as quantitygoods,
-- 新增机柜数
(
select count(*) from machine ma
where ma.merchantid=me.merchantid 
and ma.businessstatus=4
and  DATE_FORMAT(ma.createtime, '%Y-%m-%d')='".$date."'
)
as numnewmachine,
--  当日总销售额(元)扩大100倍
ifnull(

(
select sum(p.payfee) 
from goodsorderpay p INNER JOIN goodsorder o
on p.orderid=o.orderid
where p.paystatus=1
and   DATE_FORMAT(p.paytime, '%Y-%m-%d')<='".$date."'
and o.orderstatus in (4,5,6,7,8)
and o.machineid in(

select ma.machineid from machine ma
where ma.merchantid=me.merchantid 
and ma.businessstatus=4
and  DATE_FORMAT(ma.createtime, '%Y-%m-%d')<='".$date."'
)
)

,0)
as totalsales,

-- 购买用户数
ifnull((
select count(DISTINCT userid) 
from goodsorderpay p INNER JOIN goodsorder o
on p.orderid=o.orderid
where p.paystatus=1
and   DATE_FORMAT(p.paytime, '%Y-%m-%d')<='".$date."'
and o.orderstatus in (4,5,6,7,8)
and o.machineid in(

select ma.machineid from machine ma
where ma.merchantid=me.merchantid 
and ma.businessstatus=4
and  DATE_FORMAT(ma.createtime, '%Y-%m-%d')<='".$date."'
)
)
,0)
as purchasedusersnum


 from merchant me
where  DATE_FORMAT(me.approvertime, '%Y-%m-%d')<='".$date."'
and  me.status=2 
            
   
    
            ");
        foreach( $result as $k => $v ) {
            $result[$k]['dailymerchantid'] = uuid();
            $result[$k]['statdate'] = $date;
            $result[$k]['exetime'] = $now;
        }
    
        Db::table( 'statdailymerchant' )->insertAll( $result );
    
    }
    
}
