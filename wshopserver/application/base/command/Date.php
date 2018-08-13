<?php
namespace app\base\command;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use \think\Log;
use app\lib\enum\GoodsOrderStatusEnum;
/**
 * 计划任务 DATE
 * 每天一点钟更新订单状态（每天定时任务把前一天的订单修改 已完成（不能申请退款了））
 * @author nick
 *
 */
class Date extends Command{
    protected function configure(){
        $this->setName('Date')->setDescription('计划任务 Date');
    }

    protected function execute(Input $input, Output $output){
        $output->writeln('Date Crontab job start...');
        /*** 计划任务列表集 START ***/

        $yesterday=date('Y-m-d',time ()- ( 1  *  24  *  60  *  60 ));
        Db::table('goodsorder')->where("DATE_FORMAT(createtime, '%Y-%m-%d')=:param1 and orderstatus=:param2",['param1'=>$yesterday,'param2'=>GoodsOrderStatusEnum::PAID])->update(['orderstatus' => GoodsOrderStatusEnum::COMPLETE]);
        /*** 计划任务列表集 END ***/
        $output->writeln('Date Crontab job end...');
    }

}
?>