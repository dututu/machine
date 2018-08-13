<?php
namespace app\common\model;

use think\Model;
/**
 * 平台对账总表
 *
 * @author      alvin
 * @version     1.0
 */
class Balanceplatformaccount extends Model
{
    protected $autoWriteTimestamp = false;

    public function billCount($type,$s_time) 
    {
        $timerange = explode('~',$s_time);
        return $this->where('billstatus',$type)
            ->whereTime('reconciliationtime', 'between', [$timerange[0], $timerange[1]])
            ->count('accountid');
    }
    public function getList($rows,$sidx,$sord,$offset,$s_time,$s_select,$status)
    {
        $data = [];
        $data2 = '';
        if(!empty($s_time)){
            $timerange = explode('~',$s_time);
            $data2 = 'a.reconciliationtime >= \''.$timerange[0].' 00:00:00\' and a.reconciliationtime<=\''.$timerange[1].' 23:59:59\'';
        }
        if(!empty($s_select)){
            if($s_select==1) {
                $data['a.billstatus'] = $s_select;
            } else {
                $data['a.billstatus'] = 0;
            }
            
        }
        $order = [];
        if(!empty($sidx)){
            $order[$sidx] = $sord;
        } else {
            $order['reconciliationtime'] = 'desc';
        }

        $result = $this->where($data)->where($data2)
            ->order($order)
            ->limit($offset,$rows)
            ->alias('a')
            ->field('a.*')
            ->select();
        return $result;
    }
    public function getListCount($s_time,$s_select,$status)
    {
        $data = [];
        $data3 = [];
        if(!empty($s_select)){
            if($s_select==1) {
                $data['a.billstatus'] = $s_select;
            } else {
                $data['a.billstatus'] = 0;
            }
        }
        $data2 = '';
        if(!empty($s_time)){
            $timerange = explode('~',$s_time);
            $data2 = 'a.reconciliationtime >= \''.$timerange[0].' 00:00:00\' and a.reconciliationtime<=\''.$timerange[1].' 23:59:59\'';
        }
        $result = $this
            ->alias('a')->where($data)->where($data2)->count();
        return $result;
    }


}