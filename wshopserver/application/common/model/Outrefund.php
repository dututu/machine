<?php
namespace app\common\model;

use think\Model;
/**
 * 商品订单退款表
 *
 * @author      alvin
 * @version     1.0
 */
class Outrefund extends Model
{
    protected $autoWriteTimestamp = false;


    //0 退款申请中 1 审核通过 2 审核未通过 3 已退款 4 退款失败
    static public $refundstatus = ['退款申请中','审核通过','审核未通过','已退款','退款失败'];

    public function getOrderListByStatus($page,$rows,$userid,$status)
    {
        //status 100 待退款 101 扣费未成功 102 交易成功 103已退款
        $offset = ($page - 1) * $rows;
        $data = [];
        $order = [
            'a.createtime'=>'desc',
        ];
        $data['a.userid'] = $userid;
        if($status == 100){
            $data['a.refundstatus'] = 0;//array(['=',3],['=',5],['=',6],['=',7],['=',8],'or');;
        }else if($status == 103){
            $data['a.refundstatus'] = 3;
        }

        $join = [
            ['machine m','a.machineid=m.machineid'],
            ['user u','a.userid=u.userid']
        ];
        $result = $this
            ->alias('a')->where($data)
            ->order($order)
            ->limit($offset,$rows)
            ->join($join)
            ->field('a.orderid,a.orderno,a.serialno,a.userid,a.machineid,a.createtime,a.orderstatus,a.doorstatus,a.totalfee,a.payfee,m.machinename,m.location,m.dailaddress,u.nickname')
            ->select();
        $total = $this->where($data)
            ->alias('a')
            ->join($join)
            ->count();

        return [
            'result' => $result,
            'total' => $total
        ];
    }

}