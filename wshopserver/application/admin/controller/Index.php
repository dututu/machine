<?php
namespace app\admin\controller;
use think\Controller;
use think\Log;
use think\Loader;
Loader::import('wechat-php-sdk.include', EXTEND_PATH, '.php');
/**
 * 首页（控制台）
 *
 * @author      Caesar
 * @version     1.0
 */
class Index extends Adminbase
// class Index extends Adminbase
{
    protected $beforeActionList = [
        // 'checkAuth' => ['only' => 'index']
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
//        $userid = $this->getLoginUser()['userid'];
//        Log::info($userid);
//        dump($_SESSION);
        return $this->fetch();
    }
}
