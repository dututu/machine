<?php
namespace app\common\model;

use think\Model;
/**
 * 机柜图片表
 *
 * @author      alvin
 * @version     1.0
 */
class Machinepics extends Model
{
    protected $autoWriteTimestamp = false;


    public function machine()
    {
        return $this->belongsTo('machine');
    }

}