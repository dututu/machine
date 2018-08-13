<?php
namespace app\wechatservice\controller;
use think\Controller;
use think\Loader;
use think\Config;
use think\Log;
Loader::import('master.MasterApi', EXTEND_PATH, '.php');
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
Loader::import('master.GoboxApi', EXTEND_PATH, '.php');
/**
 * 商品信息
 *
 * @author      Caesar
 * @version     1.0
 */
class Goods extends  Base //Base
{
    private  $obj;
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
            $this->obj = model("Goods");
            $rfidfreefee = model('Sysparam')
                ->where('paramid', 'rfidfreefee')
                ->find();//免运费金额
            $rfidperorderfreight = model('Sysparam')
                ->where('paramid', 'rfidperorderfreight')
                ->find();//每笔订单运费
            return $this->fetch('goodsinfo',[
                'rfidfreefee'=>intval($rfidfreefee['paramvalue']),
                'rfidperorderfreight'=>intval($rfidperorderfreight['paramvalue']),
            ]);
    }

    /**
     * ajax获取商品列表
     * @access public
     * @return tp5
     * @throws \think\exception\DbException
     */
    public function goodslist(){
        $openid = session('openid', '', 'wechatservice');
        $merchantid = $this->getMerchantIdByOpenId($openid);
//        $merchantid = '516452bf528b3803fd0e6ed63c6106ec';
        //search params
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $categoryids = input("searchparams");//2,3,4,
        $offset = (input("page") - 1) * input("rows");
        $value = model('Goods')->getGoodsList($rows,$offset,$merchantid,$categoryids);
        foreach ($value as $goods){
            $goods['picurl'] = Config::get('paths.coshost').$goods['picurl'];
            $rfidspec = model('Rfidspec')::get($goods['rfidtypeid']);
            $goods['rfidtypename'] = $rfidspec['typename'];
            $category = model('Goodscategory')::get($goods['goodscategoryid']);
            $goods['goodscategoryname'] = $category['categoryname'];
            //剩余rfid标签数量
            $remaincount = model("Taglib")->where('merchantid',$merchantid)->where('barcode',$goods['goodsid'])->count();
            $goods['remaincount'] = $remaincount;
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
        $data['data'] = $value;
        return result(200,"success",$data);
    }
    public function categorylist(){
        $result = model('Goodscategory')->select();
        return result(200,"success",$result);
    }
    public function add()
    {
        # 配置参数
        $config = Config::get('wx.wxconfig');
        //js签名
        // 创建SDK实例
        $script = &\Wechat\Loader::get('Script',$config);
        // 获取JsApi使用签名，通常这里只需要传 $url参数
        $appid = Config::get('wx.wxappid');
        $url = Config::get('wx.host').'wechatservice/goods/add';//当前页面URL地址
        $options = $script->getJsSign($url, 0, '', $appid);

        $categorys  = model('Goodscategory')::where('status', 0)->select();
        return $this->fetch('goods_add',[
            'categorys'=>$categorys,
            'options'=>json_encode($options)
        ]);
    }


    public function save()
    {
        $openid = session('openid', '', 'wechatservice');
        $merchantid = $this->getMerchantIdByOpenId($openid);
        $userid = $this->getUserIdByOpenId($openid);
        $categoryid = input("post.categoryid");
        $goodsname = input("post.goodsname");
        $stucode = input("post.stucode");
        $picurl = input("post.picurl");
        $salefee = input("post.salefee");
        $originalfee = input("post.originalfee");
        $weight = input("post.weight");
        $weightdrift = input("post.weightdrift");
        $spec = input("post.spec");
        $data['goodsid'] = uuid();
        $data['goodsname'] = $goodsname;
        $data['goodscategoryid'] = $categoryid;
        $data['stucode'] = $stucode;
        $data['picurl'] = $picurl;
        $data['originalfee'] = $salefee*100;
        $data['salefee'] = $salefee*100;
        $data['originalfee'] = $originalfee*100;
        $data['spec'] = $spec;
        $data['storecondition'] = 0;//TODO
        $data['weight'] = $weight;
        $data['weightdrift'] = $weightdrift;
        $data['merchantid'] = $merchantid;
        $data['creater'] = $userid;
        $data['createtime'] = Date('Y-m-d H:i:s');
        $data['status'] = 0;
//      $res = $this->obj->save($data);
        $res =  model('Goods')->save($data);
//        }
        return $this->redirect('index');
    }


    public function edit()
    {
        $goodsid = input('get.goodsid');
        $goods = model('Goods')::get($goodsid);
        $goods['path'] = Config::get('paths.coshost').$goods['picurl'];
        $category  = model('Goodscategory')::where('categoryid', $goods['goodscategoryid'])->find();
        $categorys  = model('Goodscategory')::where('status', 0)->select();
        //
        # 配置参数
        $config = Config::get('wx.wxconfig');
        //js签名
        // 创建SDK实例
        $script = &\Wechat\Loader::get('Script',$config);
        // 获取JsApi使用签名，通常这里只需要传 $url参数
        $appid = Config::get('wx.wxappid');
        $url = Config::get('wx.host').'wechatservice/goods/edit?goodsid='.$goodsid;//当前页面URL地址
        $options = $script->getJsSign($url, 0, '', $appid);
        //
        return $this->fetch('goods_edit',[
            'categoryname'=>$category['categoryname'],
            'goods'=>$goods,
            'categorys'=>$categorys,
            'options'=>json_encode($options)
        ]);
    }

    public function update(){

        $goodsid = input("post.goodsid");
        $goodsmodel = model('Goods')->where('goodsid',$goodsid)->find();
        $preiconurl = $goodsmodel['picurl'];
        $categoryid = input("post.categoryid");
        $goodsname = input("post.goodsname");
        $stucode = input("post.stucode");
        $picurl = input("post.picurl");
        $salefee = input("post.salefee");
        $originalfee = input("post.originalfee");
        $weight = input("post.weight");
        $weightdrift = input("post.weightdrift");
        $spec = input("post.spec");
        $data['goodsname'] = $goodsname;
        $data['goodscategoryid'] = $categoryid;
        $data['stucode'] = $stucode;
        $data['picurl'] = $picurl;
        $data['originalfee'] = $salefee*100;
        $data['salefee'] = $salefee*100;
        $data['originalfee'] = $originalfee*100;
        $data['weight'] = $weight;
        $data['weightdrift'] = $weightdrift;
        $data['spec'] = $spec;
        $res = model('Goods')->save($data,['goodsid'=>$goodsid]);
        //判断图片是否和之前的一样
        if($preiconurl != $data['picurl']){
            $filename = UPLOAD_PATH.$preiconurl;
            // 检测目录
            if(file_exists($filename)){
                unlink($filename);
            }
        }
        if($goodsmodel['status'] == 1){//已审核
            $gboxApi = new \GoboxApi('','');
            $option = [];
            $option['barcode'] = $goodsid;
            $option['name'] = $data['goodsname'];
            $option['spec'] = $data['spec'];
            $option['weight'] = $data['weight'];
            $option['weight_drift'] = $data['weightdrift'];
            $masterresult = $gboxApi->updateSku($option);
            if($masterresult['code'] == 0){
            }
        }
        return $this->redirect('index');
    }

}
