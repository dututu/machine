<?php
//配置文件
error_reporting(E_ERROR | E_WARNING | E_PARSE);
return [
    // 视图输出字符串内容替换
    'view_replace_str'       => [
        '__VERSION__' => md5('20180620'),
    ],
];