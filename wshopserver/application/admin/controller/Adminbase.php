<?php
namespace app\admin\controller;
use think\Controller;
use think\Request;
use think\Log;
use think\Db;
use think\Session;
/**
 * admin 基类控制器
 */
class Adminbase extends Base {
	/**
     * TODO
	 * 初始化方法,检查权限
	 */
	public function _initialize(){
		parent::_initialize();

	}

    protected function checkAuth()
    {

        $userid = $this->getLoginUser()['userid'];
        $usergroups = Db::name('authgroupaccess')->where(array('uid'=>$userid))->select();
        $groupids = [];
        foreach ($usergroups as $usergroup){
            array_push($groupids,$usergroup['groupid']);
        }
        if(count($groupids) == 0){
            // return $this->redirect(url('/admin/login/nogroup'));
            return $this->redirect(url('/admin/index'));
        }else{
            $this->rules($groupids);
        }

//        $auth=new \think\Auth();
//        $request = Request::instance();
//        $m=$request->module();
//        $c=$request->controller();
//        $a=$request->action();
//        $rule_name=strtolower($m.'/'.$c.'/'.$a);
//        $result=$auth->check($rule_name,$userid);
//        if(!$result){
//            $this->error('您没有权限访问');
//        }

    }

    public function rules($groupids){
	    // if(Session::has('menus')){
        //     $rule_data = Session::get('menus');
        //     $this->assign(array(
        //         'menus'=>$rule_data
        //     ));
        // }else{
            //
            echo '斯柯达副科级独守空房厚大司考几号放假肯定是饭卡的伙食费可敬的沙发 ';
            $groupids = 1;
            $araresult = Db::name('authruleaccess')
                ->alias('ara')
                ->field('r.ruleid')
                ->join('authrule r' , 'ara.ruleid=r.ruleid','RIGHT')
                ->join('authgroup ag' , 'ara.groupid=ag.groupid','LEFT')
                ->where(array('ara.groupid'=>array('in',$groupids)))
                ->select();
            $rulearray = [];
            foreach ($araresult as $ara){
                array_push($rulearray,$ara['ruleid']);
            }
            // 获取规则数据
            $rule_data=Db::name('authrule')->getTreeData('level','','rightname','ruleid','pid');
            foreach ($rule_data as $key => $value){
                if(in_array($rule_data[$key]['ruleid'],$rulearray)){
                    $rule_data[$key]['hidden'] = false;
                }else{
                    $rule_data[$key]['hidden'] = true;
                }
                if(!empty($rule_data[$key]['_data'])){
                    foreach ($rule_data[$key]['_data'] as $key2 => $value2){
                        if(in_array($rule_data[$key]['_data'][$key2]['ruleid'],$rulearray)){
                            $rule_data[$key]['_data'][$key2]['hidden'] = false;
                        }else{
                            $rule_data[$key]['_data'][$key2]['hidden'] = true;
                        }
                    }
                }
            }
            $this->assign(array(
                'menus'=>$rule_data
            ));
            Session::set('menus',$rule_data);
        // }

    }
}

