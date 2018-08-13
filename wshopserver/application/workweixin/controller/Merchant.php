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
    public function merchantTrend()
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
                ->where( 'sm.statdate','between',[ "$start","$end" ] )
                ->group( 'sm.merchantid' );

            if( $type == 0 )
                $model->order( 'stotalsales',$desc );
            elseif( $type == 1 )
                $model->order( 'squantitygoods',$desc );
            else
                $model->order( 'squantitygoods/mtotalnummachine',$desc );

            $model->order( 'mtotalnummachine','desc' );

            $result = $model->field( 'sm.merchantid,ifnull(m.merchantname,"") merchantname,max(sm.totalnummachine) mtotalnummachine,sum(sm.totalsales) stotalsales,sum(sm.quantitygoods) squantitygoods' )->paginate( 10,false,$this->config );
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
}
