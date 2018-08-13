<?php
namespace app\common\model;

use think\Model;
/**
 * 储值活动表
 *
 * @author      alvin
 * @version     1.0
 */
class Rechargeactivity extends Model
{
    protected $autoWriteTimestamp = false;

//admin
    public function getList($rows,$sidx,$sord,$offset,$s_name)
    {
        $data = [];
        if(!empty($s_name)){
            $data['fee'] = $s_name*100;
        }
        $order = [];
        if(!empty($sidx)){
            $order[$sidx] = $sord;
        }
        $result = $this->where($data)
            ->order($order)
            ->limit($offset,$rows)
            ->alias('a')
            ->field('a.*')
            ->select();
//        echo $this->getLastSql();
        return $result;
    }
    public function getListCount($s_name)
    {
        $data = [];
        if(!empty($s_name)){
            $data['fee'] = $s_name;
        }
        $result = $this->where($data)->count();
        return $result;
    }

}