<?php

namespace app\admin\controller;

use think\Controller;
use think\Log;
use think\Request;
use app\common\model\Operatelog as LogModel;

/**
 * 基类base
 *
 * 用户判定用户是否登录,子类需要继承,开发时为了方便可不继承
 * @author      Caesar
 * @version     1.0
 */
class Base extends Controller
{
    public $account;

    public function _initialize()
    {

        // 判定用户是否登录
        $isLogin = $this->isLogin();
        if (!$isLogin) {
            //return $this->redirect(url('login/index'));
        } else {

        }
    }

    //判定是否登录
    public function isLogin()
    {
        // 获取sesssion
        $user = $this->getLoginUser();
        if ($user && $user['userid']) {
            return true;
        }
        return false;
    }

    public function getLoginUser()
    {
        if (!$this->account) {
            $this->account = session('sysuser', '', 'admin');
        }
        return $this->account;
    }

    /**
     * 记录操作日志
     * typeid 操作类型 1增加 2修改 3删改 4查询 5登录 6注销 7退出
     * operateresult 操作结果 0成功 1失败 2未知
     * detailinfo 详情
     */
    public function recordlog($typeid, $operateresult, $detailinfo)
    {
        $user = $this->getLoginUser();
        $operaterid = '';
        $operatername = '';
        if ($user) {
            $operaterid = $user['userid'];
            $operatername = $user['username'];
        }
        $request = Request::instance();
        $log = new LogModel;
        $log['opid'] = uuid();
        $log['operaterid'] = $operaterid;
        $log['operatername'] = $operatername;
        $log['createtime'] = Date('Y-m-d H:i:s');
        $log['typeid'] = $typeid;
        $log['operateresult'] = $operateresult;
        $log['loginip'] = $request->ip();
        $log['detailinfo'] = $detailinfo;
        $log->save();
    }
}
