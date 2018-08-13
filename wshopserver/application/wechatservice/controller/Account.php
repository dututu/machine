<?php
namespace app\wechatservice\controller;
use think\Controller;
use think\Db;
use think\Config;
use think\Log;
use think\Loader;
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
/**
 * 我的账户
 *
 * @author      Caesar
 * @version     1.0
 */
class Account extends  Base //Base
{
    protected $beforeActionList = [
        'checkSession'
    ];
    /**
     * 首页
     * @access public
     * @return tp5
     */
    public function index()
    {
        $openid = session('openid', '', 'wechatservice');
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $merchantid = $sysuser['merchantid'];
//            $merchantid = '1';
        $subsql1 = 'select machineid from machine where merchantid=?';
        $sql = 'SELECT sum(a.totalfee) t FROM goodsorder a where  a.machineid in('.$subsql1.')';
        $value = Db::query($sql, [$merchantid]);
        $totalfee = (int)reset($value)['t'];
        return $this->fetch('account',[
            'sysuser'=>$sysuser,
            'totalfee'=>$totalfee/100
        ]);


    }

    public function checkaccount()
    {
        return $this->fetch('checkaccount',[
//            'categorys'=>$categorys,
        ]);
    }

}
