<?php
namespace app\common\model;

use think\Model;
/**
 * 商品订单详情表
 *
 * @author      alvin
 * @version     1.0
 */
class Goodsorderdetail extends Model
{
    protected $autoWriteTimestamp = false;


    public function order()
    {
        return $this->belongsTo('goodsorder');
    }
    //B端
    public function getDetailList($orderid)
    {
        $data = [];
        $data['d.orderid'] = $orderid;
//        if(!empty($groupids)){
//            $data['mg.groupid'] = array('in',$groupids);
//        }
        $join = [
            ['goods g','g.goodsid = d.goodsid','LEFT']
        ];
        $result = $this
            ->alias('d')
            ->join($join)
            ->where($data)
            ->field('d.*,g.originalfee,g.picurl')
            ->select();
//        echo $this->getLastSql();
        return $result;
    }
}