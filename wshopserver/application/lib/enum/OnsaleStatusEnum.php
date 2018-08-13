<?php


namespace app\lib\enum;

/**
 * 理货订单状态
 *
 * @author      alvin
 * @version     1.0
 */
class OnsaleStatusEnum
{
    // 1待开门（扫码上货的时候状态）
    const PREOPEN = 1;
    // 2已关闭（门没开，当前有订单没完成）
    const CANCELED = 2;
    // 3已开柜（当前门关闭状态）
    const OPENED = 3;
    // 4已关柜
    const CLOSED = 4;
    // 5已完成（理货完成，拉取理货清单）
    const COMPLETED = 5;
}