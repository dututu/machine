<?php
namespace app\common\model;

use think\Model;
/**
 * rfid订单表
 *
 * @author      alvin
 * @version     1.0
 */
class Rfidorder extends Model
{
    protected $autoWriteTimestamp = false;

    //admin
    public function getList($rows,$sidx,$sord,$offset,$s_name,$s_select,$s_time,$status)
    {
        $data = [];
        $data3 = [];
        if(!empty($s_name)){
//            $data['u.nickname'] = ['like','%'.$s_name.'%'];
            $data3 = 'u.nickname like \'%'.$s_name.'%\' or u.mobile=\''.$s_name.'\' or u.username like \'%'.$s_name.'%\'';
        }
        if(!empty($s_select)){
            $data['a.orderstatus'] = $s_select;
        }
        if($status!=''){
            $data['a.orderstatus'] = $status;
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
            ['sysuser u','a.sysuserid=u.userid']
        ];
        $result = $this
            ->alias('a')
            ->join($join)->where($data)->where($data2)->where($data3)
            ->order($order)
            ->limit($offset,$rows)
            ->field('a.*,u.nickname,u.username,u.mobile')
            ->select();
        return $result;
    }
    public function getListCount($s_name,$s_select,$s_time,$status)
    {
        $data = [];
        $data3 = [];
        if(!empty($s_name)){
//            $data['u.nickname'] = ['like','%'.$s_name.'%'];
            $data3 = 'u.nickname like \'%'.$s_name.'%\' or u.mobile=\''.$s_name.'\' or u.username like \'%'.$s_name.'%\'';
        }
        if(!empty($s_select)){
            $data['a.orderstatus'] = $s_select;
        }
        if($status!=''){
            $data['a.orderstatus'] = $status;
        }
        $data2 = '';
        if(!empty($s_time)){
            $timerange = explode('~',$s_time);
            $data2 = 'a.createtime >= \''.$timerange[0].' 00:00:00\' and a.createtime<=\''.$timerange[1].' 23:59:59\'';
        }
        $join = [
            ['sysuser u','a.sysuserid=u.userid']
        ];
        $result = $this
            ->alias('a')
            ->join($join)->where($data)->where($data2)->where($data3)->count();
        return $result;
    }
//wechatservice
    public function getOrderList($rows,$offset,$orderstatus,$merchantid)
    {
        $data = [];
        if($orderstatus != 0){
            if($orderstatus == 2){//已付款
                $data['a.orderstatus'] = array(['=',2],['=',4],['=',5],'or');
            }else{
                $data['a.orderstatus'] = $orderstatus;
            }

        }
        $data['a.merchantid'] = $merchantid;
        $order['a.createtime'] = 'desc';
        $join = [
            ['sysuser u','a.sysuserid=u.userid','LEFT']
        ];
        $result = $this
            ->alias('a')
            ->join($join)
            ->where($data)
            ->order($order)
            ->limit($offset,$rows)
            ->field('a.*,u.nickname,u.mobile')
            ->select();
        return $result;
    }
    public function getOrderListCount($orderstatus,$merchantid)
    {
        $data = [];
        if($orderstatus != 0){
            $data['a.orderstatus'] = $orderstatus;
        }
        $data['a.merchantid'] = $merchantid;
        $result = $this
            ->alias('a')
            ->where($data)
            ->count();
        return $result;
    }

}