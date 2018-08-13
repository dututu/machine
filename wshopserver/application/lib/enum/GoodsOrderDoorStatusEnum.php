<?php


namespace app\lib\enum;

/**
 * 商品订单门状态枚举
 *
 * @author      alvin
 * @version     1.0
 */
class GoodsOrderDoorStatusEnum
{
    // 待开柜
    const PREPAREFOROPEN = 1;
    // 已开柜
    const OPENED = 2;
    // 未开柜
    const UNPENED = 3;
    // 已关柜
    const CLOSED = 4;
}