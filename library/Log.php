<?php
namespace Lib;

//debug、 info、 notice、 warning、 error、 critical、 alert 以及 emergency

class Log{
    public $file = 'info.log';
    protected $event;
    protected $time;


    public function __call($name, $arguments)
    {
        $this->event = $name;
        $this->time = date('Y-m-d H:i:s');
        switch ($name){
//            case "debug" :
//                $this->$name(...$arguments);
//                break;
            default :
                $this->message(...$arguments);

        }
    }


    /**
     * 书写
     * @param $content
     */
    protected function write($content){
        file_put_contents($this->file,"[" . $this->time . "]" . " " . $this->event . " " . $content . PHP_EOL,FILE_APPEND | LOCK_EX);
    }

    /**
     * 处理占位符
     * @param $message
     * @param $context
     * @return string
     */
    public function replace($message,$context){
        // 构建一个花括号包含的键名的替换数组
        $replace = array();
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = $val;
        }

        // 替换记录信息中的占位符，最后返回修改后的记录信息。
        return strtr($message, $replace);

    }


    /**
     * 记录
     * @param $message
     * @param array $context
     */
    protected function message($message,array $context = array()){
        if(is_array($message)){
            $message = var_export($message);
        }elseif (is_object($message)){
        }
        $content = $this->replace($message,$context);
        $this->write($content);
    }

}