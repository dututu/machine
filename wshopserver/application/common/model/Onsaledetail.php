<?php
namespace app\common\model;

use think\Model;
/**
 * 商品理货详情表
 *
 * @author      alvin
 * @version     1.0
 */
class Onsaledetail extends Model
{
    protected $autoWriteTimestamp = false;


    public function order()
    {
        return $this->belongsTo('onsalehistory');
    }
    //B端
    public function getDetailList($onsaleid)
    {
        $data = [];
        $data['d.onsaleid'] = $onsaleid;
//        if(!empty($groupids)){
//            $data['mg.groupid'] = array('in',$groupids);
//        }
        $join = [
            ['goods g','g.goodsid = d.goodsid','LEFT']
        ];
        $result = $this
            ->alias('d')
            ->join($join)
            ->where($data)
            ->field('d.*,g.originalfee,g.picurl')
            ->select();
//        echo $this->getLastSql();
        return $result;
    }
}