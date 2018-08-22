<?php
namespace app\workweixin\controller;
use think\Controller;
use think\Db;
use think\Config;
use think\Log;
use think\Loader;
use think\Request;

class User extends  Base
{

    /**
     * 首页
     * @access public
     * @return tp5
     */
    public function index()
    {

        return $this->fetch();

    }
    /**
     * 获取用户统计信息
     *
     * @param $start
     * @param $end
     *User statistics
     * @return array
     */
    public function getuserstat( $start,$end )
    {
        if( !$start || !$end )
            return returnErr( 100,'时间参数错误' );
        //用户信息
        $userinfo = Db::table( 'statdailyuser' )
        ->whereBetween( 'statdate',[ "$start","$end" ] )
        ->field( "ifnull(max(totalnumusers),0) as totalnumusers,ifnull(sum(newusers),0) as newusers,
            ifnull(sum(newstoredvalueusers),0) as newstoredvalueusers,ifnull(sum(newstoredvalue),0) as newstoredvalue,
            (select sum(so.amountincome)-sum(so.amountrefund) from statdailyorder so
WHERE so.statdate between '".$start."' and  '".$end."'  ) as amountincome,
(select sum(so2.numpurchased) from statdailyorder so2
WHERE so2.statdate between '".$start."' and  '".$end."'  ) as numpurchased
" )
                ->find();
        $data = [ ];
        //用户总量
        $data['totalnumusers'] = $userinfo["totalnumusers"];
        //新增用户个数
        $data['newusers'] = $userinfo["newusers"];
        //新增用户与总用户数占比
        $data['newusersratio'] =  ($data['totalnumusers']&&$data['totalnumusers']>0)?bcmul(bcdiv($data['newusers'],$data['totalnumusers'],4),100,2):0;
        //新开通储值用户个数
        $data['newstoredvalueusers'] = $userinfo["newstoredvalueusers"];
        //新开通储值用户个数与总用户数占比
        $data['newstoredvalueusersratio'] =  ($data['totalnumusers']&&$data['totalnumusers']>0)?bcmul(bcdiv($data['newstoredvalueusers'],$data['totalnumusers']),100,2):0;
        //充值总金额
        $data['newstoredvalue'] = bcdiv($userinfo["newstoredvalue"],100,2);
        //流水金额
        $data['amountincome'] = bcdiv($userinfo["amountincome"],100,2);
        //用户数
        $data['numpurchased'] = $userinfo["numpurchased"];
        //人均消费
      $data['perconsumption'] = ($data['numpurchased']&&$data['numpurchased']>0)?bcdiv($data['amountincome'],$data['numpurchased'],2):0;

        // dump($data);
    
        return ajaxReturn( 0,'',$data );
    }
    
    /**
     * 用户购买频次
     * @return array|\think\response\Json
     * User purchase frequency
     */
    public function userpurchasefrequency()
    {
        // if( Request()->isGet() ) {
        $start = input( 'start' );
        $end = input( 'end' );

        if( !$start || !$end )
            return returnErr( 100,'时间参数错误' );
        $days = (int) getDateDays( $start,$end );

        $orderbystr = "";
       
        $result = Db::query('
select retab.times as countnum,sum(retab.usernum1) as usernum  from
(
select 
(case 
when newcount.1count<5 then concat(newcount.1count,"次")
when newcount.1count>=5 then "5次及以上"
end) as times
,sum(newcount.2count)  usernum1 from
(
select count(*) as 1count, count(*) as 2count from 
goodsorder inner join goodsorderpay 
on goodsorder.orderid=goodsorderpay.orderid
          inner join machine 
            on machine.machineid = goodsorder.machineid
where DATE_FORMAT(createtime, '."'%Y-%m-%d')  between '".$start."' and  '".$end."' ". 
' and orderstatus in (4,5,6,7,8) and payfee>0
 and goodsorderpay.paystatus=1 and machine.status in (1,2,3,4,10)
GROUP BY userid
) as newcount
GROUP BY newcount.1count
) as retab
GROUP BY  retab.times

            ');
      // dump( Db::getLastSql());
    
        $arr = [ ]; //走势图
        $arr[0]['t'] = '用户购买频次';
        $arr[0]['n'] = [ ];

            foreach( $result as $k => $v ) {
                $arr[0]['n'][$k] = array($v['countnum'],$v['usernum']);
            }
 
       // dump($arr);
        $arr[0]['n'] = array_values( $arr[0]['n'] );
        $arr_x = getDateList( $start,$end );
        $data = [ ];
        $data['arr_x'] = $arr_x;
        $data['arr'] = $arr;
        return ajaxReturn( 0,'',$data );
        // } else {
        ///      return ajaxReturn( 101,'提交方式错误' );
        //  }
    }
    
}
