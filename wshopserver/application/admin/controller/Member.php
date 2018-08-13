<?php
namespace app\admin\controller;
use think\Controller;
use think\Log;
use think\Loader;
use think\Db;
use app\admin\service\Common;

Loader::import('qrcode.phpqrcode', EXTEND_PATH, '.php');
/**
 * 成员管理（平台用户）
 *
 * @author      Caesar
 * @version     1.0
 */
class Member extends  Adminbase //Base
{
    private  $obj;
    public function _initialize() {
        parent::_initialize();
        $this->obj = model("Sysuser");
    }
    // protected $beforeActionList = [
    //     'checkAuth' => ['only' => 'index']
    // ];
    /**
     * 成员管理页
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function index()
    {
        $stopcount = $this->obj->where('status',2)->count();
        $normalcount = $this->obj->where('status',1)->count();
        $totalcount = $this->obj->count();
        return $this->fetch('index',[
            'stopcount'=>$stopcount,
            'normalcount'=>$normalcount,
            'totalcount'=>$totalcount,
        ]);
    }

    /**
     * ajax获取用户列表
     *
     * 前端使用jqgrid控件渲染表格
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function memberlist(){
        //search params
        $s_name = input("s_name");
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $offset = (input("page") - 1) * input("rows");
        $value = $this->obj->getList($rows,$sidx,$sord,$offset,$s_name);
        $records = $this->obj->getListCount($s_name);
        $total = ceil($records/$rows);
//        $this->recordlog('4',0,'获取用户列表');
        return pagedata($page,$total,$records, $value);
    }
    /**
     * 新增成员页
     *
     * @access public
     * @return tp5
     */
    public function addmember()
    {
        return $this->fetch('member_add',[
//            'categorys'=>$categorys,
        ]);
    }
    /**
     * 新增成员第一步
     *
     * @access public
     * @return tp5
     */
    public function step1()
    {
        if(!request()->isPost()){
            $this ->error('请求失败');
        }
        $data = input("post.");
        //把$data 提交model层
        $data['userid'] = uuid();
        $loginuserid = $this->getLoginUser()['userid'];
        $data['creater'] = $loginuserid;
        $data['createtime'] = Date('Y-m-d H:i:s');
        $data['status'] = 0;
        $res = $this->obj ->save($data);
        //
        //生成二维码
        $commonservice = new Common();
        $qrdata = $commonservice->generateParamQRCode(100);
        $verifydata['bindid'] = uuid();
        $verifydata['status'] = 0;
        $verifydata['userid'] = $data['userid'];
        $verifydata['ticket'] = $qrdata['ticket'];
        Db::name('wechataccountbind')->insert($verifydata);
        $this->recordlog('1',0,'新增成员第一步');
        return $this->fetch('member_add_bind',[
            'picurl'=>$qrdata['imageString'],
            'ticket'=>$qrdata['ticket']
        ]);
    }
    /**
     * 轮询扫码信息
     *
     * @access public
     * @return tp5
     */
    public function querystatus($ticket)
    {
        $accountbind = Db::name('wechataccountbind')->where(['ticket' => $ticket])->find();
        if($accountbind){
            if($accountbind['status'] == 1){
                $this -> result($_SERVER['HTTP_REFERER'],1,'success');
            }else if($accountbind['status'] == 2){
                $this -> result($_SERVER['HTTP_REFERER'],2,'fail');
            }else{
                $this -> result($_SERVER['HTTP_REFERER'],0,'normal');
            }

        }
    }
    /**
     * 更新用户状态
     *
     * @access public
     * @return tp5
     */
    public function updatestatus()
    {
        $userid = input("userid");
        $status = input("status");
        $data['status'] = $status;
        $res = $this->obj->save($data,['userid' => $userid]);
        if($res){
            $this->recordlog('2',0,'更新用户状态');
            $this -> result($_SERVER['HTTP_REFERER'],1,'success');
        }else{
            $this->recordlog('2',1,'更新用户状态');
            $this -> result($_SERVER['HTTP_REFERER'],0,'fail');
        }
    }
    /**
     * 绑定微信页面
     *
     * @access public
     * @return tp5
     */
    public function bindwechat()
    {
        $userid = input("userid");
        //
        //生成二维码
        $commonservice = new Common();
        $qrdata = $commonservice->generateParamQRCode(100);
        $verifydata['bindid'] = uuid();
        $verifydata['status'] = 0;
        $verifydata['userid'] = $userid;
        $verifydata['ticket'] = $qrdata['ticket'];
        Db::name('wechataccountbind')->insert($verifydata);
        return $this->fetch('member_add_bind',[
            'picurl'=>$qrdata['imageString'],
            'ticket'=>$qrdata['ticket']
        ]);
    }
}
