<?php
namespace app\workweixin\controller;
use think\Controller;
use think\Db;
use think\Config;
use think\Log;
use think\Loader;
use think\Request;

class Machine extends  Base
{

    /**
     * 首页
     * @access public
     * @return tp5
     */
    public function index()
    {
        $tab = Request()->get('tab',1);
        switch($tab){
            case 1:
                $fetch = 'index';
            break;
            case 2:
                $fetch = 'agent';
                break;
            default:
                $fetch = '';
        }

        return $this->fetch($fetch,[
            'tab'=>$tab
        ]);

    }


    public function machineDetail(){
        return $this->fetch();
    }
}
