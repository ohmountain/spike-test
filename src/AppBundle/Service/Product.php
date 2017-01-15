<?php

namespace AppBundle\Service;

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
