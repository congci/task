<?php

namespace Handler;
use Lib\Job;


//如果失败、清楚throw new Exception();

class InvoiceHandler{

    public function handle(Job $job){
//        var_dump($job);
        instance('log')->info('成功');

    }


}