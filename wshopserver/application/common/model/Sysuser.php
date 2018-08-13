<?php
namespace app\common\model;

use think\Model;
/**
 * 成员表
 *
 * @author      alvin
 * @version     1.0
 */
class Sysuser extends Model
{
    protected $autoWriteTimestamp = false;


//admin
    public function getList($rows,$sidx,$sord,$offset,$s_name)
    {

        $data = '';
        if(!empty($s_name)){
            $data = 'username like\'%'.$s_name.'%\' or mobile=\''.$s_name.'\'';
        }
        $order = [];
        if(!empty($sidx)){
            $order[$sidx] = $sord;
        }
        $data2['a.userid'] = ['IN',function($query){
            $query->table('authgroupaccess')->field('uid');
        }];
        $result = $this
            ->alias('a')
            ->where($data)
            ->where($data2)
            ->order($order)
            ->limit($offset,$rows)
            ->field('a.*')
            ->select();
//        echo $this->getLastSql();
        return $result;
    }
    public function getListCount($s_name)
    {
        $data = '';
        if(!empty($s_name)){
            $data = 'username like\'%'.$s_name.'%\' or mobile=\''.$s_name.'\'';
        }

        $data2['a.userid'] = ['IN',function($query){
            $query->table('authgroupaccess')->field('uid');
        }];
        $result = $this->alias('a')->where($data)->where($data2)->count();
        return $result;
    }
}