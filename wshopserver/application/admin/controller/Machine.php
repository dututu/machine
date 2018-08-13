<?php
namespace app\admin\controller;
use think\Controller;
use think\Config;
use app\common\model\Machine as MachineModel;
use think\Loader;
use \think\Log;
use think\Db;
use app\common\model\User as UserModel;
use app\worker\controller\WechatMessage;
use app\common\model\Goods as GoodsModel;
use app\common\model\Shelf as ShelfModel;
use app\lib\enum\MachineBusinessStatusEnum;
use app\lib\enum\MachineStatusEnum;
Loader::import('master.MasterApi', EXTEND_PATH, '.php');
Loader::import('master.RfidApiV2', EXTEND_PATH, '.php');
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
Loader::import('master.GoboxApi', EXTEND_PATH, '.php');
/**
 * 机柜管理
 *
 * @author      Caesar
 * @version     1.0
 */
class Machine extends  Adminbase //Adminbase
{
    private  $obj;
    public function _initialize() {
        parent::_initialize();
        $this->obj = model("Machine");
    }
    protected $beforeActionList = [
        'checkAuth' => ['only' => 'index']
    ];
    /**
     * 机柜管理页
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function index()
    {
        $userid = $this->getLoginUser()['userid'];
        $stopcount = model("Machine")->where('businessstatus',5)->whereOr('businessstatus',6)->count();
        $normalcount = model("Machine")->where('businessstatus',4)->count();
        $precount = model("Machine")->where('businessstatus',3)->count();
        $rukucount = model("Machine")->where('businessstatus',1)->whereOr('businessstatus',2)->count();
        return $this->fetch('index',[
            'stopcount'=>$stopcount,
            'normalcount'=>$normalcount,
            'precount'=>$precount,
            'rukucount'=>$rukucount,
        ]);
    }
    /**
     * 机柜详情页面
     *
     * @access public
     * @return tp5
     */
    public function detail($page=1,$machineid='',$status='')
    {
        return $this->fetch('machine_detail',[
            'machineid'=>$machineid,
            'status'=>$status,
            'page'=>$page,
        ]);
    }
    /**
     * 机柜详情页面ajax获取详情信息
     *
     * @access public
     * @return tp5
     * @throws
     */
    public function detaildata($machineid='')
    {
        $machine = model('Machine')->where('machineid',$machineid)->find();
        //configs
        $factoryconfig = Config::get('machinedict.factory');
        $functypeconfig = Config::get('machinedict.functype');
        $doortypeconfig = Config::get('machinedict.doortype');
        $placeconfig = Config::get('machinedict.place');
        $rfidtypeconfig = Config::get('machinedict.rfidtype');
        $machine['faname'] = $factoryconfig[$machine['facode']];
        $machine['funcname'] = $functypeconfig[$machine['funccode']];
        $machine['doortypename'] = $doortypeconfig[$machine['doortypecode']];
        $machine['placename'] = $placeconfig[$machine['placecode']];
        if($machine['rfidtypecode']){
            $machine['rfidtypename'] = $rfidtypeconfig[$machine['rfidtypecode']];
        }

        $merchant = model('Merchant')->where('merchantid',$machine['merchantid'])->find();
        $sysuser = model('Sysuser')->where('merchantid',$machine['merchantid'])->find();
        $statusconfig = Config::get('machinedict.status');
        $businessstatusconfig = Config::get('machinedict.businessstatus');
        return result(200,'success',[
            'machine'=>$machine,
            'merchant'=>$merchant,
            'sysuser'=>$sysuser,
            'business'=>$businessstatusconfig[$machine['businessstatus']],
            'machinestatus'=>$statusconfig[$machine['status']]
        ]);
    }
    /**
     * ajax获取机柜列表
     *
     * 前端使用jqgrid控件渲染表格
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function machinelist(){
        //search params
        $s_name = input("s_name");
        $s_select = input("s_select");
        $s_time = '';
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $status = input("status");
        $offset = (input("page") - 1) * input("rows");
        $machinestatus = [];
        if($status!=''){
            if($status == 5 || $status == 6){
                $machinestatus['0'] = 5;
                $machinestatus['1'] = 6;
            }else if($status == 2){
                $machinestatus['0'] = 1;
                $machinestatus['1'] = 2;
            }else{
                $machinestatus['0'] = $status;
            }
        }
        $value = $this->obj->getList($rows,$sidx,$sord,$offset,$s_name,$s_select,$s_time,$machinestatus);
        //configs
        $factoryconfig = Config::get('machinedict.factory');
        $functypeconfig = Config::get('machinedict.functype');
        $doortypeconfig = Config::get('machinedict.doortype');
        $placeconfig = Config::get('machinedict.place');
        $rfidtypeconfig = Config::get('machinedict.rfidtype');
        foreach($value as $subvalue){
            if(isset($subvalue['facode'])){
                $subvalue['faname'] = $factoryconfig[$subvalue['facode']];
                $subvalue['funcname'] = $functypeconfig[$subvalue['funccode']];
                $subvalue['doortypename'] = $doortypeconfig[$subvalue['doortypecode']];
                $subvalue['placename'] = $placeconfig[$subvalue['placecode']];
                if($subvalue['rfidtypecode']){
                    $subvalue['rfidtypename'] = $rfidtypeconfig[$subvalue['rfidtypecode']];
                }

            }

        }
        $records = $this->obj->getListCount($s_name,$s_select,$s_time,$machinestatus);
        $total = ceil($records/$rows);
//        $this->recordlog('4',0,'获取机柜列表');
        return pagedata($page,$total,$records, $value);
    }
    /**
     * 分配商户时ajax获取商户列表
     *
     * @access public
     * @return tp5
     */
    public function selectmerchantlist(){
        //search params
        $s_name = input("s_name");
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $offset = (input("page") - 1) * input("rows");
        $value = model('Merchant')->getSelectList($rows,$sidx,$sord,$offset,$s_name);
        foreach($value as $merchant){
            $count = model("Machine")->where('merchantid',$merchant['merchantid'])->count();
            $merchant['machinecount'] = $count;
        }
        $records = model('Merchant')->getSelectListCount($s_name);
        $total = ceil($records/$rows);
        return pagedata($page,$total,$records, $value);
    }
    /**
     * 新增机柜页面
     *
     * @access public
     * @return tp5
     */
    public function addmachine()
    {
        $factory = Config::get('machinedict.factory');
        $functype = Config::get('machinedict.functype');
        $doortype = Config::get('machinedict.doortype');
        $place = Config::get('machinedict.place');
        $rfidtype = Config::get('machinedict.rfidtype');
        return $this->fetch('machine_add',[
            'factory'=>$factory,
            'functype'=>$functype,
            'doortype'=>$doortype,
            'place'=>$place,
            'rfidtype'=>$rfidtype
        ]);
    }
    /**
     * 保存机柜
     *
     * @access public
     * @return tp5
     * @throws
     */
    public function machinesave(){
        $factory = input("factory");//生产厂家
        $func = input("func");//功能类型
        $doortype = input("doortype");//柜门数量
        $place = input("place");//使用场合
        $rfidtype = input("rfidtype");//识别模式
        $contractdate = input("contractdate");//订单年月日
        $remark = input("remark");//备注
        $num = input("num");//机柜数量
        //configs
        $factoryconfig = Config::get('machinedict.factory');
        $functypeconfig = Config::get('machinedict.functype');
        $doortypeconfig = Config::get('machinedict.doortype');
        $placeconfig = Config::get('machinedict.place');
        //判断当前订单年月日是否已经存在机柜
        $machinelist = MachineModel::where('contractdate', $contractdate)->order('productionnum', 'desc')->select();
        if(count($machinelist)>0){
            $lastmachine = current($machinelist);
            $lastnum = $lastmachine['productionnum'];
            for ($x=1; $x<=$num; $x++) {
                $newx = $x +$lastnum;
                $machine = new MachineModel;
                $machine['machineid'] = uuid();
                $machine['facode'] = $factory;
                $machine['funccode'] = $func;
                $machine['doortypecode'] = $doortype;
                $machine['placecode'] = $place;
                $machine['rfidtypecode'] = $rfidtype;
                $machine['contractdate'] = $contractdate;
                $machine['businessstatus'] = 1;
                $machine['productionnum'] = $newx;//生产编号
                $userid = $this->getLoginUser()['userid'];
                $machine['creater'] = $userid;
                $machine['createtime'] = Date('Y-m-d H:i:s');
                $machine['status'] = 8;
                $forthno = '';
                if($newx<10){
                    $forthno = '000'.$newx;
                }else if($newx>=10&&$newx<100){
                    $forthno = '00'.$newx;
                }else if($newx>=100&&$newx<1000){
                    $forthno = '0'.$newx;
                }
                $machine['containerid'] = strtoupper($factory).$func.$doortype.$place.$contractdate.$forthno;//机柜编号sy 运营平台生成的
                $machine['remark'] = $remark;
                $machine->save();
                //重力柜
                if($rfidtype == '3'){
                    for($i=0;$i<5;$i++){
                        $shelf = new ShelfModel;
                        $shelf['shelfid'] = uuid();
                        $shelf['machineid'] = $machine['machineid'];
                        $shelf['floor'] = ($i+1);
                        if($i == 0){
                            $shelf['pos'] = '010101';
                        }else if($i == 1){
                            $shelf['pos'] = '010201';
                        }else if($i == 2){
                            $shelf['pos'] = '010301';
                        }else if($i == 3){
                            $shelf['pos'] = '010401';
                        }else if($i == 4){
                            $shelf['pos'] = '010501';
                        }
                        $userid = $this->getLoginUser()['userid'];
                        $shelf['creater'] = $userid;
                        $shelf['createtime'] = Date('Y-m-d H:i:s');
                        $shelf->save();
                    }

                }
            }
        }else{
            for ($x=1; $x<=$num; $x++) {
                $machine = new MachineModel;
                $machine['machineid'] = uuid();
                $machine['facode'] = $factory;
                $machine['funccode'] = $func;
                $machine['doortypecode'] = $doortype;
                $machine['placecode'] = $place;
                $machine['rfidtypecode'] = $rfidtype;
                $machine['contractdate'] = $contractdate;
                $machine['businessstatus'] = 1;
                $machine['productionnum'] = $x;//生产编号
                $userid = $this->getLoginUser()['userid'];
                $machine['creater'] = $userid;
                $machine['createtime'] = Date('Y-m-d H:i:s');
                $machine['status'] = 8;
                $forthno = '';
                if($x<10){
                    $forthno = '000'.$x;
                }else if($x>=10&&$x<100){
                    $forthno = '00'.$x;
                }else if($x>=100&&$x<1000){
                    $forthno = '0'.$x;
                }
                $machine['containerid'] = strtoupper($factory).$func.$doortype.$place.$contractdate.$forthno;//机柜编号sy 运营平台生成的
                $machine['remark'] = $remark;
                $machine->save();
                //重力柜
                if($rfidtype == '3'){
                    for($i=0;$i<5;$i++){
                        $shelf = new ShelfModel;
                        $shelf['shelfid'] = uuid();
                        $shelf['machineid'] = $machine['machineid'];
                        $shelf['floor'] = ($i+1);
                        if($i == 0){
                            $shelf['pos'] = '010101';
                        }else if($i == 1){
                            $shelf['pos'] = '010201';
                        }else if($i == 2){
                            $shelf['pos'] = '010301';
                        }else if($i == 3){
                            $shelf['pos'] = '010401';
                        }else if($i == 4){
                            $shelf['pos'] = '010501';
                        }
                        $userid = $this->getLoginUser()['userid'];
                        $shelf['creater'] = $userid;
                        $shelf['createtime'] = Date('Y-m-d H:i:s');
                        $shelf->save();
                    }

                }
            }
        }
//        if ($result) {
//            $this->success('添加成功','Admin/Rule/rulelist');
//        }else{
//            $this->error('添加失败');
//        }
        $this->recordlog('1',0,'新增机柜');
        $this->success('创建成功');
    }
    public function machineupdate(){

    }

    /**
     * 分配商户页面
     *
     * @access public
     * @return tp5
     */
    public function dispatchmerchant($machineid){
        return $this->fetch('machine_dispatch',[
            'machineid'=>$machineid,
        ]);
    }
    /**
     * 重新分配商户页面
     *
     * @access public
     * @return tp5
     */
    public function redispatch($machineid){
        return $this->fetch('redispatch',[
            'machineid'=>$machineid,
        ]);
    }
    /**
     * 完善重力柜参数
     *
     * @access public
     * @return tp5
     */
    public function editboxinfo($machineid){
        return $this->fetch('editboxinfo',[
            'machineid'=>$machineid,
        ]);
    }
    public function submitboxinfo($machineid,$devid,$devuid){
        $devids = MachineModel::where('boxdevid', $devid)->select();
        if(count($devids)>0){
            return result(201,'该devid的机柜已经存在','');
        }
        $machine = model('Machine')::where('machineid', '=', $machineid)->find();
        if($machine){
            $result2 = $this->getAndSaveShelfs($devid,$machine['machineid']);
            if($result2){
                $userid = $this->getLoginUser()['userid'];
                //修改参数值并设置机柜状态为已上线 已初始化
                $result = model('Machine')::where('machineid', '=', $machineid)
                    ->update(['boxdevid' => $devid,'boxdevuid' => $devuid,'updater' => $userid,'updatetime' => Date('Y-m-d H:i:s'),'businessstatus' => MachineBusinessStatusEnum::INITED,'status' => MachineStatusEnum::ONLINED]);

                return result(200,'','');
            }else{
                return result(201,'操作失败','');
            }
        }
    }
    /**
     * 修改重力柜参数
     *
     * @access public
     * @return tp5
     */
    public function modifyboxinfo($machineid){
        $machine = model('Machine')::where('machineid', '=', $machineid)->find();
        return $this->fetch('editboxinfo',[
            'machineid'=>$machineid,
            'boxdevid'=>$machine['boxdevid'],
            'boxdevuid'=>$machine['boxdevuid'],
        ]);
    }
    public function saveboxinfo($machineid,$devid,$devuid){
        $devids = MachineModel::where('boxdevid', $devid)->select();
        if(count($devids)>0){
            return result(201,'该devid的机柜已经存在','');
        }
        $machine = model('Machine')::where('machineid', '=', $machineid)->find();
        if($machine){
            $result2 = $this->getAndSaveShelfs($devid,$machine['machineid']);
            if($result2){
                $userid = $this->getLoginUser()['userid'];
                //修改参数值并设置机柜状态为已上线 已初始化
                $result = model('Machine')::where('machineid', '=', $machineid)
                    ->update(['boxdevid' => $devid,'boxdevuid' => $devuid,'updater' => $userid,'updatetime' => Date('Y-m-d H:i:s')]);

                return result(200,'','');
            }else{
                return result(201,'操作失败','');
            }
        }
    }
    /**
     * 分配商户提交
     *
     * @access public
     * @return tp5
     */
    public function dispatchmerchantsave($machineid,$merchantid){
        $result = model('Machine')::where('machineid', '=', $machineid)
            ->update(['merchantid' => $merchantid,'businessstatus' => 4]);
        if($result){
            return result(200,'','');
        }else{
            return result(201,'操作失败','');
        }
    }
    /**
     * 重新分配商户提交
     *
     * @access public
     * @return tp5
     */
    public function redispatchsave($machineid){
        $result = model('Machine')::where('machineid', '=', $machineid)
            ->update(['businessstatus' => MachineBusinessStatusEnum::CANCELED]);//已作废
        $oldmachine = model('Machine')->where('machineid',$machineid)->find();
        //
        $factory = $oldmachine['facode'];//生产厂家
        $func = $oldmachine['funccode'];//功能类型
        $doortype = $oldmachine['doortypecode'];//柜门数量
        $place = $oldmachine['placecode'];//使用场合
        $rfidtype = $oldmachine['rfidtypecode'];//识别模式
        //
        //
        $contractdate = substr(date("Ymd",time()),2);//订单年月日
        $num = 1;//机柜数量
        $newmachineid = uuid();
        $newmachine = [];
        //判断当前订单年月日是否已经存在机柜
        $machinelist = MachineModel::where('contractdate', $contractdate)->order('productionnum', 'desc')->select();
        if(count($machinelist)>0){
            $lastmachine = current($machinelist);
            $lastnum = $lastmachine['productionnum'];
            for ($x=1; $x<=$num; $x++) {
                $newx = $x +$lastnum;
                $machine = new MachineModel;
                $machine['machineid'] = $newmachineid;
                $machine['facode'] = $factory;
                $machine['funccode'] = $func;
                $machine['doortypecode'] = $doortype;
                $machine['placecode'] = $place;
                $machine['rfidtypecode'] = $rfidtype;
                $machine['contractdate'] = $contractdate;
                $machine['businessstatus'] = 3;
                $machine['boxdevid'] = $oldmachine['boxdevid'];
                $machine['boxdevuid'] = $oldmachine['boxdevuid'];
                $machine['productionnum'] = $newx; //生产编号
                $userid = $this->getLoginUser()['userid'];
                $machine['creater'] = $userid;
                $machine['createtime'] = Date('Y-m-d H:i:s');
                $machine['status'] = 10;
                $forthno = '';
                if($newx<10){
                    $forthno = '000'.$newx;
                }else if($newx>=10&&$newx<100){
                    $forthno = '00'.$newx;
                }else if($newx>=100&&$newx<1000){
                    $forthno = '0'.$newx;
                }
                $machine['containerid'] = strtoupper($factory).$func.$doortype.$place.$contractdate.$forthno;//机柜编号sy 运营平台生成的
                $machine['remark'] = '';
                $machine->save();
                $newmachine = $machine;
            }
        }else{
            for ($x=1; $x<=$num; $x++) {
                $machine = new MachineModel;
                $machine['machineid'] = $newmachineid;
                $machine['facode'] = $factory;
                $machine['funccode'] = $func;
                $machine['doortypecode'] = $doortype;
                $machine['placecode'] = $place;
                $machine['rfidtypecode'] = $rfidtype;
                $machine['contractdate'] = $contractdate;
                $machine['boxdevid'] = $oldmachine['boxdevid'];
                $machine['boxdevuid'] = $oldmachine['boxdevuid'];
                $machine['businessstatus'] = 3;
                $machine['productionnum'] = $x;//生产编号
                $userid = $this->getLoginUser()['userid'];
                $machine['creater'] = $userid;
                $machine['createtime'] = Date('Y-m-d H:i:s');
                $machine['status'] = 10;
                $forthno = '';
                if($x<10){
                    $forthno = '000'.$x;
                }else if($x>=10&&$x<100){
                    $forthno = '00'.$x;
                }else if($x>=100&&$x<1000){
                    $forthno = '0'.$x;
                }
                $machine['containerid'] = strtoupper($factory).$func.$doortype.$place.$contractdate.$forthno;//机柜编号sy 运营平台生成的
                $machine['remark'] = '';
                $machine->save();
                $newmachine = $machine;
            }
        }
        //重力柜
        if($rfidtype == '3'){
//            $onsuccess = true;
//            $message = '';
//            $gboxApi = new \GoboxApi('','');
//            $option = [];
//            $option['dev_id'] = $oldmachine['boxdevid'];
//            $masterresult = $gboxApi->querySkuBox($option);
//            if($masterresult['code'] == 0) {
//                $skuList = $masterresult['output']['sku_list'];
//                foreach ($skuList as $sku) {
//                    $goods = GoodsModel::get(['goodsid' => $sku['barcode']]);
//                    if($goods){
//                        $option2 = [];
//                        $option2['time'] = time();
//                        $option2['dev_id'] = $oldmachine['boxdevid'];
//                        $option2['pos'] = $sku['pos'];
//                        $option2['barcode'] = $goods['goodsid'];
//                        $masterresult2 = $gboxApi->delSkuBox($option2);
//                        if($masterresult2['code'] != 0){
//                            $onsuccess = false;
//                            $message = $message.' '.$goods['goodsname'].'下架失败:'.$masterresult['code'];
//                        }
//                    }
//                }
//            }
            $gboxApi = new \GoboxApi('','');
            $option = [];
            $option['dev_id'] = $oldmachine['boxdevid'];
            $option['sku_list'] = [];
            $masterresult = $gboxApi->fullSyncSku($option);
            $this->getAndSaveShelfs($oldmachine['boxdevid'],$newmachineid);
            if($masterresult['code'] == 0) {
                return result(200,'','');
            }else{
                return result(201,'星星全量同步sku到机柜返回失败','');
            }
        }

        if($result){
            return result(200,'','');
        }else{
            return result(201,'操作失败','');
        }
    }
    public function getAndSaveShelfs($dev_id,$machineid){
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_id'] = $dev_id;
        $masterresult = $gboxApi->queryDevice($option);
        if($masterresult['code'] == 0 && isset($masterresult['output']) && isset($masterresult['output']['dev_list'])){
            //delete before
            model('Shelf')->where('machineid',$machineid)->delete();
            //
            $devList = $masterresult['output']['dev_list'];
            foreach ($devList as $dev){
                $pos_list = $dev['pos_list'];
                $lock_list = $dev['lock_list'];
                if(is_array($pos_list)){
                    $size = count($pos_list);
                    for($i=0; $i<$size; $i++) {
                        $shelf = new ShelfModel;
                        $shelf['shelfid'] = uuid();
                        $shelf['machineid'] = $machineid;
                        $shelf['floor'] = ($i+1);
                        $shelf['pos'] = $pos_list[$i];
                        $userid = $this->getLoginUser()['userid'];
                        $shelf['creater'] = $userid;
                        $shelf['createtime'] = Date('Y-m-d H:i:s');
                        $shelf->save();
                    }
                    Db::name('machine')
                        ->where('machineid', $machineid)
                        ->update(['floor' => $size, 'plat' => $size]);

                }
            }
            return true;
        }else{
            return false;
        }
    }
    /**
     * 同步机柜
     *
     * @access public
     * @return tp5
     * @throws
     */
    public function regdevice(){
        $machineid = input("machineid");
        $machine = model('Machine')->where('machineid',$machineid)->find();
        $rfidtype = $machine['rfidtypecode'];
        if($rfidtype == '3'){
            $machine['status'] = MachineStatusEnum::PRODUCE;
            $machine['businessstatus'] = MachineBusinessStatusEnum::NOTCONNECTED;
            $machine ->save();
            return result(200,'同步成功','');
        }else{
            $wxOrderData = new \MasterApi('','');
            //configs
            $factoryconfig = Config::get('machinedict.factory');
            $functypeconfig = Config::get('machinedict.functype');
            $doortypeconfig = Config::get('machinedict.doortype');
            $placeconfig = Config::get('machinedict.place');
            $remark = '';
            if($machine['remark'] != null){
                $remark = $machine['remark'];
            }
            $param = array(
                'containerid' => $machine['containerid'],
                'callbackurl' => Config::get('wx.host').'callbackapi/device/regis',
                'timestamp' => time(),
                'func' => $functypeconfig[$machine['funccode']],
                'doortype' => $doortypeconfig[$machine['doortypecode']],
                'place' => $placeconfig[$machine['placecode']],
                'fa' => $factoryconfig[$machine['facode']],
                'remark' => $remark,
                'openfrom' => 2,
            );
            $json = $wxOrderData->deviceRegister($param);
            $result = json_decode($json,true);
            if($result['code'] == 200){
                return result(200,$result['msg'],'');
            }else{
                return result(201,$result['msg'],'');
            }
        }

    }
    /**
     * 刷新机柜信息(标签柜)
     *
     * @access public
     * @return tp5
     * @throws
     */
    public function refreshdevice(){
        $userid = $this->getLoginUser()['userid'];
        $machineid = input("machineid");
        $machine = model('Machine')->where('machineid',$machineid)->find();
        $rfidtype = $machine['rfidtypecode'];
        if($rfidtype == '3'){
            $machine['updater'] = $userid;
            $machine['updatetime'] = Date('Y-m-d H:i:s');
            $machine['businessstatus'] = MachineBusinessStatusEnum::INITED;//new version added
            $machine['status'] = MachineStatusEnum::ONLINED;
            $machine->save();
            //发送客服消息
//            $sysuser = model('Sysuser')->where('merchantid',$machine['merchantid'])->find();
//            $adminuser = model('Sysuser')->where('userid',$userid)->find();
//            # 配置参数
//            $config = Config::get('wx.wxconfig');
//            $wechatreceive = &\Wechat\Loader::get('Receive', $config);
//            // 发送客服消息（$data为数组，具体请参数微信官方文档）
//            $replytext = array(
//                'touser' => $sysuser['openid'],
//                'msgtype' => "text",
//                'text'=>array(
//                    'content' => "您的新机柜已经上线 \n操作时间：".$machine['updatetime']."\n操作用户：".$adminuser['username']." ".$adminuser['mobile']."\n详细地址：".$machine['location']
//                )
//            );
//            $wechatreceive->sendCustomMessage($replytext);
            return result(201,'该机柜是重力柜，不能进行刷新操作','');
        }else{
            $wxOrderData = new \MasterApi('','');

            $param = array(
                'containerid' => $machine['containerid'],
                'lsnumber' => '1509435888',
                'timestamp' => time(),
            );
            $json = $wxOrderData->deviceStatusQuery($param);
            $result = json_decode($json,true);
            if($result['code'] == 200){
                $status = $result['data']['status'];//#设备配置参数 只有值为3的时候才正常
                if($status == 3){
                    $rfid_mac = $result['data']['rfid_mac'];
                    $machine['mac'] = $rfid_mac;
                    if(isset($result['data']['sn'])){
                        $machine['sn'] = $result['data']['sn'];
                    }
                    $machine['updater'] = $userid;
                    $machine['updatetime'] = Date('Y-m-d H:i:s');
                    $machine['businessstatus'] = MachineBusinessStatusEnum::INITED;//new version added
                    $machine['status'] = MachineStatusEnum::ONLINED;
                    $machine->save();
                    //发送客服消息
//                    $sysuser = model('Sysuser')->where('merchantid',$machine['merchantid'])->find();
//                    $adminuser = model('Sysuser')->where('userid',$userid)->find();
//                    # 配置参数
//                    $config = Config::get('wx.wxconfig');
//                    $wechatreceive = &\Wechat\Loader::get('Receive', $config);
//                    // 发送客服消息（$data为数组，具体请参数微信官方文档）
//                    $replytext = array(
//                        'touser' => $sysuser['openid'],
//                        'msgtype' => "text",
//                        'text'=>array(
//                            'content' => "您的新机柜已经上线 \n操作时间：".$machine['updatetime']."\n操作用户：".$adminuser['username']." ".$adminuser['mobile']."\n详细地址：".$machine['location']
//                        )
//                    );
//                    $wechatreceive->sendCustomMessage($replytext);
                    return result(200,'success','');
                }else{
                    return result(201,'设备生产或调试中,没有返回mac信息','');
                }

            }else{
                return result(201,$result['msg'],'');
            }
        }

    }
    /**
     * 完成检测
     *
     * @access public
     * @return tp5
     */
    public function testdevice(){
        $machineid = input("machineid");
        $machine = model('Machine')->where('machineid',$machineid)->find();
        if($machine){
            model('Machine')::where('machineid', '=', $machineid)
                ->update(['businessstatus' => MachineBusinessStatusEnum::PREDISPATCH]);
            return result(200,'success','');
        }else{
            return result(200,'机柜不存在','');
        }
    }
    /**
     * 启用机柜
     *
     * @access public
     * @return tp5
     */
    public function startdevice(){
        $machineid = input("machineid");
        $machine = model('Machine')->where('machineid',$machineid)->find();
        if($machine){
            model('Machine')::where('machineid', '=', $machineid)
                ->update(['businessstatus' => 4]);
            return result(200,'success','');
        }else{
            return result(200,'机柜不存在','');
        }
    }
    /**
     * 停用机柜
     *
     * @access public
     * @return tp5
     */
    public function stopdevice(){
        $machineid = input("machineid");
        $machine = model('Machine')->where('machineid',$machineid)->find();
        if($machine){
            model('Machine')::where('machineid', '=', $machineid)
                ->update(['businessstatus' => 6]);
            return result(200,'success','');
        }else{
            return result(200,'机柜不存在','');
        }
    }
    /**
     * 删除机柜
     *
     * @access public
     * @return tp5
     */
    public function deldevice(){
        $machineid = input("machineid");
        $machine = model('Machine')->where('machineid',$machineid)->find();
        if($machine){
            model('Machine')->where('machineid',$machineid)->delete();
            $this->recordlog('3',0,'删除机柜');
            return result(200,'success','');
        }else{
            $this->recordlog('3',1,'删除机柜-机柜不存在');
            return result(200,'机柜不存在','');
        }
    }
    /**
     * 更新机柜信息
     *
     * @access public
     * @return tp5
     */
    public function updatedeviceinfo(){
        $machineid = input("machineid");
        $info = input("info");
        $machine = model('Machine')->where('machineid',$machineid)->find();
        if($machine){
            model('Machine')::where('machineid', '=', $machineid)
                ->update(['remark' => $info]);
            $this->recordlog('2',0,'更新机柜信息');
            return result(200,'success','');
        }else{
            $this->recordlog('2',1,'更新机柜信息');
            return result(200,'机柜不存在','');
        }
    }

}
