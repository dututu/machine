<?php


namespace app\lib\enum;

/**
 * 机柜运营状态 1 未联通 2已初始化 3待分配 4正常运行 5故障 6停用
 *
 * @author      alvin
 * @version     1.0
 */
class MachineBusinessStatusEnum
{
    // 未联通
    const NOTCONNECTED = 1;
    // 已初始化
    const INITED = 2;
    // 待分配
    const PREDISPATCH = 3;
    // 正常运行
    const NORMAL = 4;
    // 故障
    const BREAKDOWN = 5;
    // 停用
    const STOPPED = 6;
    // 作废
    const CANCELED = 7;
}