<?php
namespace app\admin\controller;
use think\Controller;
use think\Log;
use think\Loader;
use think\Db;
use think\Session;
use app\admin\service\Common;
use think\Request;
use app\common\model\Operatelog as LogModel;

Loader::import('qrcode.phpqrcode', EXTEND_PATH, '.php');
/**
 * 二维码登录
 *
 * @author      Caesar
 * @version     1.0
 */
class Login extends  Controller
{

    /**
     *登录入口
     */
	public function index()
    {
        $sysuser = session('sysuser', '', 'admin');
        //if($sysuser){
            return $this->redirect(url('/admin/index'));
        //}else{
            //生成二维码
        //     $commonservice = new Common();
        //     $qrdata = $commonservice->generateParamQRCode(200);
        //     $verifydata['verifyid'] = uuid();
        //     $verifydata['status'] = 0;
        //     $verifydata['ticket'] = $qrdata['ticket'];
        //     Db::name('wechataccountverify')->insert($verifydata);
        //     return $this->fetch('index',[
        //         'picurl'=>$qrdata['imageString'],
        //         'ticket'=>$qrdata['ticket']
        //     ]);
        // }
    }


    /**
     *退出登录
     */
    public function logout() {
        // 清除session

        $user = session('sysuser', '', 'admin');
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
        $log['typeid'] = '7';
        $log['operateresult'] = 0;
        $log['loginip'] = $request->ip();
        $log['detailinfo'] = '退出登录';
        $log->save();
        Session::delete('menus');
        session(null, 'admin');
        $this->redirect('login/index');
    }
    /**
     * deprecated
     */
    public function nouser() {
        // 清除session
        session(null, 'admin');
        return $this->fetch('nouser',[

        ]);
    }
    /**
     *没有分配权限
     */
    public function nogroup() {
        $this->assign(array(
            'menus'=>[]
        ));
        return $this->fetch('nogroup',[

        ]);
    }
    /**
      *ajax查询扫码登录状态
     */
    public function querystatus($ticket)
    {
        $accountverify = Db::name('wechataccountverify')->where(['ticket' => $ticket])->find();
        if($accountverify){
            if($accountverify['status'] == 1){
                $sysuser = Db::name('sysuser')->where(['openid' => $accountverify['openid']])->find();
                session('sysuser', $sysuser, 'admin');
                Db::table('wechataccountverify')->where('verifyid',$accountverify['verifyid'])->delete();
                $this -> result($_SERVER['HTTP_REFERER'],1,'登录成功,正在跳转...');
            }else if($accountverify['status'] == 2){
                $this -> result($_SERVER['HTTP_REFERER'],2,'用户不存在,请联系管理员绑定');
            }else{
                $this -> result($_SERVER['HTTP_REFERER'],0,'success');
            }

        }
    }
}