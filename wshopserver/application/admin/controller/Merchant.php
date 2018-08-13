<?php
namespace app\admin\controller;
use think\Controller;
use app\admin\service\Common;
use app\admin\service\Templatemessage;

/**
 * 商户管理
 *
 * @author      Caesar
 * @version     1.0
 */
class Merchant extends  Adminbase //Base
{
    private  $obj;
    public function _initialize() {
        parent::_initialize();
        $this->obj = model("Merchant");
    }
    protected $beforeActionList = [
        'checkAuth' => ['only' => 'index']
    ];
    /**
     * 商户管理页
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function index()
    {
        $noapplycount = $this->obj->where('status',1)->count();
        $normalcount = $this->obj->where('status',2)->count();
        $undispatchcount = $this->obj->where('status',1)->count();
        $stopcount = $this->obj->where('status',4)->count();
        return $this->fetch('index',[
            'noapplycount'=>$noapplycount,
            'normalcount'=>$normalcount,
            'undispatchcount'=>$undispatchcount,
            'stopcount'=>$stopcount,
        ]);
    }
    /**
     * ajax获取商户列表
     *
     * 前端使用jqgrid控件渲染表格
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function merchantlist(){
        //search params
        $s_name = input("s_name");
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $offset = (input("page") - 1) * input("rows");
        $value = $this->obj->getList($rows,$sidx,$sord,$offset,$s_name);
        foreach ($value as $merchant){
            $machinecount = model('Machine')->where('merchantid',$merchant['merchantid'])->count();
            $merchant['machinecount'] = $machinecount;
        }
        $records = $this->obj->getListCount($s_name);
        $total = ceil($records/$rows);
//        $this->recordlog('4',0,'获取商户列表');
        return pagedata($page,$total,$records, $value);
    }
    /**
     * 商户审批页面
     *
     * @access public
     * @return tp5
     */
    public function toapply()
    {
        $merchantid = input("merchantid");
        //
        $merchant = $this->obj->where('merchantid',$merchantid)->find();
        $sysuser = model('Sysuser')->where('merchantid', $merchantid)->find();
        return $this->fetch('merchant_apply',[
            'merchant'=>$merchant,
            'sysuser'=>$sysuser
        ]);
    }
    /**
     * 商户编辑页面
     *
     * @access public
     * @return tp5
     */
    public function toedit()
    {
        $merchantid = input("merchantid");
        //
        $merchant = $this->obj->where('merchantid',$merchantid)->find();
        $sysuser = model('Sysuser')->where('merchantid', $merchantid)->find();
        return $this->fetch('merchant_edit',[
            'merchant'=>$merchant,
            'sysuser'=>$sysuser
        ]);
    }
    /**
     * 更新商户信息
     *
     * @access public
     * @return tp5
     */
    public function update(){
        if(!request()->isPost()){
            $this ->error('请求失败');
        }
        $data = input("post.");
        if(!empty($data['merchantid'])){
            $merchantdata['merchantid'] = $data['merchantid'];
            $merchantdata['merchantname'] = $data['merchantname'];
            $merchantdata['mobile'] = $data['mobile'];
            $merchantdata['location'] = $data['location'];
            $merchantdata['remark'] = $data['remark'];
            $res =model('Merchant')::where('merchantid', '=', $data['merchantid'])
                ->update($merchantdata);
            $userdata['username'] = $data['username'];
            $userdata['nickname'] = $data['nickname'];
            $res2 =model('Sysuser')::where('merchantid', '=', $data['merchantid'])
                ->update($userdata);
            $this->recordlog('2',0,'更新商户信息');
            $this -> result($_SERVER['HTTP_REFERER'],1,'success');
        }else{
            $this->recordlog('2',1,'更新商户信息');
        }
    }
    /**
     * 更新审核状态
     *
     * @access public
     * @return tp5
     */
    public function applystatus()
    {
        $data = input("post.");
        $merchantid = $data["merchantid"];
        $status = $data["status"];
        $newdata['status'] = $status;
        $res = '';
        if($status == '2'){//审批通过
            $sysuser = model('Sysuser')::where('merchantid', $merchantid)->find();
            $userid = $this->getLoginUser()['userid'];
            $applyuser = model('Sysuser')->where('userid', $userid)->find();
            try {
                //发送模板消息
                $templete = new Templatemessage();
                $templete->sendappplyresult($sysuser['openid'],$applyuser['username']);
            } catch (\Exception $e) {
                $this->recordlog('2',1,'更新商户审核状态');
                $this -> result($_SERVER['HTTP_REFERER'],0,'发送模板消息失败，请检查Redis服务是否开启');
            }
            $res = $this->obj->save($newdata,['merchantid' => $merchantid]);
            //设置商户标签
            $commonservice = new Common();
            $commonservice->addUser2WechatGroup($sysuser['openid']);
        }
        if($res){
            $this->recordlog('2',0,'更新商户审核状态');
            $this -> result($_SERVER['HTTP_REFERER'],1,'success');
        }else{
            $this->recordlog('2',1,'更新商户审核状态');
            $this -> result($_SERVER['HTTP_REFERER'],0,'fail');
        }
    }
    /**
     * 停用商户
     *
     * @access public
     * @return tp5
     */
    public function stopmerchant()
    {
        $merchantid = input("merchantid");
        $data['status'] = 4;
        $res = $this->obj->save($data,['merchantid' => $merchantid]);
        $sysuser = model('Sysuser')::where('merchantid', $merchantid)->find();
        //移除商户标签
        $commonservice = new Common();
        $commonservice->removeUser2WechatGroup($sysuser['openid']);
        if($res){
            $this->recordlog('2',0,'停用商户');
            $this -> result($_SERVER['HTTP_REFERER'],1,'success');
        }else{
            $this->recordlog('2',1,'停用商户');
            $this -> result($_SERVER['HTTP_REFERER'],0,'fail');
        }
    }
    /**
     * 启用商户
     *
     * @access public
     * @return tp5
     */
    public function startmerchant()
    {
        $merchantid = input("merchantid");
        $data['status'] = 2;
        $res = $this->obj->save($data,['merchantid' => $merchantid]);
        $sysuser = model('Sysuser')::where('merchantid', $merchantid)->find();
        //打商户标签
        $commonservice = new Common();
        $commonservice->addUser2WechatGroup($sysuser['openid']);
        if($res){
            $this->recordlog('2',0,'启用商户');
            $this -> result($_SERVER['HTTP_REFERER'],1,'success');
        }else{
            $this->recordlog('2',1,'启用商户');
            $this -> result($_SERVER['HTTP_REFERER'],0,'fail');
        }
    }
}
