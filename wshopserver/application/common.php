<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件 type = 0 get 1 post
function doCurl($url,$type=0,$data=[]){
    $ch = curl_init();//初始化
    //设置
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch,CURLOPT_HEADER,0);
    if($type == 1){
        curl_setopt($ch,CURLOPT_PORT,1);//POST
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);

    }
     //执行并获取内容
    $output = curl_exec($ch);
    //释放curl句柄
    curl_close($ch);
    return $output;
}

// 生成订单号(备用)
function getOrderSn() {
    list($t1, $t2) = explode(' ', microtime());
    //echo $t1."<br />";
    //echo $t2."<br/>";exit;
    $t3 = explode('.', $t1*10000);
    return $t2.$t3[0].(rand(10000, 99999));
}

//生成主键
function  uuid()
{
    $chars = md5(uniqid(mt_rand(), true));
    $uuid = substr ( $chars, 0, 8 )// . '-'
        . substr ( $chars, 8, 4 ) //. '-'
        . substr ( $chars, 12, 4 )// . '-'
        . substr ( $chars, 16, 4 ) //. '-'
        . substr ( $chars, 20, 12 );
    return $uuid ;
}
// 生成订单号
function makeOrderNo()
{
    $yCode = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
    $orderSn =
        $yCode[intval(date('Y')) - 2017] . strtoupper(dechex(date('m'))) . date(
            'd') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf(
            '%02d', rand(0, 99));
    return $orderSn;
}
function rad($d)
{
    return $d * M_PI / 180.0;
}
/**
 * 获取两个坐标点之间的距离，单位km，小数点后2位
 */
function GetDistance($lat1, $lng1, $lat2, $lng2)
{
    $EARTH_RADIUS = 6378.137;
    $radLat1 = rad($lat1);
    $radLat2 = rad($lat2);
    $a = $radLat1 - $radLat2;
    $b = rad($lng1) - rad($lng2);
    $s = 2 * asin(sqrt(pow(sin($a/2),2) + cos($radLat1)*cos($radLat2)*pow(sin($b/2),2)));
    $s = $s * $EARTH_RADIUS;
    $s = round($s * 100) / 100;
    return $s;
}
function api_notice_increment($url, $data){
    $ch = curl_init();
    $header = "Accept-Charset: utf-8";
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
//        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $tmpInfo = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close( $ch );
        return $ch;
    }else{
        curl_close( $ch );
        return $tmpInfo;
    }

}
function removeEmoji($clean_text) {

    // Match Emoticons
    $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
    $clean_text = preg_replace($regexEmoticons, '', $clean_text);

    // Match Miscellaneous Symbols and Pictographs
    $regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
    $clean_text = preg_replace($regexSymbols, '', $clean_text);

    // Match Transport And Map Symbols
    $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
    $clean_text = preg_replace($regexTransport, '', $clean_text);

    // Match Miscellaneous Symbols
    $regexMisc = '/[\x{2600}-\x{26FF}]/u';
    $clean_text = preg_replace($regexMisc, '', $clean_text);

    // Match Dingbats
    $regexDingbats = '/[\x{2700}-\x{27BF}]/u';
    $clean_text = preg_replace($regexDingbats, '', $clean_text);

    return $clean_text;
}
//获取日期的天数
function getDateDays( $start,$end ){
    $start_time = strtotime( date('Y-m-d',strtotime($start)) );//要用到的是9月    所以从8月开始
    $end_time = strtotime( date('Y-m-d',strtotime($end)) );  //结束时间
    $Days = round(($end_time-$start_time)/3600/24)+1;
    return $Days;

}
function getDateList( $start,$end )
{
    $start_time = strtotime( date('Y-m-d',strtotime($start)) );//要用到的是9月    所以从8月开始
    $end_time = strtotime( date('Y-m-d',strtotime($end)) );  //结束时间
    $dataList = [ ];
    for( $i = $start_time; $i <= $end_time; $i += 24 * 3600 ) {
        $now = date( "Y-m-d",$i );
        $dataList[] = $now;
    }
    return $dataList;
}