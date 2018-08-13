<?php
/**
 * Created by PhpStorm.
 * Power By Mikkle
 * Date: 2017/8/7
 * Time: 9:28
 */

namespace app\worker\controller;


use app\base\controller\Redis;
use think\Config;
use think\Controller;
/**
 * Worker队列基类
 */
abstract class Base
{
    protected $redis;
    protected $workList;
    protected $workerName;
    public static $instance;

    /**
     * Base constructor.
     * @param array $options
     */
    public function __construct($options=[])
    {
        $this->redis = $this->redis();
        $this->workList = "worker_list_wshop";
        $this->workerName = get_called_class();
    }


    /**
     * redis加载自定义Redis类
     * Power: Mikkle
     * @param array $options
     * @return Redis
     */
    protected static function redis($options=[]){
        $options = empty($options) ? $redis = Config::get("command.redis") : $options;
        return Redis::instance($options);
    }


    /**
     * 标注命令行执行此任务
     * Power: Mikkle
     */
    public function runWorker(){
        $this->redis->hset($this->workList,$this->workerName,$this->workerName);
    }

    /**
     * 标注命令行清除此任务
     * Power: Mikkle
     */
    public function clearWorker(){
        $this->redis->hdel($this->workList,$this->workerName);
    }


    /**
     * Power: Mikkle
     * @param array $options
     * @return static
     */
    static public function instance($options=[]){
        if (isset(self::$instance)){
            return self::$instance;
        }else{
            return new static($options);
        }
    }


}