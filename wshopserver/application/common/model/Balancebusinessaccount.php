<?php
namespace app\common\model;

use think\Model;
use think\Db;
/**
 * 商户表
 *
 * @author      alvin
 * @version     1.0
 */
class Balancebusinessaccount extends Model
{
    protected $autoWriteTimestamp = false;

    //admin
     public function getList($rows,$sidx,$sord,$offset,$s_time,$s_name,$maccountid)
    {
        $data = '';
        $data2 = '';
        $data3 = [];
        if(!empty($s_time)){
            $timerange = explode('~',$s_time);
            $data2 = 'a.billdate >= \''.$timerange[0].'\' and a.billdate<=\''.$timerange[1].'\'';
        }
        if(!empty($s_name)){
            $data = 'b.merchantname like\'%'.$s_name.'%\'';
        }
        if(!empty($maccountid)) {
            $data3['a.maccountid'] = $maccountid;
        }
        $order = [];
        if(!empty($sidx)){
            $order[$sidx] = $sord;
        }

        $result = $this
            ->join('merchant b','b.merchantid=a.merchantid','LEFT')
            ->where($data)->where($data2)->where($data3)
            ->order($order)
            ->limit($offset,$rows)
            ->alias('a')
            ->field('sum(a.orderspens) as orderspens,
            sum(a.incomeamount) as incomeamount,
            sum(a.refundamount) as refundamount,
            a.billstatus,a.maccountid,b.merchantname,a.billdate,a.merchantid')
            ->group('b.merchantid')
            ->select();
        foreach ($result as $k => $v) {
            $v['merbillstatus'] = $this->getbillstatus($v['merchantid'],$data2);
        }
    //    echo $this->getLastSql();
        return $result;
    }
    public function getListCount($s_time,$s_name,$maccountid)
    {
        $data = '';
        if(!empty($s_name)){
            $data = 'b.merchantname like\'%'.$s_name.'%\'';
        }
        $data2 = '';
        if(!empty($s_time)){
            $timerange = explode('~',$s_time);
            $data2 = 'a.billdate >= \''.$timerange[0].'\' and a.billdate<=\''.$timerange[1].'\'';
        }
        $data3 = [];
        if(!empty($maccountid)) {
            $data3['maccountid'] = $maccountid;
        }
        $result = $this
            ->alias('a')
            ->join('merchant b','b.merchantid=a.merchantid','LEFT')
            ->where($data)->where($data2)->where($data3)->group('b.merchantid')->count();
        return $result;
    }
    public function getswhere($s_time,$s_name)
    {
        $data[1] = '';
        if(!empty($s_name)){
            $data[1] = 'b.merchantname like\'%'.$s_name.'%\'';
        }
        $data[2] = '';
        if(!empty($s_time)){
            $timerange = explode('~',$s_time);
            $data[2] = 'a.billdate >= \''.$timerange[0].'\' and a.billdate<=\''.$timerange[1].'\'';
        }
        return $data;
    }
    /**
     * 判断账单状态
     */
    public function getbillstatus($merchantid,$wheretime) {
        $result = $this->alias('a')->where('merchantid',$merchantid)->where($wheretime)->field('sum(a.billstatus) as billstatus,sum(a.balancestatus) as balancestatus')->select();
        $count = $this->alias('a')->where('merchantid',$merchantid)->where($wheretime)->count();
        if($result[0]['billstatus']==0) {
            //未出账
            return 1;
        } else if($result[0]['balancestatus']==0) {
            //未结算
            return 2;
        } else if($result[0]['balancestatus']<$count) {
            //部分结算
            return 3;
        } else if($result[0]['balancestatus']==$count) {
            //全部结算
            return 4;
        }
    }
    public function getDetailList($rows,$sidx,$sord,$offset,$s_time,$maccountid) {
        $data2 = '';
        $data3 = [];
        if(!empty($s_time)){
            $timerange = explode('~',$s_time);
            $data2 = 'a.billdate >= \''.$timerange[0].'\' and a.billdate<=\''.$timerange[1].'\'';
        }
        if(!empty($maccountid)) {
            $data3['a.merchantid'] = $maccountid;
        }
        $order = [];
        if(!empty($sidx)){
            $order[$sidx] = $sord;
        }
        $result = $this
            ->where($data2)->where($data3)
            ->order($order)
            ->limit($offset,$rows)
            ->alias('a')
            ->field('a.*')
            ->select();
        return $result;
    }
    public function getDetailListCount($s_time,$maccountid)
    {
        $data2 = '';
        if(!empty($s_time)){
            $timerange = explode('~',$s_time);
            $data2 = 'a.billdate >= \''.$timerange[0].'\' and a.billdate<=\''.$timerange[1].'\'';
        }
        $data3 = [];
        if(!empty($maccountid)) {
            $data3['a.merchantid'] = $maccountid;
        }
        $result = $this
            ->alias('a')
            ->join('merchant b','b.merchantid=a.merchantid','LEFT')
            ->where($data2)->where($data3)->count();
        return $result;
    } 
}