<?php
namespace app\admin\controller;
use think\Controller;
use think\Log;
use think\Loader;
use think\Config;
use think\Request;
use think\Db;

use app\common\model\Balancewxpaybill;
use app\common\model\Balancewxpaybilldetail;
use app\common\model\Goodsorderpay;
use app\common\model\Merchant;
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
/**
 * TODO
 * 商户对账（放一放）
 *
 * @author      Caesar
 * @version     1.0
 */
class Checkaccount extends Controller
{
    // protected $beforeActionList = [
    //     'checkAuth' => ['only' => 'index']
    // ];
    public function _initialize() {
        parent::_initialize();
        $this->wxbill = model("Balancewxpaybill");
        $this->wxbilldetail = model("Balancewxpaybilldetail");
        $this->orderpay = model("Goodsorderpay");//用户订单(小程序)
        $this->rfidorder = model("Rfidorder");//商户订单(公众号)
        $this->platebill = model("Balanceplatformaccount");//平台账单(公众号)
        $this->platebilldetail = model("balanceplatformaccountdetail");//平台账单详情(公众号)
        $this->merchant = model("Merchant");//商户
        $this->goodsorder = model("Goodsorder");//订单表
        $this->goodsrefund = model("Outrefund");//退款表
        $this->merchantbill = model("Balancebusinessaccount");//商户账单(公众号)
        $this->merchantbilldetail = model("Balancebusinessaccountdetail");//商户账单详情(公众号)
    }
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
        //已经对账
        $result['complete'] = $this->platebill->billCount(1);
        //未出账
        $result['uncomplete'] = $this->platebill->billCount(0);

        return $this->fetch('index',$result);
    }
    /**
     * 账单列表
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function list()
    {
        $accountid = input('get.accountid');
        $page = input('get.page');

        
        $accountInfo = $this->getPlateListInfo($accountid);
        $accountInfo['page'] = $page;
        return $this->fetch('platebill_list',$accountInfo);
    }
    public function getPlateListInfo($accountid) 
    {
        $accountInfo = $this->platebill->where('accountid',$accountid)->find()->toArray();
        if($accountInfo['billtype']==1) {
            $billGoodbuyIn = $this->platebilldetail->where('accountid',$accountid)->where('payresult',1)->where('billtype',1)->sum('billfee')+0;
            $billGoodbuyOut = $this->platebilldetail->where('accountid',$accountid)->where('payresult',2)->where('billtype',1)->sum('billfee')+0;
            $billRecharge = $this->platebilldetail->where('accountid',$accountid)->where('billtype',2)->sum('billfee')+0;
            $orderGoodbuyIn = $this->platebilldetail->where('accountid',$accountid)->where('payresult',1)->where('billtype',1)->sum('payfee')+0;
            $orderGoodbuyOut = $this->platebilldetail->where('accountid',$accountid)->where('payresult',2)->where('billtype',1)->sum('payfee')+0;
            $orderRecharge = $this->platebilldetail->where('accountid',$accountid)->where('billtype',2)->sum('payfee')+0;
            $accountInfo['billgood'] = $billGoodbuyIn-$billGoodbuyOut;
            $accountInfo['billrecharge'] = $billRecharge;
            $accountInfo['ordergood'] = $orderGoodbuyIn-$orderGoodbuyOut;
            $accountInfo['orderrecharge'] = $orderRecharge;
            return $accountInfo;
        } else if($accountInfo['billtype']==2) {
            return $accountInfo;
        }
    }
    /**
     * 账单详情
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function detail()
    {
        $accountid = input('get.accountid');
        $page = input('get.page');
        return $this->fetch('platebill_detail',['accountid'=>$accountid,'page'=>$page]);
    }
    /**
     * 下载账单原始数据
     * 
     */
    public function downLoadBills() 
    {
        $isRefresh = input('?get.refresh') ? input('get.refresh') :'';
        $type = input('?get.type') ? input('get.type') : 1;//默认是小程序账单1小程序2公众号账单
        if(!input('?get.date'))
            $date = date("Ymd", time()-3600*24);//不传就是昨天的
        else 
            $date = input('get.date');
        //判断是否强制刷新
        $where['billdate'] = $date;
        $where['type'] = $type;
        $hasBill = $this->wxbill->where($where)->find();
        if($hasBill) {
            if($isRefresh) {
                //强制刷新就删除数据
                if(strtotime($date)+3600*24*30<time()) {
                    Log::info('只能强制下载30天内账单');
                    return;
                }
                $hasBill->delete();
                $map['billid'] = $hasBill['billid'];
                $this->wxbilldetail->where($map)->delete();
            } else {
                Log::info('账单已经下载');
                return;
            }
        }
        if($type==1) {
            $config = Config::get('wxappconfig');
        } else if($type==2) {
            $config = Config::get('wxconfig');
        }
        if($config) {
            $this->getWxBill($date,$config,$type);
        } else {
            log::write('微信配置失败');
        }
        

    }
    /**
     * 把下载的微信账单插入数据库
     * 
     */
    private function getWxBill($date,$config,$type)
    {
        $time = date("Y-m-d H:i:s");
        $billData = self::downLoadWxBill($date,$config);
        if($billData==='') {
            //账单不存在插入空的数据
            $billid = uuid();
            //插入数据库
            $data['billid'] = $billid;
            $data['billdate'] = $date;
            $data['type'] = $type;
            $data['totalpens'] = 0;
            $data['totalincomes'] = 0;
            $data['totalrefunds'] = 0;
            $data['totalenterpriseredrefunds'] = 0;
            $data['totalfees'] = 0;
            $result = $this->wxbill->save($data);
            Log::info('账单不存在,插入空账单');
            return;
        }
        $billid = uuid();
        //插入数据库
        $data['billid'] = $billid;
        $data['billdate'] = $date;
        $data['type'] = $type;
        $data['totalpens'] = $billData['sum']['sum'];
        $data['totalincomes'] = bcmul($billData['sum']['incomeprice'],100);
        $data['totalrefunds'] = bcmul($billData['sum']['refundprice'],100);
        $data['totalenterpriseredrefunds'] = bcmul($billData['sum']['orgrefund'],100);
        $data['totalfees'] = bcmul($billData['sum']['fees'],10000);
        $result = $this->wxbill->save($data);
        if($result>0) {
            //格式化账单数据
            unset($billData['sum']);
            foreach ($billData as $k => &$v) {
            	if(isset($v['totalamount'])){
            		$v['totalamount'] = bcmul($v['totalamount'],100);
            	}
            	if(isset($v['vouchersdiscountamount'])){
            		$v['vouchersdiscountamount'] = bcmul($v['vouchersdiscountamount'],100);
            	}
            	if(isset($v['refundprice'])){
            		$v['refundprice'] = bcmul($v['refundprice'],100);
            	}
            	if(isset($v['refundvouchersdiscountamount'])){
            		$v['refundvouchersdiscountamount'] = bcmul($v['refundvouchersdiscountamount'],100);
            	}
            	if(isset($v['fees'])){
            		$v['fees'] = bcmul($v['fees'],10000);
            	}
            	if(isset($v['rates'])){
            		$v['rates'] = bcmul((float)$v['rates']/100,10000);
            	}
                $v['createtime'] = $time;
                $v['detailid'] = uuid();	
                $v['billid'] = $billid;	
            }

            $updateCount = $this->wxbilldetail->insertAll($billData);
            if($updateCount == count($billData)) {
                Log::info('账单下载成功');
            } else {
                Log::info('账单详情下载失败');
            }
        } else {
            Log::info('wx账单插入数据库失败');
        }
    }   
    public function viewbill() 
    {
         $date = input('get.date');
         $config = Config::get('wxappconfig');
         $result = $this->getPlateBillDetail(1,$date,'aaa');
         //$result = $orderInfo = $this->orderpay->sumOrder($date);
        //  $accumresult = Db::table( 'statdailyorder' )
        //     ->field( 'machineid,ifnull(SUM(amountincome)-SUM(amountrefund),0) amounaccum')
        //     ->group("merchantid")
        //     ->select();
        //     echo Db::table( 'statdailyorder' )->getLastsql();
    }
    /**
     * 微信支付对账单下载
     * 
     * 
     */
    private static function downLoadWxBill($date,$config)
    {
        $pay = &\Wechat\Loader::get('Pay',$config);
        $billData = $pay->getBill($date);

        if(substr($billData, 0 , 5) == "<xml>"){
            $xml = new \SimpleXMLElement($billData);
            echo $xml->return_msg;
            if($xml->return_msg=='No Bill Exist')
                return '';
            else 
                return false;
            Log::info($date.'--wx账单下载失败----:'.$xml->return_msg.'-----'.$xml->return_code);
            return false;
        } else {
            $array=explode(',`',$billData);
            $r = self::arrangeArrray('ALL',$array);
            Log::info($date.'--wx账单下载成功----:'.json_encode($r));
            return $r;
        }
    }
    /**
     * 重置模板数组
     * 
     */
    private static function arrangeArrray($billType,$array) {
		
		$arr1 = explode(',', $array[0]);
		$num = count($arr1)-1;
		$newArray = self::getData($num,$array,$billType);
		if($billType=='ALL') {
			$sum = $newArray['sum'];
			unset($newArray['sum']);
			//创建数组模板
			/*交易时间,公众账号ID,商户号,子商户号,设备号,
			微信订单号,商户订单号,用户标识,交易类型,交易状态,
			付款银行,货币种类,总金额,企业红包金额,微信退款单号,
			商户退款单号,退款金额,企业红包退款金额,退款类型,退款状态,
			商品名称,商户数据包,手续费,费率*/
			$template[0] = 'transactiontime';
			$template[] = 'appid';
			$template[] = 'mchid';
			$template[] = 'submchid';
			$template[] = 'deviceno';
			$template[] = 'wxordernumber';
			$template[] = 'mchordernumber';
			$template[] = 'userid';
			$template[] = 'transactiontype';
			$template[] = 'transactionstatus';
			$template[] = 'paymentbank';
			$template[] = 'currency';
			$template[] = 'totalamount';
			$template[] = 'vouchersdiscountamount';
			$template[] = 'wxrefundtransactionid';
			$template[] = 'refundtransactionid';
			$template[] = 'refundprice';
			$template[] = 'refundvouchersdiscountamount';
			$template[] = 'refundtype';
			$template[] = 'refundstatus';
			$template[] = 'productname';
			$template[] = 'merchantdatapackage';
			$template[] = 'fees';
			$template[] = 'rates';
			//删除数组
		} else if($billType=='SUCCESS') {
			$sum = $newArray['sum'];
			unset($newArray['sum']);
			//交易时间,公众账号ID,商户号,子商户号,设备号,微信订单号,
			//商户订单号,用户标识,交易类型,交易状态,付款银行,货币种类,
			//总金额,企业红包金额,商品名称,商户数据包,手续费,费率
			$template[0] = 'transactionTime';
			$template[] = 'appId';
			$template[] = 'mchId';
			$template[] = 'subMchId';
			$template[] = 'deviceInfo';
			$template[] = 'wxOrderNumber';
			$template[] = 'mchOrderNumber';
			$template[] = 'userId';
			$template[] = 'transactionType';
			$template[] = 'transactionStatus';
			$template[] = 'paymentBank';
			$template[] = 'currency';
			$template[] = 'totalAmount';
			$template[] = 'vouchersOrDiscountAmount';
			$template[] = 'productName';
			$template[] = 'data';
			$template[] = 'fees';
			$template[] = 'rates';
		} else if($billType=='REFUND'||$billType=='RECHARGE_REFUND') {

			$sum = $newArray['sum'];
			unset($newArray['sum']);
			/*交易时间,公众账号ID,商户号,子商户号,设备号,
			微信订单号,商户订单号,用户标识,交易类型,交易状态,
			付款银行,货币种类,总金额,企业红包金额,退款申请时间,
			退款成功时间,微信退款单号,商户退款单号,退款金额,
			企业红包退款金额,退款类型,退款状态,商品名称,商户数据包,手续费,费率*/
			$template[0] = 'transactionTime';
			$template[] = 'appId';
			$template[] = 'mchId';
			$template[] = 'subMchId';
			$template[] = 'deviceInfo';
			$template[] = 'wxOrderNumber';
			$template[] = 'mchOrderNumber';
			$template[] = 'userId';
			$template[] = 'transactionType';
			$template[] = 'transactionStatus';
			$template[] = 'paymentBank';
			$template[] = 'currency';
			$template[] = 'totalAmount';
			$template[] = 'vouchersOrDiscountAmount';
			//退款申请时间
			$template[] = 'refundApplicationTime';
			//退款成功时间
			$template[] = 'refundSuccessTime';
			$template[] = 'wxRefundTransactionId';
			$template[] = 'refundTransactionId';
			$template[] = 'refundPrice';
			$template[] = 'refundVouchersOrDiscountAmount';
			$template[] = 'refundType';
			$template[] = 'refundStatus';
			$template[] = 'productName';
			$template[] = 'data';
			$template[] = 'fees';
			$template[] = 'rates';
        }
        $newData = self::formatData($template,$newArray);
		$newData['sum'] = $sum;
		return $newData;
    }
    /**
     * 格式化数据 把账单二维索引数组转换便于插入数据库
     * 
     */
    private static function formatData($template,$newArray) {
        $time = date("Y-m-d H:i:s");

		foreach ($newArray as $k => $v) {
			foreach ($v as $kk => $vv) {
				if(isset($template[$kk])) {
					$result[$k][$template[$kk]] = $vv;
				}
				
			}
		}
		
		
		return $result;
    }
    /**
     * 账单数据流转换成数组
     * 
     */
    private static function getData($num,$array,$billType) {
		//取得数组第一项并获得时间
        $time=explode('`',$array[0]);
        $array[0] = $time[1];
        $newArray = array_chunk($array, $num);
        $sumArr2 = array_pop($newArray);
        $lastFee = trim(explode("\n",$sumArr2[0])[0]);
        
        $sumArr['refundprice'] = trim($sumArr2[2]);
        $sumArr['orgrefund'] = trim($sumArr2[3]);
        $sumArr['incomeprice'] = trim($sumArr2[1]);
        $sumArr['fees'] = trim($sumArr2[4]);
        $newArr = explode('`',$sumArr2[0]);
        $sumArr['sum'] = $newArr[1];
        foreach ($newArray as $k => $v) {
            if($k>=1&&strpos($v[0],'`')!==false) {
                $arr = explode('`', $v[0]);
                $newArray[$k][0] = $arr[1];
                $newArray[$k-1][] = trim($arr[0]);
            }
            if($k+1==count($newArray))
                $newArray[$k][] = $lastFee;
        }
        unset($array);
        $newArray['sum'] = $sumArr;
        return $newArray;
    }
    /**微信原始下载账单结束 */


    /**
     * 对账数据合成
     * 
     */
    public function combineOrders() 
    {
        $isRefresh = input('?get.refresh') ? input('get.refresh') :'';
        $time = date("Y-m-d H:i:s");
        if(!input('?get.date'))
            $date = date("Ymd", time()-3600*24);//不传就是昨天的
        else 
            $date = input('get.date');
        
        $type = input('?get.type') ? input('get.type') : 1;//默认是小程序账单1小程序2公众号账单
       
        //收入订单
        $orderInfo = $this->orderpay->sumOrder($date);
        
        
        $wxBill = $this->wxbill->where('billdate',$date)->where('type',$type)->find();
        
        if(!$wxBill) {
            Log::wirte('还没有下载账单请下载账单后再试'.$date);
            return ;
        } else if($wxBill['totalpens']>0){
            //插入数据库
            $data['reconciliationtime'] = $date;
            $data['incomeamount'] = $orderInfo['incomeMoney']+$orderInfo['rfincomeMoney'];
            if($type==1) {
                $data['refundamount'] = $orderInfo['refundMoney'];
                $data['billtype'] = 1;
            } else {
                $data['refundamount'] = 0;
                $data['billtype'] = 2;
            }
            
            $data['thirdincomeamount'] = $wxBill['totalincomes'];
            $data['thirdamount'] = $wxBill['totalrefunds'];
            $data['fees'] = $wxBill['totalfees'];
            $data['billspens'] = $wxBill['totalpens'];
            $data['orderspens'] = $orderInfo['incomeCount']+$orderInfo['refundCount']+$orderInfo['rfincomeCount'];
            if($data['orderspens']==$data['billspens'] && $data['thirdincomeamount']==$data['incomeamount']
                &&$data['refundamount']==$data['thirdamount']
            )
                $data['billstatus'] = 1;
            else 
                $data['billstatus'] = 0;
            $data['createtime'] = $time;
            $data['creater'] = 'auto';
            $data['updatetime'] = $time;
            $data['updater'] = 'auto';
            $data['accountid'] = uuid();
            // dump($data);
            // die;
            // die;
            $result = $this->platebill->insert($data);
            
            if(!$result) {
                Log::wirte('对账单导入到平台数据失败');
                return false;
            } else {
                //导入详情数据
                $detail = $this->getPlateBillDetail($type,$date,$data['accountid']);
                // dump($detail);
                // die;
                $count = count($detail);
                $num = $this->platebilldetail->insertALL($detail);
                if($count===$num)
                    Log::wirte('对账单详情导入到平台数据成功');
                else
                   Log::wirte('对账单详情导入到平台数据失败'); 
            }
            //echo $this->wxbilldetail->getLastSql();
            //  dump($orderDetail);

        } else {
            echo '没有账单插入空的';
            //插入空数据
            $data['accountid'] = uuid();
            $data['reconciliationtime'] = $date;
            $data['createtime'] = $time;
            $data['creater'] = 'auto';
            $data['updatetime'] = $time;
            $data['updater'] = 'auto';
            $this->platebill->insert($data);
            return ;
        }
    }
    /**
     * 导入平台详情数据
     * 
     */
    private function getPlateBillDetail($type,$date,$accountid)
    {
        $timeStamp = strtotime($date);
        $startTime = date('Y-m-d H:i:s',$timeStamp);    
        $endTime = date('Y-m-d H:i:s',$timeStamp+3600*24-1);
        //收入的
        // $subQuery = $this->orderpay->alias('gop')
        //     ->join('goodsorder go','go.orderid = gop.orderid')
        //     ->whereTime('gop.paytime', 'between', [$startTime, $endTime])
        //     ->where('gop.paytype',['=',1],['=',2],'or')
        //     ->where('gop.paystatus',1)
        //     ->field('gop.paytime,gop.paystatus,gop.serialno,gop.orderid,gop.paytype,gop.payfee,gop.batchno')
        //     ->order('gop.paytime desc')->buildSql();
        if($type==1){
            $sql = "select * from (SELECT `transactiontime`,(CASE WHEN transactionstatus='SUCCESS' THEN wxordernumber ELSE wxrefundtransactionid END) as wxbatchno,`mchordernumber`,`transactionstatus`,`totalamount`,`wxrefundtransactionid`,`refundtransactionid`,`refundprice`,`fees` from balancewxpaybilldetail) wx LEFT JOIN ( SELECT 1 as billtype,`gop`.`paytime`,`gop`.`paystatus`,`gop`.`serialno`,`gop`.`orderid`,`gop`.`paytype`,`gop`.`payfee`,`gop`.`batchno` from goodsorderpay gop inner JOIN goodsorder go on go.orderid=gop.orderid where gop.paystatus=1 and (gop.paytype=1 or gop.paytype=2) 
                    union SELECT 1 as billtype,fefundtime as paytime,5 as paystatus,`serialno`,`orderid`,2 as `paytype`,realfee as payfee,`batchno` FROM `outrefund` where paytype=1 and refundstatus=3
                    union SELECT 2 as billtype,createtime as paytime,1 as paystatus,`serialno`,`orderid`,1 as paytype,fee as payfee,`batchno` FROM `rechargeorder` WHERE `status` = 2 
                    union SELECT 3 as billtype,createtime as paytime,1 as paystatus,`serialno`,`orderid`,1 as paytype,payfee,`batchno` from rfidorder where (orderstatus=2 or orderstatus=5) ) a on `wx`.`wxbatchno`=`a`.`batchno` where wx.transactiontime BETWEEN '".$startTime."' and '".$endTime."' 
                    union 
                    select * from (SELECT `transactiontime`,(CASE WHEN transactionstatus='SUCCESS' THEN wxordernumber ELSE wxrefundtransactionid END) as wxbatchno,`mchordernumber`,`transactionstatus`,`totalamount`,`wxrefundtransactionid`,`refundtransactionid`,`refundprice`,`fees` from balancewxpaybilldetail) wx RIGHT JOIN ( SELECT 1 as billtype,`gop`.`paytime`,`gop`.`paystatus`,`gop`.`serialno`,`gop`.`orderid`,`gop`.`paytype`,`gop`.`payfee`,`gop`.`batchno` from goodsorderpay gop inner JOIN goodsorder go on go.orderid=gop.orderid where gop.paystatus=1 and (gop.paytype=1 or gop.paytype=2) 
                    union SELECT 1 as billtype,fefundtime as paytime,5 as paystatus,`serialno`,`orderid`,2 as `paytype`,realfee as payfee,`batchno` FROM `outrefund` where paytype=1 and refundstatus=3
                    union SELECT 2 as billtype,createtime as paytime,1 as paystatus,`serialno`,`orderid`,1 as paytype,fee as payfee,`batchno` FROM `rechargeorder` WHERE `status` = 2 
                    union SELECT 3 as billtype,createtime as paytime,1 as paystatus,`serialno`,`orderid`,1 as paytype,payfee,`batchno` from rfidorder where (orderstatus=2 or orderstatus=5) ) a on `wx`.`wxbatchno`=`a`.`batchno` where a.paytime BETWEEN '".$startTime."' and '".$endTime."'";
            $orderDetail = Db::query($sql);
            // echo $sql;
            //查看订单是否是储值订单
            //判断储值订单订单
            if($orderDetail){
                foreach ($orderDetail as $key => $v) {
                    if($v['transactionstatus']=='REFUND') {
                        $r = $this->checkorder($v['orderid']);
                        if($r) {
                            unset($orderDetail[$key]);
                        }
                    }
                }
            }
            // $orderDetail = Db::table($subQuery.' a')
            // ->join('balancewxpaybilldetail wx','wx.wxordernumber = a.batchno','left')
            // ->field('wx.transactiontime,wx.wxordernumber,wx.mchordernumber,wx.transactionstatus
            //     ,wx.totalamount,wx.wxrefundtransactionid,wx.refundtransactionid,wx.refundprice,wx.fees,
            //     a.*,1 as billtype')
            // ->union(function($query) use ($subQuery,$startTime,$endTime){
            //     $query->table($subQuery.' a')
            //         ->field('wx.transactiontime,wx.wxordernumber,wx.mchordernumber,wx.transactionstatus
            //         ,wx.totalamount,wx.wxrefundtransactionid,wx.refundtransactionid,wx.refundprice,wx.fees,
            //         a.*,1 as billtype')
            //         ->join('balancewxpaybilldetail wx','wx.wxordernumber = a.batchno','RIGHT')
            //         ->whereTime('wx.transactiontime', 'between', [$startTime, $endTime])
            //         ->group('a.orderid');
            // })
            // ->union(function($query) use ($startTime,$endTime){
            //     $query->table('rechargeorder')->alias('ro')
            //         ->field('wx.transactiontime,wx.wxordernumber,wx.mchordernumber,wx.transactionstatus
            //         ,wx.totalamount,wx.wxrefundtransactionid,wx.refundtransactionid,wx.refundprice,wx.fees,
            //         ro.createtime as paytime,ro.status as paystatus,ro.serialno,ro.orderid,1 as paytype,ro.fee as payfee,ro.batchno,2 as billtype')
            //         ->join('balancewxpaybilldetail wx','wx.wxordernumber = ro.batchno','left')
            //         ->where('ro.status',1)
            //         ->whereTime('ro.createtime', 'between', [$startTime, $endTime]);
            // })->union(function($query) use ($startTime,$endTime){
            //     $query->table('balancewxpaybilldetail')->alias('wx')
            //         ->field('wx.transactiontime,wx.wxordernumber,wx.mchordernumber,wx.transactionstatus
            //         ,wx.totalamount,wx.wxrefundtransactionid,wx.refundtransactionid,wx.refundprice,wx.fees,
            //         ro.createtime as paytime,ro.status as paystatus,ro.serialno,ro.orderid,1 as paytype,ro.fee as payfee,ro.batchno,2 as billtype')
            //         ->join('rechargeorder ro','wx.wxordernumber = ro.batchno','left')
            //         ->where('ro.status',1)
            //         ->whereTime('ro.createtime', 'between', [$startTime, $endTime]);
            // })
            // ->union(function($query) use ($startTime,$endTime) {
            //     $query->table('goodsorderpay')->alias('gop')
            //     ->join('outrefund of','of.orderid = gop.orderid')
            //     ->join('balancewxpaybilldetail wx','wx.wxordernumber = of.batchno','left')
            //     ->whereTime('gop.paytime', 'between', [$startTime, $endTime])
            //     ->where('gop.paytype',['=',1],['=',2],'or')
            //     ->where('gop.paystatus',5)
            //     ->where('of.refundstatus',['=',1],['=',3],'or')
            //     ->field('wx.transactiontime,wx.wxordernumber,wx.mchordernumber,wx.transactionstatus
            //         ,wx.totalamount,wx.wxrefundtransactionid,wx.refundtransactionid,wx.refundprice,wx.fees,
            //         of.fefundtime as paytime,gop.paystatus,of.serialno,gop.orderid,gop.paytype,of.realfee as payfee,of.batchno,1 as billtype');
            // })
            // ->union(function($query) use ($startTime,$endTime) {
            //     $query->table('goodsorderpay')->alias('gop')
            //     ->join('outrefund of','of.orderid = gop.orderid')
            //     ->join('balancewxpaybilldetail wx','wx.wxordernumber = of.batchno','right')
            //     ->whereTime('gop.paytime', 'between', [$startTime, $endTime])
            //     ->where('gop.paytype',['=',1],['=',2],'or')
            //     ->where('gop.paystatus',5)
            //     ->where('of.refundstatus',['=',1],['=',3],'or')
            //     ->field('wx.transactiontime,wx.wxordernumber,wx.mchordernumber,wx.transactionstatus
            //         ,wx.totalamount,wx.wxrefundtransactionid,wx.refundtransactionid,wx.refundprice,wx.fees,
            //         of.fefundtime as paytime,gop.paystatus,of.serialno,gop.orderid,gop.paytype,of.realfee as payfee,of.batchno,1 as billtype');
            // })
            // ->whereTime('wx.transactiontime', 'between', [$startTime, $endTime])
            // ->group('a.orderid')
            // ->select();
        } else if($type==2) {
            $orderDetail = $this->wxbilldetail->alias('wx')
            ->field('wx.transactiontime,wx.wxordernumber,wx.mchordernumber,wx.transactionstatus
            ,wx.totalamount,wx.wxrefundtransactionid,wx.refundtransactionid,wx.refundprice,wx.fees,go.payfee,
            go.orderstatus as paystatus,ro.serialno,3 as billtype')
            ->join('rfidorder ro','wx.mchordernumber = ro.serialno')
            
            ->union(function($query) use ($startTime,$endTime){
                $query->table('rfidorder')->alias('ro')
                ->field('wx.transactiontime,wx.wxordernumber,wx.mchordernumber,wx.transactionstatus
            ,wx.totalamount,wx.wxrefundtransactionid,wx.refundtransactionid,wx.refundprice,wx.fees,go.payfee,
            go.orderstatus as paystatus,ro.serialno,3 as billtype')
                ->join('balancewxpaybilldetail wx','wx.mchordernumber = ro.serialno')
                ->where('ro.paytype',1)
                ->whereTime('ro.createtime', 'between', [$startTime, $endTime]);
            })
            ->where(function ($query) {
                $query->where('ro.paytype', 1);
            })
            ->whereTime('ro.createtime', 'between', [$startTime, $endTime])
            ->select();
        }
        
        foreach ($orderDetail as $key => $v) {
                    
            if($v['transactionstatus']=='SUCCESS' && $v['paystatus']==1) {
                
                $newArr[$key]['payresult'] = 1;
            } else if($v['transactionstatus']=='REFUND' && $v['paystatus']==5) {
                $newArr[$key]['payresult'] = 2;
            } else {
                $newArr[$key]['payresult'] = 3;//异常
            }
            if($v['totalamount']+$v['refundprice']-$v['payfee']!=0) {
                $newArr[$key]['accountstatus'] = 2;
            } else {
                $newArr[$key]['accountstatus'] = 1;
            }
            if($v['transactionstatus']=='SUCCESS'||$v['paystatus']==1) {
                $newArr[$key]['billfee'] = $v['totalamount'];
            } else if($v['transactionstatus']=='REFUND'||$v['paystatus']==5) {
                $newArr[$key]['billfee'] = $v['refundprice'];
            } else {
                $newArr[$key]['billfee'] = 0;
            }
            $newArr[$key]['batchno'] = $v['wxbatchno'];
            if($v['billtype']==1) {
                $newArr[$key]['machineid'] = $this->goodsorder->getmachineid($v['orderid']); 
            } else {
                $newArr[$key]['machineid'] = 0;
            }
            $newArr[$key]['orderid'] = $v['orderid'];
            $newArr[$key]['payfee'] = isset($v['payfee'])?$v['payfee']:0;
            $newArr[$key]['paytype'] = 2;
            $newArr[$key]['billtype'] = $v['billtype'];
            $newArr[$key]['detailid'] = uuid();
            $newArr[$key]['accountid'] = $accountid;
            $newArr[$key]['transactiontime'] = $v['transactiontime'];
            $newArr[$key]['reconciliationtime'] = $date;
            $newArr[$key]['serialno'] = $v['mchordernumber'];
            $newArr[$key]['fees'] = $v['fees'];
            $newArr[$key]['createtime'] = date("Y-m-d H:i:s");
            $newArr[$key]['creater'] = 'auto';
            $newArr[$key]['updatetime'] = date("Y-m-d H:i:s");
            $newArr[$key]['updater'] = 'auto';
            $newArr[$key]['remark'] = '';
        
        }
        return $newArr;
        
    }

     // 商户平台对账
    public function buildBillData()
    {
        if(Request::instance()->isGet()){
            $date = input('date');
            
            //获得商户列表
            $merchantList = $this->merchant->select();
            //轮询订单并插入数据库
            foreach ($merchantList as $k => $v) {
                $this->getMerchantBill($v['merchantid'],$date);
            }
        }
    }
    //获取商户的账单
    public function getMerchantBill($merchantid,$date)
    {
        $billinfo = $this->merchantbill->where('billdate',$date)->where('merchantid',$merchantid)->find();
        if(!empty($billinfo)) {
            Log::write('已经有了');
            echo '重复导入';
            return ;
        }
        $timeStamp = strtotime($date);
        $startTime = date('Y-m-d H:i:s',$timeStamp);    
        $endTime = date('Y-m-d H:i:s',$timeStamp+3600*24-1);
        $machineList = $this->merchant->getMachineList($merchantid);
        //die;
        Log::write('商户id'.$merchantid.'机柜列表'.json_encode($machineList));
        $time = date("Y-m-d H:i:s");
        $maccountid = uuid();
        $data['maccountid'] = $maccountid;//账单id
        $data['merchantid'] = $merchantid;//账单id
        $data['billstatus'] = 0;//未结算
        $data['billdate'] = $date;//账单id
        $data['createtime'] = $time;
        $data['updatetime'] = $time;
        $data['creater'] = 'auto';
        $data['updater'] = 'auto';
        $orderList = [];
        if(!empty($machineList)) {
            //储值订单
            $rechargeIn = [];
            $rechargeOut = [];
            $rechargeIn = $this->goodsorder->alias('g')
            ->field('g.machineid,gp.paytime as transactiontime,gp.serialno,gp.batchno,gp.paytype,gp.payfee,1 as accountstatus,0 as fees')
            ->join('goodsorderpay gp','gp.orderid=g.orderid','LEFT')
            ->whereIn('g.machineid',$machineList)
            ->whereTime('gp.paytime', 'between', [$startTime, $endTime])
            ->where('gp.paystatus',1)
            ->where('gp.paytype',0)
            ->select();
            $rechargeOut = $this->goodsrefund->alias('r')
            ->field('g.machineid,r.fefundtime as transactiontime,r.serialno,r.batchno,r.paytype,r.realfee as payfee,1 as accountstatus,0 as fees')
            ->join('goodsorder g', 'g.orderid=r.orderid','LEFT')
            ->whereIn('g.machineid',$machineList)
            ->whereTime('r.fefundtime', 'between', [$startTime, $endTime])
            ->where('r.paytype',0)
            ->where('r.refundstatus',3)
            ->select();


            //订单统计信息
            //收入订单列表
            $orderinList = [];
            $orderoutList = [];
            $orderinList = $this->platebilldetail
                ->field('machineid,transactiontime,serialno,batchno,paytype,payfee,accountstatus,fees')
                ->whereIn('machineid', $machineList)
                ->whereTime('transactiontime', 'between', [$startTime, $endTime])
                ->where('payresult',1)
                ->select();
            $accountstatus = 0;
            if(count($orderinList)>0 || count($rechargeIn)>0) {
                $orderinList= array_merge($orderinList,$rechargeIn);
                $orderincome = 0;
                
                foreach ($orderinList as $k => $v) {

                    $orderincome += $v['payfee'];
                    $accountstatus +=$v['accountstatus'];
                    $orderList[$k]['mdetailid'] = uuid();
                    $orderList[$k]['maccountid'] = $maccountid;
                    $orderList[$k]['machineid'] = $v['machineid'];
                    $orderList[$k]['transactiontime'] = $v['transactiontime'];
                    $orderList[$k]['reconciliationtime'] = $v['transactiontime'];
                    $orderList[$k]['serialno'] = $v['serialno'];
                    $orderList[$k]['batchno'] = $v['batchno'];
                    $orderList[$k]['payfee'] = $v['payfee'];
                    $orderList[$k]['paytype'] = $v['paytype'];
                    $orderList[$k]['ordertype'] = 1;
                    $orderList[$k]['createtime'] = $time;
                    $orderList[$k]['creater'] = 'auto';
                    $orderList[$k]['updatetime'] = $time;
                    $orderList[$k]['updater'] = 'auto';
                    $orderList[$k]['fees'] = $v['fees'];
                }
                $data['incomeamount'] = $orderincome;
            } else {
                $orderList = [];
                $data['incomeamount'] = 0;
            }
            $orderoutList = $this->platebilldetail
                ->field('machineid,transactiontime,serialno,batchno,paytype,payfee,accountstatus,fees')
                ->whereIn('machineid', $machineList)
                ->whereTime('transactiontime', 'between', [$startTime, $endTime])
                ->where('payresult',5)
                ->select();
            if(count($orderoutList)>0 || count($rechargeOut)>0) {
                $orderoutList= array_merge($orderoutList,$rechargeOut);
                $orderoutcome = 0;
                foreach ($orderoutList as $k => $v) {
                    $orderoutcome += $v['payfee'];
                    $accountstatus +=$v['accountstatus'];
                    $orderList[$k]['mdetailid'] = uuid();
                    $orderList[$k]['maccountid'] = $maccountid;
                    $orderList[$k]['machineid'] = $v['machineid'];
                    $orderList[$k]['transactiontime'] = $v['transactiontime'];
                    $orderList[$k]['reconciliationtime'] = $v['transactiontime'];
                    $orderList[$k]['serialno'] = $v['serialno'];
                    $orderList[$k]['batchno'] = $v['batchno'];
                    $orderList[$k]['payfee'] = $v['payfee'];
                    $orderList[$k]['paytype'] = $v['paytype'];
                    $orderList[$k]['ordertype'] = 1;
                    $orderList[$k]['createtime'] = $time;
                    $orderList[$k]['creater'] = 'auto';
                    $orderList[$k]['updatetime'] = $time;
                    $orderList[$k]['updater'] = 'auto';
                    $orderList[$k]['fees'] = $v['fees'];
                }
                $data['refundamount'] = $orderoutcome;
            } else {
                
                $data['refundamount'] = 0;
            }
            if( $accountstatus==0) {
                $data['billstatus'] = 0;
            } else {
                $data['billstatus'] = 1;
            }
            $data['orderspens'] = count($orderinList)+count($orderoutList);
            $data['fees'] = 0;
            //die;
        } else {
            //表示没有机柜 所以没有交易信息
            $data['incomeamount'] = 0;
            $data['refundamount'] = 0;
            $data['orderspens'] = 0;
            $data['fees'] = 0;
        }
        // dump($data);
        // dump($orderList);
        //die;
        $this->merchantbill->insert($data);
        if(!empty($orderList))
            $this->merchantbilldetail->insertAll($orderList);
    }







    public function getBillList() 
    {
        //search params
        
        $s_select = input("s_select");
        $s_time = input("s_time");
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $status = input("status");
        $offset = (input("page") - 1) * input("rows");
        $value = $this->platebill->getList($rows,$sidx,$sord,$offset,$s_time,$s_select,$status);
        $records = $this->platebill->getListCount($s_time,$s_select,$status);
        $total = ceil($records/$rows);
//        $this->recordlog('4',0,'获取商品列表');
        return pagedata($page,$total,$records, $value);
    }
    public function getBillDetailList() 
    {
        //search params
        $accountid = input("accountid");
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $offset = (input("page") - 1) * input("rows");
        $value = $this->platebilldetail->getList($rows,$sidx,$sord,$offset,$accountid);
        $records = $this->platebilldetail->getListCount($accountid);
        $total = ceil($records/$rows);
//        $this->recordlog('4',0,'获取商品列表');
        return pagedata($page,$total,$records, $value);
    }
    public function detaildata() 
    {
        if(Request::instance()->isPost()){
            $batchno = input('batchno');
            $billtype = input('billtype');
            $records = $this->platebilldetail->getListDetail($batchno,$billtype);
            if($records)
                return json(array('code'=>0,'msg'=>'success','result'=>$records));
            else 
                return json(array('code'=>1,'msg'=>'订单不存在'));
        }
        
    }
   
    
    public function merchantindex()
    {
        $date = date("Y-m-d");
        $result['date'] = $date.'~'.$date;
        $result['count'] = $this->merchantbill->where('billdate',$date)->count('merchantid');
        $result['income'] = ($this->merchantbill->where('billdate',$date)->sum('incomeamount'))/100;
        $result['outcome'] = ($this->merchantbill->where('billdate',$date)->sum('refundamount'))/100;
        $result['pens'] = (INT)$this->merchantbill->where('billdate',$date)->sum('orderspens');
        return $this->fetch('merchantindex',$result);
    }
    public function totalMoney()
    {
        $s_time = input('s_time');
        $s_name = input('s_name');
        $where = $this->merchantbill->getswhere($s_time,$s_name);

        $result['count'] = $this->merchantbill->alias('a')
        ->join('merchant b','b.merchantid=a.merchantid','LEFT')
        ->where($where[1])->where($where[2])->count('distinct b.merchantid');
        $result['income'] = $this->merchantbill->alias('a')
        ->join('merchant b','b.merchantid=a.merchantid','LEFT')
        ->where($where[1])->where($where[2])->sum('a.incomeamount')/100;
        $result['outcome'] = $this->merchantbill->alias('a')
        ->join('merchant b','b.merchantid=a.merchantid','LEFT')
        ->where($where[1])->where($where[2])->sum('a.refundamount')/100;
        $result['pens'] = (int)$this->merchantbill->alias('a')
        ->join('merchant b','b.merchantid=a.merchantid','LEFT')
        ->where($where[1])->where($where[2])->sum('a.orderspens');

        return $result;
    }
    public function mlist() 
    {
        return $this->fetch('merchantlist');
    }
    public function merchantdetail() 
    {
        $result['time'] = input("time");
        $result['maccountid'] = input("maccountid");
        return $this->fetch('merchantdetail',$result);
    }
    public function merchandetaillist() 
    {
        $billInfo = $this->merchantbill->where('maccountid',input("maccountid"))->find();
        $result['maccountid'] = input("maccountid");
        $result['wxpay'] = $this->merchantbilldetail->sumPayType($result['maccountid'],1);
        $result['rechargepay'] = $this->merchantbilldetail->sumPayType($result['maccountid'],0);
        $result['sum'] = $this->merchantbilldetail->sumPayType($result['maccountid'],-1);
        $result['count'] = $billInfo->orderspens;
        $result['billdate'] = $billInfo->billdate;
        return $this->fetch('merchandetaillist',$result);
    }
    
    public function getmBillList() 
    {
        //search params
        $date = date("Y-m-d");
        $maccountid = input("maccountid") ? input("maccountid") :'';
           
        $s_name = input("s_name");
        $s_time = input("s_time") ? input("s_time"):$date.'~'.$date;
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $offset = (input("page") - 1) * input("rows");
        $value = $this->merchantbill->getList($rows,$sidx,$sord,$offset,$s_time,$s_name,$maccountid);
        $records = $this->merchantbill->getListCount($s_time,$s_name,$maccountid);
        $total = ceil($records/$rows);
        return pagedata($page,$total,$records, $value);
    }
    public function getmBillDetailList() 
    {
        //search params
        $date = date("Y-m-d");
        $maccountid = input("maccountid") ? input("maccountid") :'';
        $s_time = input("s_time") ? input("s_time"):$date.'~'.$date;
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $offset = (input("page") - 1) * input("rows");
        $value = $this->merchantbill->getDetailList($rows,$sidx,$sord,$offset,$s_time,$maccountid);
        $records = $this->merchantbill->getDetailListCount($s_time,$maccountid);
        $total = ceil($records/$rows);
        return pagedata($page,$total,$records, $value);
    }
    public function getMerBillDetailList()
    {
        $maccountid = input("maccountid");

        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $offset = (input("page") - 1) * input("rows");
        $value = $this->merchantbilldetail->getList($rows,$sidx,$sord,$offset,$maccountid);
        $records = $this->merchantbilldetail->getListCount($maccountid);
        $total = ceil($records/$rows);
        return pagedata($page,$total,$records, $value);
    }

    /**
     * 商户对账结算
     */
    public function merchantBalance()
    {
        if (Request::instance()->isPost()){ 
            $maccountid = input('maccountid');
            $type = input('type');
            $time = input('time');
            if($type=='all') {
                $timerange = explode('~',$time);
                $data = 'billdate >= \''.$timerange[0].'\' and billdate<=\''.$timerange[1].'\'';
            } else {
                $data['billdate'] = $time;
            }
            $data['maccountid'] = $maccountid;
            $data2['balancestatus'] = 1;
            $data2['updatetime'] = date("Y-m-d H:i:s");
            $result = $this->merchantbill->where($data)->update($data2);
            //echo $this->merchantbill->getLastsql();
            if($result) {
                return json(['code'=>0,'msg'=>'成功']);
            } else {
                return json(['code'=>101,'msg'=>'失败']);
            }
            
        }
    }
    //平台对账结算
    public function plateBalance()
    {
        if (Request::instance()->isPost()){ 
            $accountid = input('accountid');
            $data['accountid'] = $mccountid;
            $data2['billstatus'] = 1;
            $data2['updatetime'] = date("Y-m-d H:i:s");
            
            $result1 = $this->platebill->where($data)->update($data2);
            $result2 = $this->platebilldetail->where($data)->update($data2);
            //echo $this->merchantbill->getLastsql();
            if($result1 && $result2) {
                return json(['code'=>0,'msg'=>'成功']);
            } else {
                return json(['code'=>101,'msg'=>'失败']);
            }
            
        }
    }
    private function checkorder($orderid)
    {
        $result = $this->orderpay->where('orderid',$orderid)->order('paytime desc')->find();
        if ($result) {
            if($result['paystatus']==1&&$result['paytype']==0) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    //下载


    public function exportPlateBill(){
        //search params
        $value = $this->platebilldetail
        ->where('accountid',input('accountid'))
        ->field('transactiontime,serialno,batchno,billtype,payresult,paytype,payfee,billfee,fees')
        ->select();
        
        $newlist = [];
        $newitem = [];
        foreach($value as $v) {
            $newitem['transactiontime'] = $v['transactiontime'];
            $newitem['serialno'] = $v['serialno'];
            $newitem['batchno'] = $v['batchno'];
            if($v['billtype']==1) {
                $newitem['billtype'] = '商品购买';
            } else if($v['billtype']==2) {
                $newitem['billtype'] = '储值购买';
            } else if($v['billtype']==3) {
                $newitem['billtype'] = 'rfid购买';
            }
            if($v['payresult']==1) {
                $newitem['payresult'] = '收款';
            } else if($v['payresult']==2) {
                $newitem['payresult'] = '退款';
            } else {
                $newitem['payresult'] = '未知状态';
            }
            if ($v['paytype'] == 2) {
                $newitem['paytype'] = '微信支付';
            } else if ($v['paytype'] == 3) {
                $newitem['paytype'] = '支付宝支付';
            } else {
                $newitem['paytype'] = '未知';
            }
            $newitem['payfee'] = $v['payfee']/100;
            $newitem['billfee'] = $v['billfee']/100;
            $newitem['fees'] = $v['fees']/10000;
            
            array_push($newlist,$newitem);
        }
        
        $excel=new Excel();
        $table_name="goodsorder";
        //['交易时间', '流水号', '第三方订单号', '交易类型', '支付类型', '支付方式', '实付金额(平台)', '对账单金额(第三方)', '手续费(元)', '对账状态', '操作']
        // $field=["orderno"=>"订单编号","batchno"=>"支付单号","serialno"=>"交易流水号","merchantname"=>"商户名称","mobile"=>"买家联系方式","containerid"=>"机柜编号","totalfee"=>"总金额","payfee"=>"付款金额","status"=>"状态","createtime"=>"时间"];
        $field=["transactiontime"=>"交易时间",'serialno'=>'流水号','batchno'=>'第三方订单号','billtype'=>'交易类型','payresult'=>'支付类型','paytype'=>'支付方式','payfee'=>'订单金额(平台)','billfee'=>'对账单金额(第三方)','fees'=>'手续费(元)'];
        $excel->setExcelName("交易订单".Date('Y-m-d H:i:s'))
            ->createOrderSheet("订单数据",$table_name,$field,$newlist)
            ->downloadExcel();
    }
    public function updaterefund(){
        if(!input('?get.date'))
            $date = date("Ymd", time()-3600*24);//不传就是昨天的
        else 
            $date = input('get.date');
        $timeStamp = strtotime($date);
        $startTime = date('Y-m-d H:i:s',$timeStamp);    
        $endTime = date('Y-m-d H:i:s',$timeStamp+3600*24-1);

        $sql1 = 'update  outrefund outre INNER JOIN goodsorder orde
                on outre.orderid =orde.orderid
                INNER JOIN goodsorderpay p
                on p.orderid=orde.orderid
                set outre.paytype=(CASE 
                WHEN  p.paytype=0  THEN 0
                WHEN  p.paytype=1  THEN 1 
                WHEN  p.paytype=2  THEN 1 
                WHEN  p.paytype=3  THEN 2 
                WHEN  p.paytype=5  THEN 2 
                end ) where p.paystatus=1 and p.paytime BETWEEN "'.$startTime.'" and "'.$endTime.'"';
        $sql2 = 'update  outrefund o INNER join balancewxpaybilldetail b on b.refundtransactionid=o.orderno
                set o.batchno=b.wxrefundtransactionid where o.fefundtime BETWEEN "'.$startTime.'" and "'.$endTime.'"';

        Db::query($sql1);
        Db::query($sql2);
        
    }
}

