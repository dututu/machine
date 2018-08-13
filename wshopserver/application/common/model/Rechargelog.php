<?php
namespace app\common\model;

use think\Model;
/**
 * 储值记录表
 *
 * @author      alvin
 * @version     1.0
 */
class Rechargelog extends Model
{
    protected $autoWriteTimestamp = false;

    public function getList($rows,$offset,$userid)
    {
        $data = [];
        $data['userid'] = $userid;
        $order['createtime'] = 'desc';
        $result = $this->where($data)->order($order)
            ->limit($offset,$rows)
            ->select();
        $total = $this->where($data)
            ->count();

        return [
            'result' => $result,
            'total' => $total
        ];
    }

    //admin
    public function getLogList($rows,$sidx,$sord,$offset,$userid)
    {
        $data = [];
        $data['a.userid'] = $userid;
        $order = [];
        $order['createtime'] = 'desc';
        if(!empty($sidx)){
            $order[$sidx] = $sord;
        }
//        $join = [
//            ['goodscategory g','a.goodscategoryid=g.categoryid'],
//            ['merchant m','a.merchantid=m.merchantid'],
//            ['rfidspec r','a.rfidtypeid=r.specid']
//        ];
        $result = $this->where($data)
            ->order($order)
            ->limit($offset,$rows)
            ->alias('a')
//            ->join($join)
            ->field('a.*')
            ->select();
//        echo $this->getLastSql();
        return $result;
    }
    public function getListCount($userid)
    {
        $data = [];
        $data['userid'] = $userid;
        $result = $this->where($data)->count();
        return $result;
    }
}