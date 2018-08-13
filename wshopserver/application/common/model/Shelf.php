<?php
namespace app\common\model;

use think\Model;
use think\Db;
/**
 * 货架表
 *
 * @author      alvin
 * @version     1.0
 */
class Shelf extends Model
{
    protected $autoWriteTimestamp = false;

    //wechatservice
    public function getList($machineid)
    {

        $data['a.machineid'] = $machineid;
        $order['a.floor'] = 'asc';
        $result = $this->where($data)
            ->alias('a')
            ->order($order)
            ->select();
        return $result;
    }

}