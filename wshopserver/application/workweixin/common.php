<?php
function pagedata($page,$total,$records, $rows) {
    return [
        'page' => intval($page),
        'total' => intval($total),
        'records' => intval($records),
        'rows' => $rows,
    ];
}
function result($status, $message='' , $data=[]) {
    return [
        'code' => intval($status),
        'msg' => $message,
        'data' => $data,
    ];
}

function returnErr($errcode,$errmsg='',$data=[]){
    if($errcode)
        $errmsg = $errmsg ?: '网络繁忙';
    return array('errcode'=>$errcode,'errmsg'=>$errmsg,'data'=>$data);
}

//接口返回
function ajaxReturn($errcode=0,$errmsg='',$data=[]){
    $d['errcode'] = $errcode;
    $d['errmsg'] = $errmsg;
    $d['data'] = $data;
    return json($d);
}