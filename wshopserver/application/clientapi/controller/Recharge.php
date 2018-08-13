<?php
namespace app\clientapi\controller;
use app\clientapi\validate\IDMustBePositiveInt;
use think\Controller;
use think\Config;
use \think\Log;
use \think\Request;
use app\common\model\User as UserModel;
use app\common\model\Rechargeactivity as RechargeModel;
use app\common\model\Rechargeorder as RechargeOrderModel;
use app\common\model\Rechargelog as RechargeLogModel;
use app\common\model\Formmessage as MessageModel;
use app\clientapi\service\Token;

/**
 * 充值相关接口
 */
class Recharge extends Controller
{

    /**
     * 获取充值活动列表
     * @param
     * @return 详情
     */
    public function activity() {
        $list = RechargeModel::where('status',0)->order('fee asc')->field(['activityid','fee','giftfee'])->select();
        return result(200,"success",$list);
    }
    /**
     * 交易明细列表
     * @param
     * @return 详情
     */
    public function logs() {
        $request = Request::instance();
        $uid = Token::getCurrentUid();
        $page = input("page");
        $model = new RechargeLogModel();
        $offset = ( $page - 1) * 20;
        $result = $model->getList(20,$offset,$uid);
        $list = $result['result'];
        foreach ($list as $log){
            if($log['logtype'] == 1){//储值
                $order = model('Rechargeorder')::where('orderid',$log['serialno'])->find();
                if($order){
                    $log['orderno'] = $order['orderno'];
                }

            }else if($log['logtype'] == 2){//消费
                $order = model('Goodsorder')::where('orderid',$log['serialno'])->find();
                if($order){
                    $log['orderno'] = $order['orderno'];
                }
            }else if($log['logtype'] == 3){//退款
                $order = model('Outrefund')::where('orderid',$log['serialno'])->find();
                if($order){
                    $log['orderno'] = $order['orderno'];
                }
            }
        }
        $total = $result['total'];
        $data = [];
        $hasnext = true;
        if($page*20>=$total){
            $hasnext = false;
        }
        $data['page'] = $page;
        $data['total'] = $total;
        $data['hasnext'] = $hasnext;
        $data['data'] = $list;
        return result(200,"success",$data);
    }
    /**
     * 创建储值订单
     * @param userid 用户id
     * @return show status 1 成功
     */
    public function generateorder()
    {
        $request = Request::instance();
        $uid = Token::getCurrentUid();
        $data = [];
        $activityid = input('post.activityid');
        $formid = input('post.formid');
        $rechargeModel = RechargeModel::get($activityid);
        //新建订单
        $order = [];
        $uuid = uuid();
        $order['orderid'] = $uuid;
        $order['userid'] = $uid;
        $order['orderno'] = makeOrderNo();
        $order['activityid'] = $activityid;
        $serialno = uuid();
        $order['serialno'] = $serialno;
//        $order['batchno'] = $batchno;
        $order['fee'] = $rechargeModel['fee'];
        $order['realfee'] = $rechargeModel['fee']+$rechargeModel['giftfee'];
        $order['status'] = 1;//待付款
        $order['createtime'] = date("Y-m-d H:i:s", time());
        RechargeOrderModel::create($order);
        //新建模版消息记录
//        if($formid){
//            $message = [];
//            $muuid = uuid();
//            $message['fmid'] = $muuid;
//            $message['formid'] = $formid;
//            $message['orderid'] = $uuid;
//            $message['templateid'] = 'UNB-x9AA8nNC-u-wUp3SRFMxa7bjYHTIX1lQEBRbWhw';
//            $message['userid'] = $uid;
//            $message['sendstatus'] = 0;
//            $message['createtime'] = date("Y-m-d H:i:s", time());
//            $message['templatedata'] = '';
//            MessageModel::create($message);
//        }

        //
        $data = [];
        $data['orderid'] = $uuid;
        return result(200, 'success', $data);
    }
    /**
     * 更新储值订单支付状态
     * @param userid 用户id
     * @return show status 1 成功
     */
    public function orderpaystatus()
    {
        $uid = Token::getCurrentUid();
        $orderid = input('post.orderid');
        $paystatus = input('post.status');
        $order = RechargeOrderModel::get($orderid);
        if($paystatus == 1){//成功
            $order['status'] = 2;
            $user = UserModel::get($uid);
            $user['fee'] = $user['fee']+$order['realfee'];
            $user->save();
            //记录日志
            $log = [];
            $uuid = uuid();
            $log['logid'] = $uuid;
            $log['userid'] = $uid;
            $log['logtype'] = 1;
            $log['fee'] = $order['realfee'];
            $log['serialno'] = $orderid;
            $log['createtime'] = date("Y-m-d H:i:s", time());
            RechargeLogModel::create($log);
            //更新模版消息记录
//            $message = MessageModel::where('orderid',$orderid)
//                ->where('userid',$uid)
//                ->find();
//            if($message){
//                $AppID = Config::get('wx.app_id');
//                $AppSecret = Config::get('wx.app_secret');
//                $tokenurl = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$AppID."&secret=".$AppSecret;
//                $tokenresult = doCurl($tokenurl,0,[]);
//                Log::info($tokenresult);
//                $tokenarray = json_decode($tokenresult,true);
//                $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=".$tokenarray['access_token'];
//                $openid = $user['openid'];
//                $template_id = $message['templateid'];
//                $page = 'pages/index/index';
//                $form_id = $message['formid'];
//                $keyword1 = '储值';
//                $keyword2 = $order['createtime'];
//                $keyword3 = (($order['realfee'])/100).'元';
//                $keyword4 = $order['orderno'];
//                $keyword5 = (($order['realfee']-$order['fee'])/100).'元';
//                $keyword6 = ($user['fee']/100).'元';
//                $keyword7 = (($order['fee'])/100).'元';
//                $keyword8 = '您已充值成功';
//                $messagedata = '{"touser":"'.$openid.'","template_id":"'.$template_id.'","page":"'.$page.'","form_id":"'.$form_id.'","data":{"keyword1":{"value":"'.$keyword1.'"},"keyword2":{"value":"'.$keyword2.'"},"keyword3":{"value":"'.$keyword3.'"},"keyword4":{"value":"'.$keyword4.'"},"keyword5":{"value":"'.$keyword5.'"},"keyword6":{"value":"'.$keyword6.'"},"keyword7":{"value":"'.$keyword7.'"},"keyword8":{"value":"'.$keyword8.'"}}}';
//                $ch = api_notice_increment($url,$messagedata);
//                $message->sendstatus     = 1;
//                $message->templatedata    = $messagedata;
//                $message->save();
//            }

        }else{
            $order['status'] = 3;
        }
        $order->save();
        return status(200, 'success');
    }
}
