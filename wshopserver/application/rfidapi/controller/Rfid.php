<?php
namespace app\rfidapi\controller;
use think\Controller;
use think\Db;
use \think\Log;
use \think\Request;
use think\Loader;
use traits\model\Transaction;
use app\common\model\Taglib as TaglibModel;
use app\common\model\Orderbase as OrderbaseModel;
use app\common\model\Orderepc as OrderepcModel;
use app\common\model\Orderdetail as OrderdetailModel;
use app\common\model\Salegoods as SalegoodsModel;
Loader::import('master.MasterApis', EXTEND_PATH, '.php');
class Rfid extends Controller
{
    // 验证请求方式
    public function methodcheck()
    {
        if(request()->isGet())
        {
            $result = array('code'=>'201','msg'=>'请求方式不对哦');
           return $result;
        } else {
           $result = array('code'=>'200','msg'=>'验证成功');
           return $result;
        }
    }

    // 验证请求参数
    public function paramcheck($param)
    {
        $result = array('code'=>'200','msg'=>'验证成功');
        // 1、获取参数
        if($param == "")
        {
            $result['code'] = "201";
            $result['msg'] = "获取param错误";
            return $result;exit;
        }
        // 2、解密参数
        $jdecode = json_decode($param,true);
        $masterApi = new \MasterApis('','');
        $dparam = $masterApi->decrypt($jdecode['param']);
        if($dparam == "" || $dparam == "null")
        {
            $result['code'] = "202";
            $result['msg'] = "解密错误";
            return $result;exit;
        }
        // 3、参数校验
        // $dparam2 = $param;
        $dparam2 = json_decode($dparam,true);
        $orderid = isset($dparam2['orderid']) ? $dparam2['orderid'] : "";
        $containerid = isset($dparam2['containerid']) ? $dparam2['containerid'] : "";
        $type = isset($dparam2['type']) ? (int)$dparam2['type'] : "";
        $adds = isset($dparam2['adds']) ? $dparam2['adds'] : array();
        $down = isset($dparam2['down']) ? $dparam2['down'] : array();
        $result['data'] = $dparam2;
        // 验证单号
        if($orderid == "" || $containerid == "")
        {
            $result['code'] = "2031";
            $result['msg'] = "机柜或订单参数错误";
            return $result;exit;
        }
        // 验证订单号
        $history = DB::query("select orderno from orderbase where orderid = '$orderid'");
        if(count($history) > 0)
        {
            $result['code'] = "200";
            $result['msg'] = "2031订单号已经存在";
            return $result;exit;
        }
        // 验证类型
        if($type != 1 && $type != 2)
        {
            $result['code'] = "2032";
            $result['msg'] = "类型参数错误";
            return $result;exit;
        }
        // 验证总数
        /*if($type == 1 && count($adds) != 0)
        {
            $result['code'] = "2033";
            $result['msg'] = "购物入库错误";
            return $result;exit;
        }*/
        return $result;
    }

    // 上架下架空操作
    public function kongdo($dparam2)
    {
        $OrderbaseModel = new OrderbaseModel();
        $data['orderno'] = uuid();
        $data['orderid'] = $dparam2['orderid'];
        $data['containerid']= $dparam2['containerid'];
        $data['type'] = $dparam2['type'];
        $data['adds'] = 0;
        $data['down'] = 0;
        $data['createtime'] = date("Y-m-d H:i:s",time());
        
        $res = $OrderbaseModel->save($data);
        if($res)
        {
            $result['code'] = "200";
            $result['msg'] = "操作成功";
            return $result;exit;
        } else {
            $result['code'] = "204";
            $result['msg'] = "订单入库异常";
            return $result;exit;
        }
    }

    // 获取标签商品映射关系
    public function epclist($toepc)
    {
        $Taglib = new TaglibModel();
        $epcs = implode($toepc, ",");
        $where['epc'] = array('in',$epcs);
        $kuobj = $Taglib->where($where)->field('epc,barcode')->select();
        $epclist = array();
        foreach($kuobj as $val)
        {
            $list = $val->toArray();
            $epc = $list['epc'];
            $barcode = $list['barcode'];
            $epclist[$epc] = $barcode;
        }
        return $epclist;
    }

    // 购物操作
    public function typeo($dparam2,$toepc,$epclist){
        // 基本参数
        $orderid = $dparam2['orderid'];
        $containerid = $dparam2['containerid'];
        $type = $dparam2['type'];
        $adds = $dparam2['adds'];
        $down = $dparam2['down'];
        $addscount = count($dparam2['adds']);
        $downcount = count($dparam2['down']);
        $epclistkey = array_keys($epclist);
        $uuid = uuid();
        $time = date("Y-m-d H:i:s");
        // 基础订单
        $basesql = "INSERT INTO orderbase VALUES('$uuid','$orderid','$containerid','$type','$addscount','$downcount','$time')";
        $epcsql = "";

        $detailarr = $barcodes = $epcdel = array();
        foreach ($toepc as $key => $value) 
        {
           $uuid = uuid(); //epc订单
           $status = in_array($value, $epclistkey) ? "1" : "2";//异常
           $barcode = isset($epclist[$value]) ? $epclist[$value] : "";
           $epcsql .= "('$uuid','$orderid','$containerid','$value','$barcode',3,$status),";
          if(in_array($value, $epclistkey))
          {
            if(in_array($barcode, $barcodes))
            {
                $detailarr[$barcode] += 1;
            }else{
                $detailarr[$barcode] = 1;
            }
            $barcodes[] = $barcode;
            $epcdel[] = $value; // 删除的epc
          }
        }
        // 订单sql
        $datailsql = "";
        foreach ($detailarr as $key => $value)
        {
            $uuid = uuid();
            $datailsql .= "('$uuid','$orderid','$containerid','$key','$value',2),";
        }

        // taglib库信息
        $epcsqlparm = $salesql ="";
        foreach ($epcdel as $key => $value) 
        {
            $uuid = uuid();
            $epcsqlparm .= "'$value',";
            $barcode = $epclist[$value];
            $salesql = "('$uuid','$orderid','$containerid','$value','$barcode','$time'),";
        }
        // 执行sql
        if($epcsql != ""){
            $epcsql = "INSERT INTO orderepc(`orderepcno`,`orderid`,`containerid`,`epc`,`barcode`,type,status) VALUES ".$epcsql;
            $epcsql = substr($epcsql, 0,strlen($epcsql)-1);
        }

        if($datailsql != "")
        {
            $datailsql = substr($datailsql, 0,strlen($datailsql)-1);
            $datailsql = "INSERT INTO orderdetail(`orderdetailno`,`orderid`,`containerid`,`barcode`,`amount`,`type`) VALUES $datailsql";
        }
        // 73、tagelib变更
        $taglibsql = "";
        if($epcsqlparm != "")
        {
            $epcsqlparm = substr($epcsqlparm, 0,strlen($epcsqlparm)-1);
            $taglibsql = "delete from taglib where epc in ($epcsqlparm)";
        }
        // 74、salegoods信息
        if($salesql != "")
        {
            $salesql = substr($salesql, 0,strlen($salesql)-1);
            $salesql = "INSERT INTO salegoods(`saleno`,`orderid`,`containerid`,`epc`,`barcode`,`createtime`) VALUES $salesql";
        }

/*echo $basesql."<br>";
echo $epcsql."<br>";
echo $taglibsql."<br>";
echo $datailsql."<br>";
echo $salesql."<br>";
die;*/
        Db::startTrans();
        try{
            Db::query($basesql);
            if($epcsql != ""){Db::query($epcsql);}
            if($taglibsql != ""){Db::query($taglibsql);}
            if($datailsql != ""){Db::query($datailsql);}
            if($salesql != ""){Db::query($salesql);}
            Db::commit();
            $result['code'] = 200;
            $result['msg'] = "成功";
            return $result;exit;
        }catch(\Exception $e){
            Db::rollback();
            $result['code'] = 206;
            $result['msg'] = "操作失败";
            return $result;exit;
        }
    }

    // 理货操作
    public function typet($dparam2,$toepc,$epclist)
    {
      /*  echo "<pre>";
        print_r($dparam2);
        print_r($toepc);
        print_r($epclist);*/
        // 基础参数
        $orderid = $dparam2['orderid'];
        $containerid = $dparam2['containerid'];
        $type = $dparam2['type'];
        $adds = $dparam2['adds'];
        $down = $dparam2['down'];
        $addscount = count($dparam2['adds']);
        $downcount = count($dparam2['down']);
        $epclistkey = array_keys($epclist);
        $uuid = uuid();
        $time = date("Y-m-d H:i:s");
        // 基础订单basesql
        $basesql = "INSERT INTO orderbase VALUES('$uuid','$orderid','$containerid','$type','$addscount','$downcount','$time')";
        // epc订单basesql
        $epcsql = "INSERT INTO orderepc(`orderepcno`,`orderid`,`containerid`,`epc`,`barcode`,type,status) VALUES";
        $str1 = $str2 = $str3 = $str4 = "";
        $detailarr = array(
            'down' => array(),
            'adds' => array(),
        );
        $baradds = $bardown = array();
        foreach ($toepc as $key => $value) 
        {
           $uuid = uuid();//epc订单
           $status = in_array($value, $epclistkey) ? "1" : "2"; //异常
           $type = in_array($value, $adds) ? 1 : 2; //入出
           $barcode = isset($epclist[$value]) ? $epclist[$value] : "";
           $epcsql .= "('$uuid','$orderid','$containerid','$value','$barcode',$type,$status),";
           //detail订单
           if(in_array($value, $epclistkey))
           {
                $barcode = $epclist[$value];
                if(in_array($value, $down)){ //下
                    if(in_array($barcode, $bardown))
                    {//基础1存在
                        $detailarr['down'][$barcode] = $detailarr['down'][$barcode]+1;
                    }else{
                        $detailarr['down'][$barcode] = 1;
                    }
                    $bardown[] = $barcode;
                }else{ //上
                    if(in_array($barcode, $baradds))
                    {//基础1存在
                        $detailarr['adds'][$barcode] = $detailarr['adds'][$barcode]+1;
                    }else{
                        $detailarr['adds'][$barcode] = 1;
                    }
                    $baradds[] = $barcode;
                }
           }
           // 关系表
           if(in_array($value, $epclistkey))
           {
                $str1 .= "when '$value' THEN $type ";
                if(in_array($value, $adds))
                {
                    $str2 .= "when '$value' THEN '$containerid' ";
                } else {
                    $str2 .= "when '$value' THEN '' ";
                }
                $str3 .= "when '$value' THEN '$time' ";
                $str4 .= "'$value',";
           }
        }
        
        $datailsql = "";
        foreach ($detailarr['adds'] as $key => $value)
        {
            $uuid = uuid();
            $datailsql .= "('$uuid','$orderid','$containerid','$key','$value',1),";
        }
        foreach ($detailarr['down'] as $key => $value)
        {
            $uuid = uuid();
            $datailsql .= "('$uuid','$orderid','$containerid','$key','$value',2),";
        }
        // print_r($detailarr);
        // 61、epc详情
        $epcsql = substr($epcsql, 0,strlen($epcsql)-1);
        // 62、detail商品详情
        if($datailsql != ""){
            $datailsql = substr($datailsql, 0,strlen($datailsql)-1);
            $datailsql = "INSERT INTO orderdetail(`orderdetailno`,`orderid`,`containerid`,`barcode`,`amount`,`type`) VALUES $datailsql";
        }

        // 64、tagelib变更
        if($str4 != ""){
            $str4 = substr($str4, 0,strlen($str4)-1);
            $tagupsql = "UPDATE taglib SET status = CASE epc $str1 END,containerid = CASE epc $str2 END,updatetime = CASE epc $str3 END WHERE epc IN ($str4)";
            
        }

        // echo $basesql."<br>";
        // echo $epcsql."<br>";
        // echo $datailsql."<br>";
        // echo $tagupsql."<br>";
        // die;    

        Db::startTrans();
        try{
            Db::query($basesql);
            Db::query($epcsql);
            if($datailsql != "")
            {
                Db::query($datailsql);
            }
            if($str4 != "")
            {
                Db::query($tagupsql);
            }
            Db::commit();
            $result['code'] = 200;
            $result['msg'] = "理货成功";
            return $result;exit;
        }catch(\Exception $e){
            Db::rollback();
            $result['code'] = 206;
            $result['msg'] = "理货失败数据问题";
            return $result;exit;
        }
    }

    public function index()
    {
        $result = array('code'=>200,'msg'=>'成功','time'=>time());
       
        //1、验证请求方式
       /* $methodcheck = $this->methodcheck(); 
        if($methodcheck['code'] != 200)
        {
           file_put_contents("rfid.txt", date('Y-m-d H:i:s',time())."___请求方式问题：".json_encode($methodcheck)."\r\n",FILE_APPEND);
            echo json_encode($methodcheck);exit;
        }*/
        
        $param = file_get_contents('php://input');
        file_put_contents("rfid.txt", date('Y-m-d H:i:s',time())."___返回数据：".$param."\r\n",FILE_APPEND);



        //2、验证请求参数
        $paramcheck = $this->paramcheck($param);
        if($paramcheck['code'] != 200)
        {
            echo json_encode($paramcheck);exit;
        }
        $dparam2 = $paramcheck['data'];
        //3、执行空操作
        if(count($dparam2['adds']) == 0 && count($dparam2['down']) == 0)
        {
           $kongdo = $this->kongdo($dparam2);


file_put_contents("rfid.txt", date('Y-m-d H:i:s',time())."___返回数据：".$param."___解密数据：".json_encode($dparam2)."__MSG"."___返回数据：".json_encode($kongdo)."\r\n",FILE_APPEND);

           echo  json_encode($kongdo);exit;
        }
        // 4、获取对应关系 传输epc
        


        // 5、购物操作
        if($dparam2['type'] == 1)
        {   
            $toepc = $dparam2['down'];
        
            //epc=>barcode
            $epclist = $this->epclist($toepc);
            
            $typeo = $this->typeo($dparam2,$toepc,$epclist);  
file_put_contents("rfid.txt", date('Y-m-d H:i:s',time())."___返回数据：".$param."___解密数据：".json_encode($dparam2)."__MSG"."___返回数据：".json_encode($typeo)."\r\n",FILE_APPEND);
            echo json_encode($typeo);

        // 6、理货操作
        } else { 
            $toepc = array_merge($dparam2['adds'], $dparam2['down']);
        
            //epc=>barcode
            $epclist = $this->epclist($toepc);

            $typet = $this->typet($dparam2,$toepc,$epclist);  
file_put_contents("rfid.txt", date('Y-m-d H:i:s',time())."___返回数据：".$param."___解密数据：".json_encode($dparam2)."__MSG"."___返回数据：".json_encode($typet)."\r\n",FILE_APPEND);

            echo json_encode($typet);
        }
        
    }
   

}