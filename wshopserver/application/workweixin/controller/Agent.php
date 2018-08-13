<?php
namespace app\workweixin\controller;
use think\Controller;
use think\Db;
use think\Config;
use think\Log;
use think\Loader;
use think\Request;

class Agent extends  Base
{

    /**
     * 首页
     * @access public
     * @return tp5
     */
    public function index()
    {

        return $this->fetch();

    }

    /**
     * 代理商详情
     * @return mixed
     */
    public function detail(){
        return $this->fetch();
    }

    /**
     * 商户详情
     */
    public function busDetail(){
        return $this->fetch();
    }
}
