<?php
namespace app\workweixin\controller;

use app\common\model\Goodsorder;
use app\common\model\Goodsorderdetail;
use app\common\model\Outrefund;
use think\Controller;
use think\Db;
use think\Config;
use think\Log;
use think\Loader;
use think\Paginator;
use think\Request;

class Order extends Base
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
     * 销售列表
     */
    public function saleList()
    {
        $machineid = input( 'machineid','' );
        $start = input( 'start',$this->start );
        $end = input( 'end',$this->end );
        $this->assign( 'start',$start );
        $this->assign( 'end',$end );
        $this->assign( 'machineid',$machineid );
        return $this->fetch();
    }


    /**
     * 获取时间内机柜交易信息
     *
     * @param $start
     * @param $end
     *
     * @return array
     */
    public function getDayOrder( $start,$end )
    {
        if( !$start || !$end )
            return returnErr( 100,'时间参数错误' );
        //机柜总数
        $totalmachine = Db::table( 'statdailymachine' )->whereBetween( 'statdate',[ "$start","$end" ] )->max( 'totalnumonline' );
        $result = Db::table( 'statdailyorder' )
            ->where( 'statdate','between',[ "$start","$end" ] )
            ->select();
        
        //
        $sum_result = Db::table( 'statdailyorder' )
        ->where( 'statdate','between',[ "$start","$end" ] )
        ->field("sum(pensrefund) as pensrefund,sum(pensincome) as pensincome")
        ->find();
        
        //按商户取得取得所有的累计值
        $accumresult = Db::table( 'statdailyorder' )
            ->group( "merchantid" )
            ->column( 'machineid,ifnull(SUM(amountincome)-SUM(amountrefund),0) amounaccum' );
        $totalsales = 0;
        $numpurchased = 0;
        $order_order = [ ]; //有效交易
        $order_null = [ ];//无效交易
        $amounaccum = 0;
        $realincome = 0;
        $pensrefund = 0;
        $pensincome = 0;
        $pensrefund = $sum_result['pensrefund'];
        $pensincome = $sum_result['pensincome'];
        foreach( $result as $k => $v ) {
            //总交易额
            $totalsales += $v['totalsales'] / 100;
            //商户累计
            $amounaccum == $accumresult[$v['machineid']];
            $amounaccum += $v['totalsales'] / 100;
            //实际收入
            $realincome += number_format( ( $v['amountincome'] - $v['amountrefund'] ) / 100,2 );
            //总购买人数
            $numpurchased += $v['numpurchased'];
            //有交易机柜订单数
            if( $order_order[$v['machineid']] == 1 )
                continue;
            else
                $order_order[$v['machineid']] = 0;

            if( $v['pensrefund']>0||$v['pensincome']>0 )
                $order_order[$v['machineid']] = 1;


        }
        //实际收入

        //有效机柜总数量数量
        $order_order = array_count_values( $order_order );
        $order_ok = $order_order[1] ? $order_order[1] : 0;
        //无效机柜总数量
        $order_invalid = $totalmachine - $order_ok;
        $days = getDateDays( $start,$end );
        //机柜日均交易额
        $machine_pvg = 0;
        $user_pvg = 0;
        if( $order_ok ) {
            $machine_pvg = number_format( $realincome / $days / $order_ok,2 );
            //机柜日均用户
            $user_pvg = (string) round( $numpurchased / $order_ok/ $days,2 );
        }
        $data = [ ];
        $data['totalmachine'] = $totalmachine;
        $data['totalnumonline'] = $totalmachine;
        $data['totalsales'] = $totalsales;
        $data['numpurchased'] = $numpurchased;
        $data['order_order'] = $order_order;
        $data['order_null'] = $order_null;
        $data['realincome'] = number_format( $realincome,2 );
        $data['pensrefund'] = $pensrefund;
        $data['pensincome'] = $pensincome;
        $data['order_ok'] = $order_ok;
        $data['order_invalid'] = $order_invalid;
        $data['machine_pvg'] = $machine_pvg;
        $data['user_pvg'] = $user_pvg;
        //百分比
        if( $totalmachine > 0 ) {
            $data['total_ok'] = round( $order_ok / $totalmachine * 100,2 );
            $data['total_invalid'] = round( $order_invalid / $totalmachine * 100,2 );
        } else {
            $data['total_ok'] = 0;
            $data['total_invalid'] = 0;
        }

        return returnErr( 0,'',$data );
    }


    /**
     * //订单列表
     * @return array|\think\response\Json
     */
    public function getOrderList()
    {
        if( Request()->isPost() ) {

            $machineid = input( 'machineid','' );
            $start = input( 'start','' );
            $end = input( 'end','' );
            if( !$start || !$end )
                return returnErr( 100,'时间参数错误' );
            if( !$machineid )
                return returnErr( 100,'获取机柜id失败' );

            $this->getPage();
            $end = date( "Y-m-d",strtotime( "+1 day",strtotime( $end ) ) );


            $orderList = Goodsorder::where( 'machineid',$machineid )
                ->alias( 'g' )
                ->join( 'outrefund of','of.orderid = g.orderid','left' )
                ->whereBetween( "g.createtime",[ "$start","$end" ] )
                ->whereIn( 'g.orderstatus',[ 4,5,6,7,8 ] )
                ->where( 'g.payfee','>',0 )
                ->field( 'g.orderid,g.machineid,g.orderno,g.totalfee,payfee,g.orderstatus,g.createtime,of.refundstatus' )
                ->order( 'g.createtime desc' )
                ->paginate( 10,false,$this->config );

            $data = $this->getPaginator( $orderList );
            //查找商品
            $orderIdList = array_column( $data['data'],'orderid' );
            $goods = Goodsorderdetail::whereIn( 'orderid',$orderIdList )
                ->alias( 'gd' )
                ->join( 'goods g','g.goodsid = gd.goodsid','left' )
                ->field( 'gd.*,g.picurl' )
                ->select();

            //订单状态
            $orderstatus = Goodsorder::$orderstatus;
            //退款单状态
            $refundstatus = Outrefund::$refundstatus;
            foreach( $data['data'] as $k => $v ) {
                $data['data'][$k]['goods'] = [ ];
                $data['data'][$k]['totalgoods'] = 0;
                $data['data'][$k]['payfee'] = (string) round( $v['payfee'] / 100,2 );
                $data['data'][$k]['text'] = $orderstatus[$v['orderstatus']];
                if( $v['orderstatus'] == 7 ) {
                    $data['data'][$k]['text'] = $refundstatus[$v['refundstatus']] ? : '';
                }
                $data['data'][$v['orderid']] = $data['data'][$k];
                unset( $data['data'][$k] );
            }

            foreach( $goods as $k => $v ) {
                $g = $v->toArray();
                $g['picurl'] = Config::get( 'coshost' ) . $g['picurl'];
                $data['data'][$v['orderid']]['goods'][] = $g;
                $data['data'][$v['orderid']]['totalgoods'] += $g['amount'];
            }
            return ajaxReturn( 0,'',$data );
        } else {
            return ajaxReturn( 101,'提交方式错误' );
        }
    }

    /**
     * @return \think\response\Json
     */
    public function transactionRanking()
    {
        if( Request()->isPost() ) {
            $this->getPage();
            $start = input( 'start' );
            $end = input( 'end' );
            $sort = input( 'sort',1 );
            if( !$start || !$end )
                return returnErr( 100,'时间参数错误' );

            $desc = $sort ? 'desc' : 'asc';

            //0销售金额 1交易商品数量 2客单价
            $type = input( 'type',0 );

            $accumresultsql = 'select machineid,ifnull(SUM(amountincome)-SUM(amountrefund),0) amounaccum
                from statdailyorder 
                group by machineid;
                ';
            $accumresult = Db::table( 'statdailyorder' )->query( $accumresultsql );
           

            $total_r = Db::table( 'statdailyorder' )
                ->where( 'statdate','<=',"$end" )
                ->group( 'machineid' )
                ->column( 'machineid,sum(totalpens) stotalpens,sum(totalsales) stotalsales,sum(totaltorderspens) stotaltorderspens' );

            $model = Db::table( 'statdailyorder' )
                ->alias( 'so' )
                ->join( 'merchant m','so.merchantid = m.merchantid','left' )
                ->join( 'machine ma','ma.machineid = so.machineid','right' )
                ->join( 'agency a','a.agencyid = so.agentid','left' )
                ->where( 'so.statdate','between',[ "$start","$end" ] );

            if( $type == 0 ){
                $model->group( 'so.machineid' )
                ->order( 'realincome',$desc );
            }
            elseif( $type == 1 ){
                $model->group( 'so.machineid' )
                ->order( 'totalnumgoods',$desc );
            }else{
                $model->group( 'so.machineid' )
                ->order( 'sum(so.totalsales)/sum(so.numpurchased)',$desc );
            }
            $result = $model->field( 'so.machineid,ma.containerid,ifnull(m.merchantname,"待分配") merchantname,ifnull(a.username,"") username,
                ma.rfidtypecode,sum(so.totalpens) totalpens,sum(so.totalsales) totalsales,sum(so.totalnumgoods) totalnumgoods,sum(so.totaltorderspens) totaltorderspens,
                sum(so.amountrefund) as amountrefund,sum(so.pensrefund) as pensrefund ,sum(so.amountincome) as amountincome ,sum(so.pensincome) as pensincome, (sum(so.amountincome)-sum(so.amountrefund)) as realincome 
                ,(case when (m.merchantname is null &&(ifnull(sum(so.amountincome),0)+ifnull(sum(so.amountrefund),0))=0) then 0 else 1 end) as hastran ' )
               ->where('ma.status', 'in',[ 1,2,3,4,10])
               ->where('ma.businessstatus',4)
               //->having('hastran>0')
                ->paginate( 10,false,$this->config );
            
            
            /*
            
            $sqlWherestr=' where 1=1 and between '.$start.' and '.$end.' ';
            $sqlWherestr += " `ma`.`status` IN (1,2,3,4,10) AND `ma`.`businessstatus` = 4 ";
            $sqlGroupstr='';
            $sqlOrderstr = '';
            $sqlHavingstr=' having hastran>0 ';
            if( $type == 0 ){
                $sqlGroupstr = " group by so.machineid ";
                $sqlOrderstr = " order by realincome $desc";
            }
            elseif( $type == 1 ){
                $sqlGroupstr = " group by so.machineid ";
                $sqlOrderstr = " order by totalnumgoods $desc";
            }else{
                $sqlGroupstr = " group by so.machineid ";
                $sqlOrderstr = " order by sum(so.totalsales)/sum(so.numpurchased) $desc";
            }
            $sqlstr="
SELECT `so`.`machineid`,`ma`.`containerid`,ifnull(m.merchantname,'待分配') merchantname,
ifnull(a.username,'') username,`ma`.`rfidtypecode`,sum(so.totalpens) totalpens,
sum(so.totalsales) totalsales,sum(so.totalnumgoods) totalnumgoods,sum(so.totaltorderspens) totaltorderspens,
sum(so.amountrefund) as amountrefund,sum(so.pensrefund) as pensrefund,
sum(so.amountincome) as amountincome,sum(so.pensincome) as pensincome,
(sum(so.amountincome)-sum(so.amountrefund)) as realincome,
(case when (m.merchantname is null &&(ifnull(sum(so.amountincome),0)+ifnull(sum(so.amountrefund),0))=0) then 0 else 1 end) as hastran
 FROM `statdailyorder` `so` LEFT JOIN `merchant` `m` ON `so`.`merchantid`=`m`.`merchantid`
 RIGHT JOIN `machine` `ma` ON `ma`.`machineid`=`so`.`machineid` LEFT JOIN `agency` `a` 
ON `a`.`agencyid`=`so`.`agentid`               
 ".$sqlWherestr.$sqlGroupstr.$sqlHavingstr.$sqlOrderstr;
             $result =Db::query($sqlstr)->paginate( 10,false,$this->config );
             */
            //log::write( "==========================" . $model->getLastSql()."-------");// .$model->getDbError());
            $data = $this->getPaginator( $result );
            /*$newarr = array();
            foreach( $data['data'] as $k => $v ) {
                if( $data['data'][$k]['hastran']==1 ){
                 //array_splice($data['data'], $k, 1);
                 array_push($newarr, $data['data'][$k]);
                 }
                
            }
            $data['data']=$newarr;
           */
            foreach( $data['data'] as $k => $v ) {
                $data['data'][$k]['totalsales'] = number_format( $v['totalsales'] / 100,2 );
                $data['data'][$k]['amountrefund'] = number_format( $v['amountrefund'] / 100,2 );
                //$data['data'][$k]['amounaccum']=number_format($accumresult['merchantid']['amounaccum']/100,2);
                if( $accumresult ) {
                    for( $i = 0; $i < sizeof( $accumresult ); $i++ ) {
                        //log::write( "+++++++" . json_encode( $v ) . "________" . $accumresult[$i] );
                        if( $v['machineid'] && $accumresult[$i]['machineid'] && $v['machineid'] == $accumresult[$i]['machineid'] ) {
                            //log::write( "+++++++" . json_encode( $v ) . "________" . json_encode( $v['merchantid'] ) );
                            $data['data'][$k]['amounaccum'] = number_format( $accumresult[$i]['amounaccum'] / 100,2 );
                            //$data['data'][$k]['amounaccum'] = number_format($accv['amounaccum']/100,2);
                        }
                    }
                }
                $data['data'][$k]['amountincome'] = number_format( $v['amountincome'] / 100,2 );
                $data['data'][$k]['realincome'] = number_format( $v['realincome'] / 100,2 );
                $data['data'][$k]['stotalpens'] = isset( $total_r[$v['machineid']]['stotalpens'] ) ? $total_r[$v['machineid']]['stotalpens'] : 0;
                $data['data'][$k]['stotaltorderspens'] = isset( $total_r[$v['machineid']]['stotaltorderspens'] ) ? $total_r[$v['machineid']]['stotaltorderspens'] : 0;
                $data['data'][$k]['stotalsales'] = isset( $total_r[$v['machineid']]['stotalsales'] ) ? round( $total_r[$v['machineid']]['stotalsales'] / 100,2 ) : 0;
            }

            //return ajaxReturn(0,'',$accumresult);
            return ajaxReturn( 0,'',$data );
        } else {
            return ajaxReturn( 101,'提交方式错误' );
        }

    }


    /**
     * 走势图
     * @return array|\think\response\Json
     */
    public function machineTrend()
    {
        if( Request()->isPost() ) {
            $start = input( 'start' );
            $end = input( 'end' );
            if( !$start || !$end )
                return returnErr( 100,'时间参数错误' );
            $days = (int) getDateDays( $start,$end );
            if( $days < 7 )
                $start = date( 'Y-m-d',strtotime( '-6 days',strtotime( $start ) ) );
            $result = Db::table( 'statdailyorder' )
                ->where( 'statdate','between',[ "$start","$end" ] )
                ->field('statdailyorder.*,
                    (select ma.totalnumonline from statdailymachine ma
where ma.statdate=statdailyorder.statdate
ORDER BY ma.exetime DESC
LIMIT 1) as totalnumonline ')
                ->order('statdate','asc')
                ->select();
            

            $arr = [ ]; //走势图
            $arr[0]['t'] = '有交易机柜数量';
            $arr[0]['n'] = [ ];
            $arr[1]['t'] = '无交易机柜数量';
            $arr[1]['n'] = [ ];
            foreach( $result as $k => $v ) {
                $arr[0]['n'][$v['statdate']] += 0;
                $arr[1]['n'][$v['statdate']] += 0;
                //按照机柜交易 走势图
                
                if( $v['pensrefund']>0||$v['pensincome']>0 ){
                    $arr[0]['n'][$v['statdate']]++;
                    $arr[1]['n'][$v['statdate']]=$v['totalnumonline'] -$arr[0]['n'][$v['statdate']];
                }
 
            }

            $arr[1]['n'] = array_values( $arr[1]['n'] );
            $arr[0]['n'] = array_values( $arr[0]['n'] );
            $arr_x = getDateList( $start,$end );
            $data = [ ];
            $data['arr_x'] = $arr_x;
            $data['arr'] = $arr;
            return ajaxReturn( 0,'',$data );
        } else {
            return ajaxReturn( 101,'提交方式错误' );
        }
    }
    
    
    /**
     * 近七日销售
     * @return array|\think\response\Json
     */
    public function getrecent7days()
    {
      // if( Request()->isGet() ) {
            $start = input( 'start' );
            $end = input( 'end' );
            if( !$start || !$end )
                return returnErr( 100,'时间参数错误' );
            $days = (int) getDateDays( $start,$end );
            if( $days < 7 )
                $start = date( 'Y-m-d',strtotime( '-6 days',strtotime( $start ) ) );
            
            $result = Db::table( 'statdailyorder' )
            ->where( 'statdate','between',[ "$start","$end" ] )
            ->field('sum((amountincome-amountrefund)/100) as totalsales,statdate')
            ->group('statdate')
            ->select();
            
            $arr = [ ]; //走势图
            $arr[0]['t'] = '销售趋势';
            $arr[0]['n'] = [ ];
            foreach( $result as $k => $v ) {
                $arr[0]['n'][$v['statdate']] = $v['totalsales'];

            }
            $arr[0]['n'] = array_values( $arr[0]['n'] );
            $arr_x = getDateList( $start,$end );
            $data = [ ];
            $data['arr_x'] = $arr_x;
            $data['arr'] = $arr;
            return ajaxReturn( 0,'',$data );
      //  } else {
       //     return ajaxReturn( 101,'提交方式错误' );
       // }
    }
    
    /**
     * 订单构成
     * @return array|\think\response\Json
     */
    public function ordercomposition()
    {
       // if( Request()->isGet() ) {
            $start = input( 'start' );
            $end = input( 'end' );
            if( !$start || !$end )
                return returnErr( 100,'时间参数错误' );
            $days = (int) getDateDays( $start,$end );
            if( $days < 7 )
                $start = date( 'Y-m-d',strtotime( '-6 days',strtotime( $start ) ) );
    
            $result = Db::table( 'statdailyorder' )
            ->where( 'statdate','between',[ "$start","$end" ] )
            ->field('sum(numarrears) as numarrears,sum(totaltorderspens) totaltorderspens,sum(0) as "0orderpend", statdate')
            ->group('statdate')
            ->select();
           
            $arr = [ ]; //走势图
            $arr[0]['t'] = '有效订单';
            $arr[0]['n'] = [ ];
            $arr[1]['t'] = '0元订单';
            $arr[1]['n'] = [ ];
            $arr[2]['t'] = '欠费订单';
            $arr[2]['n'] = [ ];
            foreach( $result as $k => $v ) {
                $arr[0]['n'][$v['statdate']] = $v['totaltorderspens'];
                $arr[1]['n'][$v['statdate']] = $v['numarrears'];
                $arr[2]['n'][$v['statdate']] = $v['0orderpend'];
    
            }
    
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
    
    /**
     * 销售商品分类占比
     * @return array|\think\response\Json
     * Sales goods classification ratio
     */
    public function goodsclassification()
    {
        // if( Request()->isGet() ) {
        $start = input( 'start' );
        $end = input( 'end' );
        $orderby = input( 'type' );
        if( !$start || !$end )
            return returnErr( 100,'时间参数错误' );
        $days = (int) getDateDays( $start,$end );

        $orderbystr = "";
        if(empty($orderby)){
            $orderbystr = " 1 desc ";
        }else{
           
            if($orderby=='2'){
                $orderbystr=" 2 desc ";
            }else{
                $orderbystr=" 1 desc ";
            }
        }

        $result = Db::table( 'statdailygoods' )
        ->where( 'statdate','between',[ "$start","$end" ] )
        ->field('goodsclass,sum(purchasequantity) as purchasequantity,sum(totalamount) totalamount,statdate')
        ->group('goodsclass')
        ->order($orderbystr)
        ->select();
        
        $arr = [ ]; //走势图
        $arr[0]['t'] = '商品分类销售占比';
        $arr[0]['n'] = [ ];
            if($orderby&&$orderby==2){
                foreach( $result as $k => $v ) {
                    $arr[0]['n'][$k] = array($v['goodsclass'],$v['purchasequantity']);
                }
            }else{
                foreach( $result as $k => $v ) {
                    $arr[0]['n'][$k] = array($v['goodsclass'],bcdiv($v['totalamount'],100,2));
                }

            }

    
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
    
    /**
     * 获取销售统计信息
     *
     * @param $start
     * @param $end
     *Sales statistics
     * @return array
     */
    public function getsalesstat( $start,$end )
    {
        if( !$start || !$end )
            return returnErr( 100,'时间参数错误' );
        //交易总额
        $salesinfo = Db::table( 'statdailyorder' )->whereBetween( 'statdate',[ "$start","$end" ] )
        ->field( 'ifnull(sum(totalsales),0) as totalsales,ifnull(sum(totalpens),0) as totalpens,
            ifnull(sum(numpurchased),0) as numpurchased,ifnull(sum(wxdeductedamount),0) as wxdeductedamount,
            ifnull(sum(amountstoredvalue),0) as amountstoredvalue,ifnull(sum(numarrears),0) as numarrears,
            ifnull(sum(wxpaymentpens),0) as wxpaymentpens,
            ifnull(sum(amountincome),0) as amountincome,ifnull(sum(amountrefund),0) as amountrefund,
            ifnull(sum(totalnumgoods),0) as totalnumgoods
            ' )
        ->find();
        $result = Db::table( 'statdailyorder' )
            ->where( 'statdate','between',[ "$start","$end" ] )
            ->select();
        $data = [ ];
        //交易总额
        $data['totalsales'] = bcdiv(bcsub($salesinfo["amountincome"],$salesinfo["amountrefund"],2),100);
        //销售商品件数soldnum
        $data['soldnum'] = $salesinfo["totalnumgoods"];
        //订单数量
        $data['totalpens'] = $salesinfo["totalpens"];
        //用户数量
        $data['numpurchased'] = $salesinfo["numpurchased"];
        //人均消费（元）  改成客单价
       $data['perconsumption'] = ($data['totalpens']&&$data['totalpens'] >0)?bcdiv($data['totalsales'] ,$data['totalpens'],2):0;
      // dump($data['perconsumption'] );
        //微信代扣金额
       $data['wxdeductedamount'] = bcdiv($salesinfo["wxdeductedamount"],100);
       $data['wxdeductedamountratio'] = ($data['totalsales']&&$data['totalsales']>0)?bcdiv($data['wxdeductedamount'] ,$data['totalsales'],2):0;
       $data['wxdeductedamount'] = number_format(bcdiv($salesinfo["wxdeductedamount"],100),2);
       //使用储值金额
       $data['amountstoredvalue']=  bcdiv($salesinfo["amountstoredvalue"],100);
       $data['amountstoredvalueratio'] = ($data['totalsales']&&$data['totalsales']>0)?number_format(bcdiv($data['amountstoredvalue'],$data['totalsales'],2),2):0;
       $data['amountstoredvalue']=  number_format(bcdiv($salesinfo["amountstoredvalue"],100),2);
       //欠费笔数
       $data['numarrears'] =  $salesinfo["numarrears"];
       $data['amountstoredvalueratio'] = ($data['totalsales']&&$data['totalsales']>0)?number_format(bcdiv($data['numarrears'],$data['totalpens'],2),2):0;
       //微信支付（补缴）笔数
       $data['wxpaymentpens'] = $salesinfo["wxpaymentpens"];
       
       $data['totalsales'] = number_format($data['totalsales'],2);
      // dump($data);

        return ajaxReturn( 0,'',$data );
    }
    

}
