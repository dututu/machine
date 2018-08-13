<?php
namespace app\workweixin\controller;

use think\Controller;
use think\Cookie;
use think\Exception;
use think\Log;
use think\Loader;
use think\Config;
use think\Paginator;
use think\Request;
use app\workweixin\service\Common;

Loader::import( 'wechat-php-sdk.include',EXTEND_PATH,'.php' );

/**
 * 基类base
 *
 * @author      Caesar
 * @version     1.0
 */
class Base extends Controller
{
    protected $start;
    protected $date;
    protected $obj;
    protected $config = [];

    public function _initialize()
    {
        $ep_secret = Config::get( 'workreportconfig.secret' );
        $this->obj = new Common( $ep_secret );
        $this->getDate();
        //$this->checkPower();
    }

    protected function getDate()
    {
        $this->start = input('start',date( 'Y-m-d',strtotime( '-1 day' )));
        $this->end = input('end',date( 'Y-m-d',strtotime( '-1 day' )));
        $this->assign( 'start',$this->start );
        $this->assign( 'end',$this->end );
    }


    protected function checkPower()
    {
        $agentid = Config::get( 'workreportconfig.agentid' );

        $code = input( 'code' );
        $user_id = base64_decode( Cookie::get( 'user_id' ) );
        if( $user_id ) {
            return true;
        } elseif( $code ) {
            $res = $this->obj->getUserTicketByCode( $code );
            if( $res && $res['errcode'] == 0 ) {
                $res = $this->checkTag($res['user_ticket']);
                if( !$res )
                    return $this->error('没有权限查看');

            } else {
                return $this->error( $res['errmsg'] );
            }
        } else {
            $base = Request()->baseUrl();
            //获取优惠券审核应用标签
            $redirect_uri = urlencode( rtrim(Config::get( 'host' ),'/') . $base );
            $scope = 'snsapi_userinfo';
            $state = '';
            $corpID = Config::get( 'cropid' );
            $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $corpID . '&redirect_uri=' . $redirect_uri . '&response_type=code&scope=' . $scope . '&agentid=' . $agentid . '&state=' . $state.'#wechat_redirect';
            return $this->redirect( $url );
        }
    }

    protected function checkTag($user_ticket){
        //检测当前人是否有权限查看应用内 内容
        $res = $this->obj->getUserIdByTicket($user_ticket);
        $res = $this->checkResult($res);
        $userid = $res['userid'];
        //获取标签成员
        $tagid = Config::get('workreportconfig.platform_report');
        $res = $this->obj->getTagByTagId($tagid);
        $res = $this->checkResult($res);
        $userlist = array_column($res['userlist'], 'userid');
        if( in_array($userid,$userlist)){
            Cookie::set( 'user_id',base64_encode($userid),7200);
            return true;
        }else{
            return false;
        }
    }


    /**
     * 验证结果
     * @param $res
     */
    public function checkResult($res){
        if( $res ){
            if( isset($res['errcode']) && $res['errcode'] != 0){
                $this->error($res['errmsg']);
                exit();
            }else{
                return $res;
            }
        }else{
            $this->error('获取失败');
            exit();
        }

    }

    /**
     * 获取分页页码
     */
    protected function getPage(){
        $page = Paginator::getCurrentPage();
        $arr = [];
        $arr['page'] = $page;
        $this->config = $arr;
    }

    protected function ajaxPageMore( $result ){
        $data['paginator'] = [];
        $data['paginator']['hasMore'] = $result->getCurrentPage() < $result->lastPage();
        $data['paginator']['total'] = $result->total();
        $data['paginator']['lastPage'] = $result->lastPage();
        $data['paginator']['path'] = $result->getCurrentPath();
        $data['paginator']['page'] = (int)$result->getCurrentPage();
        $data['paginator']['listRows'] = $result->listRows();
        $data['paginator'] = $result->toArray();
        return $data;
    }

    protected function getPaginator($result){
        $data = $result->toArray();
        $data['hasMore'] = $result->getCurrentPage() < $result->lastPage();
        return $data;
    }




}
