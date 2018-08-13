<?php


namespace app\lib\enum;

/**
 * 机柜状态
 *
 * @author      alvin
 * @version     1.0
 */
class MachineStatusEnum
{
    ////设备状态1待开柜 2已开柜3未开柜 4已关柜 5已拉取 6待分配 7停用故障 8待生产 9生产中 10设备上线
    // 待开柜
    const PREPAREFOROPEN = 1;
    // 已开柜
    const OPENED = 2;
    // 未开柜
    const UNOPENED = 3;
    // 已关柜
    const CLOSED = 4;
    // 已拉取
    const FETCHED = 5;
    // 待分配
    const PREDISPATCH = 6;
    // 停用故障
    const STOPPED = 7;
    //待生产
    const PREPRODUCE = 8;
    //生产中
    const PRODUCE = 9;
    //设备上线
    const ONLINED = 10;
}