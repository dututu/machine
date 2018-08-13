<?php
namespace app\common\model;

use think\Model;
/**
 * rfid订单详情表
 *
 * @author      alvin
 * @version     1.0
 */
class Rfidorderdetail extends Model
{
    protected $autoWriteTimestamp = false;

    public function getCountById($goodsid)
    {
        $data = [];
        if(!empty($goodsid)){
            $data['a.goodsid'] = $goodsid;
        }
        $result = $this
            ->alias('a')
            ->where($data)->count();
        return $result;
    }

    public function getTotalCountByMerchant($merchantid)
    {
        $data = [];
        if(!empty($merchantid)){
            $data['t.merchantid'] = $merchantid;
        }
        $data2 = 't.orderstatus = 2 or t.orderstatus = 4 or t.orderstatus = 5 ';
        $join = [
            ['rfidorder t','a.orderid=t.orderid','LEFT']
        ];
        $result = $this
            ->alias('a')
            ->join($join)->where($data)->where($data2)->count();
        return $result;
    }
}