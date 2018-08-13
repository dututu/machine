<?php
namespace app\clientapi\controller;
use think\Controller;
use think\Db;
use app\clientapi\service\Token;
use \think\Log;
use think\Loader;
use think\Config;
use app\common\model\Machine as MachineModel;
use app\common\model\Shelf as ShelfModel;
use app\common\model\Goods as GoodsModel;

Loader::import('master.RfidApiV2', EXTEND_PATH, '.php');
Loader::import('master.GoboxApi', EXTEND_PATH, '.php');
/**
 * 机柜相关接口
 */
class Machine extends BaseController
{
    private  $obj;
    public function _initialize() {
//        $this->obj = model("Device");
    }
//    protected $beforeActionList = [
//        'checkExclusiveScope' => ['only' => 'nearbymachines'],
//        'checkPrimaryScope' => ['only' => 'getDetail,getSummaryByUser'],
//        'checkSuperScope' => ['only' => 'delivery,getSummary']
//    ];

    /**
     * 获取指定坐标附近的机柜（1公里）
     * @param lon lat 经度 纬度
     * @return 机柜列表(机柜id 机柜名称 详细地址 经度 纬度 机柜状态 机柜中商品分类的列表)
     */
    public function nearbymachines()
    {
        $lon = input('get.lon');
        $lat = input('get.lat');
        $distance = input('get.distance');
        $machineModel = new MachineModel();
        $machineList = $machineModel->getNearbyList($lon, $lat, $distance);
        foreach ($machineList as $machine) {
            $mash = MachineModel::get($machine['machineid']);
            $pics = $mash->pics;
            $machine['pics'] = $pics;
        }
        return result(200, "success", $machineList);
    }
    /**
     * 机柜详情
     * @param 机柜id
     * @return 详情
     */
    public function machinedetail() {

        $machineid = input('get.machineid');
        $machine = MachineModel::with('pics')->find($machineid);
        foreach ($machine['pics'] as $pic) {
            $pic = Config::get('paths.coshost').$pic;
        }
        return result(200,"success",$machine);

    }
    /**
     * 首页点击机柜展示机柜详情
     * @param 机柜id
     * @return 详情
     */
    public function indexmachinedetail() {
        $lon = input('get.lon');
        $lat = input('get.lat');
        $machineid = input('get.machineid');
        $machine = MachineModel::get($machineid);
        if($machine['rfidtypecode'] == 3){//重力柜子
            //gbox平台库存
            $gboxApi = new \GoboxApi('','');
            $option = [];
            $option['dev_id'] = $machine['boxdevid'];
            $masterresult = $gboxApi->querySkuBox($option);
            $goodscatearray = [];
            if($masterresult['code'] == 0){
                $machine['nums'] = $masterresult['output']['nums'];
                $skuList = $masterresult['output']['sku_list'];
                foreach ($skuList as $sku) {
                    $goods = GoodsModel::get(['goodsid' => $sku['barcode']]);
                    if($goods){
                        $goodscategory = model('Goodscategory')::where('categoryid', $goods['goodscategoryid'])->find();
                        if($goodscategory){
                            $goodscategory['iconurl'] = Config::get('paths.coshost').$goodscategory['iconurl'];
                            if (!array_key_exists($goods['goodscategoryid'],$goodscatearray)){
                                $goodscatearray[$goods['goodscategoryid']] = $goodscategory;
                            }

                        }
                    }
                }
            }
            $machine['categorys'] = $goodscatearray;
        }else{
            //        $merchantid = $machine['merchantid'];
            //search params
            $containerids = [];
            array_push($containerids, $machine['containerid']);
            $pushdata = array(
                'serialsnumber' => uuid(),
                'containerids' => $containerids,
                'timestamp' => time(),
            );
            //需从rfid平台拉取商品列表
            $wxOrderData = new \RfidApiV2('','');
            $orderresult = $wxOrderData->selCabinetStockInfo($pushdata);
            $result = json_decode($orderresult,true);
            $goodscatearray = [];
            if($result&&$result['code'] == 200){
                foreach ($result['containerids'][$machine['containerid']] as $returngoods) {
                    $goods = model('Goods')::where('goodsid', $returngoods['barCode'])->find();
                    if($goods){
                        $goodscategory = model('Goodscategory')::where('categoryid', $goods['goodscategoryid'])->find();
                        if($goodscategory){
                            $goodscategory['iconurl'] = Config::get('paths.coshost').$goodscategory['iconurl'];
                            if (!array_key_exists($goods['goodscategoryid'],$goodscatearray)){
                                $goodscatearray[$goods['goodscategoryid']] = $goodscategory;
                            }

                        }


                    }
                }
            }
            $machine['categorys'] = $goodscatearray;
        }

        $dis = GetDistance($machine['lat'],$machine['lon'],$lat,$lon);
        $machine['distance'] = $dis;
        return result(200,"success",$machine);

    }
    /**
     * 机柜商品
     * @param 机柜id
     * @return 详情
     */
    public function goods() {
        $machineid = input('get.machineid');
        $salesort = input('get.salesort');
        $machine = MachineModel::get($machineid);
//        $merchantid = $machine['merchantid'];
        if($machine['rfidtypecode'] == 3){//重力柜子
            //gbox平台库存
            $gboxApi = new \GoboxApi('','');
            $option = [];
            $option['dev_id'] = $machine['boxdevid'];
            $masterresult = $gboxApi->querySkuBox($option);
            $goodsarray = [];
            if($masterresult['code'] == 0){
                $machine['nums'] = $masterresult['output']['nums'];
                $skuList = $masterresult['output']['sku_list'];
                foreach ($skuList as $sku) {
                    $goods = GoodsModel::get(['goodsid' => $sku['barcode']]);
                    if($goods){
                        $goods['amount'] = $sku['count'];//库存
                        $goods['picurl'] = Config::get('paths.coshost').$goods['picurl'];
                        array_push($goodsarray,$goods);
                    }
                }
            }
            $machine['goods'] = $goodsarray;
        }else{
            //search params
            $containerids = [];
            array_push($containerids, $machine['containerid']);
            $pushdata = array(
                'serialsnumber' => uuid(),
                'containerids' => $containerids,
                'timestamp' => time(),
            );
            //需从rfid平台拉取商品列表
            $wxOrderData = new \RfidApiV2('','');
            $orderresult = $wxOrderData->selCabinetStockInfo($pushdata);
            $result = json_decode($orderresult,true);
            $goodsarray = [];
            if($result&&$result['code'] == 200){
                foreach ($result['containerids'][$machine['containerid']] as $returngoods) {
                    $goods = model('Goods')::where('goodsid', $returngoods['barCode'])->find();
                    if($goods){
                        $goods['amount'] = $returngoods['amount'];//库存
                        $goods['picurl'] = Config::get('paths.coshost').$goods['picurl'];
                        //按销量查询
                        if($salesort == '1'){
                            $sql = 'SELECT sum(a.amount) t FROM goodsorderdetail a where  a.goodsid =?';
                            $value = Db::query($sql, [$goods['goodsid']]);
                            $totalsales = (int)reset($value)['t'];
                            $goods['totalsales'] = $totalsales;
                        }
                        array_push($goodsarray,$goods);

                        if($salesort == '1'){
                            uasort($goodsarray,function($a,$b){
                                return $a['totalsales'] < $b['totalsales'];
                            });
                        }
                    }
                }
            }
            $machine['goods'] = $goodsarray;
        }

        //orderby and page param
        $page = input("page");
        $rows = input("rows");
//        $offset = (input("page") - 1) * input("rows");
//        $value = model('Goods')->getGoodsList($rows,$offset,$merchantid);
//        $records = model('Goods')->getGoodsListCount($merchantid);
//        $total = ceil($records/$rows);
//        $hasnext = true;
//        if($page*$rows>=$records){
//            $hasnext = false;
//        }
        $data['page'] = $page;
        $data['total'] = count($machine['goods']);//$total;
        $data['hasnext'] = false;//$hasnext;
        $data['data'] = $machine['goods'];//$value;
        return result(200,"success",$data);

    }
    //获取货架列表
    function shelfs(){
        $machineid = input('get.machineid');
        $shelfs = model('Shelf')::where('machineid', '=', $machineid)->order('floor', 'asc')->select();
        foreach ($shelfs as $key => $value){
            $shelfs[$key]['weight'] = 0;
        }
        return result(200,"success",$shelfs);
    }

}
