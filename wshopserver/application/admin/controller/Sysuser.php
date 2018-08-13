<?php
namespace app\admin\controller;
use think\Db;
use think\Request;
use think\Controller;
/**
 * 后台首页控制器
 */
class Sysuser extends Adminbase{
    protected $beforeActionList = [
        'checkAuth'
    ];
	/**
	 * 用户列表
	 */
	public function index(){
		$data=Db::name('authgroupaccess')
            ->alias('aga')
            ->field('u.userid,u.nickname,u.mobile,aga.groupid,ag.authname')
            ->join('sysuser u' , 'aga.uid=u.userid','RIGHT')
            ->join('authgroup ag' , 'aga.groupid=ag.groupid','LEFT')
            ->select();
        $first=$data[0];
        $first['authname']=array();
        $user_data[$first['userid']]=$first;
        // 组合数组
        foreach ($data as $k => $v) {
            foreach ($user_data as $m => $n) {
                $uids=array_map(function($a){return $a['userid'];}, $user_data);
                if (!in_array($v['userid'], $uids)) {
                    $v['authname']=array();
                    $user_data[$v['userid']]=$v;
                }
            }
        }
        // 组合管理员title数组
        foreach ($user_data as $k => $v) {
            foreach ($data as $m => $n) {
                if ($n['userid']==$k) {
                    $user_data[$k]['authname'][]=$n['authname'];
                }
            }
            $user_data[$k]['authname']=implode('、', $user_data[$k]['authname']);
        }
            
        $assign=array(
            'data'=>$user_data
            );
        $this->assign($assign);
        return $this->fetch();
	}

    /**
     * 添加管理员
     */
    public function add_user(){
        if(Request::instance()->post()){
            $data=input('post.');
            // dump($data);
            $userdata=[
                'username'=>$data['username'],
//                'phone'=>$data['phone'],
//                'password'=>$data['password'],
//                'email'=>$data['email'],
//                'status'=>$data['status'],
            ];
            $result=Db::name('sysuser')->insert($userdata);
            $datagroup=Db::name('sysuser')->where(['username'=>$data['username']])->find();
            if($result){
                if (!empty($data['group_ids'])) {
                    foreach ($data['group_ids'] as $k => $v) {
                        $group=array(
                            'agid'=>uuid(),
                            'uid'=>$datagroup['id'],
                            'groupid'=>$v
                            );
                        Db::name('authgroupaccess')->insert($group);
                    }                   
                }
                // 操作成功
                $this->success('添加成功','Admin/User/index');
            }else{
                $this->error('修改失败');
            }
        }else{
            $data=Db::name('authgroup')->select();
            $assign=array(
                'data'=>$data
                );
            $this->assign($assign);
            return $this->fetch();
        }
    }


    /**
     * 修改管理员
     */
    public function edituser($userid){
        if(Request::instance()->post()){
            $data=input('post.');
            // dump($data);
            Db::name('authgroupaccess')->where(array('uid'=>$userid))->delete();
            if (!empty($data['group_ids'])) {
                foreach ($data['group_ids'] as $k => $v) {
                    $group=[
                        'agid'=>uuid(),
                        'uid'=>$userid,
                        'groupid'=>$v
                       ];
                    Db::name('authgroupaccess')->insert($group);
                }
            }
            // $data=array_filter($data);
            // 如果修改密码则md5
            // p($data);die;
            // if ($data['password']!=null) {
//                $userup['password']=md5($data['password']);
            // }
            $userup=[
//                'password'=>md5($data['password']),
                'username'=>$data['username'],
//                'phone'=>$data['phone'],
//                'email'=>$data['email'],
//                'status'=>$data['status'],
            ];

            $results=Db::name('sysuser')->where(['userid'=>$userid])->find();
            $result=Db::name('sysuser')->where(['userid'=>$userid])->update($userup);
            if($results){
                // 操作成功
                $this->recordlog('2',0,'修改角色');
                $this->success('编辑成功','Admin/sysuser/index');
            }else{
                $this->recordlog('2',1,'修改角色');
                $this->error('修改失败');
            }
        }else{
            // 获取用户数据
            $user_data=Db::name('sysuser')->find($userid);
            // 获取已加入用户组
            $group_data=Db::name('authgroupaccess')
                ->where(array('uid'=>$userid))
                ->select();
            $newgroupdataarray = [];
            foreach ($group_data as $ara){
                array_push($newgroupdataarray,$ara['groupid']);
            }
            // 全部用户组
            $data=Db::name('authgroup')->select();
            $assign=array(
                'data'=>$data,
                'user_data'=>$user_data,
                'group_data'=>$newgroupdataarray
                );
            $this->assign($assign);
            return $this->fetch();
        }
    }


    /*个人中心*/    /*分开写是为了将权限更细化*/
    public function my_center(){
        return $this->fetch();
    }

    /*修改个人资料*/
    public function change_msg(){
//        if(Request::instance()->post()){
//            $data['username']  =  trim(input('post.username'));
//            $data['email']  =  trim(input('post.email'));
//            $data['phone']=trim(input('post.phone'));
//            $map=array(
//                'username'=>session('user')['username']
//                );
//            if (!empty(input('post.password'))) {
//                $data['password']=md5(input('post.password'));
//            }
//            $result=Db::name('users')->where($map)->update($data);
//
//            if($result){
//                // 操作成功
//                session('user',null);
//                $this->success('退出成功、前往登录页面','Home/Index/index');
//            }else{
//                $this->error("您没有做任何修改");
//            }
//        }
    }


}
