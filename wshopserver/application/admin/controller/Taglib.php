<?php
namespace app\admin\controller;
use think\Controller;
use think\Loader;
use think\Config;
use think\Log;
use think\Db;
use app\common\model\User as UserModel;
use app\lib\enum\WechatTemplate;
use app\worker\controller\WechatMessage;
use app\admin\service\Csv;
use think\File;
Loader::import('master.RfidApiV2', EXTEND_PATH, '.php');

/**
 * 电子标签管理Taglib
 *
 * @author      Caesar
 * @version     1.0
 */
class Taglib extends  Adminbase //Base
{
    private  $obj,$typeobj;
    public function _initialize() {
        parent::_initialize();
        $this->typeobj = model("Rfidspec");
    }
    protected $beforeActionList = [
        'checkAuth' => ['only' => 'index,setting']
    ];
    /**
     * taglib管理页
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function index()
    {
        $onsalecount = model("Taglib")->where('status',1)->count();
        $rukucount = model("Taglib")->where('status',0)->count();
        $offsales = model("Taglib")->where('status',2)->count();
        return $this->fetch('index',[
            'onsalecount'=>$onsalecount,
            'rukucount'=>$rukucount,
            'offsales'=>$offsales,
        ]);
    }
    /**
     * ajax获取rfid列表
     *
     * 前端使用jqgrid控件渲染表格
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function rfidlist(){
        //search params
        $s_name = input("s_name");
        $s_select = input("s_select");
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $offset = (input("page") - 1) * input("rows");
        $value = model('Taglib')->getList($rows,$sidx,$sord,$offset,$s_name,$s_select);
        $records = model('Taglib')->getListCount($s_name,$s_select);
        $total = ceil($records/$rows);
//        $this->recordlog('4',0,'获取rfid列表');
        return pagedata($page,$total,$records, $value);
    }
    /**
     * 订单详情页
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function detail($page=1,$ebno='',$status='')
    {
//        $ordermodel = model('Rfidorder')::where('orderid', '=', $orderid)->find();
//        if($ordermodel){
//            if($ordermodel['orderstatus'] == 4){
//                $data = array(
//                    'serialsnumber' => $ordermodel['orderno'],
//                    'timestamp' => time(),
//                );
//
//            }
//        }

        return $this->fetch('rfid_detail',[
            'ebno'=>$ebno,
            'page'=>$page,
            'status'=>$status
        ]);
    }

    /**
     * ajax获取订单详情
     *
     * @access public
     * @return tp5
     */
    public function detaildata($ebno=''){
        $taglib = model('Taglib')->where('ebno', $ebno)->find();
        $goods = model('Goods')->where('goodsid', $taglib['barcode'])->find();
        $rfidorder = model('Rfidorder')->where('orderid', $taglib['orderid'])->find();
        $orderspecs = model('Orderepc')->where('orderid',$rfidorder['orderno'])->select();
        if($orderspecs){
            foreach ($orderspecs as $orderspec){
                if($orderspec['type'] == 1){
                    $orderspec['ordertype'] = '商品理货';
                    $orderspec['typename'] = '上架';
                }else if($orderspec['type'] == 2){
                    $orderspec['ordertype'] = '商品理货';
                    $orderspec['typename'] = '下架';
                }else if($orderspec['type'] == 3){
                    $orderspec['ordertype'] = '商品销售';
                    $orderspec['typename'] = '销售';
                }
            }
        }
//        $user = model('Sysuser')->where('userid',$rfidorder['sysuserid'])->find();

        return result(200,'success',[
            'taglib'=>$taglib,
            'rfidorder'=>$rfidorder,
            'orderspecs'=>$orderspecs,
            'goods'=>$goods,
        ]);
    }
    /*
         * CSV导入
         */
    public function uptaglib()
    {
        $fileTypes = array('csv');
        if (!empty($_FILES)) {
            $fileParts = pathinfo($_FILES['Filedata']['name']);
            if (in_array(strtolower($fileParts['extension']), $fileTypes)) {
                $extend = substr(strrchr($_FILES['Filedata']['name'], '.'), 1);
                $tempFile = $_FILES['Filedata']['tmp_name'];
                $file = new File($tempFile);
                // 移动到框架应用根目录/public/uploads/ 目录下
                $info = $file->move(ROOT_PATH.'public'.DS.'upload');
                //获取文件（日期/文件），$info->getFilename();
                $filename = ROOT_PATH.'public'.DS.'upload/'.$info->getSaveName();
                $handle = fopen($filename,'r');
                $csv = new Csv();
                $result = $csv->input_csv($handle); // 解析csv
                $len_result = count($result);
                if($len_result == 0){
//            $this->error('此文件中没有数据！');
                    return json_encode(result(0,'此文件中没有数据！'));
                }
                $data_values = '';
                for($i = 1;$i < $len_result+1;$i ++) { // 循环获取各字段值
                    $arr = array_values($result[$i]);
                    $ebno = uuid();
                    $orderid = iconv('gb2312','utf-8',$arr[0] ); // 中文转码
                    $merchantid = iconv('gb2312','utf-8',$arr[1]);
                    $epc = $arr[2];
                    $barcode = iconv('gb2312','utf-8',$arr[3]);
                    $createtime = Date('Y-m-d H:i:s');
                    $status = 0;
                    $checktaglib = model('Taglib')->where('epc', $epc)->select();
                    if(count($checktaglib) > 0){
                        return json_encode(result(0,'epc:'.$epc.'已存在'));
                    }
                    $data_values .= "('$ebno','$orderid','$merchantid','$epc','$barcode','$createtime','$status'),";
                }
                $data_values = substr($data_values,0,- 1 ); // 去掉最后一个逗号
                fclose($handle); // 关闭指针
                // 批量插入数据表中
                $result = DB::execute("insert into taglib (ebno,orderid,merchantid,epc,barcode,createtime,status) values $data_values" );
                if($result){
                    return json_encode(result(1,'文件上传成功，数据已经导入！'));
//            $this->success('文件上传成功，数据已经导入！','exampaper',3);
                }else{
                    return json_encode(result(0,'error'));
                    // 上传失败获取错误信息
//            $this->error($file->getError());
                }
            }
        }
    }
}
