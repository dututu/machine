<?php
namespace app\admin\validate;
use think\Validate;


//需要建一个目录 名字一样
class Recharge extends Validate{
    protected $rule = [
        ['name','require|max:10','分类名不能为空|分类名长度不能超过10个字符'],
        ['parent_id','number'],
        ['id','number'],
        ['status','number|in:-1,0,1','状态必须是数字|状态范围不合法'],
        ['listorder','number'],
    ];
    /***场景设置***/
    protected $scene = [
        'add' => ['name','parent_id','id'],//添加
        'listorder' => ['id','listorder'],//排序
        'status' => ['id','status'],
    ];
}