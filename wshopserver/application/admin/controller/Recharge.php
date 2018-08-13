<?php
namespace app\admin\controller;
use think\Controller;

/**
 * 储值管理
 *
 * @author      Caesar
 * @version     1.0
 */
class Recharge extends  Adminbase //Base
{
    private  $obj,$ruleobj;
    public function _initialize() {
        parent::_initialize();
        $this->obj = model("Rechargeorder");
        $this->ruleobj = model("Rechargeactivity");
    }
    protected $beforeActionList = [
        'checkAuth' => ['only' => 'index,rechargeorder']
    ];
    /**
     * 会员储值管理页
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function rechargeorder()
    {
        $prerefundcount = 0;
        $successcount = $this->obj->where('status',4)->count();
        $refundcount = 0;
        return $this->fetch('rechargeorder',[
            'prerefundcount'=>$prerefundcount,
            'successcount'=>$successcount,
            'refundcount'=>$refundcount,
        ]);
    }
    /**
     * 新增规则页面
     *
     * @access public
     * @return tp5
     */
    public function add()
    {
        return $this->fetch('rechargerule_add',[
//            'categorys'=>$categorys,
        ]);
    }
    /**
     * 储值规则管理页
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function index()
    {
        return $this->fetch();
    }
    /**
     * ajax获取储值规则列表
     *
     * 前端使用jqgrid控件渲染表格
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function rulelist(){
        //search params
        $s_name = input("s_name");
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $offset = (input("page") - 1) * input("rows");
        $value = $this->ruleobj->getList($rows,$sidx,$sord,$offset,$s_name);
        $records = $this->ruleobj->getListCount($s_name);
        $total = ceil($records/$rows);
//        $this->recordlog('4',0,'获取储值规则列表');
        return pagedata($page,$total,$records, $value);
    }
    /**
     * 保存储值规则
     *
     * @access public
     * @return tp5
     */
    public function saverule()
    {
        if(!request()->isPost()){
            $this ->error('请求失败');
        }
        $data = input("post.");
//        $validate =  validate('Recharge');
//        if(!$validate ->scene('add') ->check($data)){
//            $this -> error($validate -> getError());
//        }
        if(!empty($data['activityid'])){
            return $this ->updaterule($data);
        }
        //把$data 提交model层
        $data['activityid'] = uuid();
        $data['fee'] = $data['fee']*100;
        $data['giftfee'] = $data['giftfee']*100;
        $userid = $this->getLoginUser()['userid'];
        $data['creater'] = $userid;
        $data['createtime'] = Date('Y-m-d H:i:s');
        $data['activityname'] = '默认充值';
        $res = $this->ruleobj ->save($data);
        if($res){
            $this->recordlog('1',0,'保存储值规则');
            $this -> result($_SERVER['HTTP_REFERER'],1,'success');
        }else{
            $this->recordlog('1',1,'保存储值规则');
            $this -> result($_SERVER['HTTP_REFERER'],0,'fail');
        }
    }
    /**
     * 更新储值规则
     *
     * @access public
     * @return tp5
     */
    public function updaterule($data){
        $data['fee'] = $data['fee']*100;
        $data['giftfee'] = $data['giftfee']*100;
        $userid = $this->getLoginUser()['userid'];
        $data['updater'] = $userid;
        $data['updatetime'] = Date('Y-m-d H:i:s');
        $res =  $this->ruleobj->save($data,['activityid'=>intval($data['activityid'])]);
        if($res){
            $this->recordlog('2',0,'更新储值规则');
            $this -> result($_SERVER['HTTP_REFERER'],1,'success');
        }else{
            $this->recordlog('2',1,'更新储值规则');
            $this -> result($_SERVER['HTTP_REFERER'],0,'fail');
        }
    }
    /**
     * 更新状态
     *
     * @access public
     * @return tp5
     */
    public function updatestatus($id,$status)
    {
        $userid = $this->getLoginUser()['userid'];
        $res = $this->ruleobj->save(['status'=>$status,'updater'=>$userid,'updatetime'=>Date('Y-m-d H:i:s')],['activityid'=>$id]);
        if($res){
            $this->recordlog('2',0,'更新储值规则状态');
            $this -> result($_SERVER['HTTP_REFERER'],1,'success');
        }else{
            $this->recordlog('2',1,'更新储值规则状态');
            $this -> result($_SERVER['HTTP_REFERER'],0,'fail');
        }
    }
    /**
     * ajax获取储值列表
     *
     * 前端使用jqgrid控件渲染表格
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function rechargelist(){
        //search params
        $s_name = input("s_name");
        $s_select = input("s_select");
        $s_time = input("s_time");
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $offset = (input("page") - 1) * input("rows");
        $value = $this->obj->getList($rows,$sidx,$sord,$offset,$s_name,$s_select,$s_time);
        $records = $this->obj->getListCount($s_name,$s_select,$s_time);
        $total = ceil($records/$rows);
//        $this->recordlog('4',0,'获取储值列表');
        return pagedata($page,$total,$records, $value);
    }
    /**
     * 储值详情页
     *
     * @access public
     * @return tp5
     */
    public function rechargedetail($page=1){
        $orderid = input("orderid");

        $rechargeorder = model('Rechargeorder')->where('orderid',$orderid)->find();
        $user = model('User')->where('userid',$rechargeorder['userid'])->find();
        $activitymodel = model('Rechargeactivity')->where('activityid',$rechargeorder['activityid'])->find();
        $rechargeorder['activityno'] = $activitymodel['activityno'];

        return $this->fetch('recharge_detail',[
            'orderid'=>$orderid,
            'user'=>$user,
            'rechargeorder'=>$rechargeorder,
            'page'=>$page
        ]);
    }

}
