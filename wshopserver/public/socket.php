<?php
/**
 * Created by PhpStorm.
 * User: caesar
 * Date: 2018/3/25
 * Time: 下午3:26
 */
// 启动socket，通过cmd启动

// 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');
//绑定到socket所在模块
define('BIND_MODULE','socket/Index/index');
// 加载框架引导文件
require __DIR__ . '/../thinkphp/start.php';
