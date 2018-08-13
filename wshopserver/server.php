<?php
/**
 * Created by PhpStorm.
 * User: caesar
 * Date: 2018/3/25
 * Time: 下午3:22
 */
#!/usr/bin/env php
namespace think;

define('APP_PATH', __DIR__ . '/application/');

// 加载基础文件
require __DIR__ . '/thinkphp/base.php';

// 执行应用并响应
Container::get('app',[APP_PATH])->bind('push/Worker')->run()->send();