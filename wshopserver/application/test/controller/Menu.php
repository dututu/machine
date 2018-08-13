<?php
/**
 * Created by PhpStorm.
 * User: caesar
 * Date: 2018/3/27
 * Time: 下午10:14
 */
namespace app\test\controller;
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
class Menu extends Controller
{
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
                    'appid'=>'wx9b0e57d73efd4ba1',
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
                    'appid'=>'wx9b0e57d73efd4ba1',
                    'pagepath'=>'pages/index/index',
                ),
                array(
                    'name'=>'商家运营',
                    'sub_button'=>array(
                        array(
                            'name'=>'开门上货',
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
                            'name'=>'上货准备',
                            'type'=>'view',
                            'url'=>Config::get('wx.host').'wechatservice/onsale/index'
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
                'tag_id'=> Config::get('wx.merchanttagid')
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
    public function parseurl(){
        $result = parse_url('http://www.xxxx.com?id=xxxxxx');
        $query = $result['query'];
        $querys = explode('&',$query);
        if(count($querys)==1){
            $machinesn = explode('=',$querys[0])[1];
            dump($machinesn);
        }else if(count($querys)==2){
            $machinesn = explode('=',$querys[0])[1];
            $lockid = explode('=',$querys[1])[1];
            dump($machinesn);
            dump($lockid);
        }
    }

}