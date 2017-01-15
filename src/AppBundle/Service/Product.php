<?php

namespace AppBundle\Service;

use AppBundle\Entity\Product as RealProduct;

class Product
{
    private $redis;

    public function __construct($config)
    {
        $config = array_merge(['host' => '127.0.0.1', 'port' => 6379], $config);
        $redis  = new \Redis();
        $redis->connect($config['host'], $config['port']);

        $this->redis = $redis;
    }

    /**
     * 初始化redis
     * 在redis中存入秒杀商品的数量
     *
     * @param RealProduct $product
     */
    public function initSpike(RealProduct $product)
    {
        $id             = $product->getId();
        $total_key      = "product_{$id}_count";
        $complete_key   = "complete_{$id}_count";

        $this->redis->set($total_key, $product->getCount());
        $this->redis->set($complete_key, 0);
    }

    /**
     * 结束时触发
     * 从redis中删除秒杀商品的数量
     *
     * @param RealProduct $product
     */
    public function destructSpike(RealProduct $product)
    {
        $id             = $product->getId();
        $total_key      = "product_{$id}_count";
        $complete_key   = "complete_{$id}_count";

        $this->redis->del($total_key);
        $this->redis->del($complete_key);
    }

    /**
     * 每次请求都触发
     * 成功次数自增，如果小于等于总数，则说明秒杀还没有完成
     *
     * @param $id
     * @return boolean
     */
    public function isSpikeAble($id)
    {
        $total_key      = "product_{$id}_count";
        $complete_key   = "complete_{$id}_count";

        return $this->redis->incr($complete_key) <= $this->redis->get($total_key);
    }



    /**
     * 单用户并发锁
     *
     * @return boolean
     */
    public function isUserSpiking($user)
    {
        $key = "{$user}_spiking";

        $spiking = $this->redis->getSet($key, $user);

        return $user == $spiking;
    }

    /**
     * 用户秒杀结束时调用，删掉并发锁
     *
     * @return void
     */
    public function setUserSpiked($user)
    {
        $key = "{$user}_spiking";
        $this->redis->del($key);
    }
}
