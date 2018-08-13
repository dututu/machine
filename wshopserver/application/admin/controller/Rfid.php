<?php
namespace app\admin\controller;
use think\Controller;
use think\Loader;
use think\Config;
use think\Log;
use think\Db;
use app\common\model\User as UserModel;
use app\lib\enum\WechatTemplate;
use app\worker\controller\WechatMessage;
Loader::import('master.RfidApiV2', EXTEND_PATH, '.php');

/**
 * 电子标签管理
 *
 * @author      Caesar
 * @version     1.0
 */
class Rfid extends  Adminbase //Base
{
    private  $obj,$typeobj;
    public function _initialize() {
        parent::_initialize();
        $this->typeobj = model("Rfidspec");
    }
    protected $beforeActionList = [
        'checkAuth' => ['only' => 'index,setting']
    ];
    /**
     * rfid管理页
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function index()
    {
        $precount = model("Rfidorder")->where('orderstatus',6)->count();
        $predelicount = model("Rfidorder")->where('orderstatus',4)->count();
        $donecount = model("Rfidorder")->where('orderstatus',5)->count();
        $rukucount = model("Rfidorder")->where('orderstatus',2)->count();
        return $this->fetch('index',[
            'precount'=>$precount,
            'predelicount'=>$predelicount,
            'donecount'=>$donecount,
            'rukucount'=>$rukucount,
        ]);
    }
    /**
     * ajax获取rfid列表
     *
     * 前端使用jqgrid控件渲染表格
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function rfidlist(){
        //search params
        $s_name = input("s_name");
        $s_select = input("s_select");
        $s_time = input("s_time");
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $status = input("status");
        $offset = (input("page") - 1) * input("rows");
        $value = model('Rfidorder')->getList($rows,$sidx,$sord,$offset,$s_name,$s_select,$s_time,$status);
        $records = model('Rfidorder')->getListCount($s_name,$s_select,$s_time,$status);
        $total = ceil($records/$rows);
//        $this->recordlog('4',0,'获取rfid列表');
        return pagedata($page,$total,$records, $value);
    }
    /**
     * 订单详情页
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function detail($page=1,$orderid='',$status='')
    {
//        $ordermodel = model('Rfidorder')::where('orderid', '=', $orderid)->find();
//        if($ordermodel){
//            if($ordermodel['orderstatus'] == 4){
//                $data = array(
//                    'serialsnumber' => $ordermodel['orderno'],
//                    'timestamp' => time(),
//                );
//
//            }
//        }

        return $this->fetch('rfid_detail',[
            'orderid'=>$orderid,
            'page'=>$page,
            'status'=>$status
        ]);
    }

    /**
     * ajax获取订单详情
     *
     * @access public
     * @return tp5
     */
    public function detaildata($orderid=''){
        $rfidorder = model('Rfidorder')->where('orderid', $orderid)->find();
        $goods = model('Rfidorderdetail')->where('orderid', $orderid)->select();
        $user = model('Sysuser')->where('userid',$rfidorder['sysuserid'])->find();
        foreach ($goods as $good){
            $gmodel = model('Goods')->where('goodsid', $good['goodsid'])->find();
            $specmodel = model('Rfidspec')->where('specid', $good['rfidspec'])->find();
            if($gmodel){
                $good['goodsname'] = $gmodel['goodsname'];
            }
            if($specmodel){
                $good['typename'] = $specmodel['typename'];
            }
        }
        //查询退款订单
        $refunds = model('Rfidoutrefund')->where('orderid', $orderid)->select();
        if(count($refunds)>0){
//            foreach ($refunds as $refund){
//                $refundpics = $this->outrefundpics->where('refundid', $refund['refundid'])->select();
//                $refundgoods = $this->orderdetailobj->where('refundorder', $refund['refundid'])->select();
//                $refund['refundpics'] = $refundpics;
//                $refund['refundgoods'] = $refundgoods;
//            }

        }
        return result(200,'success',[
            'orderid'=>$orderid,
            'rfidorder'=>$rfidorder,
            'goods'=>$goods,
            'user'=>$user,
            'refunds'=>$refunds
        ]);
    }
    /**
    *退款
     */
    public function fahuo(){
        $orderid = input("orderid");
        $expresscompany = input("expresscompany");
        $expressno = input("expressno");
        $rfidorder = model('Rfidorder')->where('orderid', $orderid)->find();
        if($rfidorder!=null&&($rfidorder['orderstatus'] == 2)){
            $res = model('Rfidorder')
                ->where('orderid', $orderid)
                ->update([
                    'orderstatus' => 5,
                    'expresscompany' => $expresscompany,
                    'expressno' => $expressno
                ]);
            //添加异步命令行发送模版消息
            $userModel = new UserModel();
            $user = $userModel->where('userid', $rfidorder['sysuserid'])->find();
            $template = array(
                'cmd' => 0,
                'data' =>array(
                    'touser' => $user['openid'],
                    'template_id' => WechatTemplate::RFIDORDER,
                    'data' => array(
                        'first'=>array(
                            'value' => '标签已发货，请查看物流信息',
                            'color' => '#173177'
                        ),
                        'keyword1'=>array(
                            'value' => $rfidorder['createtime'],
                            'color' => '#173177'
                        ),
                        'keyword2'=>array(
                            'value' => $rfidorder['orderno'],
                            'color' => '#173177'
                        ),
                        'keyword3'=>array(
                            'value' => 'rfid订单',
                            'color' => '#173177'
                        ),
                        'keyword4'=>array(
                            'value' => $rfidorder['receiver'],
                            'color' => '#173177'
                        ),
                        'keyword5'=>array(
                            'value' => $rfidorder['mobile'],
                            'color' => '#173177'
                        ),
                        'remark'=>array(
                            'value' => '总价：'.($rfidorder['totalfee']/100).'元',
                            'color' => '#173177'
                        ),
                    )
                )
            );
            $re = WechatMessage::add(json_encode($template),"erp_options");
            return result(200,'','');
        }else{
            return result(201,'订单状态非已支付状态','');
        }
    }
    /**
     * rfid设置页
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function setting()
    {
        $rfidfreefee = model('Sysparam')->where('paramid','rfidfreefee')->find();
        $rfidperorderfreight = model('Sysparam')->where('paramid','rfidperorderfreight')->find();
        return $this->fetch('setting',[
            'rfidperorderfreight'=>$rfidperorderfreight['paramvalue']/100,
            'rfidfreefee'=>$rfidfreefee['paramvalue']/100
        ]);
    }
    public function savefeight(){
        $rfidperorderfreight = input("rfidperorderfreight");
        $rfidfreefee = input("rfidfreefee");
        $res = model('Sysparam')
            ->where('paramid', 'rfidperorderfreight')
            ->update([
                'paramvalue'	      => $rfidperorderfreight*100
            ]);
        $res2 = model('Sysparam')
            ->where('paramid', 'rfidfreefee')
            ->update([
                'paramvalue'	      => $rfidfreefee*100
            ]);
        Log::info($res2);
        $this->recordlog('2',0,'修改rfid运费');
        $this -> result($_SERVER['HTTP_REFERER'],1,'success');
    }
    /**
     * ajax获取rfid类型列表
     *
     * 前端使用jqgrid控件渲染表格
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function typelist(){
        //search params
        $s_name = input("s_name");
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $offset = (input("page") - 1) * input("rows");
        $value = $this->typeobj->getList($rows,$sidx,$sord,$offset,$s_name);
        $records = $this->typeobj->getListCount($s_name);
        $total = ceil($records/$rows);
//        $this->recordlog('4',0,'获取rfid类型列表');
        return pagedata($page,$total,$records, $value);
    }
    /**
     * 新增rfid类型页
     *
     * @access public
     * @return tp5
     */
    public function addtype()
    {
        return $this->fetch('rfid_type_add',[
//            'categorys'=>$categorys,
        ]);
    }
    /**
     * 编辑rfid类型页
     *
     * @access public
     * @return tp5
     */
    public function edittype($typeid)
    {
        $typemodel = $this->typeobj->where('specid',$typeid)->find();
        return $this->fetch('rfid_type_edit',[
            'type'=>$typemodel
        ]);
    }
    /**
     * 保存rfid类型
     *
     * @access public
     * @return tp5
     */
    public function savetype()
    {
        if(!request()->isPost()){
            $this ->error('请求失败');
        }
        $data = input("post.");
//        $validate =  validate('Recharge');
//        if(!$validate ->scene('add') ->check($data)){
//            $this -> error($validate -> getError());
//        }
        if(!empty($data['specid'])){
            return $this ->updaterule($data);
        }
        //把$data 提交model层
        $data['specid'] = uuid();
        $data['unitfee'] = $data['unitfee']*100;
        $userid = $this->getLoginUser()['userid'];
        $data['creater'] = $userid;
        $data['createtime'] = Date('Y-m-d H:i:s');
        $res = $this->typeobj ->save($data);
        if($res){
            $this->recordlog('1',0,'保存rfid类型');
            $this -> result($_SERVER['HTTP_REFERER'],1,'success');
        }else{
            $this->recordlog('1',1,'保存rfid类型');
            $this -> result($_SERVER['HTTP_REFERER'],0,'fail');
        }
    }
    /**
     * 更新rule
     *
     * @access public
     * @return tp5
     */
    public function updaterule($data){
        $data['unitfee'] = $data['unitfee']*100;
        $userid = $this->getLoginUser()['userid'];
        $data['updater'] = $userid;
        $data['updatertime'] = Date('Y-m-d H:i:s');
        $res =  $this->typeobj->save($data,['specid'=>$data['specid']]);
        if($res){
            $this -> result($_SERVER['HTTP_REFERER'],1,'success');
        }else{
            $this -> result($_SERVER['HTTP_REFERER'],0,'fail');
        }
    }
    /**
     * 运费修改页
     *
     * @access public
     * @return tp5
     */
    public function gotofreightedit()
    {
        return $this->fetch('freight_edit',[
//            'categorys'=>$categorys,
        ]);
    }
    /**
     * 删除类型
     *
     * @access public
     * @return tp5
     */
    public function deltype($id)
    {
        $res = $this->typeobj->where(['specid'=>$id])->delete();
        if($res){
            $this->recordlog('3',0,'删除rfid类型');
            $this -> result($_SERVER['HTTP_REFERER'],1,'success');
        }else{
            $this->recordlog('3',1,'删除rfid类型');
            $this -> result($_SERVER['HTTP_REFERER'],0,'fail');
        }
    }
}
