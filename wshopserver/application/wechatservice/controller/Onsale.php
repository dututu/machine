<?php
namespace app\wechatservice\controller;
use think\Controller;
use think\Config;
use think\Db;
use think\Log;
use think\Loader;
use app\lib\enum\OnsaleStatusEnum;
use app\common\model\Goodsorder as GoodsorderModel;
use app\common\model\Goodsorderpay as GoodsorderpayModel;
use app\common\model\Goods as GoodsModel;
use app\common\model\Goodsorderdetail as GoodsOrderDetailModel;
use app\common\model\Outrefund as OutrefundModel;
use app\common\model\Machinegroup;
use app\common\model\Machinegroups;
use app\common\model\Machine as MachineModel;
use app\common\model\Shelf as ShelfModel;
use app\common\model\Machinepics as MachinepicsModel;
use app\common\model\Onsalehistory as OnsaleModel;
use app\common\model\Onsaledetail as OnsaleDetailModel;
use app\common\model\Onsalehistory as OnsaleHistoryModel;
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
Loader::import('master.GoboxApi', EXTEND_PATH, '.php');
/**
 * 上货
 *
 * @author      Caesar
 * @version     1.0
 */
class Onsale extends  Base //Base
{
    protected $beforeActionList = [
        'checkSession'
    ];

    public function index()
    {
//        return $this->fetch('machinelist',[
////            'categorys'=>$categorys,
//        ]);

        $openid = session('openid', '', 'wechatservice');
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $merchantid = $sysuser['merchantid'];
        $remaincount = model('Taglib')->getCanUseCountByMerchantId($merchantid);
        $totalcount = model('Rfidorderdetail')->getTotalCountByMerchant($merchantid);

        return $this->fetch('index',[
            'remaincount'=>$remaincount,
            'totalcount'=>$totalcount
        ]);

    }
    /**
     * 首页我的标签
     * @return
     */
    public function tagliblist()
    {
        $openid = session('openid', '', 'wechatservice');
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $merchantid = $sysuser['merchantid'];
//        $merchantid = '516452bf528b3803fd0e6ed63c6106ec';
        //search params
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        if($merchantid!=null&&$merchantid!=''){
            $offset = (input("page") - 1) * input("rows");
            $value = model('Taglib')->rfidlist($rows,$offset,$merchantid);
            foreach ($value as $item){
                $item['picurl'] = Config::get('paths.coshost').$item['picurl'];
                $totalrfid = model('Rfidorderdetail')->getCountById($item['goodsid']);
                $item['usedcount'] = $totalrfid - $item['rfidcount'];
            }
            $records = model('Taglib')->rfidlistcount($merchantid);
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

    public function weightbox()
    {
        return $this->fetch('weightbox',[
//            'categorys'=>$categorys,
        ]);
    }
    public function boxplan()
    {
        $machineid = input('get.machineid');
        $openid = session('openid', '', 'wechatservice');
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $merchantid = $sysuser['merchantid'];
        $machine = MachineModel::get($machineid);
        if($merchantid!=null&&$merchantid!=''){
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
        return $this->fetch('boxplan',[
            'machine'=>$machine,
        ]);
    }
    /**
     * 根据creater来获取自己创建的机柜信息
     * @return 机柜列表(机柜id 机柜名称 详细地址 经度 纬度 机柜状态 机柜中商品分类的列表)
     */
    public function machinelist()
    {
        $openid = session('openid', '', 'wechatservice');
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $merchantid = $sysuser['merchantid'];
//        $merchantid = '516452bf528b3803fd0e6ed63c6106ec';
        //search params
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $rfidtypecodes='3';
        if($merchantid!=null&&$merchantid!=''){
            $typeids = input("searchparams");//2,3,4,
            $offset = (input("page") - 1) * input("rows");
            $value = model('Machine')->getMachineList($rows,$offset,$merchantid,$typeids,$rfidtypecodes);
            foreach ($value as $machine) {
//                $mash = MachineModel::get($machine['machineid']);
                $shelfList = model('Shelf')->getList($machine['machineid']);

                $gboxApi = new \GoboxApi('','');
                $option = [];
                $option['dev_id'] = $machine['boxdevid'];
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
//                $pics = $mash->pics;
//                foreach ($pics as $pic){
//                    $pic['url'] = Config::get('wx.host').'/'.$pic['url'];
//                }

            }
            $records = model('Machine')->getMachineListCount($merchantid,$typeids,$rfidtypecodes);
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
    public function onsalegoods()
    {
        $shelfid = input("shelfid");
        $machineid = input("machineid");
        return $this->fetch('onsalegoods',[
            'shelfid'=>$shelfid,
            'machineid'=>$machineid,
        ]);
    }

    public function subonsale()
    {
        $shelfid = input('post.shelfid');
        $machineid = input('post.machineid');
        $goods = input('post.goods/a');
        $machine = MachineModel::get($machineid);
        $shelf = ShelfModel::get($shelfid);
        $onsuccess = true;
        $message = '';
        foreach ($goods as $goodsid) {
            $goods = GoodsModel::get($goodsid);
            $option = [];
            $option['time'] = time();
            $option['dev_id'] = $machine['boxdevid'];
            $option['pos'] = $shelf['pos'];
            $option['barcode'] = $goods['goodsid'];
            $option['check_level'] = 1;
            $gboxApi = new \GoboxApi('','');
            $masterresult = $gboxApi->addSkuBox($option);
            if($masterresult['code'] != 0){
                $onsuccess = false;
//                if($masterresult['msg']['code'] == 3){
////                    $barcode1 = $masterresult['msg']['code'];
//                    $message = $message.' '.$goods['goodsname'].'上架失败:商品质量存在倍数关系';
//                }else{
//                    $message = $message.' '.$goods['goodsname'].'上架失败';
//                }
                $message = $message.' '.$goods['goodsname'].'上架失败:请检查商品质量是否相同或存在倍数关系';
            }
        }
        $data['message'] = $message;
        if($onsuccess){
            return result(1,'success',$data);
        }else{
            return result(0,'fail',$data);
        }

    }

    public function offsalegoods()
    {
        $shelfid = input("shelfid");
        $machineid = input("machineid");
        return $this->fetch('offsalegoods',[
            'shelfid'=>$shelfid,
            'machineid'=>$machineid,
        ]);
    }

    public function suboffsale()
    {
        $shelfid = input('post.shelfid');
        $machineid = input('post.machineid');
        $goods = input('post.goods/a');
        $machine = MachineModel::get($machineid);
        $shelf = ShelfModel::get($shelfid);
        $onsuccess = true;
        $message = '';
        foreach ($goods as $goodsid) {
            $goods = GoodsModel::get($goodsid);
            $option = [];
            $option['time'] = time();
            $option['dev_id'] = $machine['boxdevid'];
            $option['pos'] = $shelf['pos'];
            $option['barcode'] = $goods['goodsid'];
//            $option['check_level'] = 2;
            $gboxApi = new \GoboxApi('','');
            $masterresult = $gboxApi->delSkuBox($option);
            if($masterresult['code'] != 0){
                $onsuccess = false;
                $message = $message.' '.$goods['goodsname'].'下架失败:'.$masterresult['code'];
            }
        }
        $data['message'] = $message;
        if($onsuccess){
            return result(1,'success',$data);
        }else{
            return result(0,'fail',$data);
        }

    }

    public function floorgoodslist(){
        $openid = session('openid', '', 'wechatservice');
//        $merchantid = $this->getMerchantIdByOpenId($openid);
//        $merchantid = '516452bf528b3803fd0e6ed63c6106ec';
        $shelfid = input("shelfid");
        $machineid = input("machineid");

        $machine = MachineModel::get($machineid);
        $shelf = ShelfModel::get($shelfid);
        $goodsList = [];
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_id'] = $machine['boxdevid'];
        $masterresult = $gboxApi->querySkuBox($option);
        if($masterresult['code'] == 0){
            $skuList = $masterresult['output']['sku_list'];
            foreach ($skuList as $sku) {
                if($sku['pos'] == $shelf['pos']){
                    $goods = GoodsModel::get(['goodsid' => $sku['barcode']]);
                    if($goods){
                        $goods['picurl'] = Config::get('paths.coshost').$goods['picurl'];
                        array_push($goodsList,$goods);
                    }
                }

            }
        }

        $data['data'] = $goodsList;
        return result(200,"success",$data);
    }
    //上货调整
    public function onsalecheck()
    {
        $machineid = input('get.machineid');
        $userid = input('get.userid');
        $historyid = input('get.historyid');
        //
//        $machineid = 'aebb641f8be3895eadfea62786937628';
        //        $openid = session('openid', '', 'wechatservice');
//        $sysuser = model('Sysuser')::where('openid', $openid)->find();
//        $merchantid = $sysuser['merchantid'];
//        $merchantid = '516452bf528b3803fd0e6ed63c6106ec';
        $machine = MachineModel::get($machineid);
        $shelfList = model('Shelf')->getList($machine['machineid']);
        $gboxApi = new \GoboxApi('','');
        $option = [];
        $option['dev_id'] = $machine['boxdevid'];
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
                            $goods['count'] = $sku['count'];
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
        return $this->fetch('onsalecheck',[
            'machine'=>$machine,
            'userid'=>$userid,
            'historyid'=>$historyid,
        ]);
    }
    public function savecheck()
    {
        $machineid = input('post.machineid');
        $userid = input('post.userid');
        $historyid = input('post.historyid');
//        $machineid = 'aebb641f8be3895eadfea62786937628';
        $adds = input('post.adds/a');
        $mins = input('post.mins/a');
        $machine = MachineModel::get($machineid);
        $onsuccess = true;
        $message = '';
        //
        $onsalecount = 0;
        $offsalecount = 0;
        //理货订单
        $onsaleModel = OnsaleHistoryModel::where('historyid', '=', $historyid)->find();;
        //
        if(is_array($adds)){
            foreach ($adds as $add) {
                $goodsid = $add[0];
                $pos = $add[1];
                $count = $add[2];
                if($count > 0){
                    $goods = GoodsModel::get($goodsid);
                    $option = [];
                    $option['time'] = time();
                    $option['option'] = 0;
                    $option['dev_id'] = $machine['boxdevid'];
                    $option['pos'] = $pos;
                    $option['barcode'] = $goodsid;
                    $option['num'] = $count;
                    $option['memo'] = '';
                    $gboxApi = new \GoboxApi('','');
                    $masterresult = $gboxApi->updateSkuCount($option);
                    if($masterresult['code'] != 0){
                        $onsuccess = false;
                        $message = $message.' '.$goods['goodsname'].'操作失败';
                    }else{
                        $onsalecount = $onsalecount + $count;
                        $goodsModel = GoodsModel::get(['goodsid' => $goodsid]);
                        if($goodsModel){
                            $detailModel = new OnsaleDetailModel;
                            $detailModel['detailid'] = uuid();
                            $detailModel['onsaleid'] = $historyid;
                            $detailModel['goodsid'] = $goodsModel['goodsid'];
                            $detailModel['goodsname'] = $goodsModel['goodsname'];
                            $detailModel['spec'] = $goodsModel['spec'];
                            $detailModel['unitfee'] = $goodsModel['salefee'];
                            $detailModel['amount'] = $count;
                            $detailModel['flag'] = 0;
                            $detailModel->save();
                        }else{
                            Log::info('主控云平台推送 订单结果（理货） --商品不存在--barcode:'.$goodsid);
                        }
                    }
                }

            }
        }
        if(is_array($mins)){
            foreach ($mins as $min) {
                $goodsid = $min[0];
                $pos = $min[1];
                $count = $min[2];
                if($count>0){
                    $goods = GoodsModel::get($goodsid);
                    $option = [];
                    $option['time'] = time();
                    $option['option'] = 1;
                    $option['dev_id'] = $machine['boxdevid'];
                    $option['pos'] = $pos;
                    $option['barcode'] = $goodsid;
                    $option['num'] = $count;
                    $option['memo'] = '';
                    $gboxApi = new \GoboxApi('','');
                    $masterresult = $gboxApi->updateSkuCount($option);
                    if($masterresult['code'] != 0){
                        $onsuccess = false;
                        $message = $message.' '.$goods['goodsname'].'操作失败';
                    }else{
                        $offsalecount = $offsalecount + $count;
                        $goodsModel = GoodsModel::get(['goodsid' => $goodsid]);
                        if($goodsModel){
                            $detailModel = new OnsaleDetailModel;
                            $detailModel['detailid'] = uuid();
                            $detailModel['onsaleid'] = $historyid;
                            $detailModel['goodsid'] = $goodsModel['goodsid'];
                            $detailModel['goodsname'] = $goodsModel['goodsname'];
                            $detailModel['spec'] = $goodsModel['spec'];
                            $detailModel['unitfee'] = $goodsModel['salefee'];
                            $detailModel['amount'] = $count;
                            $detailModel['flag'] = 1;
                            $detailModel->save();
                        }else{
                            Log::info('主控云平台推送 订单结果（理货） --商品不存在--barcode:'.$goodsid);
                        }
                    }
                }

            }
        }

        $onsaleModel['status'] = OnsaleStatusEnum::COMPLETED;//理货完成
        $onsaleModel['offsalecount'] = $offsalecount;
        $onsaleModel['onsalecount'] = $onsalecount;
        $onsaleModel->save();
        //保存上货订单结束
        $data['message'] = $message;
        if($onsuccess){
            return result(1,'success',$data);
        }else{
            return result(0,'fail',$data);
        }

    }

    public function ongoodslist(){
        $openid = session('openid', '', 'wechatservice');
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $merchantid = $sysuser['merchantid'];
        //
        $machineid = input('get.machineid');
        $machine = MachineModel::get($machineid);
        $skuList = [];
        if($merchantid!=null&&$merchantid!=''){
            $gboxApi = new \GoboxApi('','');
            $option = [];
            $option['dev_id'] = $machine['boxdevid'];
            $masterresult = $gboxApi->querySkuBox($option);
            if($masterresult['code'] == 0){
                $machine['nums'] = $masterresult['output']['nums'];
                $skuList = $masterresult['output']['sku_list'];

            }
        }
        //
        $newgoods = [];
        //search params
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $categoryids = input("searchparams");//2,3,4,
        $offset = (input("page") - 1) * input("rows");
        $value = model('Goods')->getGoodsList($rows,$offset,$merchantid,$categoryids);
        foreach ($value as $goods){
            $exits = false;
            foreach ($skuList as $sku) {
                $goodsitem = GoodsModel::get(['goodsid' => $sku['barcode']]);
                if($goodsitem&&($goodsitem['goodsid'] == $goods['goodsid'])){
                    $exits = true;
                    break;
                }
            }
            if(!$exits){
                $goods['picurl'] = Config::get('paths.coshost').$goods['picurl'];
                $rfidspec = model('Rfidspec')::get($goods['rfidtypeid']);
                $goods['rfidtypename'] = $rfidspec['typename'];
                $category = model('Goodscategory')::get($goods['goodscategoryid']);
                $goods['goodscategoryname'] = $category['categoryname'];
                array_push($newgoods,$goods);
            }

        }
        $records = model('Goods')->getGoodsListCount($merchantid,$categoryids);
        $total = ceil($records/$rows);
        $hasnext = true;
        if($page*$rows>=$records){
            $hasnext = false;
        }
        $data['page'] = $page;
        $data['total'] = $total;
        $data['hasnext'] = $hasnext;
        $data['data'] = $newgoods;
        return result(200,"success",$data);
    }

}
