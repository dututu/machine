<?php
namespace app\base\command;
use app\base\controller\Redis;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Exception;
use think\Log;
/**
 * 任务类，与redis配合发送微信模板消息
 * User: caesar
 * Date: 2018/1/1
 * Time: 下午4:23
 */
class Mikkle extends Command
{
    protected $sleep = 3;
    protected $redis;
    protected $listName;
    protected $pcntl;
    public function __construct($name= null)
    {
        parent::__construct($name);
        $this->redis=Redis::instance(Config::get("command.redis"));
        $this->listName = "worker_list_wshop";
        $this->pcntl =true;
    }

    protected function configure()
    {
        $this->setName('mikkle')->setDescription('Here is the mikkle\'s command ');
    }

    protected function execute(Input $input, Output $output)
    {
//        echo "==================================================";
        while(true){
            //标记后端服务运行中
            $this->signWorking();
//            echo "==================================================".PHP_EOL;
            $this->autoClass();
//            echo "==================================================".PHP_EOL;
            $this->sleep();
        }
    }


    /**
     * 自动执行
     * Power: Mikkle
     * @return bool
     */
    protected function autoClass()
    {
        $works = $this->getWorks();
        if ($works) {
            foreach ($works as $item => $work) {
                if ($this->pcntl) {
                    $this->pcntlWorker($work, $item);
                } else {
                    $this->runWorker($work, $item);
                }
            }
        } else {
            return false;
        }
    }

    public function getWorks(){
        try{
            return $this->redis->hget($this->listName);
        }catch (Exception $e){
            return false;
        }
    }


    /**
     * 检测执行方法是否存在
     * Power: Mikkle
     * @param $work
     * @param $item
     * @return bool
     */
    protected function checkWorkerExists($work,$item){

        if (class_exists($work)){
            if(method_exists($work,'run')){
                return true;
            }else{
                return false;
            }
        }

    }

    /**
     * 运行任务
     * Power: Mikkle
     * @param $work
     * @param $item
     */
    protected function runWorker($work,$item){
        try{
            if($this->checkWorkerExists($work, $item)) {
                echo "执行[{$work}]任务" . PHP_EOL;
                $work::run();
                Log::notice("执行[{$work}]任务");
            }else{
                echo "执行[{$work}]任务的run方法不存在".PHP_EOL;
                $this->redis->hdel($this->listName,$item);
            }
        }catch (Exception $e){
            echo "执行[{$work}]任务失败" . PHP_EOL;
            Log::notice($e->getMessage());
            if ($this->pcntl) {
                $this->pcntlKill();
            }
        }
    }


    /**
     * 分进程
     * Power: Mikkle
     * @param $work
     * @param $item
     */
    protected function pcntlWorker($work,$item)
    {
        try{
            // 通过pcntl得到一个子进程的PID
            $pid = pcntl_fork();
            if ($pid == -1) {
                // 错误处理：创建子进程失败时返回-1.
                die ('could not fork');
            } else if ($pid) {
                // 父进程逻辑

                // 等待子进程中断，防止子进程成为僵尸进程。
                // WNOHANG为非阻塞进程，具体请查阅pcntl_wait PHP官方文档
                pcntl_wait($status, WNOHANG);
            } else {
                // 子进程逻辑
                $pid_2 = pcntl_fork();
                if ($pid_2 == -1) {
                    // 错误处理：创建子进程失败时返回-1.
                    die ('could not fork');
                } else if ($pid_2) {
                    // 父进程逻辑
                    echo "父进程逻辑开始" . PHP_EOL;
                    // 等待子进程中断，防止子进程成为僵尸进程。
                    // WNOHANG为非阻塞进程，具体请查阅pcntl_wait PHP官方文档
                    pcntl_wait($status, WNOHANG);
                    echo "父进程逻辑结束" . PHP_EOL;
                } else {
                    // 子进程逻辑
                    echo "子进程逻辑开始" . PHP_EOL;

                    $this->runWorker($work,$item);

                    echo "子进程逻辑结束" . PHP_EOL;
                    $this->pcntlKill();
                }
                $this->pcntlKill();
            }
        }catch (Exception $e){
            Log::error($e->getMessage());
        }
    }

    /**
     * Kill子进程
     * Power: Mikkle
     */
    protected function pcntlKill(){
        // 为避免僵尸进程，当子进程结束后，手动杀死进程
        if (function_exists("posix_kill")) {
            posix_kill(getmypid(), SIGTERM);
        } else {
            system('kill -9' . getmypid());
        }
        exit ();
    }

    public function signWorking(){
        $this->redis->set("command","true");
        $this->redis->setExpire("command",10);
    }
    public function sleep($second=""){
        $second = $second ? $second : $this->sleep;
        //  echo "开始睡眠{$second}秒!当前时间:" . date('h:i:s') . PHP_EOL;
        sleep(sleep($second));   //TP5的命令行 sleep($second) 不生效
//        echo "睡眠{$second}秒成功!当前时间:" . date('h:i:s') . PHP_EOL;
    }

    /**
     * @return int
     */
    public function getSleep()
    {
        return $this->sleep;
    }

    /**
     * @param  int $sleep
     * @return void
     */
    public function setSleep($sleep)
    {
        $this->sleep = $sleep;
    }


}
?>