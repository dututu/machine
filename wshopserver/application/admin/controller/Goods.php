<?php
namespace app\admin\controller;
use think\Controller;
use think\Loader;
use think\Config;
use think\Log;
use app\common\model\Merchant as MerchantModel;

Loader::import('master.RfidApiV2', EXTEND_PATH, '.php');
Loader::import('master.GoboxApi', EXTEND_PATH, '.php');
/**
 * 商品管理
 *
 * @author      Caesar
 * @version     1.0
 */
class Goods extends  Adminbase
{
    private  $obj,$categoryobj;
    public function _initialize() {
        parent::_initialize();
        $this->obj = model("Goods");
        $this->categoryobj = model("Goodscategory");
    }
    protected $beforeActionList = [
        'checkAuth' => ['only' => 'index,category']
    ];
    /**
     * 商品管理页
     * @access public
     * @return tp5
     * @throws
     */
    public function index()
    {
        $unapply = $this->obj->getCountByStatus(0);
        $apply = $this->obj->getCountByStatus(1);
        $list = MerchantModel::select();
        return $this->fetch('',[
            'unapply'=>$unapply,
            'apply'=>$apply,
            'merchants'=>$list
        ]);
    }
    /**
     * 商品详情页
     * @access public
     * @param $page $goodsid $status
     * @return tp5
     * @throws
     */
    public function detail($page=1,$goodsid='',$status='',$sidx='',$sord='',$name='')
    {
        $rfidspecs = model('Rfidspec')->select();
        return $this->fetch('product_detail',[
            'goodsid'=>$goodsid,
            'page'=>$page,
            'status'=>$status,
            'sidx'=>$sidx,
            'sord'=>$sord,
            'name'=>$name,
            'rfidspecs'=>$rfidspecs,
        ]);
    }
    /**
     * 商品审核
     *
     * @access public
     * @param goodsid
     * @throws
     */
    public function apply()
    {
        $goodsid = input("goodsid");
        $status = input("status");
        $rfidspec = input("rfidspec");

        $data['status'] = $status;
        $data['rfidtypeid'] = $rfidspec;
        $userid = $this->getLoginUser()['userid'];
        $data['updater'] = $userid;
        $data['updatetime'] = Date('Y-m-d H:i:s');
        //
        $goodsmodel = model('Goods')->where('goodsid',$goodsid)->find();
        $option = [];
        $option['time'] = time();
        $option['barcode'] = $goodsid;
        $option['name'] = $goodsmodel['goodsname'];
        $option['weight'] = $goodsmodel['weight'];
        $option['spec'] = $goodsmodel['spec'];
        $option['weight_drift'] = $goodsmodel['weightdrift'];
        $gboxApi = new \GoboxApi('','');
        $masterresult = $gboxApi->addSku($option);
        if($masterresult['code'] == 0){
            $res = $this->obj->save($data,['goodsid' => $goodsid]);
            $this -> result($_SERVER['HTTP_REFERER'],1,'success');
        }else{
            $this -> result($_SERVER['HTTP_REFERER'],0,'审核失败-添加到erp库失败,原因:'.$masterresult['msg']);
        }


    }
    /**
     * ajax获取商品列表
     *
     * 前端使用jqgrid控件渲染表格
     * @access public
     * @return tp5
     */
    public function goodslist(){
        //search params
        $s_name = input("s_name");
        $s_select = input("s_select");
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $status = input("status");
        $offset = (input("page") - 1) * input("rows");
        $value = $this->obj->getList($rows,$sidx,$sord,$offset,$s_name,$s_select,$status);
        foreach ($value as $goods){
            $goods['picurl'] = Config::get('paths.coshost').$goods['picurl'];
        }
        $records = $this->obj->getListCount($s_name,$s_select,$status);
        $total = ceil($records/$rows);
//        $this->recordlog('4',0,'获取商品列表');
        return pagedata($page,$total,$records, $value);
    }

    /**
     * 商品分类管理
     *
     * @access public
     * @param
     * @param
     * @param
     * @return tp5
     */
    public function category()
    {
        return $this->fetch();
    }

    /**
     * ajax获取商品分类列表
     *
     * 前端使用jqgrid控件渲染表格
     * @access public
     * @return tp5
     */
    public function categorylist(){
        //search params
        $s_name = input("s_name");
        $s_select = input("s_select");
        //orderby and page param
        $page = input("page");
        $rows = input("rows");
        $sidx = input("sidx");
        $sord = input("sord");
        $offset = (input("page") - 1) * input("rows");
        $value = $this->categoryobj->getList($rows,$sidx,$sord,$offset,$s_name,$s_select);
        $records = $this->categoryobj->getListCount($s_name,$s_select);
        $total = ceil($records/$rows);
//        $this->recordlog('4',0,'获取商品分类列表');
        return pagedata($page,$total,$records, $value);
    }
    /**
     * 新增分类页面
     *
     * @access public
     * @return tp5
     */
    public function categoryadd()
    {
        return $this->fetch('product_category_add',[
//            'categorys'=>$categorys,
        ]);
    }
    /**
     * 新增分类提交
     *
     * @access public
     * @return tp5
     */
    public function categorysave()
    {
        if(!request()->isPost()){
            $this ->error('请求失败');
        }
        $data = input("post.");
//        $validate =  validate('Recharge');
//        if(!$validate ->scene('add') ->check($data)){
//            $this -> error($validate -> getError());
//        }
        if(!empty($data['categoryid'])){
            return $this ->categoryupdate($data);
        }
        $data['categoryid'] = uuid();
        $loginuserid = $this->getLoginUser()['userid'];
        $data['creater'] = $loginuserid;
        $data['createtime'] = Date('Y-m-d H:i:s');
        $data['parentid'] = '0';
        $res = $this->categoryobj ->save($data);
        if($res){
            $this->recordlog('1',0,'新增商品分类');
            $this -> result($_SERVER['HTTP_REFERER'],1,'success');
        }else{
            $this->recordlog('1',1,'新增商品分类');
            $this -> result($_SERVER['HTTP_REFERER'],0,'fail');
        }
    }
    /**
     * 更新分类操作
     *
     * @access public
     * @return tp5
     */
    public function categoryupdate($data){
        $cate = model('Goodscategory')->where('categoryid',$data['categoryid'])->find();
        $preiconurl = $cate['iconurl'];
        $loginuserid = $this->getLoginUser()['userid'];
        $data['updater'] = $loginuserid;
        $data['updatetime'] = Date('Y-m-d H:i:s');
        $res =  $this->categoryobj->save($data,['categoryid'=>$data['categoryid']]);
        if($res){
            //判断图片是否和之前的一样
            if($preiconurl != $data['iconurl']){
                $filename = UPLOAD_PATH.$preiconurl;
                // 检测目录
                if(file_exists($filename)){
                    unlink($filename);
                }
            }
            $this->recordlog('2',0,'更新商品分类');
            $this -> success('更新成功');
        }else{
            $this->recordlog('2',1,'更新商品分类');
            $this ->error('更新失败');
        }
    }
    /**
     * 编辑分类页面
     *
     * @access public
     * @return tp5
     */
    public function gotoeditcategory($categoryid='')
    {
        $typemodel = $this->categoryobj->where('categoryid',$categoryid)->find();
        return $this->fetch('product_category_edit',[
            'category'=>$typemodel,
        ]);
    }
    /**
     * 删除分类
     *
     * @access public
     * @return tp5
     */
    public function delcategory($id)
    {
        $res = $this->categoryobj->where(['categoryid'=>$id])->delete();
        if($res){
            $this->recordlog('3',0,'删除商品分类');
            $this -> result($_SERVER['HTTP_REFERER'],1,'success');
        }else{
            $this->recordlog('3',1,'删除商品分类');
            $this -> result($_SERVER['HTTP_REFERER'],0,'fail');
        }
    }
    /**
     * 停用分类
     *
     * @access public
     * @return tp5
     */
    public function stopcategory($id)
    {
        $loginuserid = $this->getLoginUser()['userid'];
        $res = $this->categoryobj->where(['categoryid'=>$id])->update(['status' => 1,'updater' => $loginuserid,'updatetime' => Date('Y-m-d H:i:s')]);
        if($res){
            $this -> result($_SERVER['HTTP_REFERER'],1,'success');
        }else{
            $this -> result($_SERVER['HTTP_REFERER'],0,'fail');
        }
    }
    /**
     * 启动分类
     *
     * @access public
     * @return tp5
     */
    public function startcategory($id)
    {
        $loginuserid = $this->getLoginUser()['userid'];
        $res = $this->categoryobj->where(['categoryid'=>$id])->update(['status' => 0,'updater' => $loginuserid,'updatetime' => Date('Y-m-d H:i:s')]);
        if($res){
            $this -> result($_SERVER['HTTP_REFERER'],1,'success');
        }else{
            $this -> result($_SERVER['HTTP_REFERER'],0,'fail');
        }
    }
}
