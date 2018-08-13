<?php


namespace app\lib\enum;

/**
 * 商户状态
 *
 * @author      alvin
 * @version     1.0
 */
class MerchantStatusEnum
{
    // 商户状态 1待审批2正常已审批3审批不通过4停用5删除
    // 待审批
    const PREAPPLY = 1;
    // 正常已审批
    const NORMAL = 2;
    // 审批不通过
    const REJECTED = 3;
    // 停用
    const STOP = 4;
    // 删除
    const DELETE = 5;
}