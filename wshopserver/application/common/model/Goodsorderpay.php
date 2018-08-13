<?php
namespace app\common\model;

use think\Model;
/**
 * 商品订单支付表
 *
 * @author      alvin
 * @version     1.0
 */
class Goodsorderpay extends Model
{
    protected $autoWriteTimestamp = false;
    /**
     * 汇总支付订单数据
     * 
     */
    public function sumOrder($date) {
        $timeStamp = strtotime($date);
        $startTime = date('Y-m-d H:i:s',$timeStamp);    
        $endTime = date('Y-m-d H:i:s',$timeStamp+3600*24-1);
        $result['incomeCount'] = $this->orderCount($startTime,$endTime,1) | 0;
        $result['refundCount'] = $this->orderreCount($startTime,$endTime) | 0;
        $result['incomeMoney'] = $this->orderMoney($startTime,$endTime,1) | 0;
        $result['refundMoney'] = $this->orderreMoney($startTime,$endTime) | 0;
        //rfid订单
        $result['rfincomeCount'] = $this->orderrfCount($startTime,$endTime) | 0;
        $result['rfincomeMoney'] = $this->orderrfMoney($startTime,$endTime) | 0;
        
        return $result;
    }
    /**
     * 订单笔数查询
     * 
     * 
     */
    private function orderCount($startTime,$endTime,$type) 
    {
        $result['rechargecount'] = 0;
        //1微信支付收款2微信支付退款
        //商品购买
        $result['shopcount'] = $this->where(function ($query) use ($type){
            if($type==1) {
                $query->where('paystatus', 1);
            } else if($type==2){
                $query->where('paystatus', 5);
            }
        })
        ->where(function ($query) {
            $query->where('paytype', 1)->whereOr('paytype', 2);
        })
        ->whereTime('paytime', 'between', [$startTime, $endTime])
        ->count();
        //储值购买
        if($type==1) {
            $result['rechargecount'] = Rechargeorder::where('status', 2)
            ->whereTime('createtime', 'between', [$startTime, $endTime])
            ->count();
        }
        return (int)$result['shopcount']+(int)$result['rechargecount'];
    }
    /**
     * 订单收退款查询
     * 
     */
    private function orderMoney($startTime,$endTime,$type)
    {
        $result['rechargefee'] = 0;
        //1微信支付收款2微信支付退款
        //商品购买
        $result['shopfee'] = $this->where(function ($query) use ($type){
            if($type==1) {
                $query->where('paystatus', 1);
            } else if($type==2){
                $query->where('paystatus', 5);
            }
        })
        ->where(function ($query) {
            $query->where('paytype', 1)->whereOr('paytype', 2);
        })
        ->whereTime('paytime', 'between', [$startTime, $endTime])
        ->sum('payfee');
        //储值购买
        if($type==1) {
            $result['rechargefee'] = Rechargeorder::where('status', 2)
            ->whereTime('createtime', 'between', [$startTime, $endTime])
            ->sum('fee');
        }
        return (int)$result['shopfee']+(int)$result['rechargefee'];
    }
    //对账汇总
    public function sumOrders($machineList,$startTime,$endTime) {
        $result['incomeMoney'] = $this->getOrderMoney($machineList,$startTime,$endTime,1)|0;//收入
        //$result['refundMoney'] = $this->getOrderMoney($machineList,$startTime,$endTime,5)|0;//退款
        $result['incomeCount'] = $this->getOrderCount($machineList,$startTime,$endTime,1)|0;//收入笔数
        //$result['refundCount'] = $this->getOrderCount($machineList,$startTime,$endTime,5)|0;//退款笔数
        //手续费
        $result['fees'] = 0;
        return $result;
    }
    private function getOrderMoney($machineList,$startTime,$endTime,$orderType)
    {
        $money = $this->alias('gop')
                    ->join('goodsorder go','go.orderid=gop.orderid','LEFT')
                    ->field('gop.*')
                    ->whereIn('go.machineid', $machineList)
                    ->whereTime('gop.paytime', 'between', [$startTime, $endTime])
                    ->where('gop.payfee','>',0)
                    ->where('gop.paystatus',$orderType)
                    ->sum('gop.payfee');
                    
        return $money;
    }
    private function getOrderCount($machineList,$startTime,$endTime,$orderType)
    {
        $count = $this->alias('gop')
                    ->join('goodsorder go','go.orderid=gop.orderid','LEFT')
                    ->field('gop.*')
                    ->whereIn('go.machineid', $machineList)
                    ->whereTime('gop.paytime', 'between', [$startTime, $endTime])
                    ->where('gop.payfee','>',0)
                    ->where('gop.paystatus',$orderType)
                    ->count('gop.orderpayid');
        return $count;
    }
    /*退款相关*/
    private function orderreCount($startTime,$endTime)
    {
        $num = Outrefund::whereTime('fefundtime', 'between', [$startTime, $endTime])->where('paytype',1)->where('refundstatus',3)->count('refundid');
        return $num; 
    }
    private function orderreMoney($startTime,$endTime)
    {
        $money = Outrefund::whereTime('fefundtime', 'between', [$startTime, $endTime])->where('paytype',1)->where('refundstatus',3)->sum('realfee');
        
        
        return $money; 
    }
    // rfid
     /**/
    private function orderrfCount($startTime,$endTime)
    {
        $num = Rfidorder::whereTime('createtime', 'between', [$startTime, $endTime])->where('paytype',1)->where('orderstatus',['=',2],['=',5],'or')->count('orderid');
        return $num; 
    }
    private function orderrfMoney($startTime,$endTime)
    {
        $money = Rfidorder::whereTime('createtime', 'between', [$startTime, $endTime])->where('paytype',1)->where('orderstatus',['=',2],['=',5],'or')->sum('payfee');
        
        return $money; 
    }
   

}