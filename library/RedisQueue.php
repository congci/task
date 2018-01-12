<?php
namespace Lib;


class RedisQueue{
    public $client;
    protected $retryAfter = 0;
    protected $queue;

    public function __construct()
    {
        $configRedis = config('redis');
        $this->client = new RedisClient($configRedis);
        $this->client->connect();
    }


    //获取下一个消息
    public function nextJob($queue){
        $this->migrate($queue);
        list($job,$reserved) = $this->retrieveNextJob($queue);
        if($reserved){
            return new Job($queue,$this,$job,$reserved,$this->retryAfter);
        }
        return false;
    }

    protected function migrate($queue){
        $this->migrateExpiredJobs($queue.':delayed', $queue);
        if (! is_null($this->retryAfter)) {
            $this->migrateExpiredJobs($queue.':reserved', $queue);
        }
    }

    protected function retrieveNextJob($queue){
        $job = $this->client->lPop($queue);
        $reserved = false;
        if($job && !empty($job)){
            $reserved = json_decode($job,true);
            $num = isset($reserved['attempts']) ? $reserved['attempts'] : 1;
            $reserved['attempts'] = $num + 1;
            $reserved = json_encode($reserved);
            $res = $this->client->zAdd($queue.':reserved',$this->availableAt($this->retryAfter),$reserved);
            if(!$res){
                $this->client->rPush($queue,$job);
                return [];
            }
        }
        return [$job,$reserved];
    }

    protected function availableAt($delay){
        return time() + $delay;
    }

    protected function migrateExpiredJobs($from, $to){
        $time = time();
        $val = $this->client->zRangeByScore($from,'-inf',$time);
        $this->client->Transation(function($redis)use ($from,$to,$val){
            $count = count($val);
            if(!empty($val)){
                    $redis->zRemRangeByRank($from,0,$count-1);
                for ($i = 0;$i < $count;$i = $i+100){
                    $redis->rPush($to,implode(array_slice($val,$i,100),' '));
                }
            }
        });
    }

    //从reserved队列中删除、并且加入到delay队列里面
    public function deleteAndRelease($queue, $job, $delay){
        $reserved = $job->getReservedJob();
        if($reserved){
            $this->client->Transation(function ($redis)use($queue,$reserved,$delay){
                $redis->zRem($queue.':reserved',$reserved);
                $redis->zAdd($queue.':delayed',$this->availableAt($delay),$reserved);
            });
        }
    }

    //从reserved和delayed有序集合里面删除
    public function delete($queue,$job){
        $reserved = $job->getReservedJob();
        $this->client->zRem($queue.':reserved',$reserved);
        $this->client->zRem($queue.':delayed',$reserved);
        $job->delete = 1;
    }


    public function limitNum($job,$num){
        if(!$job || $job->delete) return false;
        if($job->getAttempts() > $num ){
            return true;
        }
        return false;
    }






}