<?php
namespace app\common\model;

use think\Model;
/**
 * rfid规格表
 *
 * @author      alvin
 * @version     1.0
 */
class Rfidspec extends Model
{
    protected $autoWriteTimestamp = false;

//admin
    public function getList($rows,$sidx,$sord,$offset,$s_name)
    {
        $data = [];
        if(!empty($s_name)){
//            $data['typename'] = $s_name;
            $data = 'typename like \'%'.$s_name.'%\' ';
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
            $data = 'typename like \'%'.$s_name.'%\' ';
        }
        $result = $this->where($data)->count();
        return $result;
    }

}