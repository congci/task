<?php
include_once __DIR__  .'/vendor/autoload.php';
require_once __DIR__ . '/config/local.php';
require_once __DIR__ . '/library/func.php';
require_once __DIR__ . '/library/RedisQueue.php';
require_once __DIR__ . '/library/RedisClient.php';
require_once __DIR__ . '/library/Log.php';
require_once __DIR__ . '/library/Job.php';


date_default_timezone_set('Asia/Shanghai');

class Command{
    protected $name;
    protected $delay = 30;
    protected $try = 3;
    protected $once = false;
    protected $limitMemory = 128; //M

    public $log;
    public $queue;

    private static $instance;

    private function __construct(){}
    private function __clone(){}

    private function setPro($config){
        isset($config['name']) && $this->name = $config['name'];
        isset($config['delay']) && $this->delay = $config['delay'];
        isset($config['try']) && $this->try = $config['try'];
        isset($config['once']) && $this->once = $config['once'];
        isset($config['limitMemory']) && $this->limitMemory = $config['limitMemory'];
    }


    protected function getQueue(){
        $this->queue =  new Lib\RedisQueue();
        return $this->queue;
    }

    protected function getLog(){
        $this->log = new Lib\Log();
        return $this->log;

    }

    //内存限制、然后由super重启
    protected function memoryLimit($limit = 128){
        $mem = memory_get_usage();
        if($mem /1024 /1024 > $limit){
            $this->log->info('内存超过限制、可能有内存泄漏风险');
            exit;
        }
    }

    public static function getInstance(){
        if(!self::$instance instanceof self){
            self::$instance = new self;
        }
        return self::$instance;
    }

    //循环执行
    public function run($config){
        $this->setPro($config);
        $class = new $config['class']();
        $queue = $this->getQueue();
        $log   = $this->getLog();
        $log->file = $config['logfile'];

        while(1){
            $this->memoryLimit($this->limitMemory);
            //从redis中取
            try{
                //以一个对象标示、真正的数据在$job->job中
                $job = $queue->nextJob($this->name);
                if(!$job || empty($job)){
                    sleep(1);
                    continue;
                }
                //记录日志
                $log->info(json_encode($job->job));
                //执行
                $class->handle($job);
                if($this->once){
                    exit;
                }
                //如果没有失败、则直接删除、失败请throw异常
                $queue->delete($this->name,$job);

                //
            }catch (Exception $e){
                $queue->deleteAndRelease($this->name,$job,$this->delay);
            }finally{
                //最大执行数
                if($queue->limitNum($job,$this->try)){
                    $queue->delete($this->name,$job);
                }
            }
        }
    }

    //测试、返回redis对象 可以直接操作redis
    public function testQueue(){
        return $this->getQueue()->client;
    }

}