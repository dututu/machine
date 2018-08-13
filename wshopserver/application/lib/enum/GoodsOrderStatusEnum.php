<?php


namespace app\lib\enum;

/**
 * 商品订单状态
 *
 * @author      alvin
 * @version     1.0
 */
class GoodsOrderStatusEnum
{
    // 购物中
    const SHOPPING = 1;
    // 待结账
    const PREPAREFORPAY = 2;
    // 已取消
    const CANCELED = 3;
    // 待支付
    const UNPAID = 4;
    // 已付款
    const PAID = 5;
    // 已欠费
    const ARREARAGED = 6;
    // 转退款
    const REFUND = 7;
    // 已完成
    const COMPLETE = 8;
}