* 此项目是模仿的laravel的队列原理做的，提出来脱离了原来笨重的框架、精简了一下。适合单独写任务进程。
* 提交的描述错误有错别字、应该是laravel的现成class


1、支持composer <br>
2、依赖supervisor 大部分运维都会装 <br>
3、在exec目录中写执行方法、如例子中缩写 <br>
4、config() 获取配置数据、instance获取基础对象（原理是command类的属性） <br>
<br>

目录：<br>
   config <br>
      --local.php 里面`必须`写redis配置、其中eval为是否启用lua脚本执行redis命令、如果服务器支持、建议打开 <br>
   exec <br>
      --任务的入口文件、一个任务写一个 <br>
   handler
      --任务的执行文件、执行函数名称固定为handle <br>
   library <br>
      --一些函数和类 <br>
   vender <br>
      --一些类库、支持composer <br>
   Command.php <br>
      --执行命令入口逻辑、 <br>
    
<br>


原理：<br>
一个队列 queue <br>
两个有序集合 reserved delayed <br>

1、从队列中取出、放到reserved中暂存、如果执行成功、则删除、如果没有执行成功、则从reserved有序集合中加入到delayed有序集合中、按照当前时间+延迟后的时候存 <br>

2、实现延时的原理就是只取有序集合最小到当前时间的所有数据、加入到队列中、<br>

3、循环往复。<br>












