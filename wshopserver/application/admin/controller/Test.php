<?php
namespace app\admin\controller;
use think\Controller;
use think\Loader;
use think\Config;
use think\Log;
use think\Db;
use think\Request;
use app\worker\controller\WechatMessage;
use app\lib\enum\WechatTemplate;
use Wechat\Lib\Tools;

Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
Loader::import('sms.simple-sms', EXTEND_PATH, '.php');
Loader::import('master.RfidApiV2', EXTEND_PATH, '.php');
Loader::import('master.MasterApi', EXTEND_PATH, '.php');
/**
 * 测试类，后期可删除
 *
 * @author      lhw
 * @version     1.0
 */
class Test extends  Controller //Base
{
    private  $obj;
    protected $wechat;
    public function _initialize() {
        $this->obj = model("Goodsorder");
    }
    public function testlog(){
        # 配置参数
        $config = Config::get('wx.wxconfig');
        $wechatreceive = &\Wechat\Loader::get('Receive', $config);
        Tools::log("test.", "ERR");
    }
    public function testopens(){
        $template = array(
            'cmd' => 2,
            'machineid' => '24161e0abf5694e32d2d5274ee169f55',
            'userid' => 'fa334692edebc927b573d25ef3c287cb',
        );
        $re = WechatMessage::add(json_encode($template), "erp_options");
    }
    public function testfiledelete(){
        $filename = UPLOAD_PATH."/upload/20180112/2496ff854907872db52812780c6b42cd.png";
        // 检测目录
        if(file_exists($filename)){
            echo '文件存在,可以删了';
            unlink($filename);
        } else {
            echo '猪,文件不存在,可能路径添错了!';
        }
    }
    public function testrequestexception(){
        $masterApi = new \MasterApi('','');
        $masterresult = $masterApi->testrequest();
        if(!empty($masterresult)) {
            $result2 = json_decode($masterresult, true);
            dump($result2['code']);
        }
    }
    public function testmenus(){
        $groupid = '1';
        $ars = model('Authruleaccess')::where('groupid', $groupid)->select();
        $authrules = [];
        foreach ($ars as $ar){

        }
        dump($ars);
    }


    public function testxiaoliang(){
//        $goods = model('Goods')::where('goodsid', '18')->find();
//        if($goods){
//            $sql = 'SELECT sum(a.amount) t FROM goodsorderdetail a where  a.goodsid =?';
//            $value = Db::query($sql, ['18']);
//            $totalsales = (int)reset($value)['t'];
//            dump($totalsales);
//        }
        $goods = [];
        $good1['goodsid'] = '1';
        $good1['totalsales'] = 4;
        $good2['goodsid'] = '2';
        $good2['totalsales'] = 14;
        $good3['goodsid'] = '3';
        $good3['totalsales'] = 1;
        array_push($goods,$good1);
        array_push($goods,$good2);
        array_push($goods,$good3);
        uasort($goods,function($a,$b){
            return $a['totalsales'] < $b['totalsales'];
        });
        print_r($goods);
    }
    public function sendcustommessage(){
        $machine = model('Machine')->where('machineid','d5361e302e32fc62e3db70b7bb94e385')->find();
        $sysuser = model('Sysuser')->where('userid','579f03cd49291216553be41f70e7854c')->find();
        # 配置参数
        $config = Config::get('wx.wxconfig');
        $wechatreceive = &\Wechat\Loader::get('Receive', $config);
        $openid = $sysuser['openid'];
        // 发送客服消息（$data为数组，具体请参数微信官方文档）
        $replytext = array(
            'touser' => $openid,
            'msgtype' => "text",
            'text'=>array(
                'content' => "您的新机柜已经上线 \n操作时间：".$machine['updatetime']."\n操作用户：".$sysuser['username']." ".$sysuser['mobile']."\n详细地址：".$machine['location']
            )
        );
        $wechatreceive->sendCustomMessage($replytext);
}
public function sendcustommessage2(){
    $onsaleModel = model('Onsalehistory')->where('orderno', '=', 'B124606117306095')->find();
    $sysuser = model('Sysuser')->where('userid','579f03cd49291216553be41f70e7854c')->find();
    $machine = model('Machine')->where('machineid','d5361e302e32fc62e3db70b7bb94e385')->find();
    //发送客服消息
    $goodstr = "";
    $goodstr2 = "";
    $sgoods = model('Onsaledetail')->where('onsaleid', $onsaleModel['historyid']) ->select();
    foreach ($sgoods as $subgoods) {
        if($subgoods['flag'] == 0){//上架
            $goodstr = $goodstr.$subgoods['goodsname']."(数量:".$subgoods['amount'].")；";
        }else{
            $goodstr2 = $goodstr2.$subgoods['goodsname']."(数量:".$subgoods['amount'].")；";
        }
    }
    $template = array(
        'cmd' => 3,
        'data' =>array(
            'touser' => $sysuser['openid'],
            'msgtype' => "text",
            'text'=>array(
                'content' => $machine['location']."购物柜刚刚完成了上货：\n操作时间：".$onsaleModel['operatetime']."\n操作用户：".$sysuser['username']." ".$sysuser['mobile']."\n上架商品：".$goodstr."\n下架商品：".$goodstr2
            )
        )
    );
    $re = WechatMessage::add(json_encode($template),"erp_options");
}
public function sendcustommessage3(){
    //给商户发送客服消息
    $sysuser = model('Sysuser')->where('userid','579f03cd49291216553be41f70e7854c')->find();
    $user = model('User')->where('userid','10d8c9e1f90aa916407690fb7cabb896')->find();
    $machine = model('Machine')->where('machineid','d5361e302e32fc62e3db70b7bb94e385')->find();
    $goodstr = "";
    $sgoods = model('Goodsorderdetail')->where('orderid', '61f918eb9b319f79340dd880cd86b3c6') ->select();
    foreach ($sgoods as $subgoods) {
        $goodstr = $goodstr.$subgoods['goodsname']."(数量:".$subgoods['amount'].")；";
    }
    $template = array(
        'cmd' => 3,
        'data' =>array(
            'touser' => $sysuser['openid'],
            'msgtype' => "text",
            'text'=>array(
                'content' => $machine['location']."购物柜刚刚产生了一笔交易：\n操作时间：".$machine['createtime']."\n操作用户：".$user['nickname']." ".$user['mobile']."\n购买商品：".$goodstr."\n交易金额：".(35/100)."元"
            )
        )
    );
    $re = WechatMessage::add(json_encode($template),"erp_options");
}
public function sendtempmessage(){
    $sysuser = model('Sysuser')->where('userid','a55e3be1c79616b2dde8e59589af65ba')->find();
    $template = array(
        'cmd' => 0,
        'data' =>array(
            'touser' => $sysuser['openid'],
            'template_id' => WechatTemplate::REHARGECHANGE,
            'data' => array(
                'first'=>array(
                    'value' => '尊敬的用户，您的储值余额产生了变动：',
                    'color' => '#173177'
                ),
                'keyword1'=>array(
                    'value' => '微信支付充值',
                    'color' => '#173177'
                ),
                'keyword2'=>array(
                    'value' => '+1000.0元',
                    'color' => '#173177'
                ),
                'keyword3'=>array(
                    'value' => '1050元',
                    'color' => '#173177'
                ),
                'remark'=>array(
                    'value' => '感谢您的支持，欢迎再次光临',
                    'color' => '#173177'
                ),
            )
        )
    );
    WechatMessage::add(json_encode($template),"erp_options");
}
    public function sendtempmessage4(){
//        $sysuser = model('Sysuser')->where('userid','579f03cd49291216553be41f70e7854c')->find();
//        $new_tel2 = substr_replace($sysuser['mobile'], '****', 3, 4);
        $template = array(
            'cmd' => 1,
            'data' =>array(
                'touser' => "oIu0D0VHGJn5GWTjWnP3OmpXk694",
                'template_id' => WechatTemplate::REGSUCCESS,
                'data' => array(
//                    'first'=>array(
//                        'value' => '您好，您已成功注册芝麻开门购物柜会员',
//                        'color' => '#173177'
//                    ),
                    'keyword1'=>array(
                        'value' => "234",
                        'color' => '#173177'
                    ),
                    'keyword2'=>array(
                        'value' => "222",
                        'color' => '#173177'
                    ),
//                    'remark'=>array(
//                        'value' => '感谢您的支持，欢迎再次光临',
//                        'color' => '#173177'
//                    ),
                )
            )
        );
        WechatMessage::add(json_encode($template),"erp_options");
    }
    public function redis(){
        echo phpinfo();
//        $redis = new \Redis();
//        $redis->connect('127.0.0.1', 6379);
//        $redis->set("name",'mikkle');
//        $name= $redis->get("name") ;
//
//        dump($name);
        //添加异步命令行发送模版消息
        $template = array(
            'cmd' => 0,
            'data' =>array(
                'touser' => 'o45AfwTPzvlkREEihhId8gLaw9Dg',
                'template_id' => 'QXlos_5ecjYHSFD_b_0DufFmzPlHUnGLK_yYh2U5AdQ',
                'data' => array(
                    'first'=>array(
                        'value' => '您好，您已经通过审批，正式成为芝麻开门购物柜运营商',
                        'color' => '#173177'
                    ),
                    'keyword1'=>array(
                        'value' => 'aabbccdd',
                        'color' => '#173177'
                    ),
                    'keyword2'=>array(
                        'value' => '同意成为运营商',
                        'color' => '#173177'
                    ),
                    'keyword3'=>array(
                        'value' => Date('Y-m-d H:i:s'),
                        'color' => '#173177'
                    ),
                    'remark'=>array(
                        'value' => '',
                        'color' => '#173177'
                    ),
                )
            )
        );
        //小程序模板消息测试
//        $template = array(
//            'cmd' => 1,
//            'data' =>array(
//                'touser' => 'oIu0D0VHGJn5GWTjWnP3OmpXk694',
//                'template_id' => 'UNB-x9AA8nNC-u-wUp3SRFMxa7bjYHTIX1lQEBRbWhw',
//                'page' => 'pages/index/index',
//                'form_id' => '5867548850a4906329c33e5d27bbe0df',
//                'data' => array(
//                    'keyword1'=>array(
//                        'value' => '储值'
//                    ),
//                    'keyword2'=>array(
//                        'value' => Date('Y-m-d H:i:s')
//                    ),
//                    'keyword3'=>array(
//                        'value' => '50.01'
//                    ),
//                    'keyword4'=>array(
//                        'value' => "AC20516830324560"
//                    ),
//                    'keyword5'=>array(
//                        'value' => "50"
//                    ),
//                    'keyword6'=>array(
//                        'value' => "15004"
//                    ),
//                    'keyword7'=>array(
//                        'value' => "0.01"
//                    ),
//                    'keyword8'=>array(
//                        'value' => "您已充值成功"
//                    )
//                )
//            )
//        );
        $re = WechatMessage::add(json_encode($template),"erp_options");
        //dump($re);
//        Log::notice("发送{$uuid_name}提交的{$type["title"]}审核模版消息成功!$re");
//        dump($re);
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
        $value = $this->obj->getListTest(10,0,'','');
//        $records = $this->obj->getListCount($s_name,$s_select);
//        $total = ceil($records/$rows);
        return $this->fetch('index',[
            'orders'=>$value,
        ]);
    }
    public function socket()
    {
        return $this->fetch('socket',[
            'orders'=>'',
        ]);
    }
    public function testdot(){
        $goodsnames = '';
        $goods = model('Goodsorderdetail')->where('orderid', 'c4a36b182cd764e474250ffcc88fb0ba')->select();
        foreach ($goods as $subgoods) {
            $goodsnames = $goodsnames.$subgoods['goodsname'].'*'.$subgoods['amount'].';';
        }
    }
    public function getMachineGoods(){
        $containerids = [];
        array_push($containerids, '888');
        $pushdata = array(
            'serialsnumber' => uuid(),
            'containerids' => $containerids,
            'timestamp' => time(),
        );
//        $testd = json_encode($pushdata);
        $wxOrderData = new \RfidApiV2('','');
        $orderresult = $wxOrderData->selCabinetStockInfo($pushdata);
        if(!empty($orderresult)){
            $result = json_decode($orderresult,true);
            foreach ($result['containerids']['888'] as $returngoods) {
                $goods = model('Goods')::where('stucode', $returngoods['barCode'])->find();
                if($goods){
                    $goods['amount'] = $returngoods['amount'];//库存
                }
            }
        }else{
            dump('result is empty');
        }

    }
    public function createMenu(){
        # 配置参数
        $config = Config::get('wx.wxconfig');
        $this->wechat = &\Wechat\Loader::get('menu', $config);
        // 创建微信菜单
        $postArr=array(
            'button'=>array(
                array(
                    'name'=>'我的交易',
                    'type'=>'miniprogram',
                    'url'=>'http://mp.weixin.qq.com',
                    'appid'=>'wxe8eac4281b3a65d7',
                    'pagepath'=>'pages/index/index',
                ),
                array(
                    'name'=>'我要加盟',
                    'sub_button'=>array(
                        array(
                            'name'=>'我要运营',
                            'type'=>'view',
                            'url'=>Config::get('wx.host').'wechatservice/operate'
                        ),//第一个二级菜单
                        array(
                            'name'=>'产品介绍',
                            'type'=>'view',
                            'url'=>'http://mp.weixin.qq.com/s?__biz=MzI5NTg2ODgzNw==&mid=100000087&idx=1&sn=3e856096dfbd3a3af6792fb11373986d&chksm=6c4c419d5b3bc88b27dbd7a9cd64bab987d8534fb30cb1b61c7d35893a9a86e2b429424eea3e#rd'
                        ),//第二个二级菜单
                        array(
                            'name'=>'销售政策',
                            'type'=>'view',
                            'url'=>'http://mp.weixin.qq.com/s?__biz=MzI5NTg2ODgzNw==&mid=100000068&idx=1&sn=98e5f82ed596f10b2ac954e94f2feaaf&chksm=6c4c418e5b3bc8984c892fb718a0e80fcaad75cb5ea300524b9be0574c49c9ae4a83a3992e19#rd'
                        ),//第二个二级菜单
                        array(
                            'name'=>'了解我们',
                            'type'=>'view',
                            'url'=>'http://mp.weixin.qq.com/s?__biz=MzI5NTg2ODgzNw==&mid=100000078&idx=1&sn=175792bcbe1fba9bf344cfe888ab8e4a&chksm=6c4c41845b3bc892156544ce13f9a779b06038645ad22d2ebfcf02cbc1972686fd8ba411cb6b#rd'
                        ),
                    )
                ),

            ));
        $result = $this->wechat->createMenu($postArr);

        // 处理创建结果
        if($result===FALSE){
            // 接口失败的处理
            echo $this->wechat->errMsg;
        }else{
            // 接口成功的处理
            echo $this->wechat->errMsg;
        }
    }

    public function createCustomMenu(){
        # 配置参数
        $config = Config::get('wx.wxconfig');
        $this->wechat = &\Wechat\Loader::get('menu', $config);
        $productinfo = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=wx476a68faeef5b3ca&redirect\_uri=192.168.1.103:8888/wechatservice/goods&response\_type=code&scope=snsapi\_base&state=123#wechat\_redirect';
        // 创建微信菜单
        $postArr=array(
            'button'=>array(
                array(
                    'name'=>'我的交易',
                    'type'=>'miniprogram',
                    'url'=>'http://mp.weixin.qq.com',
                    'appid'=>'wxe8eac4281b3a65d7',
                    'pagepath'=>'pages/index/index',
                ),
                array(
                    'name'=>'商家运营',
                    'sub_button'=>array(
                        array(
                            'name'=>'扫码开柜',
                            'type'=>'scancode_waitmsg',
                            'key'=>'requestopen'
                        ),//第一个二级菜单
                        array(
                            'name'=>'库存查看',
                            'type'=>'view',
                            'url'=>Config::get('wx.host').'wechatservice/stock'
                        ),//第二个二级菜单
                        array(
                            'name'=>'商品信息',
                            'type'=>'view',
                            'url'=>Config::get('wx.host').'wechatservice/goods'
                        ),//第二个二级菜单
                        array(
                            'name'=>'电子标签',
                            'type'=>'view',
                            'url'=>Config::get('wx.host').'wechatservice/rfid/orders'
                        )
                    )
                ),
                array(
                    'name'=>'商家中心',
                    'sub_button'=>array(
                        array(
                            'name'=>'我的销售',
                            'type'=>'view',
                            'url'=>Config::get('wx.host').'wechatservice/sales/statis'
                        ),//第一个二级菜单
                        array(
                            'name'=>'我的机柜',
                            'type'=>'view',
                            'url'=>Config::get('wx.host').'wechatservice/machine'
                        ),//第二个二级菜单
                        array(
                            'name'=>'我的账户',
                            'type'=>'view',
                            'url'=>Config::get('wx.host').'wechatservice/account'
                        )
                    )
                )
            ),
            'matchrule'=>array(
                'tag_id'=>'103'
            ));
        $result = $this->wechat->createCondMenu($postArr);

        // 处理创建结果
        if($result===FALSE){
            // 接口失败的处理
            echo $this->wechat->errMsg;
        }else{
            // 接口成功的处理
            dump('success');
            echo $this->wechat->errMsg;
        }
    }


    public function getuserinfo(){
        # 配置参数
        $config = Config::get('wx.wxconfig');
        $this->wechat = &\Wechat\Loader::get('User', $config);
        // 创建微信菜单
        $result = $this->wechat->getUserInfo('o45Afwe3W7d9lY1c2tlnexzjSH00');
        // 处理创建结果
        if($result===FALSE){
            // 接口失败的处理
            dump($this->wechat->errMsg) ;
        }else{
            // 接口成功的处理
            dump($result) ;
        }
    }
    public function uploadparams(){
        echo ini_get('upload_max_filesize');
        echo ini_get('post_max_size');
    }
    public function yesterdayorders(){
        $yesterday=date('Y-m-d',time ()- ( 1  *  24  *  60  *  60 ));
        dump($yesterday);
        $goodsorders = Db::table('goodsorder')->where("DATE_FORMAT(createtime, '%Y-%m-%d')=:param1 and orderstatus=:param2",['param1'=>$yesterday,'param2'=>5])->update(['orderstatus' => 8]);
        dump($goodsorders);
    }
    public function userlabels(){
        # 配置参数
        $config = Config::get('wx.wxconfig');
        $user = &\Wechat\Loader::get('User', $config);
        //获取粉丝标签列表
        $result = $user->getTags();

        //处理结果
        if($result===FALSE){
            //接口失败的处理
            echo $user->errMsg;
        }else{
            //接口成功的处理
            dump($result['tags'])  ;
        }
    }
    public function addUser2WechatGroup(){
        # 配置参数
        $config = Config::get('wx.wxconfig');
        $user = &\Wechat\Loader::get('User', $config);
        $openid_list = array(
            0=>'o45AfwTPzvlkREEihhId8gLaw9Dg'
        );
        //批量为粉丝打标签 商户的标签id是100
        $result = $user->batchAddUserTag(100, $openid_list);

        //处理结果
        if($result===FALSE){
            //接口失败的处理
            echo $user->errMsg;
        }else{
            //接口成功的处理
        }
    }
    public function sss(){
        $machine = model('Machine')->where('containerid','XX2111804160018')->find();
        dump($machine);
        Db::table('machine')->where("containerid=:param1",['param1'=>'XX2111804160018'])->update(['businessstatus' => 4]);
//        $machine = model('Machine')->where('containerid','XX2211805100004')->find();
//        dump($machine);

    }
    public function getUsersUnderTag100(){
        # 配置参数
        $config = Config::get('wx.wxconfig');
        $user = &\Wechat\Loader::get('User', $config);
        //获取标签下的粉丝列表
        $result = $user->getTagUsers(100);

        //处理结果
        if($result===FALSE){
            //接口失败的处理
            echo $user->errMsg;
        }else{
            //接口成功的处理
            dump($result);
        }
    }
    public function testarray(){
        $arr = array (
            'order_number' => 'AC21223083458613',
            'timestamp' => 1513822379,
            'commoditys' =>
                array (
                    0 =>
                        array (
                            'commodity_name' => '小猫',
                            'commodity_grades' => '只',
                            'commodity_barcode' => '7004397f2c234d2f78c8ac7f00031281',
                            'commodity_number' => 1,
                            'commodity_skus' => 'ghhjhhhghh225888',
                        ),
                ),
        );
        dump(json_encode($arr));
    }
    public function sendappplyresult($openid){
        # 配置参数
        $config = Config::get('wx.wxconfig');
        # 加载对应操作接口
        $wechat = &\Wechat\Loader::get('Receive', $config);
        $postArr = array(
            'touser' => $openid,
            'template_id' => 'QXlos_5ecjYHSFD_b_0DufFmzPlHUnGLK_yYh2U5AdQ',
            'data' => array(
                'first'=>array(
                    'value' => '您好，您已经通过审批，正式成为芝麻开门购物柜运营商',
                    'color' => '#173177'
                ),
                'keyword1'=>array(
                    'value' => '长跑男',
                    'color' => '#173177'
                ),
                'keyword2'=>array(
                    'value' => '同意成为运营商',
                    'color' => '#173177'
                ),
                'keyword3'=>array(
                    'value' => Date('Y-m-d H:i:s'),
                    'color' => '#173177'
                ),
                'remark'=>array(
                    'value' => '',
                    'color' => '#173177'
                ),
            )
        );
        $wechat->sendTemplateMessage($postArr);
    }

    public function testarrays(){
        $people = array("Bill", "Steve", "Mark", "David");

        echo current($people) . "<br>"; // 当前元素是 Bill
        echo next($people) . "<br>"; // Bill 的下一个元素是 Steve
        echo current($people) . "<br>"; // 现在当前元素是 Steve
        echo prev($people) . "<br>"; // Steve 的上一个元素是 Bill
        echo end($people) . "<br>"; // 最后一个元素是 David
        echo prev($people) . "<br>"; // David 之前的元素是 Mark
        echo current($people) . "<br>"; // 目前的当前元素是 Mark
        echo reset($people) . "<br>"; // 把内部指针移动到数组的首个元素，即 Bill
        echo next($people) . "<br>"; // Bill 的下一个元素是 Steve

    }
    public function testconfig(){
        $factory = Config::get('machinedict.doortype')['1'];
        dump($factory);
    }
    public function sendsms(){
        $rand = rand(1000,9999);
        $content = '【知码开门】您的短信验证码为 '.$rand;
        $mobole = '18678902870';
        $smsid = uuid();
        $result = sendSMS($content,$mobole,$smsid);
        if($result['code'] == 'SUCCESS'){
            dump('success');
        }else{
            dump('fail');
        }
    }
    public function deletemenus(){
        # 配置参数
        $config = Config::get('wx.wxconfig');
        $menu = &\Wechat\Loader::get('menu', $config);
        // 取消发布微信菜单
        $result = $menu->deleteMenu();
        // 处理创建结果
        if($result===FALSE){
            // 接口失败的处理
            echo $menu->errMsg;
        }else{
            // 接口成功的处理
        }
    }
    public function getMenus(){
        # 配置参数
        $config = Config::get('wx.wxconfig');
        $menu = &\Wechat\Loader::get('menu', $config);
        // 取消发布微信菜单
        $result = $menu->getMenu();
        // 处理创建结果
        if($result===FALSE){
            // 接口失败的处理
            echo $menu->errMsg;
        }else{
            // 接口成功的处理
            dump($result);
        }
    }
    public function testAuth(){
        # 配置参数
        $config = Config::get('wx.wxconfig');
        $oauth = &\Wechat\Loader::get('Oauth', $config);
        // 执行接口操作
        $callback = Config::get('wx.host').'wechatservice/goods';
        $state = 12345;
        $scope = 'snsapi_base';//snsapi_userinfo
        $result = $oauth->getOauthRedirect($callback, $state, $scope);
        Log::info($result);

// 处理返回结果
        if($result===FALSE){
            // 接口失败的处理
            return false;
        }else{
            // 接口成功的处理
//            array (
//                'code' => '061BlsM01nfrj02ozuN011dcM01BlsMC',
//                'state' => '1234',
//            )
            return $this->redirect($result);
            // 执行接口操作

        }
    }

    public function myrifdlist(){
        $value = model('Rfidorderdetail')->rfidlist(100,0,'35a724b69c9a640db3cd7a51008fdfcd');
        dump($value);
    }
}
