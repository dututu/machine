<?php
namespace app\common\model;

use think\Model;
/**
 * 平台对账分表
 * 
 *
 * @author      alvin
 * @version     1.0
 */
class Balanceplatformaccountdetail extends Model
{
    protected $autoWriteTimestamp = false;


    public function getList($rows,$sidx,$sord,$offset,$accountid)
    {
        $data['a.accountid'] = $accountid;
        $order = [];
        if(!empty($sidx)){
            $order[$sidx] = $sord;
        }

        $result = $this->where($data)
            ->order('accountstatus DESC')
            ->limit($offset,$rows)
            ->alias('a')
            ->field('a.*')
            ->select();
        return $result;
    }
    public function getListCount($accountid)
    {
        
        if(!empty($accountid)){
            $data['a.accountid'] = $accountid;
        }
        
        $result = $this
            ->alias('a')->where($data)->count();
        return $result;
    }
    public function getListDetail($batchno,$billtype) 
    {
        $billInfo = $this->where('batchno',$batchno)->find();
        if($billInfo['billtype']==1) {
            //商品购买
            //收款
            if($billInfo['payresult']==1) {
                $result = $this->alias('a')
                ->join('goodsorderpay gop','a.batchno = gop.batchno','LEFT')
                ->join('goodsorder go','gop.orderid = go.orderid','LEFT')
                ->join('user u', 'u.userid = go.userid','LEFT')
                ->join('machine m','m.machineid = go.machineid','LEFT')
                ->field('a.orderno,gop.payfee,gop.paytype,gop.paystatus,u.nickname,u.mobile,
                u.unionid,a.batchno,a.serialno,a.payfee as income,a.paytype as billtype,a.fees,m.machinename,a.accountstatus,a.payresult,a.billtype as bill,a.rates')
                ->where('a.batchno',$batchno)
                ->find();
            } else {
                
                 //退款
                $result = $this->alias('a')
                ->join('outrefund rf','rf.batchno = a.batchno','LEFT')
                ->join('goodsorder go','rf.orderid = go.orderid','LEFT')
                ->join('user u', 'u.userid = go.userid','LEFT')
                ->join('machine m','m.machineid = go.machineid','LEFT')
                ->field('a.orderno,rf.realfee as payfee,rf.paytype,1 as paystatus,u.nickname,u.mobile,
                u.unionid,a.batchno,a.serialno,a.payfee as income,a.paytype as billtype,a.fees,m.machinename,a.accountstatus,a.payresult,a.billtype as bill,a.rates')
                ->where('a.batchno',$batchno)
                ->find();
            }
            
           

        } else if($billInfo['billtype']==2) {
            //储值购买
            $result = $this->alias('a')
            ->join('rechargeorder ro','ro.batchno=a.batchno','LEFT')
            ->join('user u', 'u.userid = ro.userid','LEFT')
            ->field('ro.fee as payfee,1 as paytype,1 as paystatus,u.nickname,u.mobile,
                u.unionid,a.batchno,a.serialno,a.payfee as income,a.paytype as billtype,a.fees,0 as machinename,a.accountstatus,a.payresult,a.billtype as bill,a.rates')
            ->where('a.batchno',$batchno)
            ->find();
        } else if($billInfo['billtype']==3) {
            //rfid标签购买
            $result = $this->alias('a')
            ->join('rfidorder ro','ro.batchno=a.batchno','LEFT')
            
            ->field('ro.payfee,1 as paytype,1 as paystatus,0 as nickname,0 as mobile,
                0 as unionid,a.batchno,a.serialno,a.payfee as income,a.paytype as billtype,a.fees,0 as machinename,a.accountstatus,a.payresult,a.billtype as bill')
            ->where('a.batchno',$batchno)
            ->find();
        } else {
            $reuslt = '';
        }
        //echo $this->getLastsql();
        return $result;
    }

}