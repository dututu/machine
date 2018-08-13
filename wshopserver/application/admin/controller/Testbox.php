<?php
namespace app\admin\controller;
use think\Controller;
use think\Loader;
use think\Config;
use think\Log;
use think\Db;
use think\Request;
use app\worker\controller\WechatMessage;
use app\lib\enum\WechatTemplate;
use Wechat\Lib\Tools;

Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
Loader::import('sms.simple-sms', EXTEND_PATH, '.php');
Loader::import('master.RfidApiV2', EXTEND_PATH, '.php');
Loader::import('master.MasterApi', EXTEND_PATH, '.php');
Loader::import('master.GoboxApi', EXTEND_PATH, '.php');
/**
 * 测试类，后期可删除
 *
 * @author      lhw
 * @version     1.0
 */
class Testbox extends  Controller //Base
{
    private  $obj;
    protected $wechat;
    public function _initialize() {
        $this->obj = model("Goodsorder");
    }
    public function gen(){
        $gboxApi = new \GoboxApi('','');
        $masterresult = $gboxApi->accesstoken('');
        dump($masterresult) ;
    }
    public function addsku(){
        $gboxApi = new \GoboxApi('','');
        $masterresult = $gboxApi->addSku('');
        echo $masterresult;
    }
    public function delsku(){
        $gboxApi = new \GoboxApi('','');
        $masterresult = $gboxApi->delSku('');
        echo $masterresult;
    }
    public function updatesku(){
    $gboxApi = new \GoboxApi('','');
    $masterresult = $gboxApi->updateSku('');
    echo $masterresult;
}
    public function querysku(){
        $gboxApi = new \GoboxApi('','');
        $masterresult = $gboxApi->querySku('');
        dump($masterresult) ;
    }
    public function updatestock(){
        $gboxApi = new \GoboxApi('','');
        $masterresult = $gboxApi->updateStock('');
        echo $masterresult;
    }
    public function querystock(){
        $gboxApi = new \GoboxApi('','');
        $masterresult = $gboxApi->queryStock('');
        echo $masterresult;
    }
    public function queryDevice(){
        $gboxApi = new \GoboxApi('','');
        $masterresult = $gboxApi->queryDevice('');
        echo $masterresult;
    }
    public function addSkuStock(){
        $gboxApi = new \GoboxApi('','');
        $masterresult = $gboxApi->addSkuStock('');
        echo $masterresult;
    }
    public function fullSyncSku($devid){
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_id'] = $devid;
        $option['sku_list'] = [];
        $masterresult = $gboxApi->fullSyncSku($option);
        dump($masterresult);
    }
    public function delSkuStock(){
        $gboxApi = new \GoboxApi('','');
        $masterresult = $gboxApi->delSkuStock('');
        echo $masterresult;
    }
    public function querySkuStock(){
        $gboxApi = new \GoboxApi('','');
        $masterresult = $gboxApi->querySkuStock('');
        echo $masterresult;
    }
    public function querySkuBox(){
        $gboxApi = new \GoboxApi('','');
        $masterresult = $gboxApi->querySkuBox('');
        dump($masterresult) ;
    }
    public function regEvtCB(){
        $gboxApi = new \GoboxApi('','');
        $masterresult = $gboxApi->regEvtCB('');
        dump($masterresult) ;
    }
    public function GetTransEvt(){
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_id'] = '1038';
        $option['transid'] = '111';

        $code = 404;//timeout
        for ($x=0; $x<5; $x++) {
            $masterresult = $gboxApi->GetTransEvt($option);
            if(isset($masterresult['code'])){
                $code = $masterresult['code'];
                if($masterresult['code'] == 0){
                    break;
                }else if($masterresult['code'] == 3){
                    //机柜离线
                    dump('机柜离线') ;
                }else if($masterresult['code'] == 203){
                    //交易不存在
                    dump('交易不存在') ;
                }else{
                    break;
                }
            }
        }



    }
    public function sign(){
        $option = [];
        $option['command'] = 'GetToken';
        $option['time'] = 1445307713;
        $option['user'] = 'admin';
        ksort($option);
        $buff = json_encode($option);
        $partnerKey = '123456abcdef';
        $sign = md5("{$buff}{$partnerKey}");
        echo $sign;
    }



}
