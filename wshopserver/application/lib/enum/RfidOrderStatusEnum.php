<?php


namespace app\lib\enum;

/**
 * rfid订单状态
 *
 * @author      alvin
 * @version     1.0
 */
class RfidOrderStatusEnum
{
    // 1待付款（订单产生的状态）
    const UNPAID = 1;
    // 2已付款（订单支付成功）
    const PAID = 2;
    // 3已关闭（订单支付失败）
    const CLOSED = 3;
    // 4已接收（同步到RFID）
    const RECEIVED = 4;
    // 5已发货（查询RFID订单信息，返回5已发货时，本地数据库变更，同事更新订单物流信息）
    const DLIVED = 5;
    // 6转退款
    const REFUND = 6;
}