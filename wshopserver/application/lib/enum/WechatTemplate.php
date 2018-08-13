<?php


namespace app\lib\enum;

/**
 * rfid订单状态
 *
 * @author      alvin
 * @version     1.0
 */
class WechatTemplate
{
    //-------服务号-------
    // rfid标签状态
    const RFIDORDER = 'LxbcWv9Zafujv4r0NgmM7_x_yaBNV28aBPDmjFK5MB4';
    // 商户审核结果通知
    const MERCHANTAPPLY = 'QXlos_5ecjYHSFD_b_0DufFmzPlHUnGLK_yYh2U5AdQ';
    // 储值余额变动通知
    const REHARGECHANGE = 'VM4vBSyl1EgFg0keUUzBScCrs6pBH0fPgj_hE0LKhcg';
    //-------小程序-------
    // 注册成功通知
    const REGSUCCESS = 'pk6HrqnCl0feji2I4ZRGxCjn01sTvJmccI4H2ykPosk';//没用
    // 小程序订单支付成功通知
    const APPPAYSUCCESS = 'DVhSlL_7oT8H86PMxRkz2S_d56WYAQLTg0s95hhU9-k';
    // 小程序订单未支付通知
    const APPUNPAY = '4nKjenk4lnAenqFkZzPZnv2qMIf2TgfU77JAMjE5bTI';
}
