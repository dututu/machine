<?php
/**
 * Created by PhpStorm.
 * User: caesar
 * Date: 2018/3/27
 * Time: 下午10:14
 */
namespace app\test\controller;
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
class Workman extends Controller
{
    //
    public function sendmsg(){
        // 建立socket连接到内部推送端口
        $client = stream_socket_client('tcp://127.0.0.1:5678', $errno, $errmsg, 1);
        // 推送的数据，包含uid字段，表示是给这个uid推送
        $data = array('uid'=>'1234', 'percent'=>'88%');
        // 发送数据，注意5678端口是Text协议的端口，Text协议需要在数据末尾加上换行符
        fwrite($client, json_encode($data)."\n");
        // 读取推送结果
        echo fread($client, 8192);
    }

}