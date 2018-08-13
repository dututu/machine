<?php
namespace app\base\command;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use \think\Log;
use think\Loader;
Loader::import('master.RfidApiV2', EXTEND_PATH, '.php');
Loader::import('master.MasterApi', EXTEND_PATH, '.php');
/**
 * 计划任务 Status
 * 每隔一分钟检查主控平台和rfid平台的状态
 * @author nick
 *
 */
class Status extends Command{
    protected function configure(){
        $this->setName('Status')->setDescription('计划任务 Status');
    }

    protected function execute(Input $input, Output $output){
        Log::info('计划任务列表集 START');
        $output->writeln('Status Crontab job start...');
        /*** 计划任务列表集 START ***/
//        $rfidApi = new \RfidApi('','');
//        $rfidresult = $rfidApi->deviceStatus();
//        if(!empty($rfidresult)) {
//            $result2 = json_decode($rfidresult, true);
//            if ($result2['code'] == 200) {
//                model('Businessparam')::where('paramname', '=', 'masterstatus')
//                    ->update(['paramvalue' => 1]);
//            } else {
//                model('Businessparam')::where('paramname', '=', 'masterstatus')
//                    ->update(['paramvalue' => 0]);
//            }
//        }
        //
        $masterApi = new \MasterApi('','');
        $masterresult = $masterApi->masterStatus();
        if(!empty($masterresult)) {
            $result2 = json_decode($masterresult, true);
            if ($result2['code'] == 200) {
                model('Businessparam')::where('paramname', '=', 'rfidstatus')
                    ->update(['paramvalue' => 1]);
            }else{
                model('Businessparam')::where('paramname', '=', 'rfidstatus')
                    ->update(['paramvalue' => 0]);
            }
        }
        /*** 计划任务列表集 END ***/
        $output->writeln('Status Crontab job end...');
        Log::info('计划任务列表集 END');
    }

}
?>