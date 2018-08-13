<?php
namespace app\clientapi\controller;
use think\Controller;
use think\Db;
use \think\Log;

/**
 * 用户相关接口
 */
class Index extends Controller
{

    /**
     * 扫码机柜二维码原始链接，防止其它工具扫码报找不到控制器的错误
     * @return 详情
     */
    public function index() {
        echo '您没有小程序体验权限或请使用微信扫描机柜二维码';
    }

}
