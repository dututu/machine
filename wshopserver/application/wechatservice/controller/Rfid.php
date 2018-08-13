<?php
namespace app\wechatservice\controller;
use think\Controller;

use think\Log;
use think\Config;
use \think\Loader;
use app\common\model\Goods as GoodsModel;
use app\common\model\Rfidorder as RfidOrderModel;
use app\common\model\Rfidorderdetail as RfidOrderDetailModel;
use app\common\model\Rfidspec as RfidSpecModel;
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
/**
 * Rfid
 *
 * @author      Caesar
 * @version     1.0
 */
class Rfid extends  Base //Base
{
    protected $beforeActionList = [
        'checkSession'
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
    public function orders()
    {

        $openid = session('openid', '', 'wechatservice');
        return $this->fetch('rfidorders',[
//            'categorys'=>$categorys,
        ]);

    }

    public function rfidorderlist(){
        $openid = session('openid', '', 'wechatservice');
        $sysuser = model('Sysuser')::where('openid', $openid)->find();
        $merchantid = $sysuser['merchantid'];
        //search params
        $orderstatus = input("orderstatus");
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $offset = (input("page") - 1) * input("rows");
        $value = model('Rfidorder')->getOrderList($rows,$offset,$orderstatus,$merchantid);
        //orderdetail start
        foreach ($value as $item) {
            $join = [
                ['goods g','a.goodsid=g.goodsid','LEFT'],
                ['rfidspec r','a.rfidspec=r.specid','LEFT']
            ];
            $condition['orderid'] = $item['orderid'];
            $goods = model('Rfidorderdetail')->where($condition)
                ->alias('a')
                ->join($join)
                ->field('a.rfidcount,a.unitfee,a.totalfee,g.*,r.typename')
                ->select();
            $item['goods'] = $goods;
            if($item['orderstatus'] == 1){
                $item['payfee'] = $item['totalfee']+$item['freightfee'];
            }

        }
        //orderdetail end
        $records = model('Rfidorder')->getOrderListCount($orderstatus,$merchantid);
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

    public function buyrfid()
    {
        $rfidfreefee = model('Sysparam')
        ->where('paramid', 'rfidfreefee')
        ->find();//免运费金额
        $rfidperorderfreight = model('Sysparam')
            ->where('paramid', 'rfidperorderfreight')
            ->find();//每笔订单运费
        return $this->fetch('buyrfid',[
            'rfidfreefee'=>intval($rfidfreefee['paramvalue']),
            'rfidperorderfreight'=>intval($rfidperorderfreight['paramvalue']),
        ]);
    }
    public function generateOrder()
    {
        $openid = session('openid', '', 'wechatservice');
        if($openid){
            $merchantid = $this->getMerchantIdByOpenId($openid);
            $sysuserid = $this->getUserIdByOpenId($openid);
            $location = input('post.location');
            $username = input('post.username');
            $mobile = input('post.mobile');
            $rgoodsid = input('post.rgoodsid/a');
            $rfidcount = input('post.rfidcount/a');
            $totalfee = input('post.ftotalfee');
            $freightfeevalue = input("post.freightfeevalue");
            $totalcount = 0;
            foreach ($rfidcount as $count) {
                $totalcount = $totalcount+$count;
            }
            //
            $rfidorder = new RfidOrderModel;
            $orderid = uuid();
            $orderno = makeOrderNo();
            $rfidorder['orderid'] = $orderid;
            $rfidorder['orderno'] = $orderno;
            $rfidorder['serialno'] = uuid();
            $rfidorder['merchantid'] = $merchantid;
            $rfidorder['sysuserid'] = $sysuserid;
            $rfidorder['totalcount'] = $totalcount;
            $rfidorder['totalfee'] = $totalfee;
            $rfidorder['payfee'] = 0;
            $rfidorder['freightfee'] = $freightfeevalue;
            $rfidorder['paytype'] = 1;
            $rfidorder['orderstatus'] = 1;
            $rfidorder['createtime'] = Date('Y-m-d H:i:s');
            $rfidorder['receiver'] = $username;
            $rfidorder['mobile'] = $mobile;
            $rfidorder['dailaddress'] = $location;
            $rfidorder->save();
            //
            for ($x=0; $x<count($rgoodsid); $x++) {
                $goodsid = $rgoodsid[$x];
                $goodsModel = GoodsModel::get($goodsid);
                $rfidspec = RfidSpecModel::get($goodsModel['rfidtypeid']);
                $rfidorderdetail = new RfidOrderDetailModel;
                $rfidorderdetail['detailid'] = uuid();
                $rfidorderdetail['orderid'] = $orderid;
                $rfidorderdetail['goodsid'] = $goodsid;
                $rfidorderdetail['rfidspec'] = $goodsModel['rfidtypeid'];
                $rfidorderdetail['rfidcount'] = $rfidcount[$x];
                $rfidorderdetail['unitfee'] = $rfidspec['unitfee'];
                $rfidorderdetail['totalfee'] = $rfidspec['unitfee']*$rfidcount[$x];
                $rfidorderdetail->save();
            }
            $data['totalfee'] = $rfidorder['totalfee']+$rfidorder['freightfee'];
            $data['orderno'] = $orderno;
            return result(1,'success',$data);
        }



    }
    public function orderresult(){
        $orderno = input('get.orderno');
        $totalfee = input('get.totalfee');
        # 配置参数
        $config = Config::get('wx.wxconfig');
        //js签名
        // 创建SDK实例
        $script = &\Wechat\Loader::get('Script',$config);
        // 获取JsApi使用签名，通常这里只需要传 $url参数
        $appid = Config::get('wx.wxappid');
        $url = Config::get('wx.host').'wechatservice/rfid/orderresult?totalfee='.$totalfee.'&orderno='.$orderno;//当前页面URL地址
        $options = $script->getJsSign($url, 0, '', $appid);

        // 处理执行结果
        if($options===FALSE){
            // 接口失败的处理
            echo $script->errMsg;
            $msg = $script->errMsg;
            dump($msg);
        }else{
            // 实例支付接口
            $pay = &\Wechat\Loader::get('Pay',$config);
            // 获取预支付ID
            $openid = session('openid', '', 'wechatservice');
            $body = 'rfid标签购买';
            $out_trade_no = $orderno;
            $total_fee = $totalfee;
            $notify_url = config('wx.js_pay_back_url');
            $result = $pay->getPrepayId($openid, $body, $out_trade_no, $total_fee, $notify_url, $trade_type = "JSAPI");

            // 处理创建结果
            if($result===FALSE){
                // 接口失败的处理
                $msg = $pay->errMsg;
                Log::info($pay->errMsg);
                return $this->fetch('orderresult',[
                    'msg'=>$msg,
                ]);

            }else{
                // 接口成功的处理
                // 创建JSAPI签名参数包
                $payoptions = $pay->createMchPay($result);//$prepayid
                Log::info(json_encode($options));
                Log::info(json_encode($payoptions));
//                {"appId":"wxdee00ccdb3bec1a6","timeStamp":"1513610438","nonceStr":"vl01x32lvdqcc5khrzluklozeem390wg","package":"prepay_id=wx20171218231734b56ee454170815511812","signType":"MD5","paySign":"AA8D480E597263F7A69557EBD0F8957B","timestamp":"1513610438"}
                return $this->fetch('orderresult',[
                    'options'=>json_encode($options),
                    'payoptions'=>json_encode($payoptions),
                    'totalfee'=>$totalfee
                ]);
            }
        }



    }

}
