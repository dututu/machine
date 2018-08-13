<?php
namespace app\common\model;

use think\Model;
/**
 * 商品分类表
 *
 * @author      alvin
 * @version     1.0
 */
class Goodscategory extends Model
{
    protected $autoWriteTimestamp = false;

//admin
    public function getList($rows,$sidx,$sord,$offset,$s_name,$s_select)
    {
        $data = [];
        if(!empty($s_name)){
            $data['categoryname'] = ['like','%'.$s_name.'%'];
        }
//        if(!empty($s_select)){
//            $data['storecondition'] = $s_select;
//        }
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
    public function getListCount($s_name,$s_select)
    {
        $data = [];
        if(!empty($s_name)){
            $data['categoryname'] = ['like','%'.$s_name.'%'];
        }
//        if(!empty($s_select)){
//            $data['merchantid'] = $s_select;
//        }
        $result = $this->where($data)->count();
        return $result;
    }

}