<?php

use Lib\LuaScripts;
$job = [
    'order_id' => 15131592439575,
    'FromUserName' => 'efe984b4dad892bc91f25779cc55d255',
    'attempts' =>1
];

$name = 'invoice:queue';

require __DIR__ . '/../Command.php';

$redis = Command::getInstance()->testQueue();
$redis->rPush($name,json_encode($job));

//$res = $redis->eval(
//    LuaScripts::pop(), 2, $name, $name.':reserved',
//    time()
//);
//var_dump($res);


//var_dump($redis->eval("return {1,2,3,redis.call('SADD',KEYS[1],'queueuav')}",1,'invoice:queue:delayed'));