<?php
namespace app\wechatservice\controller;
use think\Controller;
use think\Config;
use think\Log;
use app\common\model\Machine as MachineModel;
use app\common\model\Machinepics as MachinepicsModel;
use app\common\model\Goods as GoodsModel;

use think\Loader;
Loader::import('master.MasterApi', EXTEND_PATH, '.php');
Loader::import('master.RfidApiV2', EXTEND_PATH, '.php');
Loader::import('master.GoboxApi', EXTEND_PATH, '.php');
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');

/**
 * 商品库存
 *
 * @author      Caesar
 * @version     1.0
 */
class Stock extends  Base //Base
{
    protected $beforeActionList = [
        'checkSession'
//        'checkSession' => ['only' => 'index']
    ];
    /**
     * 首页
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function index()
    {
        $openid = session('openid', '', 'wechatservice');
        return $this->fetch('stock',[
//                'merchantid'=>$merchant['merchantid'],
        ]);
    }
    public function onsale()
    {
        return $this->fetch('onsale', [
//                'merchantid'=>$merchant['merchantid'],
        ]);
    }
    /**
     * ajax获取机柜库存列表
     */
    public function stocklist(){
        $openid = session('openid', '', 'wechatservice');
        $merchantid = $this->getMerchantIdByOpenId($openid);
//        $merchantid = '516452bf528b3803fd0e6ed63c6106ec';
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $merchantid = $sysuser['merchantid'];
        //search params
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $offset = (input("page") - 1) * input("rows");
        if($merchantid!=null&&$merchantid!=''){
            $machinelist = model('Machine')->getMachineList($rows,$offset,$merchantid,'');
            $containerids = [];
            foreach ($machinelist as $machine) {
                $machine['goods'] = [];
                $machine['subgoods'] = [];
                if($machine['rfidtypecode'] == 1 || $machine['rfidtypecode'] == 2){
                    //从rfid平台拉取机柜商品
                    array_push($containerids, $machine['containerid']);
                }

            }
            if(count($containerids)>0){
                //rfid平台库存
                $pushdata = array(
                    'serialsnumber' => uuid(),
                    'containerids' => $containerids,
                    'timestamp' => time(),
                );
                $wxOrderData = new \RfidApiV2('','');
                $orderresult = $wxOrderData->selCabinetStockInfo($pushdata);
                $result = json_decode($orderresult,true);
                if($result&&$result['code'] == 200){
                    foreach ($machinelist as $machine) {
                        if($machine['rfidtypecode'] == 1 || $machine['rfidtypecode'] == 2){
                            $goodsarray = [];
                            $subgoods1 = [];
                            $subgoods2 = [];
                            foreach ($result['containerids'][$machine['containerid']] as $returngoods) {
                                $goods = model('Goods')::where('goodsid', $returngoods['barCode'])->find();

                                if($goods){
                                    $goods['amount'] = $returngoods['amount'];//库存
                                    $goods['picurl'] = Config::get('paths.coshost').$goods['picurl'];
                                    array_push($goodsarray,$goods);
                                }
                            }
                            if(count($goodsarray)>2){
                                $x = 0;
                                foreach ($goodsarray as $good) {
                                    if($x<2){
                                        array_push($subgoods1,$good);
                                    }else{
                                        array_push($subgoods2,$good);
                                    }
                                    $x++;
                                }

                                $machine['goods'] = $subgoods1;
                                $machine['subgoods'] = $subgoods2;
                            }else{
                                $machine['goods'] = $goodsarray;
                                $machine['subgoods'] = [];
                            }
                        }
                    }
                }
            }

            //gbox平台库存
            $gboxApi = new \GoboxApi('','');

            foreach ($machinelist as $machine) {
                if($machine['rfidtypecode'] == 3){
                    $option = [];
                    $option['dev_id'] = $machine['boxdevid'];
                    $masterresult = $gboxApi->querySkuBox($option);
                    $goodsarray = [];
                    $subgoods1 = [];
                    $subgoods2 = [];
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
                    if(count($goodsarray)>2){
                        $x = 0;
                        foreach ($goodsarray as $good) {
                            if($x<2){
                                array_push($subgoods1,$good);
                            }else{
                                array_push($subgoods2,$good);
                            }
                            $x++;
                        }

                        $machine['goods'] = $subgoods1;
                        $machine['subgoods'] = $subgoods2;
                    }else{
                        $machine['goods'] = $goodsarray;
                        $machine['subgoods'] = [];
                    }
                }
            }


            //
            $records = model('Machine')->getMachineListCount($merchantid,'');
            $total = ceil($records/$rows);
            $hasnext = true;
            if($page*$rows>=$records){
                $hasnext = false;
            }
        }else{
            $total = 0;
            $hasnext = false;
            $machinelist = [];
        }

        $data['page'] = $page;
        $data['total'] = $total;
        $data['hasnext'] = $hasnext;
        $data['data'] = $machinelist;
        return result(200,"success",$data);
    }
    /**
     * ajax获取上架记录列表
     *
     */
    public function onsalelist(){
        $openid = session('openid', '', 'wechatservice');
//        $merchantid = $this->getMerchantIdByOpenId($openid);
//        $merchantid = '6dd05bfbe5a047aef3f763c58dff2624';
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $merchantid = $sysuser['merchantid'];
        //search params
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $offset = (input("page") - 1) * input("rows");
        $value = model('Onsalehistory')->getOnsaleList($rows,$offset,$merchantid);
        foreach ($value as $onsale) {
            $goods = model('Onsaledetail')->getDetailList($onsale['historyid']);
            foreach ($goods as $subgoods) {
                if($subgoods['flag'] == 0){//上架
                    $subgoods['updowninfo'] = '+'.$subgoods['amount'];
                }else if($subgoods['flag'] == 1){//下架
                    $subgoods['updowninfo'] = '-'.$subgoods['amount'];
                }
                $subgoods['picurl'] = Config::get('paths.coshost').$subgoods['picurl'];
            }
            $onsale['goods'] = $goods;
        }
        //
        $records = model('Onsalehistory')->getOnsaleListCount($merchantid);
        $total = ceil($records/$rows);
        $hasnext = true;
        if($page*$rows>=$records){
            $hasnext = false;
        }
        $data['page'] = $page;
        $data['total'] = $total;
        $data['hasnext'] = $hasnext;
        $data['data'] = $value;
        return result(200,"success",$data);
    }

}
