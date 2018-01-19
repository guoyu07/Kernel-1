<?php

namespace Kernel\Proxy;

use Kernel\Pools\RedisAsynPool;
use Kernel\Log\Logger;
use Kernel\Server\Server;
use Kernel\Config\Config;

class RedisProxyMasterSlave implements IProxy
{
    /**
     * @var string 代理标识，它代表一个Redis集群
     */
    private $name;

    /**
     * @var array 连接池列表，数字索引的连接池名称列表
     */
    private $pools;

    /**
     * @var string Redis集群中主节点的连接池名称
     */
    private $master;

    /**
     * @var array Redis集群中从节点的连接池名称列表
     */
    private $slaves;

    /**
     * @var array 通过探活检测的连接池列表
     */
    private $goodPools;

    /**
     * @var array 读的Redis指令列表
     */
    private static $readOperation = [
        // Strings
        'GET', 'MGET', 'BITCOUNT', 'STRLEN', 'GETBIT', 'GETRANGE',
        // Keys
        'KEYS', 'TYPE', 'SCAN', 'EXISTS', 'PTTL', 'TTL',
        // Hashes
        'HEXISTS', 'HGETALL', 'HKEYS', 'HLEN', 'HGET', 'HMGET',
        // Set
        'SISMEMBER', 'SMEMBERS', 'SRANDMEMBER', 'SSCAN', 'SCARD', 'SDIFF', 'SINTER',
        // List
        'LINDEX', 'LLEN', 'LRANGE',
        // Sorted Set
        'ZCARD', 'ZCOUNT', 'ZRANGE', 'ZRANGEBYSCORE', 'ZRANK', 'ZREVRANGE', 'ZREVRANGEBYSCORE',
        'ZREVRANK', 'ZSCAN', 'ZSCORE',
    ];


    /**
     * RedisProxyMasterSlave constructor.
     *
     * @param string $name 代理标识
     * @param array $config 配置对象
     */
    public function __construct(string $name, array $config)
    {
        $this->name = $name;
        $this->pools = $config['pools'];
        try {
            $this->startCheck();
            if (!$this->master) {
                throw new Exception('No master redis server in master-slave config!');
            }

            if (empty($this->slaves)) {
                throw new Exception('No slave redis server in master-slave config!');
            }
        } catch (Exception $e) {
            Logger::getInstance()->log('error', 'RedisProxyMasterSlave', [
                'data' => 'Redis Proxy ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 启动时检测Redis集群状态
     *
     * @return bool
     */
    public function startCheck()
    {
        foreach ($this->pools as $pool) {
            try {
                $poolInstance = Server::getInstance()->getAsynPool($pool);
                if (!$poolInstance) {
                    $poolInstance = new RedisAsynPool(Config::get('store.redis', null), $pool);
                    Server::getInstance()->addAsynPool($pool, $poolInstance, true);
                }

                if ($poolInstance->getSync()->set('msf_active_master_slave_check_' . gethostname(), 1, 5)) {
                    $this->master = $pool;
                    break;
                }
            } catch (\RedisException $e) {
                // throw new Exception($e->getMessage());
            }
        }

        //探测从节点
        if (count($this->pools) === 1) {
            $this->slaves[] = $this->master;
        } else {
            foreach ($this->pools as $pool) {
                $poolInstance = Server::getInstance()->getAsynPool($pool);
                if (!$poolInstance) {
                    $poolInstance = new RedisAsynPool(Config::get('store.redis', null), $pool);
                    Server::getInstance()->addAsynPool($pool, $poolInstance, true);
                }

                if ($pool != $this->master) {
                    try {
                        if ($poolInstance->getSync()
                                ->get('msf_active_master_slave_check_' . gethostname()) == 1
                        ) {
                            $this->slaves[] = $pool;
                        }
                    } catch (\RedisException $e) {
                        // throw new Exception($e->getMessage());
                    }
                }
            }
        }

        if (empty($this->slaves)) {
            return false;
        }

        return true;
    }
}
