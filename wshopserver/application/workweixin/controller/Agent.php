<?php
namespace app\workweixin\controller;
use think\Controller;
use think\Db;
use think\Config;
use think\Log;
use think\Loader;
use think\Request;

class Agent extends  Base
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
     * 代理商详情
     * @return mixed
     */
    public function detail(){
        return $this->fetch();
    }

    /**
     * 商户详情
     */
    public function busDetail(){
        return $this->fetch();
    }
    
    /**
     * 代理商排序列表
     */
    public function agentranking(){
           //if( Request()->isPost() ) {
            $this->getPage();
            $start = input( 'start' );
            $end = input( 'end' );
            $sort = input( 'sort',1 );
            $agentbusiness = input( 'agentbusiness');

            if( !$start || !$end )
                return returnErr( 100,'时间参数错误' );
    
            $desc = $sort ? 'desc' : 'asc';
    
            //0交易金额 1商品数量 2机柜销售均价
            $type = input( 'type',0 );
    
            $model = Db::table( 'statdailymerchant' )
            ->alias( 'sm' )
            ->join('agency ag','ag.agencyid = sm.agentid')
            ->field('ifnull(SUM(totalsales),0) totalsales,ifnull(SUM(quantitygoods),0) quantitygoods,
                ifnull(SUM(totalsales)/totalnummachine,0) averagedailymachine,
                (case when totalnummachine=0 then 0
                else ifnull(SUM(totalsales)/totalnummachine,0)
                end) as averagedailymachine,
                sm.agentid,ag.name
                ')
            ->where( 'sm.statdate','between',[ "$start","$end" ] );
    
            if(agentbusiness){
                $model->where( 'sm.agentbusiness',$agentbusiness);
            }
            if( $type == 0 ){
                $model->group( 'sm.agentid' )
                ->order( ' totalsales ',$desc );
            }
            elseif( $type == 1 ){
                $model->group( 'sm.agentid' )
                ->order( ' quantitygoods ',$desc );
            }else{
                $model->group( 'sm.agentid' )
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
     * 商务负责人列表
     */
    public function agentbusinesslist(){
        //if( Request()->isPost() ) {
        $this->getPage();

    
        $desc = $sort ? 'desc' : 'asc';
    
        //0交易金额 1商品数量 2机柜销售均价
        $type = input( 'type',0 );
    
        $model = Db::table( 'agency' )
        ->alias( 'ag' )
        ->join('sysuser su','su.userid = ag.userid')
        ->field('username ,ag.userid
               
                ');

        $result = $model
        ->paginate( 10,false,$this->config );
    
        //log::write( "==========================" . $model->getLastSql()."-------");// .$model->getDbError());
        $data = $this->getPaginator( $result );
  
        return ajaxReturn( 0,'',$data );
        // } else {
        //     return ajaxReturn( 101,'提交方式错误' );
        // }
    }
}
