<?php
namespace app\common\model;

use think\Model;
use think\Db;
/**
 * 商户表
 *
 * @author      alvin
 * @version     1.0
 */
class Merchant extends Model
{
    protected $autoWriteTimestamp = false;

    //admin
    public function getList($rows,$sidx,$sord,$offset,$s_name)
    {

        $data = '';
        if(!empty($s_name)){
            $data = 'a.merchantname like \'%'.$s_name.'%\' or a.mobile=\''.$s_name.'\'';
        }
        $order = [];
        if(!empty($sidx)){
            $order[$sidx] = $sord;
        }else{
            $order['a.createtime'] = 'desc';
        }
        //很久之前字查询例子
//        $join1 = [
//            ['sysuser su','ag.userid = su.userid']
//        ];
//        $subsql = Db::table('agency')
//            ->alias('ag')
//            ->join($join1)
//            ->field('su.nickname,su.userid,ag.agencyid')
//            ->buildSql();
//        $result = $this->where($data)
//            ->order($order)
//            ->limit($offset,$rows)
//            ->alias('a')
//            ->join([$subsql=> 'w'], 'a.agencyid = w.agencyid')
//            ->field('a.*,w.*')
//            ->select();
        //直接用merchant和sysuser两张表查询即可
        $join1 = [
            ['sysuser su','a.merchantid = su.merchantid','LEFT']
        ];
        $result = $this->where($data)
            ->alias('a')
            ->join($join1)
            ->field('a.*,su.username,su.nickname')
            ->order($order)
            ->limit($offset,$rows)
            ->select();
        return $result;
    }
    public function getListCount($s_name)
    {
        $data = '';
        if(!empty($s_name)){
            $data = 'merchantname like\'%'.$s_name.'%\' or mobile=\''.$s_name.'\'';
        }
        $result = $this->where($data)->count();
        return $result;
    }
    public function getSelectList($rows,$sidx,$sord,$offset,$s_name)
    {

        $data = '';
        if(!empty($s_name)){
            $data = 'merchantname like\'%'.$s_name.'%\'';
        }
        $order = [];
        if(!empty($sidx)){
            $order[$sidx] = $sord;
        }
        //很久之前字查询例子
//        $join1 = [
//            ['sysuser su','ag.userid = su.userid']
//        ];
//        $subsql = Db::table('agency')
//            ->alias('ag')
//            ->join($join1)
//            ->field('su.nickname,su.userid,ag.agencyid')
//            ->buildSql();
//        $result = $this->where($data)
//            ->order($order)
//            ->limit($offset,$rows)
//            ->alias('a')
//            ->join([$subsql=> 'w'], 'a.agencyid = w.agencyid')
//            ->field('a.*,w.*')
//            ->select();
        //直接用merchant和sysuser两张表查询即可
        $join1 = [
            ['sysuser su','a.merchantid = su.merchantid','LEFT']
        ];
        $result = $this->where($data)
            ->alias('a')
            ->join($join1)
            ->field('a.*,su.username,su.nickname')
            ->order($order)
            ->limit($offset,$rows)
            ->select();
        return $result;
    }
    public function getSelectListCount($s_name)
    {
        $data = '';
        if(!empty($s_name)){
            $data = 'merchantname like\'%'.$s_name.'%\'';
        }
        $result = $this->where($data)->count();
        return $result;
    }
    public function getMachineList($merchantid)
    {
        $newArr = [];
        $machineList = Machine::where('merchantid',$merchantid)->select();
        if(is_null($machineList)) {
            return array();
        } else {
            foreach ($machineList as $k => $v) {
                $newArr[] = $v['machineid'];
            }
            return $newArr;
        }
    }
}