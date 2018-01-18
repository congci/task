<?php
namespace Lib;
use \Exception;
/**
 * Created by PhpStorm.
 * User: luhuijie
 * Date: 16/6/29
 * Time: 下午3:23
 */
/**
 * 客户端协议实现.
 */
class Rpc
{

    private $connection;
    private $accessToken;
    protected $rpcClass;
    private $rpcLang;
    private $rpcUri;
    private $rpcId;
    private $appKey;
    private $curCacheKey;

    private static $events = array();

    public static function getIp()
    {
        if(!empty($_SERVER["HTTP_CLIENT_IP"])){
            $ip = $_SERVER["HTTP_CLIENT_IP"];
        }
        elseif(!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        }
        elseif(!empty($_SERVER["HTTP_X_FORWARDED_FOR"])){
            $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        }
        elseif(!empty($_SERVER["REMOTE_ADDR"])){
            $ip = $_SERVER["REMOTE_ADDR"];
        }
        else{
            $ip = "0.0.0.0";
        }
        return $ip;
    }
    /**
     * 设置或读取配置信息.
     *
     * @param array $config 配置信息.
     *
     * @return array|void
     */
    public static function config(array $config = array())
    {
        static $_config = array();
        if (empty($config)) {
            return $_config;
        }

        $_config = $config;
    }

    /**
     * 获取RPC对象实例.
     *
     * @param array $config 配置信息, 或配置节点.
     *
     * @return TextRpcClient
     */
    public static function instance($config = array())
    {
        $className = get_called_class();

        static $instances = array();
        $key = $className . '-';
        if (empty($config)) {
            $key .= 'whatever';
        } else {
            $key .= md5(serialize($config));
        }
        if (empty($instances[$key])) {
            $instances[$key] = new $className($config);
            $instances[$key]->rpcClass = $className;
        }
        return $instances[$key];
    }

    /**
     * 自动检测或者跟新api接口访问access token
     *
     * @return string app access and micTime.
     */
    private function getAppAccess()
    {
        list($usec, $sec) = explode(" ", microtime());
        $micTime  = ceil($usec * 1000) + $sec * 1000;

        $appAccess = array(
            'id'    => $this->rpcId,
            'requestTimeMillis' => $micTime,
            'sign'  => md5($micTime . $this->appKey),
        );

        return $appAccess;
    }

    /**
     * 检查返回结果是否包含错误信息.
     *
     * @param mixed $ctx 调用RPC接口时返回的数据.
     *
     * @return boolean
     */
    public static function hasErrors(&$ctx)
    {
        if (is_array($ctx)) {
            if (isset($ctx['error'])) {
                $ctx = $ctx['error'];
                return true;
            }
            if (isset($ctx['errors'])) {
                $ctx = $ctx['errors'];
                return true;
            }
        }
        return false;
    }

    /**
     * 注册各种事件回调函数.
     *
     * @param string   $eventName     事件名称, 如: read, recv.
     * @param function $eventCallback 回调函数.
     *
     * @return void
     */
    public static function on($eventName, $eventCallback)
    {
        if (empty(self::$events[$eventName])) {
            self::$events[$eventName] = array();
        }
        array_push(self::$events[$eventName], $eventCallback);
    }

    /**
     * 调用事件回调函数.
     *
     * @param $eventName 事件名称.
     *
     * @return void.
     */
    private static function emit($eventName)
    {
        if (!empty(self::$events[$eventName])) {
            $args = array_slice(func_get_args(), 1);
            foreach (self::$events[$eventName] as $callback) {
                @call_user_func_array($callback, $args);
            }
        }
    }

    /**
     * 构造函数.
     *
     * @param array $config 配置信息, 或配置节点.
     *
     * @throws Exception 抛出开发错误信息.
     */
    private function __construct(array $config = array())
    {

        if (empty($config)) {
            $config = self::config();
        } else {
            self::config($config);
        }

        if (empty($config)) {
            throw new Exception('TextRpcClient: Missing configurations');
        }

        $className = get_called_class();
        //特殊处理
        if (preg_match('/^[A-Za-z0-9]+_([A-Za-z0-9]+)_([A-Za-z0-9]+)/', substr($className,strpos($className,'\\')+1), $matches)) {
            $module = $matches[1];
            $this->remoteController    = $matches[2];
            if (empty($config[$module])) {
                throw new Exception(sprintf('TextRpcClient: Missing configuration for `%s`', $module));
            } else {
                $this->init($config[$module]);
            }
        } else {
            throw new Exception(sprintf('TextRpcClient: Invalid class name `%s`', $className));
        }

        if ($this->rpcLang == 'php') {
            $this->openConnection();
        }
    }

    /**
     * 析构函数.
     */
    public function __destruct()
    {
        if ($this->rpcLang == 'php') {
            $this->closeConnection();
        }
    }

    /**
     * 读取初始化配置信息.
     *
     * @param array $config 配置.
     *
     * @return void
     */
    public function init(array $config)
    {
        $this->rpcLang  = isset($config['lang']) ? $config['lang'] : 'php';
        $this->rpcUri  	= $config['uri'];
        $this->rpcId 	= isset($config['id']) ? $config['id'] : $config['user'];
        $this->appId    = $this->rpcId;
        if ('php' == $this->rpcLang) {
            $this->appKey   = isset($config['appkey']) ? $config['appkey'] : $config['secret'];
            $this->appId    =  $config['appId'];//$this->rpcId;
        } else {
            $curApp     = Oauth::Instance()->getTokenData();
            $this->rpcId      = isset($curApp['appid']) ? $curApp['appid'] : '';
            $this->appKey     = isset($curApp['appkey']) ? $curApp['appkey'] : '';
        }

    }

    /**
     * 创建网络链接.
     *
     * @throws Exception 抛出链接错误信息.
     *
     * @return void
     */
    private function openConnection()
    {
        /*
        $this->connection = @stream_socket_client($this->rpcUri, $errno, $errstr);
        if (!$this->connection) {
            throw new Exception(sprintf('TextRpcClient: %s, %s', $this->rpcUri, $errstr));
        }
        @stream_set_timeout($this->connection, 60);
        */
        $address = $this->rpcUri[array_rand($this->rpcUri)];
        $this->connection = stream_socket_client($address, $err_no, $err_msg);
        if(!$this->connection)
        {
            throw new Exception("can not connect to $address , $err_no:$err_msg");
        }
        stream_set_blocking($this->connection, true);
        stream_set_timeout($this->connection, 60);
    }

    /**
     * 关闭网络链接.
     *
     * @return void
     */
    private function closeConnection()
    {
        @fclose($this->connection);
        $this->connection   = null;
    }

    /**
     * 请求数据签名.
     *
     * @param string $data   待签名的数据.
     * @param string $secret 私钥.
     *
     * @return string
     */
    private function encrypt($data, $secret)
    {
        return md5($data . '&' . $secret);
    }

    /**
     * 调用 RPC 方法.
     *
     * @param string $method    PRC 方法名称.
     * @param mixed  $arguments 方法参数.
     *
     * @throws Exception 抛出开发用的错误提示信息.
     *
     * @return mixed
     */
    public function __call($method, $arguments)
    {

        if ($this->rpcLang == 'php') {
            $data           = $this->prepareSendData($method, $arguments);
            $bin_data       = $data[0];
            $cacheBinData   = $data[1];

            /*
            $cacheObj       = Yii::app()->cache;

            $randCnt	= rand(1, $cacheObj->cnt);
            $curCacheKey    = 'php-data'.md5($cacheBinData);
            $data           = $cacheObj->get($curCacheKey);
            if ($data) { return $data; }
            */

            $this->sendData($bin_data);

            $data           = $this->recvData();

            /*
            if ($data) {
                $cacheObj->set($curCacheKey, $data, 10);
            }
            */

            return $data;
        }

        $sign = '' . $this->rpcSecret;

        $fn = null;
        if (!empty($arguments) && is_callable($arguments[count($arguments) - 1])) {
            $fn = array_pop($arguments);
        }

        $ctx = $this->remoteCall($method, $arguments);

        if (isset($ctx['exception']) && is_array($ctx['exception'])) {
            throw new Exception('RPC Exception: ' . var_export($ctx['exception'], true));
        }

        if ($fn === null)
            return $ctx;

        if ($this->hasErrors($ctx)) {
            $fn(null, $ctx);
        } else {
            $fn($ctx, null);
        }
    }

    /**
     * 从服务端接收数据
     * @throws Exception
     */
    public function recvData()
    {
        $ret = fgets($this->connection);
        $this->closeConnection();
        if(!$ret)
        {
            throw new Exception("recvData empty");
        }
        return JsonProtocol::decode($ret);
    }

    /**
     * 准备传入参数数据.
     *
     * @param string $method
     * @param array $arguments
     *
     * @thorws \Exception
     * @return string
     */
    protected function prepareSendData($method, $arguments)
    {
        $user       = $this->rpcId;
        $secret     = $this->appKey;
        $timestamp  = microtime(true);

        list($usec, $sec) = explode(" ", microtime());
        $micTime  = ceil($usec * 1000) + $sec * 1000;
        $curApp     = array();//Oauth::Instance()->getTokenData();
        $openId     = isset($curApp['openid']) ? $curApp['openid'] : '';

        $wxAccessToken    = isset($curApp['wxAccessToken']) ? $curApp['wxAccessToken'] : '';
        $movieOpenid      = isset($curApp['movieopenid']) ? $curApp['movieopenid'] : '';


        $appId  = $this->appId;
        $appKey = $this->appKey;


        $ENV        = array(
            'X_CLIENT_IP'           => self::getIp(),
            'X_USER_ID'             => isset($curApp['userid']) ? $curApp['userid'] : 0,
            'X_OPEN_ID'             => $openId,
            'X_MOVIE_OPEN_ID'       => $movieOpenid,
            'X_WECHAT_TOKEN'        => $wxAccessToken,
            'X_IS_REFLECT_GETOR'    => true//isset(Yii::app()->params['isReflectParams']) ? true : false,
        );

        // 添加定制的特列环境变量.
        $SPECIALENV = false;//$this->addSpecialEnv();
        if ($SPECIALENV && is_array($SPECIALENV)) {
            $ENV['X_SPECIAL_ENV']   = $SPECIALENV;
        }
        $data    = array(
            'version'       => '1.0',
            'access' => array(
                'user' => $user,
                'password' => md5($user . $secret . $timestamp),
                'timestamp' => $timestamp,
                'app' => array(
                    'id'     => $appId,
                    'requestTimeMillis' => $micTime,
                    'sign'  => md5($micTime . $appKey),
                )
            ),
            'class'         => $this->remoteController,
            'method'        => $method,
            'param_array'   => $arguments,
            'env'   => $ENV
        );

        $cacheData  = $data;
        unset($cacheData['access']['password']);
        unset($cacheData['access']['timestamp']);
        unset($cacheData['access']['app']['requestTimeMillis']);
        unset($cacheData['access']['app']['sign']);

        $bin_data   = JsonProtocol::encode($data);
        $cacheBinData = JsonProtocol::encode($cacheData);

        return array($bin_data, $cacheBinData);
    }

    /**
     * 添加定制的特列环境变量.
     */
    protected function addSpecialEnv()
    {
        $oauth  = Oauth::Instance();
        return $oauth->getState('SPECIAL_ENV');
    }

    /**
     * 发送数据给PHPRPC Server端
     *
     * @param string $bin_data
     *
     * @return boolean
     */
    public function sendData($bin_data)
    {
        $this->openConnection();
        return fwrite($this->connection, $bin_data) == strlen($bin_data);
    }

    /**
     * 发起 RPC 调用协议.
     *
     * @param array $data RPC 数据.
     *
     * @throws Exception 抛出开发用的错误提示信息.
     *
     * @return mixed
     */
    private function remoteCall($method, array $data)
    {
        $params         = array('appAccess' => $this->getAppAccess());
        $data   = $data[0];
        if (isset($data['data'])) {
            $data   = $data['data'];
        }

        $specialApis    = array(
        );

        $params     = array('params' => array_merge($params, $data));
        $paramString    = '';
        $paramCacheString = '';
        $paramCount     = 0;
        $apiUrl         = $this->remoteController.'/'.$method;

        foreach ($params as $key => $one) {
            $oneStr     = $key.'='.urlencode(json_encode($one)).'&';
            $paramString .= $oneStr;
            if (isset($one['appAccess'])) {
                $tempOne          = $one;
                unset($tempOne['appAccess']);
                if (in_array(strtolower($apiUrl), $specialApis)) {
                    // 在特别的接口中，对所有用户数据都是一样的
                    $tempOne['appAccess']   = $one['appAccess']['id'];
                    if (isset($tempOne['userId'])) {
                        unset($tempOne['userId']);
                    }
                } else {
                    $tempOne['appAccess']   = array(
                        'id'    => $one['appAccess']['id']
                    );
                }
                $paramCacheString .= $key.'='.urlencode(json_encode($tempOne)).'&';
            } else {
                $paramCacheString .= $oneStr;
            }
            $paramCount++;
        }
        rtrim($paramString, '&');

        if (is_array($this->rpcUri)) {
            $this->rpcUri = $this->rpcUri[array_rand($this->rpcUri)];
        }

        $randCnt        = rand(1, 1);
        $curCacheKey    = 'java-data' . md5($this->rpcUri.'/'.$apiUrl.':'.$paramCacheString) . $randCnt;
        $data           = $cacheObj->get($curCacheKey);
        if ($data) { return $data; }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->rpcUri.'/'.$apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, $paramCount);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $paramString);
        $data = curl_exec($ch);
        if ($error = curl_error($ch) ) {
            throw new Exception('Remote connect error: '. $error, true);
        }
        curl_close($ch);
        $jsonData   = json_decode($data, true);
        if (null === $jsonData) {
            $jsonData   = json_decode(str_replace("'", '"', $data), true);
        }

        return $jsonData;
    }

    /**
     * 计算 RPC 请求时间.
     *
     * @return float
     */
    private function executionTime()
    {
        return microtime(true) - $this->executionTimeStart;
    }

}

/**
 * RPC 协议解析 相关
 * 协议格式为 [json字符串\n]
 * @author walkor <worker-man@qq.com>
 * */
class JsonProtocol
{
    /**
     * 从socket缓冲区中预读长度
     * @var integer
     */
    const PRREAD_LENGTH = 87380;

    /**
     * 判断数据包是否接收完整
     * @param string $bin_data
     * @param mixed $data
     * @return integer 0代表接收完毕，大于0代表还要接收数据
     */
    public static function dealInput($bin_data)
    {
        $bin_data_length = strlen($bin_data);
        // 判断最后一个字符是否为\n，\n代表一个数据包的结束
        if($bin_data[$bin_data_length-1] !="\n")
        {
            // 再读
            return self::PRREAD_LENGTH;
        }
        return 0;
    }

    /**
     * 将数据打包成Rpc协议数据
     * @param mixed $data
     * @return string
     */
    public static function encode($data)
    {
        return json_encode($data)."\n";
    }

    /**
     * 解析Rpc协议数据
     * @param string $bin_data
     * @return mixed
     */
    public static function decode($bin_data)
    {
        return json_decode(trim($bin_data), true);
    }
}
