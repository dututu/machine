<?php
namespace app\hardwaredetection\controller;
use app\common\model\Toolsinfo as Toolsinfo;
use app\common\model\Machine as Machine;
use app\common\model\Shelf as ShelfModel;
use think\Controller;
use think\Loader;
use think\Config;
use think\Log;
use think\Db;
use think\Request;
use app\worker\controller\WechatMessage;
use app\lib\enum\WechatTemplate;
use Wechat\Lib\Tools;

set_time_limit(0);
ignore_user_abort(true);

Loader::import('master.GoboxApi', EXTEND_PATH, '.php');

class Testbox extends Controller   
{
    private  $obj;
    private  $machine;
    public function _initialize() {
        $this->obj = new Toolsinfo();
        $this->machine = new machine();
    }

    // 1、查询货柜软件版本信息
    public function QuerySWVersion(){
        if(!request()->isPost()){
            $data['code'] = 1004; 
            $data['msg'] = '请求失败';
            return json($data);
        }
        $devuid = input('post.devuid');
        if(is_null($devuid)){
            $data['code'] = 1005; 
            $data['msg'] = 'devuid参数错误';
            return json($data);
        }
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_uid'] = $devuid;
        $masterresult = $gboxApi->QuerySWVersion($option);
        return json($masterresult);
    }

     // 2、使能监控录像功能
    public function EnableSaveVideo(){
        if(!request()->isPost()){
            $data['code'] = 1004; 
            $data['msg'] = '请求失败';
            return json($data);
        }
        $devuid = input('post.devuid');
        if(is_null($devuid)){
            $data['code'] = 1005; 
            $data['msg'] = 'devuid参数错误';
            return json($data);
        }
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_uid'] = $devuid;
        $option['mode'] = 1;
        $masterresult = $gboxApi->EnableSaveVideo($option);
        return json($masterresult);
    }

    // 3、配置摄像头
    public function ConfigCam(){
        if(!request()->isPost()){
            $data['code'] = 1004; 
            $data['msg'] = '请求失败';
            return json($data);
        }
        $devuid = input('post.devuid');
        if(is_null($devuid)){
            $data['code'] = 1005; 
            $data['msg'] = 'devuid参数错误';
            return json($data);
        }
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_uid'] = $devuid;
        $option['stream_chn'] = 2;
        $masterresult = $gboxApi->ConfigCam($option);
        return json($masterresult);
    }

    // 4、查询摄像头配置和基本信息
    public function QueryCam(){
        if(!request()->isPost()){
            $data['code'] = 1004; 
            $data['msg'] = '请求失败';
            return json($data);
        }
        $devuid = input('post.devuid');
        if(is_null($devuid)){
            $data['code'] = 1005; 
            $data['msg'] = 'devuid参数错误';
            return json($data);
        }
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_uid'] = $devuid;
        $masterresult = $gboxApi->QueryCam($option);
        return json($masterresult);
    }

   

     /**
     * 6、查看交易视频
     */
    public function GetTransVideo(){
        if(!request()->isPost()){
            $data['code'] = 1004; 
            $data['msg'] = '请求失败';
            return json($data);
        }
        $devuid = input('post.devuid');
        if(is_null($devuid)){
            $data['code'] = 1005; 
            $data['msg'] = 'devuid参数错误';
            return json($data);
        }
        $transid = input('post.transid');
        if(is_null($transid)){
            $data['code'] = 1006; 
            $data['msg'] = 'transid参数错误';
            return json($data);
        }
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_uid'] = $devuid;
        $option['transid'] = $transid;
        $masterresult = $gboxApi->GetTransVideo($option);
        return json($masterresult);
    }


    // 查询货柜温湿度
    public function QueryHumiture(){
        if(!request()->isPost()){
            $data['code'] = 1004; 
            $data['msg'] = '请求失败';
            return json($data);
        }
        $devuid = input('post.devuid');
        if(is_null($devuid)){
            $data['code'] = 1005; 
            $data['msg'] = 'devuid参数错误';
            return json($data);
        }
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_uid'] = $devuid;
        $masterresult = $gboxApi->QueryHumiture($option);
        return json($masterresult);
    }


    // 查询设备语音播报音量
    public function QueryVolume(){
        if(!request()->isPost()){
            $data['code'] = 1004; 
            $data['msg'] = '请求失败';
            return json($data);
        }
        $devuid = input('post.devuid');
        if(is_null($devuid)){
            $data['code'] = 1005; 
            $data['msg'] = 'devuid参数错误';
            return json($data);
        }
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_uid'] = $devuid;
        $masterresult = $gboxApi->QueryVolume($option);
        return json($masterresult);
    }

    // 开门语音变更
    public function UpdateBoxVoice(){
        if(!request()->isPost()){
            $data['code'] = 1004; 
            $data['msg'] = '请求失败';
            return json($data);
        }
        $devuid = input('post.devuid');
        if(is_null($devuid)){
            $data['code'] = 1005; 
            $data['msg'] = 'devuid参数错误';
            return json($data);
        }
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_uid'] = $devuid;
        $masterresult = $gboxApi->UpdateBoxVoice($option);
        return json($masterresult);
    }


    // 设置设备语音播报音量
    public function SetVolume(){
        if(!request()->isPost()){
            $data['code'] = 1004; 
            $data['msg'] = '请求失败';
            return json($data);
        }
        $devuid = input('post.devuid');
        if(is_null($devuid)){
            $data['code'] = 1005; 
            $data['msg'] = 'devuid参数错误';
            return json($data);
        }
        $vol = input('post.vol');
        if(is_null($vol)){
            $data['code'] = 1005; 
            $data['msg'] = 'devuid参数错误';
            return json($data);
        }
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_uid'] = $devuid;
        $option['vol'] = $vol;
        $masterresult = $gboxApi->SetVolume($option);
        return json($masterresult);
    }



    public function devonline()
    {
        $devid = input('post.devid');
        if(!is_null($devid))
        {
            $gboxApi = new \GoboxApi('','');
            $option['dev_id'] = $devid;
            $masterresult = $gboxApi->queryDevice($option);
            $data = array();
            if($masterresult['code'] == 0){
                $data['phystate'] = isset($masterresult['output']['dev_list'][0]['phy_state'])?$masterresult['output']['dev_list'][0]['phy_state']:0;
                $data['boxdevuid'] = isset($masterresult['output']['dev_list'][0]['phy_state'])?$masterresult['output']['dev_list'][0]['dev_uid']:"";
                $data['boxsn'] = isset($masterresult['output']['dev_list'][0]['phy_state'])?$masterresult['output']['dev_list'][0]['box_sn']:"";
               $res =  DB::name('machine')->where('boxdevid',$devid)->update($data);
            }
            return json($masterresult);
        }
        $where['rfidtypecode'] = 3;
        $map['merchantid']  = array('exp',' is not NULL');
        $info = Db::name('machine')->field('boxdevid,boxdevuid,containerid,phystate')->where($where)->select();
        $gboxApi = new \GoboxApi('','');
        foreach ($info as $key => $value) 
        {
            $option['dev_id'] = $devid = $value['boxdevid'];
            $masterresult = $gboxApi->queryDevice($option);
            $data = array();
            if($masterresult['code'] == 0){
                $data['phystate'] = isset($masterresult['output']['dev_list'][0]['phy_state'])?$masterresult['output']['dev_list'][0]['phy_state']:0;
                $data['boxdevuid'] = isset($masterresult['output']['dev_list'][0]['phy_state'])?$masterresult['output']['dev_list'][0]['dev_uid']:"";
                $data['boxsn'] = isset($masterresult['output']['dev_list'][0]['phy_state'])?$masterresult['output']['dev_list'][0]['box_sn']:"";
               $res =  DB::name('machine')->where('boxdevid',$devid)->update($data);
            }
        }
        $result['code'] = 0;
        $result['msg'] = "success";
        return json($result);
    }


    // 绑定
    public function bind()
    {
        if(!request()->isPost()){
            $data['code'] = 1004; 
            $data['msg'] = '请求失败';
            return json($data);
        }
        $devuid = input('post.devuid');
        $containerid = input('post.containerid');
        if(is_null($devuid)){
            $data['code'] = 1005; 
            $data['msg'] = 'devuid参数错误';
            return json($data);
        }
        if(is_null($containerid)){
            $data['code'] = 1005; 
            $data['msg'] = 'containerid参数错误';
            return json($data);
        }

        // 验证信息是否存在
        $info = $this->obj->where('devuid',$devuid)->find();
        if(is_null($info)){
            $data['code'] = 1006;
            $data['msg'] = "信息不对,设备查询重新检测";
            return json($data);
        }
        
        // 设备信息是否存在库
        $devinfo = json_decode($info->baseinfo,true);
        $devid = $devinfo['output']['dev_list'][0]['dev_id'];
        $devsn = $devinfo['output']['dev_list'][0]['dev_sn'];
        $boxsn = $devinfo['output']['dev_list'][0]['box_sn'];
        $minfo = $this->machine->where('boxdevid',$devid)->find();
        if(!is_null($minfo)){
            $data['code'] = 1006;
            $data['msg'] = $devuid."设备已绑定".$minfo['containerid'];;
            return json($data);
        }
        // 机柜信息已经绑定
        $minfo = $this->machine->where('containerid',$containerid)->find();
        if(is_null($minfo))
        {
            $data['code'] = 1006;
            $data['msg'] = $containerid."设备不存在";
            return json($data);
        }
        if(isset($minfo['boxdevid']) && !is_null($minfo['boxdevid']) && $minfo['boxdevid'] != ""){
            $data['code'] = 1006;
            $data['msg'] = $containerid."已绑定".$minfo['boxdevid'];
            return json($data);
        }


        // 创建机柜货架
        $result2 = $this->getAndSaveShelfs($devid,$minfo['machineid']);
        $result1 = Db::table('machine')->where('containerid', $containerid)
                        ->update(['boxdevid' => $devid, 'boxdevuid' => $devuid,'boxsn'=>$boxsn,'businessstatus' => 3,'status' => 10]);
        $result3 = Db::name('toolsinfo')
                        ->where('devuid', $devuid)
                        ->update(['containid' => $containerid, 'bingcheck' => 1]);
        if($result2 == true && $result1 !== false )
        {
            $data['code'] = 0;
            $data['msg'] = 'success';
        } else {
            $data['code'] = 1006;
            $data['msg'] = '绑定失败';
        }
        return json($data);
    }

    public function getAndSaveShelfs($dev_id,$machineid){
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_id'] = $dev_id;
        $masterresult = $gboxApi->queryDevice($option);
        if($masterresult['code'] == 0 && isset($masterresult['output']) && isset($masterresult['output']['dev_list'])){
            model('Shelf')->where('machineid',$machineid)->delete();
            $devList = $masterresult['output']['dev_list'];
            foreach ($devList as $dev){
                $pos_list = $dev['pos_list'];
                $lock_list = $dev['lock_list'];
                if(is_array($pos_list)){
                    $size = count($pos_list);
                    for($i=0; $i<$size; $i++) {
                        $shelf = new ShelfModel;
                        $shelf['shelfid'] = uuid();
                        $shelf['machineid'] = $machineid;
                        $shelf['floor'] = ($i+1);
                        $shelf['pos'] = $pos_list[$i];
                        $userid = "ceshi";
                        $shelf['creater'] = $userid;
                        $shelf['createtime'] = Date('Y-m-d H:i:s');
                        $shelf->save();
                    }
                    Db::name('machine')
                        ->where('machineid', $machineid)
                        ->update(['floor' => $size, 'plat' => $size]);
                }
            }
            return true;
        }else{
            return false;
        }
    }

    /**
    * 开始检测 
    */
    public function startcheck(){
        if(!request()->isPost()){
            $data['code'] = 1004; 
            $data['msg'] = '请求失败';
            return json($data);
        }
        $devuid = input('post.devuid');
        if(is_null($devuid)){
            $data['code'] = 1005; 
            $data['msg'] = 'devuid参数错误';
            return json($data);
        }
        $result = json_decode($this->queryDevice($devuid),true);
        if($result['code'] == 1006){
        	$data['code'] = 1006; 
        	$data['msg'] = '联系路路,机柜不属于微连锁';
        	return json($data);
        }else if($result['code'] == 0){
        	$info = $this->obj->where('devuid',$devuid)->find();
            // var_dump($info);
            // var_dump(is_null($info));die;
	        if(is_null($info))
	        {
	        	$data['tid'] = uuid();
                $data['devuid'] = $devuid;
	        	$data['devcheck'] = 1;
	        	$data['baseinfo'] = json_encode($result);
	        	$data['createtime'] = date("Y-m-d H:i:s");
	        	$model = $this->obj->save($data);
                $result['msg'] = "success add";
                return json($result);
	        }
	        return json($result);
        }else{
        	$data['code'] = 1007;
        	$data['msg'] = $result['msg'];
        	return json($data);
        }
    }

    /**
     * 开门检测 
     */
    public function openDoor(){
        if(!request()->isPost()){
            $data['code'] = 1004; 
            $data['msg'] = '请求失败';
            return json($data);
        }
        $devuid = input('post.devuid');
        if(is_null($devuid)){
            $data['code'] = 1005; 
            $data['msg'] = 'devuid参数错误';
            return json($data);
        }
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_uid'] = $devuid;
        $option['user_type'] = 2;
        $option['lockid'] = '1-1-1-1';
        $option['transid'] = "transid".time();
        $masterresult = $gboxApi->openDoor($option);
        return json($masterresult);
    }


    /**
     * 查询门与锁状态                       
     */
    public function getDoorStatus(){
        if(!request()->isPost()){
            $data['code'] = 1004; 
            $data['msg'] = '请求失败';
            return json($data);
        }
        $devuid = input('post.devuid');
        if(is_null($devuid)){
            $data['code'] = 1005; 
            $data['msg'] = 'devuid参数错误';
            return json($data);
        }
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_uid'] = $devuid;
        $option['lockid'] = '1‐1‐1';
        $masterresult = $gboxApi->getDoorStatus($option);
        return json($masterresult);
    }



    /**
     * 查询每层重量
     */
     public function GetWeight(){
        if(!request()->isPost()){
            $data['code'] = 1004; 
            $data['msg'] = '请求失败';
            return json($data);
        }
        $devuid = input('post.devuid');
        if(is_null($devuid)){
            $data['code'] = 1005; 
            $data['msg'] = 'devuid参数错误';
            return json($data);
        }
        $gboxApi = new \GoboxApi('','');
        $option['dev_uid'] = $devuid;
        // 重量 wgtinfo
        $wgtinfo = $gboxApi->GetWeight($option);
        if($wgtinfo['code'] > 0){
            $data['code'] = $wgtinfo['code'];
            $data['msg'] = $wgtinfo['msg'];
            return json($data);
        }
        $wgt = $wgtinfo['output']['weight_list'];
        // 设备 devinfo
        $devinfo = json_decode($this->queryDevice($devuid),true);
        $pos = isset($devinfo['output']['dev_list'][0]['pos_list'])?$devinfo['output']['dev_list'][0]['pos_list']:["010101","010201","010301","010401","010501"];
        foreach ($wgt as $key => $value) {
            $nowpos = $value['pos'];
            if(!in_array($nowpos, $pos)){
                unset($wgt[$key]);
            }else{
                $nwgt[$nowpos] = $value;
            }
        }
        ksort($nwgt);
        return json($nwgt);
    }

    /**
     * 货架清零 
     */
     public function clearWeigh(){
        if(!request()->isPost()){
            $data['code'] = 1004; 
            $data['msg'] = '请求失败';
            return json($data);
        }
        $devuid = input('post.devuid');
        if(is_null($devuid)){
            $data['code'] = 1005; 
            $data['msg'] = 'devuid参数错误';
            return json($data);
        }
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_uid'] = $devuid;
        $info = $this->queryDevice($option);
        $option['pos_list'] = isset($info['pos_list'])?$info['pos_list']:["010101","010201","010301","010401","010501","010102","010202","010302","010402","010502"];
        $masterresult = $gboxApi->clearWeigh($option);
        if($masterresult['code'] ==105){
            $masterresult['code'] = 0;
        }
        return json($masterresult);
    }


    /**
     * 查询设备状态
     */
    public function queryDevice($devuid){
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_uid'] = $devuid;
        $masterresult = $gboxApi->queryDevice($option);
        return json_encode($masterresult);
    }

}