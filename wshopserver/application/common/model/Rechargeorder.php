<?php
namespace app\common\model;

use think\Model;
/**
 * 储值订单表
 *
 * @author      alvin
 * @version     1.0
 */
class Rechargeorder extends Model
{
    protected $autoWriteTimestamp = false;


//admin
    public function getList($rows,$sidx,$sord,$offset,$s_name,$s_select,$s_time)
    {
        $data = [];
//        if(!empty($s_select)){
//            $data['merchantid'] = $s_select;
//        }
        $data1 = '';
        if(!empty($s_name)){
            $data1 = 'u.nickname like\'%'.$s_name.'%\' or u.mobile=\''.$s_name.'\'';
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
            $order['a.createtime'] = 'desc';
        }
        $join = [
            ['user u','a.userid = u.userid']
        ];
        $result = $this
            ->alias('a')
            ->join($join)
            ->field('a.*,u.nickname,u.mobile')
            ->where($data)
            ->where($data1)
            ->where($data2)
            ->order($order)
            ->limit($offset,$rows)
            ->select();
//        echo $this->getLastSql();
        return $result;
    }
    public function getListCount($s_name,$s_select,$s_time)
    {
        $data = [];
//        if(!empty($s_select)){
//            $data['merchantid'] = $s_select;
//        }
        $data1 = '';
        if(!empty($s_name)){
            $data1 = 'u.nickname like\'%'.$s_name.'%\' or u.mobile=\''.$s_name.'\'';
        }
        $data2 = '';
        if(!empty($s_time)){
            $timerange = explode('~',$s_time);
            $data2 = 'a.createtime >= \''.$timerange[0].' 00:00:00\' and a.createtime<=\''.$timerange[1].' 23:59:59\'';
        }
        $join = [
            ['user u','a.userid=u.userid']
        ];
        $result = $this
            ->alias('a')
            ->join($join)
            ->field('a.*,u.nickname,u.mobile')
            ->where($data)
            ->where($data1)
            ->where($data2)
            ->count();
        return $result;
    }
}