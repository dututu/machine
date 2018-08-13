<?php
namespace app\admin\controller;
use think\Db;
use think\Request;
use think\Controller;
use think\Log;
/**
 * 
 * 后台权限管理
 */
class Rule extends Adminbase{
    // protected $beforeActionList = [
    //     'checkAuth'
    // ];
//******************权限***********************

    /*权限列表*/
    public function rulelist(){
        $data=Db::name('authrule')->getTreeData('tree','','rightname','ruleid','pid');
        $assign=array(
            'data'=>$data
            );
        $this->assign($assign);
        return $this->fetch();
    }

    /**
     * 添加权限
     */
    public function add(){
        $data=input('post.');
        $data['status'] = 0;
        $result=Db::name('authrule')->insert($data);
        if ($result) {
            $this->recordlog('1',0,'添加权限');
            $this->success('添加成功','Admin/Rule/rulelist');
        }else{
            $this->recordlog('1',1,'添加权限');
            $this->error('添加失败');
        }
    }

    /**
     * 修改权限
     */
    public function edit(){
        $data=input('post.'); 
        $info=['rightname'=>$data['rightname'],'path'=>$data['path']];
        $result=Db::name('authrule')->where(["ruleid"=>$data['ruleid']])->update($info);
        // $result=\app\admin\model\Admin::change(["id"=>$data['id']],$info);
        if ($result) {
            $this->recordlog('2',0,'修改权限');
            $this->success('修改成功','Admin/Rule/rulelist');
        }else{
            $this->recordlog('2',1,'修改权限');
            $this->error('您没有做任何修改');
        }
    }

    /**
     * 删除权限
     */
    public function deleterule($ruleid){
        $map=array(
            'ruleid'=>$ruleid
            );
        $result=Db::name('authrule')->delete($map);
        if($result){
            $this->recordlog('3',0,'删除权限');
            $this->success('删除成功','Admin/Rule/rulelist');
        }else{
            $this->recordlog('3',1,'删除权限');
            $this->error('请先删除子权限');
        }

    }

    /**
     * 角色列表
     */
    public function rulegroup(){
        $data=Db::name('authgroup')->select();
        $assign=array(
            'data'=>$data
            );
        $this->assign($assign);
        return $this->fetch();
    }


     /**
     * 添加角色
     */
    public function addgroup(){
        $data=input('post.');
        $data['groupid'] = uuid();
        $data['authstatus'] = 0;
        $result=Db::name('authgroup')->insert($data);
        if ($result) {
            $this->recordlog('1',0,'添加角色');
            $this->success('添加成功','Admin/Rule/rulegroup');
        }else{
            $this->recordlog('1',1,'添加角色');
            $this->error('添加失败');
        }
    }

    /**
     * 修改角色
     */
    public function editgroup(){
        $data=input('post.');
        $result=Db::name('authgroup')->where(["groupid"=>$data['groupid']])->update(['authname'=>$data['authname']]);
        // $result=Db::name('auth_group')->editData($map,$data);
        if ($result) {
            $this->recordlog('2',0,'修改角色');
            $this->success('修改成功','Admin/Rule/rulegroup');
        }else{
            $this->recordlog('2',1,'修改角色');
            $this->error('您没有做任何修改');
        }
    }

    /**
     * 删除角色
     */
    public function deletegroup($groupid){
        if ($groupid==1) {
            $this->error('该分组不能被删除');
        }
        $map=array(
            'groupid'=>$groupid
            );
        $result=Db::name('authgroup')->where($map)->delete();
        if ($result) {
            $this->recordlog('3',0,'删除角色');
            $this->success('删除成功','Admin/Rule/rulegroup');
        }else{
            $this->recordlog('3',1,'删除角色');
            $this->error('删除失败');
        }
    }


    /**
     * 分配权限
     */
    public function ruledistribution($groupid){
        if(Request::instance()->post()){
            $data=input('post.');
            $rules=$data['rule_ids'];
            $map=array(
                'groupid'=>$groupid
            );
            Db::name('authruleaccess')->delete($map);
            foreach ($rules as $rule){
                $ruledata =[];
                $ruledata['arid'] = uuid();
                $ruledata['ruleid'] = $rule;
                $ruledata['groupid'] = $groupid;
                $result=Db::name('authruleaccess')->insert($ruledata);
            }

            // $result=Db::name('auth_group')->editData($map,$data);
            if ($result) {
                $this->success('操作成功','admin/rule/rulegroup');
            }else{
                $this->error('操作失败');
            }
        }else{
            $group_data=Db::name('authgroup')->where(array('groupid'=>$groupid))->find();
//            $group_data['rules']=explode(',', $group_data['rules']);
            //
            $araresult = Db::name('authruleaccess')
                ->alias('ara')
                ->field('r.ruleid')
                ->join('authrule r' , 'ara.ruleid=r.ruleid','RIGHT')
                ->join('authgroup ag' , 'ara.groupid=ag.groupid','LEFT')
                ->where(array('ara.groupid'=>$groupid))
                ->select();
            $rulearray = [];
            foreach ($araresult as $ara){
                array_push($rulearray,$ara['ruleid']);
            }
            $group_data['rules'] = $rulearray;
            Log::info($group_data['rules']);
            // 获取规则数据
            $rule_data=Db::name('authrule')->getTreeData('level','','rightname','ruleid','pid');

            Log::info($rule_data);
            $assign=array(
                'group_data'=>$group_data,
                'rule_data'=>$rule_data
                );
            // dump($group_data);
            $this->assign($assign);
            return $this->fetch();
        }
    }

}
