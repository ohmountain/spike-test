秒杀并发测试
=======

1. 没有任何处理
2. 对单个用户做并发锁处理
3. 多用户并发锁处理

## 框架 ##
[Symfony 3.2](https://symfony.com)

## 数据结构 ##
#### Product ####
* id
* title
* count
* **Results**

#### Result ####
* id
* user
* **Product**

## 压力测试 ##
1. 工具为 [gatling](http://gatling.io)
2. 请求数为 2500
3. 并发数为 834
