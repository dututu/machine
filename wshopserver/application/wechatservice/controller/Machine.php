<?php
namespace app\wechatservice\controller;
use app\common\model\Machinegroup;
use app\common\model\Machinegroups;
use think\Controller;
use app\common\model\Machine as MachineModel;
use app\common\model\Machinepics as MachinepicsModel;
use app\common\model\Goods as GoodsModel;

use think\Loader;
use think\Config;
use think\Log;
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
Loader::import('master.MasterApi', EXTEND_PATH, '.php');
Loader::import('master.GoboxApi', EXTEND_PATH, '.php');
/**
 * 智能机柜
 *
 * @author      Caesar
 * @version     1.0
 */
class Machine extends  Base //Base
{
    protected $beforeActionList = [
        'checkSession'
    ];
    /**
     * 首页
     */
    public function index()
    {
//        return $this->fetch('machinelist',[
////            'categorys'=>$categorys,
//        ]);

        return $this->fetch('machinelist',[
//            'categorys'=>$categorys,
        ]);

    }
    /**
     * 根据creater来获取自己创建的机柜信息
     * @param  creater
     * @return 机柜列表(机柜id 机柜名称 详细地址 经度 纬度 机柜状态 机柜中商品分类的列表)
     */
    public function machinelist()
    {
        $openid = session('openid', '', 'wechatservice');
//        $merchantid = $this->getMerchantIdByOpenId($openid);
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $merchantid = $sysuser['merchantid'];
//        $merchantid = '1';
        //search params
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        if($merchantid!=null&&$merchantid!=''){
            $typeids = input("searchparams");//2,3,4,
            $offset = (input("page") - 1) * input("rows");
            $value = model('Machine')->getMachineList($rows,$offset,$merchantid,$typeids);
            foreach ($value as $machine) {
                $mash = MachineModel::get($machine['machineid']);
                $pics = $mash->pics;
                foreach ($pics as $pic){
                    $pic['url'] = Config::get('paths.coshost').$pic['url'];
                }
                $machine['pics'] = $pics;
                //
                $functypeconfig = Config::get('machinedict.functype');
                $machine['typename'] = $functypeconfig[$machine['funccode']];

            }
            $records = model('Machine')->getMachineListCount($merchantid,$typeids);
            $total = ceil($records/$rows);
            $hasnext = true;
            if($page*$rows>=$records){
                $hasnext = false;
            }
        }else{
            $total = 0;
            $hasnext = false;
            $value = [];
        }

        $data['page'] = $page;
        $data['total'] = $total;
        $data['hasnext'] = $hasnext;
        $data['data'] = $value;
        return result(200,"success",$data);
    }
    public function edit()
    {
        $machineid = input('get.machineid');
        $machine = MachineModel::get($machineid);
        $pics = $machine->pics;
        $groups = model('Machinegroups')::where('machineid','=',$machineid)->select();
        $groupvalues = '';
        $groupnames = '';
        foreach ($groups as $group) {
            $machinegroup = model('Machinegroup')::where('groupid', $group['typeid'])->find();
            if($machinegroup){
                $groupnames = $groupnames.$machinegroup['groupname'].' ';
                $groupvalues = $groupvalues.$machinegroup['groupid'].',';
            }

        }
        //
        foreach ($pics as $pic){
            $pic['path'] = Config::get('paths.coshost').$pic['url'];
        }

        return $this->fetch('editmachine',[
            'machineid'=>$machineid,
            'machine'=>$machine,
            'pics'=>$pics,
            'groupvalues'=>$groupvalues,
            'groupnames'=>$groupnames
        ]);
    }
    public function save()
    {
        if(!request()->isPost()){
            $this ->error('请求失败');
        }
        $openid = session('openid', '', 'wechatservice');
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $machineid = input('post.machineid');
        $machinename = input('post.machinename');
//        $sn = input('post.sn');
        $mobile = input('post.mobile');
        $location = input('post.location');
        $lon = input('post.lon');
        $lat = input('post.lat');
        $dailaddress = input('post.dailaddress');
        $groups = input('post.groups');
        $picurl = input('post.picurl/a');
        $data['machinename'] = $machinename;
        $data['mobile'] = $mobile;
        $data['location'] = $location;
        $data['lon'] = $lon;
        $data['lat'] = $lat;
        $data['dailaddress'] = $dailaddress;
        $data['updater'] = $sysuser['userid'];
        $data['updatetime'] = Date('Y-m-d H:i:s');
        $machineModel = new MachineModel();
        $res = $machineModel->save($data,['machineid'=>$machineid]);
        //groups
        if($groups!=null&&$groups!=''){
            $typeids = rtrim($groups, ',');
            $typeids = explode(',',$typeids);
            model('Machinegroups')::where('machineid','=',$machineid)->delete();
            foreach ($typeids as $typeid) {
                $group = new Machinegroups();
                $group['groupsid'] = uuid();
                $group['typeid'] = $typeid;
                $group['machineid'] = $machineid;
                $res = $group->save();
            }
        }
        if($picurl!=null&&count($picurl)>0){
//            $premachinepics = model('Machinepics')::where('machineid','=',$machineid)->select();
            //update pics

            model('Machinepics')::where('machineid','=',$machineid)->delete();

            //
            foreach ($picurl as $pic) {
                $machinepic = model('Machinepics')::where('url','=',$pic)->select();
                if($machinepic&&count($machinepic)>0){//已经存在

                }else{
                    $machinepic = new MachinepicsModel;
                    $machinepic['picid'] = uuid();
                    $machinepic['machineid'] = $machineid;
                    $machinepic['url'] = $pic;
                    $machinepic['createtime'] = Date('Y-m-d H:i:s');
                    $machinepic->save();
                }

            }

        }

        if($res){
            $this -> success('更新成功');
        }else{
            $this ->error('更新失败');
        }
    }
    public function addgroup(){
        $label = input('post.label');
        $openid = session('openid', '', 'wechatservice');
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $merchantid = $sysuser['merchantid'];
//        $merchantid = 'e2fb88210b6b91dcaa86ae2078edd8cd';
        $group = new Machinegroup();
        $group['groupid'] = uuid();
        $group['merchantid'] = $merchantid;
        $group['groupname'] = $label;
        $group['groupstatus'] = 0;
        $res = $group->save();
        return result(200,'success',$group['groupid']);
    }
    public function grouplist(){
        $openid = session('openid', '', 'wechatservice');
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $merchantid = $sysuser['merchantid'];
        $result = model('Machinegroup')::where('merchantid','=',$merchantid)->select();
        return result(200,"success",$result);
    }
        //机柜秤台监控
    public function weight()
    {
        $machineid = input('get.machineid');
        $machine = MachineModel::get($machineid);
        $shelfList = model('Shelf')->getList($machine['machineid']);
        $machine['floors'] = $shelfList;
        return $this->fetch('weight',[
            'machine'=>$machine,
        ]);
    }
    public function weightlist()
    {
        $machineid = input('post.machineid');
        $machine = MachineModel::get($machineid);
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_id'] = $machine['boxdevid'];
//        $option['pos'] = '010101';
        $masterresult = $gboxApi->getWeight($option);
        if($masterresult['code'] == 0){
            $weights = $masterresult['output']['weight_list'];
            return result(200,"success",$weights);
        }else{
            $weights = [];
            return result(200,"success",$weights);
        }
    }
    //货柜商品清空
    public function clearpos()
    {
        $machineid = input('get.machineid');
        $machine = MachineModel::get($machineid);
        if($machine){
            $shelfList = model('Shelf')->getList($machine['machineid']);
            $gboxApi = new \GoboxApi('','');
            $option = [];
            $option['dev_id'] = $machine['boxdevid'];
//        $option['pos'] = '010101';
            $masterresult = $gboxApi->querySkuBox($option);
            if($masterresult['code'] == 0){
                $machine['nums'] = $masterresult['output']['nums'];
                $skuList = $masterresult['output']['sku_list'];
                foreach ($shelfList as $shelf) {
                    $shelfGoods = [];
                    foreach ($skuList as $sku) {
                        if($sku['pos'] == $shelf['pos']){
                            $goods = GoodsModel::get(['goodsid' => $sku['barcode']]);
                            if($goods){
                                $goods['picurl'] = Config::get('paths.coshost').$goods['picurl'];
                                array_push($shelfGoods,$goods);
                            }
                        }
                    }
                    $shelf['goods'] = $shelfGoods;
                }
            }else{
                foreach ($shelfList as $shelf) {
                    $shelfGoods = [];
                    $shelf['goods'] = $shelfGoods;
                }
            }
            $machine['floors'] = $shelfList;
        }
        return $this->fetch('clearpos',[
            'machine'=>$machine,
        ]);
    }
    public function subclearpos()
    {
        $machineid = input('post.machineid');
        $pos = input('post.pos');//-1 全部清空
        $machine = MachineModel::get($machineid);
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_id'] = $machine['boxdevid'];
        $newskulist = [];
        if($pos == -1){
            $option['sku_list'] = [];
            $masterresult = $gboxApi->fullSyncSku($option);
            if($masterresult['code'] == 0){
                return result(200,"success",'');
            }else{
                return result(201,"全部清空失败",'清空失败');
            }
        }else{
            $masterresult = $gboxApi->querySkuBox($option);
            if($masterresult['code'] == 0){
                $skuList = $masterresult['output']['sku_list'];
                foreach ($skuList as $sku) {
                    if($sku['pos'] != $pos){
                        $newsku = [];
                        $newsku['pos'] = $sku['pos'];
                        $newsku['barcode'] = $sku['barcode'];
                        array_push($newskulist,$newsku);
                    }
                }
                $option['sku_list'] = $newskulist;
                $masterresult2 = $gboxApi->fullSyncSku($option);
                if($masterresult2['code'] == 0){
                    return result(200,"success",'');
                }else{
                    return result(201,"清空货架失败",'清空失败');
                }
            }else{
                return result(201,"获取机柜sku列表失败",'清空失败');
            }
        }
        return result(200,"success",'');

    }

}
