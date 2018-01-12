1、支持composer
2、依赖supervisor 大部分运维都会装
3、在exec目录中写执行方法、如例子中缩写
4、config() 获取配置数据、instance获取基础对象（原理是command类的属性）



原理：
一个队列 queue 
两个有序集合 reserved delayed

1、从队列中取出、放到reserved中暂存、如果执行成功、则删除、如果没有执行成功、则从reserved有序集合中加入到delayed有序集合中、按照当前时间+延迟后的时候存

2、实现延时的原理就是只取有序集合最小到当前时间的所有数据、加入到队列中、

3、循环往复。












