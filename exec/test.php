<?php

$job = [
    'order_id' => 15131592439575,
    'FromUserName' => 'efe984b4dad892bc91f25779cc55d255'
];

$name = 'invoice:queue';

require __DIR__ . '/../Command.php';

Command::getInstance()->testQueue()->rPush($name,json_encode($job));
