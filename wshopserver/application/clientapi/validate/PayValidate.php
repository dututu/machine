<?php
/**
 * Created by Caesar.
 */
namespace app\clientapi\validate;

class PayValidate extends BaseValidate
{
    protected $rule = [
        'orderid' => 'require',
    ];
}
