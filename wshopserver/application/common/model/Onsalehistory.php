<?php
namespace app\common\model;

use think\Model;
/**
 * 商品理货记录表
 *
 * @author      alvin
 * @version     1.0
 */
class Onsalehistory extends Model
{
    protected $autoWriteTimestamp = false;

    public function createorder($data = []) {
        if(!is_array($data)) {
            exception('传递的数据不是数组');
        }
        $data['status'] = 1;
        $data['operatetime'] = Date('Y-m-d H:i:s');

        return $this->data($data)->allowField(true)
            ->save();
    }

    //B端
    public function getOnsaleList($rows,$offset,$merchantid)
    {
        $data = [];
        $data['m.merchantid'] = $merchantid;
//        if(!empty($groupids)){
//            $data['mg.groupid'] = array('in',$groupids);
//        }
        $join = [
            ['machine m','m.machineid = o.machineid','LEFT'],
            ['sysuser s','o.operateuserid = s.userid','LEFT']
        ];
        $result = $this
            ->alias('o')
            ->order('o.operatetime desc')
            ->join($join)
            ->where($data)
            ->field('o.*,m.dailaddress,m.location,m.machinename,s.username')
            ->limit($offset,$rows)
            ->select();
//        echo $this->getLastSql();
        return $result;
    }
    public function getOnsaleListCount($merchantid)
    {
        $data = [];
        $data['m.merchantid'] = $merchantid;
        $join = [
            ['machine m','m.machineid = o.machineid','LEFT']
        ];
        $result = $this
            ->alias('o')
            ->join($join)->where($data)->count();
        return $result;
    }
}