<?php
namespace app\workweixin\controller;
use think\Controller;
use think\Db;
use think\Config;
use think\Log;
use think\Loader;
use think\Request;

class User extends  Base
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

}
