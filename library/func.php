<?php

//获取配置、如果是多位数组、则键名用分隔符 $config['test.user'] 获取的是test数组下面的user数组
function config($name,$delimiter = '.'){
    static $local_config;
    global $config;
    if(isset($local_config[$name])){
        return $local_config[$name];
    }else{
        if(strpos($name,$delimiter)){
            $ret = $config;
            $nameArr = explode($delimiter,$name);
            foreach ($nameArr as $key){
                $ret = $ret[$key];
            }
            $local_config[$name] = $ret;
            return $ret;

        }else{
            $local_config[$name] = $config[$name];
            return $config[$name];
        }
    }
}

//对象获取
function instance($name){
    static $instance;
    if(isset($instance[$name])) return $instance[$name];
    $instance[$name] = Command::getInstance()->$name;
    return $instance[$name];
}






