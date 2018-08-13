<?php
/**
 * Created by Caesar.
 */

namespace app\clientapi\controller;


use app\clientapi\service\Token;
use think\Controller;

/**
 * 基类 检查token是否过期
 */
class BaseController extends Controller
{
    protected function checkExclusiveScope()
    {
        Token::needExclusiveScope();
    }
    //初级作用域
    protected function checkPrimaryScope()
    {
        Token::needPrimaryScope();
    }

    protected function checkSuperScope()
    {
        Token::needSuperScope();
    }
}