》此项目是模仿的laravel的队列原理做的，提出来脱离了原来笨重的框架、精简了一下。适合单独写任务进程。
》提交的描述错误有错别字、应该是laravel的现成class


1、支持composer
2、依赖supervisor 大部分运维都会装
3、在exec目录中写执行方法、如例子中缩写
4、config() 获取配置数据、instance获取基础对象（原理是command类的属性）


目录：
   config
      --local.php 里面必须写redis配置、其中eval为是否启用lua脚本执行redis命令、如果服务器支持、建议打开
   exec
      --任务的入口文件、一个任务写一个
   handler
      --任务的执行文件、执行函数名称固定为handle
   library
      --一些函数和类
   vender
      --一些类库、支持composer
   Command.php
      --执行命令入口逻辑、
    



原理：
一个队列 queue 
两个有序集合 reserved delayed

1、从队列中取出、放到reserved中暂存、如果执行成功、则删除、如果没有执行成功、则从reserved有序集合中加入到delayed有序集合中、按照当前时间+延迟后的时候存

2、实现延时的原理就是只取有序集合最小到当前时间的所有数据、加入到队列中、

3、循环往复。












