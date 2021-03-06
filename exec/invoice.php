<?php

//执行

$config['logfile'] = '/opt/logs/web/invoice.log';
$config['name'] = 'invoice:queue';//队列标志
$config['delay'] = 10; //延迟多少秒
$config['try'] = 3;//执行多少次
$config['once'] = 0;//是否属于一次性执行、
$config['class'] = Handler\InvoiceHandler::class;// 执行的方法
$config['drive'] = 'redis'; //暂时只支持redis


require __DIR__ . '/../Command.php';

Command::getInstance()->run($config);