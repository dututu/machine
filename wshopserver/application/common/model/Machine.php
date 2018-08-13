<?php
namespace app\common\model;

use think\Model;
use think\Db;
/**
 * 机柜表
 *
 * @author      alvin
 * @version     1.0
 */
class Machine extends Model
{
    protected $autoWriteTimestamp = false;
    public function pics()
    {
        return $this->hasMany('Machinepics','machineid')->field('machineid,url');
    }

    public function getNearbyList($lon,$lat,$distance)
    {
        $data = [];
        $data['businessstatus'] = 4;
        $array = $this->getAround($lat, $lon, $distance);
        $result = $this
            ->where('lat','>=',$array['minLat'])
            ->where('lat','<=',$array['maxLat'])
            ->where('lon','>=',$array['minLng'])
            ->where('lon','<=',$array['maxLng'])
            ->where($data)
            ->select();
//        echo $this->getLastSql();
        return $result;
    }
    /**
     *
     * @param  $latitude    纬度
     * @param  $longitude    经度
     * @param  $raidus        半径范围(单位：米)
     * @return multitype:number
     */
    public function getAround($latitude,$longitude,$raidus){
        $PI = 3.14159265;
        $degree = (24901*1609)/360.0;
        $dpmLat = 1/$degree;
        $radiusLat = $dpmLat*$raidus;
        $minLat = $latitude - $radiusLat;
        $maxLat = $latitude + $radiusLat;
        $mpdLng = $degree*cos($latitude * ($PI/180));
        $dpmLng = 1 / $mpdLng;
        $radiusLng = $dpmLng*$raidus;
        $minLng = $longitude - $radiusLng;
        $maxLng = $longitude + $radiusLng;
        $data = [];
        $data['minLat'] = $minLat;
        $data['maxLat'] = $maxLat;
        $data['minLng'] = $minLng;
        $data['maxLng'] = $maxLng;
        return $data;
    }

    //admin
    public function getList($rows,$sidx,$sord,$offset,$s_name,$s_select,$s_time,$businessstatus)
    {
        $data = [];
        $data3 = [];
        if(!empty($s_name)){
//            $data['m.merchantname'] = ['like','%'.$s_name.'%'];
//            $data3 = 'm.merchantname like \'%'.$s_name.'%\' or a.containerid=\''.$s_name.'\'';
            $data3 = 'm.merchantname like \'%'.$s_name.'%\' or a.containerid like \'%'.$s_name.'%\'';
        }
        if(!empty($s_select)){
            $data['a.status'] = $s_select;
        }
        if($businessstatus!=''){
            if(count($businessstatus)>1){
                $data['a.businessstatus'] = array(['=',$businessstatus['0']],['=',$businessstatus['1']],'or');
            }else if(count($businessstatus)>0){
                $data['a.businessstatus'] = $businessstatus['0'];
            }
        }
        $data2 = '';
        if(!empty($s_time)){
            $timerange = explode('~',$s_time);
            $data2 = 'a.outtime >= \''.$timerange[0].' 00:00:00\' and a.outtime<=\''.$timerange[1].' 23:59:59\'';
        }
        $order = [];
        if(!empty($sidx)){
            $order[$sidx] = $sord;
        }else{
            $order['createtime'] = 'desc';
        }
        $order2 = ' field(a.businessstatus,4) desc,field(a.phystate,0,1,2) asc';
        $join = [
            ['machinetype t','a.machinetypeid=t.typeid','LEFT'],
            ['merchant m','a.merchantid=m.merchantid','LEFT']
        ];
        $result = $this
            ->alias('a')
            ->join($join)->where($data)->where($data2)->where($data3)
//            ->order($order)
            ->order($order2)
            ->limit($offset,$rows)
            ->field('a.*,t.typename,m.merchantname')
            ->select();
//        echo $this->getLastSql();
        return $result;
    }
    public function getListCount($s_name,$s_select,$s_time,$businessstatus)
    {
        $data = [];
        $data3 = [];
        if(!empty($s_name)){
//            $data['m.merchantname'] = ['like','%'.$s_name.'%'];
//            $data3 = 'm.merchantname like \'%'.$s_name.'%\' or a.containerid=\''.$s_name.'\'';
            $data3 = 'm.merchantname like \'%'.$s_name.'%\' or a.containerid like \'%'.$s_name.'%\'';
        }
        if(!empty($s_select)){
            $data['a.status'] = $s_select;
        }
        if($businessstatus!=''){
            if(count($businessstatus)>1){
                $data['a.businessstatus'] = array(['=',$businessstatus['0']],['=',$businessstatus['1']],'or');
            }else if(count($businessstatus)>0){
                $data['a.businessstatus'] = $businessstatus['0'];
            }
        }
        $data2 = '';
        if(!empty($s_time)){
            $timerange = explode('~',$s_time);
            $data2 = 'a.outtime >= \''.$timerange[0].' 00:00:00\' and a.outtime<=\''.$timerange[1].' 23:59:59\'';
        }
        $join = [
            ['machinetype t','a.machinetypeid=t.typeid','LEFT'],
            ['merchant m','a.merchantid=m.merchantid','LEFT']
        ];
        $result = $this
            ->alias('a')
            ->join($join)->where($data)->where($data2)->where($data3)->count();
        return $result;
    }


    //B端
    public function getMachineList($rows,$offset,$merchantid,$typeids,$rfidtypecodes='1,2,3')
    {
        $data = [];
        if(!empty($merchantid)){
            $data['m.merchantid'] = $merchantid;
        }
            $rfidtypecodes = rtrim($rfidtypecodes, ',');
            $rfidtypecodes = explode(',',$rfidtypecodes);
            $data['rfidtypecode']=array('in',implode(',',$rfidtypecodes));
        if(!empty($typeids)){
            $data2 = [];
            $typeids = rtrim($typeids, ',');
            $typeids = explode(',',$typeids);
            $data2['typeid']=array('in',implode(',',$typeids));
//            $data2 = 'goodscategoryid in('.$categoryids.'x) ';
            //构造字查询
            $subQuery = Db::table('machinegroups')
                ->field('machineid')
                ->where($data2)
                ->select();
            $inids = [];
            foreach ($subQuery as $sub){
                array_push($inids,$sub['machineid']);
            }
            $data['m.machineid'] = array('in',implode(',',$inids));
            //
        }

        $join = [
            ['sysuser s','m.merchantid = s.merchantid','LEFT']
        ];
        $sdata = ' m.businessstatus != 7';
        $result = $this
            ->alias('m') ->where($data)->where($sdata)
            ->order('m.updatetime desc')
            ->limit($offset,$rows)
            ->join($join)
            ->field('m.*')
            ->select();
//        echo $this->getLastSql();
        return $result;
    }
    public function getMachineListCount($merchantid,$typeids,$rfidtypecodes='1,2,3')
    {
        $data = [];
        if(!empty($merchantid)){
            $data['m.merchantid'] = $merchantid;
        }

        $rfidtypecodes = rtrim($rfidtypecodes, ',');
        $rfidtypecodes = explode(',',$rfidtypecodes);
        $data['rfidtypecode']=array('in',implode(',',$rfidtypecodes));
        if(!empty($typeids)){
            $data2 = [];
            $typeids = rtrim($typeids, ',');
            $typeids = explode(',',$typeids);
            $data2['typeid']=array('in',implode(',',$typeids));
//            $data2 = 'goodscategoryid in('.$categoryids.'x) ';
            //构造字查询
            $subQuery = Db::table('machinegroups')
                ->field('machineid')
                ->where($data2)
                ->select();
            $inids = [];
            foreach ($subQuery as $sub){
                array_push($inids,$sub['machineid']);
            }
            $data['m.machineid'] = array('in',implode(',',$inids));
            //
        }
        $sdata = ' m.businessstatus != 7';
        $result = $this
            ->alias('m')->where($data)->where($sdata)->count();
        return $result;
    }
}