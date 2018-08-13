<?php
namespace app\common\model;

use think\Model;
use think\Log;
/**
 * 商品表
 *
 * @author      alvin
 * @version     1.0
 */
class Goods extends Model
{
    protected $autoWriteTimestamp = false;
    //admin
    public function getList($rows,$sidx,$sord,$offset,$s_name,$s_select,$status)
    {
        $data = [];
        $data2 = [];
        if(!empty($s_name)){
//            $data['a.goodsname'] = $s_name;
            $data2 = 'a.goodsname like\'%'.$s_name.'%\'';
        }
        if(!empty($s_select)){
            $data['a.merchantid'] = $s_select;
        }
        if($status!=''){
            $data['a.status'] = $status;
        }
        $order = [];
        if(!empty($sidx)){
            $order[$sidx] = $sord;
        }else{
            $order['a.createtime'] = 'desc';
        }
        $join = [
            ['goodscategory g','a.goodscategoryid=g.categoryid','LEFT'],
            ['merchant m','a.merchantid=m.merchantid','LEFT'],
            ['rfidspec r','a.rfidtypeid=r.specid','LEFT']
        ];
        $result = $this->where($data)->where($data2)
            ->order($order)
            ->limit($offset,$rows)
            ->alias('a')
            ->join($join)
            ->field('a.*,g.categoryname,m.merchantname,m.mobile as merchantmobile,r.typename')
            ->select();
//        echo $this->getLastSql();
        return $result;
    }
    public function getListCount($s_name,$s_select,$status)
    {
        $data = [];
        $data2 = [];
        if(!empty($s_name)){
            $data2 = 'goodsname like\'%'.$s_name.'%\'';
        }
        if(!empty($s_select)){
            $data['merchantid'] = $s_select;
        }
        if($status!=''){
            $data['status'] = $status;
        }
        $result = $this->where($data)->where($data2)->count();
        return $result;
    }
    public function getCountByStatus($status)
    {
        $data = [];
        $data['status'] = $status;
        $result = $this->where($data)->count();
        return $result;
    }
    //wechatservice
    public function getGoodsList($rows,$offset,$merchantid,$categoryids)
    {

        $data = [];
        $data['a.merchantid'] = $merchantid;
        $data2 = [];
        if(!empty($categoryids)){
            $categoryids = rtrim($categoryids, ',');
            $categoryids = explode(',',$categoryids);
            $data2['goodscategoryid']=array('in',implode(',',$categoryids));
//            $data2 = 'goodscategoryid in('.$categoryids.'x) ';
        }
        $join = [
            ['goodscategory g','a.goodscategoryid=g.categoryid'],
            ['merchant m','a.merchantid=m.merchantid'],
            ['rfidspec r','a.rfidtypeid=r.specid','LEFT']
        ];
        $order['stucode'] = 'asc';
        $result = $this
            ->alias('a')
            ->join($join)
            ->where($data)
            ->where($data2)
            ->order($order)
            ->limit($offset,$rows)
            ->field('a.*,g.categoryname,m.merchantname,m.mobile as merchantmobile,r.typename,r.unitfee,r.minsubcount')
            ->select();
//        echo $this->getLastSql();
        return $result;
    }
    public function getGoodsListCount($merchantid,$categoryids)
    {
        $data = [];
        $data['merchantid'] = $merchantid;
        $data2 = [];
        if(!empty($categoryids)){
            $categoryids = rtrim($categoryids, ',');
            $categoryids = explode(',',$categoryids);
            $data2['goodscategoryid']=array('in',implode(',',$categoryids));
//            $data2 = 'goodscategoryid in('.$categoryids.'x) ';
        }
        $result = $this->where($data)->where($data2)->count();
        return $result;
    }
}