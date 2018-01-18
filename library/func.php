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

function instance($name){
    static $instance;
    if(isset($instance[$name])) return $instance[$name];
    $instance[$name] = Command::getInstance()->$name;
    return $instance[$name];
}



/**
 * Curl Get 方式.
 */
function curlGet($url, $headers=array())
{
    if ($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if($headers){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $output = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        if ($curlErrNo) {
            throw new Exception('请求方式curlGet错误,url'.$url."错误内容".$curlErrNo, true);
        }
        // $curlError = curl_error($ch);
        curl_close($ch);
        return array('code' => $curlErrNo, 'message' => $output);
    } else {
        return array('code' => '-1', 'message' => 'Curl:' . $url);
    }
}


function curlPostsend($url, $data, $convertToUrlQuery = true, $raw = false)
{
    if ($url) {
        $data = ($convertToUrlQuery) ? http_build_query($data) : $data;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);

        if ($convertToUrlQuery) {
            $data = http_build_query($data);
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        if($raw){
            curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Content-Type: text/plain'));
        }
        $output = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        // $curlError = curl_error($ch);
        curl_close($ch);
        return array('code' => $curlErrNo, 'message' => $output);
    } else {
        return array('code' => '-1', 'message' => 'Curl:' . $url);
    }
}


/**
 * Curl Get 方式.
 *
 * @param string  $url               Post请求的url地址.
 * @param string  $data              Post数据内容.
 * @param boolean $convertToUrlQuery 是否转换数据为url query.
 *
 * @return array Code: 0 成功,非o 异常.
 */
function curlPost($url, $data, $convertToUrlQuery = true)
{
    if ($url) {
        $data = ($convertToUrlQuery) ? http_build_query($data) : $data;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);

//        if ($convertToUrlQuery) {
//            $data = http_build_query($data);
//        }
        //增加超时时间 连接时间4s, 数据时间5s, 总时间不超过7s
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $output = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $aStatus = curl_getinfo($ch);
        // $curlError = curl_error($ch);
        curl_close($ch);
        if($curlErrNo&&$aStatus!=200) {
            throw new Exception('请求方法curlPost错误,url'.$url."参数:".json_encode($data)."错误内容".$curlErrNo, true);
        }
        return array('code' => $curlErrNo, 'message' => $output);
    } else {
        return array('code' => '-1', 'message' => 'Curl:' . $url);
    }
}


