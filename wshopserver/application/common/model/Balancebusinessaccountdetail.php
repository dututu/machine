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
class Balancebusinessaccountdetail extends Model
{
    protected $autoWriteTimestamp = false;

    //admin
    /**
     * 总金额
     * 
     */
    public function sumPayType($maccountid,$type)
    {
        $income = $this->sumMoney($maccountid,$type,1);
        $outcome = $this->sumMoney($maccountid,$type,2);
        return ($income-$outcome);
    }

    public function sumMoney($maccountid,$type,$billType) 
    {
        $money = $this->where(function($query) use ($type) {
            if ($type==1) {
            //微信支付
                $query->where('paytype',1)->whereOr('paytype',2);
            } else if($type==0){
                //储值
                $query->where('paytype',0);
            } else if ($type==-1) {
                //全部
            }
        })->where('ordertype',$billType)->where('maccountid',$maccountid)
        ->sum('payfee');
        return $money;
    }
     public function getList($rows,$sidx,$sord,$offset,$maccountid)
    {
       
        $data3 = [];
        $data3['maccountid'] = $maccountid;
       
        $order = [];
        if(!empty($sidx)){
            $order[$sidx] = $sord;
        }

        $result = $this
            ->where($data3)
            ->order($order)
            ->limit($offset,$rows)
            ->select();
    //    echo $this->getLastSql();
        return $result;
    }
    public function getListCount($maccountid)
    {
        
        $data3['maccountid'] = $maccountid;
        
        $result = $this->where($data3)->count();
        return $result;
    }
}