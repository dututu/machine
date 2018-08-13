<?php
namespace app\common\model;

use think\Model;
// 商品标签库
class Taglib extends Model
{
    protected $autoWriteTimestamp = false;

    //可用标签总数
    public function getCanUseCountByMerchantId($merchantid)
    {
        $data = [];
        if(!empty($merchantid)){
            $data['a.merchantid'] = $merchantid;
        }
        $result = $this
            ->alias('a')
            ->where($data)->count();
        return $result;
    }
    //上货准备-我的标签列表
    public function rfidlist($rows,$offset,$merchantid)
    {
        $data = [];
        if(!empty($merchantid)){
            $data['tl.merchantid'] = $merchantid;
        }
        $order = [];
        $join = [
            ['goods g','tl.barcode=g.goodsid'],
            ['rfidspec spec','g.rfidtypeid=spec.specid']
        ];
        $result = $this
            ->alias('tl')
            ->join($join)->where($data)
            ->order($order)
            ->limit($offset,$rows)
            ->field('count(tl.barcode) as rfidcount, tl.merchantid ,spec.typename,g.goodsname,g.picurl,g.originalfee,g.salefee,g.picurl,g.spec,g.goodsid')
            ->group("tl.barcode")
            ->select();
        return $result;
    }
    public function rfidlistcount($merchantid)
    {
        $data = [];
        if(!empty($merchantid)){
            $data['tl.merchantid'] = $merchantid;
        }
        $order = [];
        $join = [
            ['goods g','tl.barcode=g.goodsid'],
            ['rfidspec spec','g.rfidtypeid=spec.specid']
        ];
        $result = $this
            ->alias('tl')
            ->join($join)->where($data)
            ->order($order)
            ->field('count(tl.barcode) as rfidcount, tl.merchantid ,spec.typename,g.goodsname,g.picurl,g.originalfee,g.salefee,g.picurl,g.spec,g.goodsid')
            ->group("tl.barcode")
            ->count();
        return $result;
    }
    //admin
    public function getList($rows,$sidx,$sord,$offset,$s_name,$s_select)
    {
        $data = [];
        $data3 = [];
        if(!empty($s_name)){
            $data3['a.epc'] = $s_name;
        }
        if($s_select == '0'){
            $data['a.status'] = '0';
        }else if($s_select == '1'){
            $data['a.status'] = '1';
        }else if($s_select == '2'){
            $data['a.status'] = '2';
        }

        $order = [];
        if(!empty($sidx)){
            $order[$sidx] = $sord;
        }else{
            $order['a.updatetime'] = 'desc';
        }

        $join = [
            ['goods g','a.barcode=g.goodsid'],
            ['merchant m','a.merchantid=m.merchantid']
        ];
        $result = $this
            ->alias('a')
            ->join($join)->where($data)->where($data3)
            ->order($order)
            ->limit($offset,$rows)
            ->field('a.*,g.goodsname,m.merchantname')
            ->select();
        return $result;
    }
    public function getListCount($s_name,$s_select)
    {
        $data = [];
        $data3 = [];
        if(!empty($s_name)){
            $data3['a.epc'] = $s_name;
        }
        if($s_select == '0'){
            $data['a.status'] = '0';
        }else if($s_select == '1'){
            $data['a.status'] = '1';
        }else if($s_select == '2'){
            $data['a.status'] = '2';
        }
        $join = [
            ['goods g','a.barcode=g.goodsid'],
            ['merchant m','a.merchantid=m.merchantid']
        ];
        $result = $this
            ->alias('a')
            ->join($join)->where($data)->where($data3)->count();
        return $result;
    }


}