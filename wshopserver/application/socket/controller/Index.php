<?php
/**
 * Created by PhpStorm.
 * User: caesar
 * Date: 2018/3/25
 * Time: 下午3:27
 */

namespace app\socket\controller;

use think\Controller;
use think\Log;
use Workerman\Worker;

class Index extends Controller
{
    public function index()
    {
        // 证书最好是申请的证书
        $context = array(
            'ssl' => array(
                // 使用绝对路径
                'local_cert' => '/webserver/nginx/Nginx/1_t.wemall.com.cn_bundle.crt', // 也可以是crt文件
                'local_pk' => '/webserver/nginx/Nginx/2_t.wemall.com.cn.key',
                'verify_peer' => false,
            )
        );
        // 初始化一个worker容器，监听4431端口
        $worker = new Worker("websocket://0.0.0.0:4431", $context);//
        $worker->transport = 'ssl';
        // 这里进程数必须设置为1
        $worker->count = 1;
        // worker进程启动后建立一个内部通讯端口
        $worker->onWorkerStart = function ($worker) {
            // 开启一个内部端口，方便内部系统推送数据，Text协议格式 文本+换行符
            $inner_text_worker = new Worker('Text://0.0.0.0:5678');
            $inner_text_worker->reusePort = true;
            $inner_text_worker->onMessage = function ($connection, $buffer) {
//                global $worker;
                // $data数组格式，里面有uid，表示向那个uid的页面推送数据
                $data = json_decode($buffer, true);
                $uid = $data['orderno'];
                echo '内部系统推送数据';
                // 通过workerman，向uid的页面推送数据
                $ret = sendMessageByUid($uid, $data['data']);
                // 返回推送结果
                $connection->send($ret ? 'ok' : 'fail');
            };
            $inner_text_worker->listen();
        };
        // 新增加一个属性，用来保存uid到connection的映射
        $worker->uidConnections = array();
        // 当有客户端发来消息时执行的回调函数
        $worker->onMessage = function ($connection, $data) use ($worker) {
            // 判断当前客户端是否已经验证,既是否设置了uid
            if (!isset($connection->uid)) {
                global $worker;
                // 没验证的话把第一个包当做uid(orderno)
                $receivedata = json_decode($data, true);//当该参数为 TRUE 时，将返回 array 而非 object
                $connection->uid = $receivedata['orderno'];
                /* 保存uid到connection的映射，这样可以方便的通过uid查找connection，
                 * 实现针对特定uid推送数据
                 */
                $worker->uidConnections[$connection->uid] = $connection;
                return;
            }
            echo '-----';
            $response['op'] = "cmd";
            $ret = sendMessageByUid($connection->uid, $response);
        };

        // 当有客户端连接断开时
        $worker->onClose = function ($connection) use ($worker) {
            global $worker;
            echo '断开连接';
            if (isset($connection->uid)) {
                echo $connection->uid;
                // 连接断开时删除映射
                unset($worker->uidConnections[$connection->uid]);
            }
        };

        // 向所有验证的用户推送数据
        function broadcast($message)
        {
            global $worker;
            foreach ($worker->uidConnections as $connection) {
                $connection->send($message);
            }
        }

        // 针对uid推送数据
        function sendMessageByUid($uid, $message)
        {
            global $worker;
            if (isset($worker->uidConnections[$uid])) {
                $connection = $worker->uidConnections[$uid];
                $connection->send($message);
                return true;
            }
            return false;
        }

        // 运行所有的worker（其实当前只定义了一个）
        Worker::runAll();
    }
}