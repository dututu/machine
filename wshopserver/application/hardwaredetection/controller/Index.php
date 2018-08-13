<?php
namespace app\hardwaredetection\controller;

use think\Controller;
use think\Db;
use think\Config;
use think\Log;
use think\Loader;
use think\Request;
use app\common\model\Toolsinfo as Toolsinfo;
use app\common\model\Machine as Machine;
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
class Index extends Controller
{
    private  $obj;
    public function _initialize() {
        $this->obj = new Toolsinfo();
        $this->Machine = new Machine();
    }
    /**
     * 首页
     * @access public
     * @return tp5
     */
    public function index()
    {
        return $this->fetch();

    }
    public function index2()
    {
        // $config = Config::get('wx.wxconfig');
        // //js签名
        // // 创建SDK实例
        // $script = &\Wechat\Loader::get('Script',$config);
        // // 获取JsApi使用签名，通常这里只需要传 $url参数
        // $appid = Config::get('wx.wxappid');
        // $url = Config::get('wx.host').'hardwaredetection/index/index2';//当前页面URL地址
        // $options = $script->getJsSign($url, 0, '', $appid);
        return $this->fetch('index2',['options'=>json_encode($options)]);

    }

    /**
     * 开门检测
     * @return mixed|\think\response\Json
     * @throws \think\Exception
     */
    public function testOpen(){
        if (Request::instance()->isPost()){
            $data = [];
            $lockstatus = input('lockstatus',0);
            $doorstatus = input('doorstatus',0);
            $openstatus = input('openstatus',0);
            $doorcheck = input('doorcheck',0);
            if( $lockstatus )
                $data['lockstatus'] = $lockstatus;
            if( $doorstatus )
                $data['doorstatus'] = $doorstatus;
            if( $openstatus )
                $data['openstatus'] = $openstatus;
            if( $doorcheck )
                $data['doorcheck'] = $doorcheck;
            $devuid = input('devuid',0);
            if( !$devuid){
                return json(['code'=>1,'msg'=>'参数错误']);
            }
            $res = Db::table('toolsinfo')->where('devuid',$devuid)->update($data);
            $ret = [];
            $ret['code'] = 0;
            if($res === false)
                $ret['code'] = -1;
            return json($ret);

        }else{
            return $this->fetch();
        }
    }
    
     /**
     * 机柜检测
     */
    public function cabinetInspection(){
        if (Request::instance()->isPost()){
            $pos = input('pos',0);
            $shelfcheck = input('shelfcheck',0);
            $devuid = input('devuid',0);
            if( !$devuid)
                return json(['code'=>1,'msg'=>'参数错误']);
            $data = [];
            if( $pos){
                $str = 'pos'.$pos;
                $data[$str] = 1;
            }
            if( $shelfcheck )
                $data['shelfcheck'] = $shelfcheck;

            $res = Db::table('toolsinfo')->where('devuid',$devuid)->update($data);
            $ret = [];
            $ret['code'] = 0;
            if($res === false)
                $ret['code'] = -1;
            return json($ret);
        }else{
            return $this->fetch();
        }

    }
    public function bindcode()
    {
        # 配置参数
        $config = Config::get('wx.wxconfig');
        //js签名
        // 创建SDK实例
        $script = &\Wechat\Loader::get('Script',$config);
        // 获取JsApi使用签名，通常这里只需要传 $url参数
        $appid = Config::get('wx.wxappid');
        $url = Config::get('wx.host').'hardwaredetection/index/bindcode';
        //当前页面URL地址
        $options = $script->getJsSign($url, 0, '', $appid);
        return $this->fetch('bindcode',['options'=>json_encode($options)]);
    }
    public function getToolInfo() 
    {
        if(!request()->isPost()){
            $data['code'] = 1004; 
            $data['msg'] = '请求失败';
            return json_encode($data);exit(); 
        }
        $devuid = input('post.devuid');
        $result = $this->obj->where('devuid',$devuid)->find();
        if($result) {
            return json($result);
        }else {
            $data['code'] = 1005; 
            $data['msg'] = '还没有添加设备';
            return json_encode($data);exit(); 
        }
    }
    public function getMachineInfo()
    {
        if(!request()->isPost()){
            $data['code'] = 1004; 
            $data['msg'] = '请求失败';
            return json_encode($data);exit(); 
        }
        $machineid = input('post.machineid');
        $result = $this->Machine->where('containerid',$machineid)->find();
        if($result) {
            return json($result);
        }else {
            $data['code'] = 1005; 
            $data['msg'] = '没有查到机柜信息';
            return json_encode($data);exit(); 
        }
    }
    
    public function getRecode(){
        if (Request::instance()->isPost()){
            $devuid = input('devuid',0);
           
            if( !$devuid){
                return json(['code'=>1,'msg'=>'参数错误']);
            }
            $res = Db::table('toolsinfo')->where('devuid',$devuid)->find();
            $ret = [];
            $ret['code'] = 0;
            if($res == false)
                $ret['code'] = -1;
            $ret['data'] = $res;
            return json($ret);
        }else{
            return json(['code'=>1]);
        }
    }
    public function clear(){
        if (Request::instance()->isPost()){
            $devuid = input('devuid',0);
            if( !$devuid){
                return json(['code'=>1,'msg'=>'参数错误']);
            }
            $data['pos1']=0;
            $data['pos2']=0;
            $data['pos3']=0;
            $data['pos4']=0;
            $data['pos5']=0;
            $data['shelfcheck']=0;
            $res = Db::table('toolsinfo')->where('devuid',$devuid)->update($data);
            $ret = [];
            $ret['code'] = 0;
            if($res === false)
                $ret['code'] = -1;
            $ret['data'] = $res;
            return json($ret);
        }else{
            return json(['code'=>1]);
        }
    }

    /**
     * 网络监测
     * @return mixed
     */
    public function checkNetwork(){
        if( Request()->isPost()){
            $networkcheck = input('networkcheck',0);
            $data['networkcheck'] = $networkcheck;
            $devuid = input('devuid',0);
            if( !$devuid){
                return json(['code'=>1,'msg'=>'参数错误']);
            }
            $res = Db::table('toolsinfo')->where('devuid',$devuid)->update($data);
            $ret = [];
            $ret['code'] = 0;
            if($res === false)
                $ret['code'] = -1;
            return json($ret);
        }else{
            return $this->fetch();
        }

    }


    /**
     * 检测温湿度
     * @return mixed
     */
    public function checkHumiture(){
        if( Request()->isPost()){
            $tempcheck = input('tempcheck');
            $volcheck = input('volcheck');
            $data = [];
            if(isset($tempcheck))
                $data['tempcheck'] = $tempcheck;
            if(isset($volcheck))
                $data['volcheck'] = $volcheck;
            $devuid = input('devuid',0);
            if( !$devuid){
                return json(['code'=>1,'msg'=>'参数错误']);
            }
            $res = Db::table('toolsinfo')->where('devuid',$devuid)->update($data);
            $ret = [];
            $ret['code'] = 0;
            if($res === false)
                $ret['code'] = -1;
            return json($ret);
        }else{
            return $this->fetch();
        }
    }
    
        /**
     * 摄像头检测
     */
    public function cameratest()
    {
        return $this->fetch('cameratest');
    }
    public function videoPass()
    {
        if (Request::instance()->isPost()){
            $devuid = input('devuid',0);
            $cameracheck = input('flag',0);
            if( !$devuid){
                return json(['code'=>1,'msg'=>'参数错误']);
            }
            $data['cameracheck']=$cameracheck;
            $res = Db::table('toolsinfo')->where('devuid',$devuid)->update($data);
            $ret = [];
            $ret['code'] = 0;
            if($res == false){
                $ret['code'] = -1;
                $ret['data'] = $res;
                return json($ret);
            }else{
                return json(['code'=>1]);
            }
        }
    }
    

}