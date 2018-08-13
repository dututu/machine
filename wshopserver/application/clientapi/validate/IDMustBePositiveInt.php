<?php
/**
 * Created by Caesar.
 */
namespace app\clientapi\validate;

class IDMustBePositiveInt extends BaseValidate
{
    protected $rule = [
        'userid' => 'require',
    ];
}
