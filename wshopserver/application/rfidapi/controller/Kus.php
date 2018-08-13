<?php
namespace app\rfidapi\controller;
use think\Controller;
use think\Db;
use \think\Log;
use \think\Request;
use think\Loader;
use traits\model\Transaction;
use app\common\model\Taglib as TaglibModel;

class Kus extends Controller
{
    public function list($dparam2)
    {
        $containeridarr = isset($dparam2['containerids']) ? $dparam2['containerids'] : array();
        if(count($containeridarr) == 0)
        {
            $result['code'] = "203";
            $result['msg'] = "机柜号没有传输";
            echo json_encode($result);exit;
        }
        $sql = "";
        foreach ($containeridarr as $key => $value)
        {
            $sql .= "SELECT barcode as barCode,count(ebno) as amount,containerid FROM taglib WHERE containerid = '$value' and `status` = 1 GROUP BY barcode union all ";
        }
        $sql = substr($sql, 0,strlen($sql)-11);
        $list = Db::query($sql);
        $kus = $containerids = array();
        // echo "<pre>";
        foreach ($list as  $value) {
           $key = $value['containerid'];
           $tmp['barCode'] = $value['barCode'];
           $tmp['amount'] = $value['amount'];
           $kus[$key][] = $tmp;
        }
        // print_r($kus); 
        $result['code'] = 200;
        $result['timestamp'] = time();
        foreach ($containeridarr as $value)
        {
            $containerids[$value] =  array_key_exists($value, $kus)?$kus[$value]:array();   
        }
        $result['containerids']  = $containerids;        
        echo json_encode($result);exit;
    }
    
}