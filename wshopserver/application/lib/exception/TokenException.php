<?php
/**
 * Created by Caesar.
 */

namespace app\lib\exception;

/**
 * token验证失败时抛出此异常 
 */
class TokenException extends BaseException
{
    public $code = 401;
    public $msg = '您还没有注册或登录';//Token已过期或无效Token
    public $errorCode = 10001;
}