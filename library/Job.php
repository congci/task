<?php
namespace Lib;

class Job{
    public $value;
    protected $attempts = 1;
    protected $release = 0;
    protected $name;
    protected $retryAfter;
    protected $reserved;
    public $job;
    public $delete;


    public function __construct($name,$queue,$job,$reserved,$retryAfter)
    {
        $this->name = $name;
        $this->queue = $queue;
        $this->job = $job;
        $this->reserved = $reserved;
        $this->retryAfter = $retryAfter;
    }

    //再次加入 也就是删除reserevd里面的加入到delay
    public function release($delay = ''){
        $delay = $delay ?: $this->retryAfter;
        $this->queue->deleteAndRelease($this->name,$this,$delay);
        $this->release = 1;
    }

    //执行次数
    public function getAttempts(){
        $this->attempts =  json_decode($this->reserved,true)['attempts'];
        return $this->attempts;
    }

    //获取数据
    public function getValue(){
        return $this->value;
    }

    //是否已经释放
    public function isRelease(){
        return $this->release;
    }

    public function getReservedJob(){
        return $this->reserved;
    }

}