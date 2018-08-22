<?php
namespace app\workweixin\controller;

use think\Controller;
use think\Db;
use think\Config;
use think\Log;
use think\Loader;
use think\Request;

class Merchant extends Base
{

    protected $status_text = [ 1 => '待开柜',2 => '已开柜',3 => '未开柜',4 => '已关柜',5 => '已拉取',6 => '待分配',7 => '停用故障',8 => '待生产',9 => '生产中',10 => '设备上线', ];
    protected $businessstatus_text = [ 1=>'未联通',  2=>'已初始化', 3=>'待分配', 4=>'正常', 5=>'故障', 6=>'停用', 7=>'已作废'];
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
     * 详情
     * @access public
     * @return tp5
     */
    public function busdetail()
    {

        $merchantid = input( 'merchantid','' );
        $t = input( 't',0 );
        $start = input( 'start','' );
        $end = input( 'end','');
        $data = model( 'merchant' )->where( 'merchantid',$merchantid )->field( 'merchantname,mobile' )->find();

        $this->assign( 'merchantid',$merchantid );
        $this->assign( 'start',$start );
        $this->assign( 'end',$end );
        $this->assign( 't',$t );
        $this->assign( 'data',$data );
        return $this->fetch();

    }


    /**
     * 商户信息 -- 宫格
     *
     * @param $start
     * @param $end
     *
     * @return array
     */
    public function getMerchantData( $start,$end )
    {
        if( !$start || !$end )
            return returnErr( 100,'时间参数错误' );
        $result = Db::table( 'statdailymerchant' )
            ->where( 'statdate','between',[ "$start","$end" ] )
            ->select();
        $count = [ ];//有交易商户\
        $n_count = [ ];//无交易商户\
        $totalsales = 0;//总销售额
        $numnewmachine = 0;//总新增机柜数
        foreach( $result as $k => $v ) {
            if( $v['totalsales'] )
                $count[$v['merchantid']] = 1;
            else
                $n_count[$v['merchantid']] = 1;
            $totalsales += $v['totalsales'];
            $numnewmachine += $v['numnewmachine'];

        }
        $count = count( $count );
        $n_count = count( $n_count );
        $days = getDateDays( $start,$end );
        //日均交易额
        $pvg = $totalsales / $days;

        //新增商户
        $new_end = $end . ' 23:59:59';
        $new_agent = model( 'Merchant' )
            ->where( 'status',2 )
            ->whereExp( "if(approvertime,approvertime,createtime) <= '$new_end'",'and' )
            ->whereExp( "if(approvertime,approvertime,createtime) >= '$start'",'and' )
            ->count();


        //商户总量
        $total_merchant = model( 'Merchant' )
            ->where( 'status',2 )
            ->whereExp( "if(approvertime,approvertime,createtime) < '$new_end'",'and' )
            ->count();

        //时间段内有购买行为的用户数量
        $numpurchased = Db::table( 'statdailyorder' )
            ->where( 'statdate','between',[ "$start","$end" ] )
            ->sum( 'numpurchased' );

        //商户日均用户
        $avg_merchant = round( bcmul( $pvg,$count ),2 );
        $avg_user = round( bcmul( $numpurchased,$count ),2 );
        $data['count'] = $count;
        $data['n_count'] = $n_count;
        $data['count_p'] = round( $count / $total_merchant,2 );
        $data['avg_merchant'] = $avg_merchant;
        $data['avg_user'] = $avg_user;
        $data['new_agent'] = $new_agent;
        $data['total_merchant'] = $total_merchant;
        return returnErr( 0,'',$data );
    }

    /**
     * 走势图
     * @return array|\think\response\Json
     */
    public function merchantTrendold()
    {
        if( Request()->isPost() ) {
            $start = input( 'start' );
            $end = input( 'end' );
            if( !$start || !$end )
                return returnErr( 100,'时间参数错误' );
            $days = (int) getDateDays($start,$end);
            if( $days < 7 )
                $start = date('Y-m-d',strtotime('-6 days',strtotime($start)));
            $result = Db::table( 'statdailymerchant' )
                ->where( 'statdate','between',[ "$start","$end" ] )
                ->select();
            $arr = [ ]; //走势图
            $arr[0]['t'] = '有交易商户数量';
            $arr[0]['n'] = [ ];
            $arr[1]['t'] = '无交易商户数量';
            $arr[1]['n'] = [ ];
            foreach( $result as $k => $v ) {
                $arr[0]['n'][$v['statdate']] += 0;
                $arr[1]['n'][$v['statdate']] += 0;
                //按照机柜交易 走势图
                if( $v['totalsales'] )
                    $arr[0]['n'][$v['statdate']]++;
                else
                    $arr[1]['n'][$v['statdate']]++;
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
     * 商户排行
     * @return array|\think\response\Json
     */
    public function getMerchantInfo()
    {

        if( Request()->isPost() ) {
            $this->getPage();
            $start = input( 'start' );
            $end = input( 'end' );
            $sort = input( 'sort',1 );
            if( !$start || !$end )
                return returnErr( 100,'时间参数错误' );

            $desc = $sort ? 'desc' : 'asc';

            //0销售金额 1交易商品数量 2机柜销售均价
            $type = input( 'type',0 );
            $model = Db::table( 'statdailymerchant' )
                ->alias( 'sm' )
                ->join( 'merchant m','sm.merchantid = m.merchantid','left' )
                ->join( 'machine  ma','sm.merchantid = m.merchantid','left' )
                ->where( 'sm.statdate','between',[ "$start","$end" ] )
                ->group( 'sm.merchantid' );

            if( $type == 0 )
                $model->order( 'stotalsales',$desc );
            elseif( $type == 1 )
                $model->order( 'squantitygoods',$desc );
            else
                $model->order( 'unitprice',$desc );

            $model->order( 'mtotalnummachine','desc' );

            $result = $model->field( 'sm.merchantid,ifnull(m.merchantname,"") merchantname,max(sm.totalnummachine) mtotalnummachine,sum(sm.totalsales) stotalsales,sum(sm.quantitygoods) squantitygoods,squantitygoods/mtotalnummachine as unitprice ' )->paginate( 10,false,$this->config );
            $data = $this->getPaginator( $result );
            foreach( $data['data'] as $k => $v ) {
                $data['data'][$k]['stotalsales'] = round( $v['stotalsales'] / 100,2 );
            }

            return ajaxReturn( 0,'',$data );
        } else {
            return ajaxReturn( 101,'提交方式错误' );
        }
    }

    /**
     * 获取商户的机柜信息
     * @return array|\think\response\Json
     */
    public function getMerchantMachineDetail()
    {
        if( Request()->isPost() ) {
            $this->getPage();
            $merchantid = input( 'merchantid','' );

            if( !$merchantid )
                return returnErr( 100,'商户参数错误' );
            $start = input( 'start' );
            $end = input( 'end' );
            if( !$start || !$end )
                return returnErr( 100,'时间参数错误' );

            $factoryconfig = Config::get( 'machinedict.factory' );
            $functypeconfig = Config::get( 'machinedict.functype' );
            $doortypeconfig = Config::get( 'machinedict.doortype' );
            $placeconfig = Config::get( 'machinedict.place' );
            $rfidtypeconfig = Config::get( 'machinedict.rfidtype' );

            $result = Db::table('statdailyorder')
                ->alias('so')
                ->join('machine ma','ma.machineid = so.machineid','left')
                ->field( 'so.machineid,sum(so.totalsales) stotalsales,ma.containerid,ma.status,ma.rfidtypecode,ma.doortypecode,ma.facode,ma.funccode,ma.doortypecode,ma.placecode,ma.businessstatus,ifnull(dailaddress,"") dailaddress' )
                ->where( 'so.merchantid',$merchantid )
                ->where( 'so.statdate','between',[ "$start","$end" ] )
                ->order('stotalsales','desc')
                ->group('so.machineid')
                ->paginate( 10,false,$this->config );

            $data = $this->getPaginator( $result );
            $machine = &$data['data'];

            foreach( $machine as $k => $v ) {
                $machine[$k]['stotalsales'] = round($v['stotalsales']/100,2);
                $machine[$k]['faname'] = $factoryconfig[$machine[$k]['facode']];
                $machine[$k]['funcname'] = $functypeconfig[$machine[$k]['funccode']];
                $machine[$k]['doortypename'] = $doortypeconfig[$machine[$k]['doortypecode']];
                $machine[$k]['placename'] = $placeconfig[$machine[$k]['placecode']];
                if( $machine[$k]['rfidtypecode'] ) {
                    $machine[$k]['rfidtypename'] = $rfidtypeconfig[$machine[$k]['rfidtypecode']];
                }
                $machine[$k]['status_text'] = $this->status_text[$v['status']];
                $machine[$k]['businessstatus_text'] = $this->businessstatus_text[$v['businessstatus']];
            }


            return ajaxReturn( 0,'',$data );
        } else {
            return ajaxReturn( 101,'提交方式错误' );
        }
    }
    
    /**
     * 获取用户统计信息
     *
     * @param $start
     * @param $end
     *merchant statistics
     * @return array
     */
    public function getmerchantstat( $start,$end )
    {
        if( !$start || !$end )
            return returnErr( 100,'时间参数错误' );
        //用户信息
        $merchantinfo = Db::table( 'statdailymerchant' )
        ->whereBetween( 'statdate',[ "$start","$end" ] )
        ->field( "ifnull(count(*),0) as merchantsnum,ifnull(
(select count(*) from 
statdailymerchant sm
where sm.totalsales>0 and sm.statdate between '".$start."' and  '".$end."' )
 ,0) as tradingmerchantsnum,
ifnull(sum(totalsales),0) as totalsales,ifnull(sum(purchasedusersnum),0) as usernummerchant,
ifnull(
(select count(merchantid) from 
merchant 
where status=2 and approvertime between '".$start."' and  '".$end."' )
,0) as newmerchant
            " )
    ->find();
        
        $data = [ ];
        //商户总数
        $data['merchantsnum'] = $merchantinfo["merchantsnum"];
        //有交易商户数
        $data['tradingmerchantsnum'] = $merchantinfo["tradingmerchantsnum"];
        //有交易商户数占比
        $data['tradingnumratio'] =  ($data['merchantsnum'] &&$data['merchantsnum'] >0)?bcmul(bcdiv($data['tradingmerchantsnum'],$data['merchantsnum'] ,4),100,2):0;
        //商户交易额
        $data['totalsales'] = $merchantinfo["totalsales"];
        //天数
        $days = (int) getDateDays( $start,$end );
        //商户日均交易额
        $data['averagedailymerchant'] =  ($days&&$days>0)?bcdiv($data['totalsales'],$days):0;
        //用户数
        $data['usernummerchant'] = $merchantinfo["usernummerchant"];
        //商户日均用户数
        $data['tradingnumratio'] =  ($days&&$days>0)?bcdiv($data['usernummerchant'] ,$days):0;
        //newmerchant新商户
        $data['newmerchant'] = $merchantinfo["newmerchant"];

    
        return ajaxReturn( 0,'',$data );
    }
    
    /**
     * 走势图
     * @return array|\think\response\Json
     */
    public function merchantTrend()
    {
       // if( Request()->isPost() ) {
            $start = input( 'start' );
            $end = input( 'end' );
            if( !$start || !$end )
                return returnErr( 100,'时间参数错误' );
            $days = (int) getDateDays($start,$end);
            if( $days < 7 )
                $start = date('Y-m-d',strtotime('-6 days',strtotime($start)));
            $result = Db::table( 'statdailymerchant' )
            ->alias("sm")
            ->where( 'sm.statdate','between',[ "$start","$end" ] )
            ->field('
               (
                select count(*) from statdailymerchant sm1
                where sm1.totalsales>0
                and  sm1.statdate=sm.statdate
                ) as numtrading,
                 (
                select count(*) from statdailymerchant sm1
                where sm1.totalsales=0
                and  sm1.statdate=sm.statdate
                ) as numnontrading
                ,sm.statdate as statdate
                ')  
            ->group('sm.statdate')
            ->select();

            $arr = [ ]; //走势图
            $arr[0]['t'] = '有交易商户数量';
            $arr[0]['n'] = [ ];
            $arr[1]['t'] = '无交易商户数量';
            $arr[1]['n'] = [ ];
            foreach( $result as $k => $v ) {
                $arr[0]['n'][$v['statdate']] =$v['numtrading'];
                $arr[1]['n'][$v['statdate']] =$v['numnontrading'];

            }

            $arr[1]['n'] = array_values( $arr[1]['n'] );
            $arr[0]['n'] = array_values( $arr[0]['n'] );
            $arr_x = getDateList( $start,$end );
            $data = [ ];
            $data['arr_x'] = $arr_x;
            $data['arr'] = $arr;
            return ajaxReturn( 0,'',$data );
       // } else {
       //     return ajaxReturn( 101,'提交方式错误' );
       // }
    }
    
    /**
     * 商户交易排行
     * @return \think\response\Json
     */
    public function merchantRanking()
    {
        //if( Request()->isPost() ) {
            $this->getPage();
            $start = input( 'start' );
            $end = input( 'end' );
            $sort = input( 'sort',1 );
            if( !$start || !$end )
                return returnErr( 100,'时间参数错误' );
    
            $desc = $sort ? 'desc' : 'asc';
    
            //0交易金额 1商品数量 2机柜销售均价
            $type = input( 'type',0 );
    
            $model = Db::table( 'statdailymerchant' )
            ->alias( 'sm' )
            ->join('merchant me','me.merchantid = sm.merchantid')
            ->field('ifnull(SUM(totalsales),0) totalsales,ifnull(SUM(quantitygoods),0) quantitygoods,
                ifnull(SUM(totalsales)/totalnummachine,0) averagedailymachine,
                (case when totalnummachine=0 then 0
                else ifnull(SUM(totalsales)/totalnummachine,0)
                end) as averagedailymachine,
                sm.merchantid,merchant.merchantname
                ')
            ->where( 'sm.statdate','between',[ "$start","$end" ] );
    
            if( $type == 0 ){
                $model->group( 'sm.merchantid' )
                ->order( ' totalsales ',$desc );
            }
            elseif( $type == 1 ){
                $model->group( 'sm.merchantid' )
                ->order( ' quantitygoods ',$desc );
            }else{
                $model->group( 'sm.merchantid' )
                ->order( ' averagedailymachine ',$desc );
            }
            $result = $model
            ->paginate( 10,false,$this->config );

            //log::write( "==========================" . $model->getLastSql()."-------");// .$model->getDbError());
            $data = $this->getPaginator( $result );
  
            foreach( $data['data'] as $k => $v ) {
                $data['data'][$k]['totalsales'] = number_format( $v['totalsales'] / 100,2 );
                $data['data'][$k]['quantitygoods'] = number_format( $v['quantitygoods'] / 100,2 );

            }

            return ajaxReturn( 0,'',$data );
       // } else {
       //     return ajaxReturn( 101,'提交方式错误' );
       // }
    
    }
    

    /**
     * @return \think\response\Json
     * 按商户的机柜排行
     */
    public function machineRanking()
    {
        if( Request()->isPost() ) {
            $this->getPage();
            $start = input( 'start' );
            $end = input( 'end' );
            $merchantid = input( 'merchantid' );
            if( !$merchantid  )
                return returnErr( 100,'商户id为必填。' );
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
    
    
    
}
