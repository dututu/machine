<?php
namespace app\common\model;

use think\Log;
use think\Model;
/**
 * 会员表
 *
 * @author      alvin
 * @version     1.0
 */
class User extends Model
{
    protected $autoWriteTimestamp = false;
    public function add($data = []) {
        if(!is_array($data)) {
            exception('传递的数据不是数组');
        }

        $data['isnopasspay'] = 0;
        $data['fee'] = 0;
        $data['havearrears'] = 0;
        $data['isblack'] = 0;
        $data['isvip'] = 0;
        $data['status'] = 0;
        $data['isnopasspay'] = 0;
        $data['createtime'] = Date('Y-m-d H:i:s');
        $data['updatetime'] = Date('Y-m-d H:i:s');

        return $this->data($data)->allowField(true)
            ->save();
    }

    //admin
    public function getList($rows,$sidx,$sord,$offset,$s_name,$s_select)
    {
        $data = [];
        if($s_select == '0' || $s_select == '1'){
            $data['isnopasspay'] = $s_select;
        }
        $data2 = '';
        if(!empty($s_name)){
            $data2 = 'nickname like\'%'.$s_name.'%\' or mobile like\'%'.$s_name.'%\'';
        }
        $order = [];
        if(!empty($sidx)){
            $order[$sidx] = $sord;
        }
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
    public function getListCount($s_name,$s_select)
    {
        $data = [];
        if($s_select == '0' || $s_select == '1'){
            $data['isnopasspay'] = $s_select;
        }
        $data2 = '';
        if(!empty($s_name)){
            $data2 = 'nickname like\'%'.$s_name.'%\' or mobile like\'%'.$s_name.'%\'';
        }
        $result = $this->where($data)->where($data2)->count();
        return $result;
    }

}