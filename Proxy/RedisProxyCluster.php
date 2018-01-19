<?php

namespace Kernel\Proxy;

use Flexihash\Flexihash;
use Flexihash\Hasher\Md5Hasher;
use Kernel\Pools\RedisAsynPool;
use Kernel\Log\Logger;
use Kernel\Server\Server;
use Kernel\Config\Config;

class RedisProxyCluster extends Flexihash implements IProxy
{
    /**
     * @var string 代理标识，它代表一个Redis集群
     */
    private $name;

    /**
     * @var array 连接池列表 key=连接池名称, value=权重
     */
    private $pools;

    /**
     * @var array 通过探活检测的连接池列表
     */
    private $goodPools = [];

    /**
     * @var mixed|string key前缀
     */
    private $keyPrefix = '';

    /**
     * @var bool|mixed 是否将key散列后储存
     */
    private $hashKey = false;
    /**
     * @var bool 随机选择一个redis，一般用于redis前面有twemproxy等代理，每个代理都可以处理请求，随机即可
     */
    private $isRandom = false;

    /**
     * RedisProxyCluster constructor.
     *
     * @param string $name 代理标识
     * @param array $config 代理配置数组
     */
    public function __construct(string $name, array $config)
    {
        $this->name      = $name;
        $this->pools     = $config['pools'];
        $this->keyPrefix = $config['keyPrefix'] ?? '';
        $this->hashKey   = $config['hashKey'] ?? false;
        $this->isRandom  = $config['random'] ?? false;
        $hasher          = $config['hasher'] ?? Md5Hasher::class;
        $hasher          = new $hasher;

        try {
            parent::__construct($hasher);
            $this->startCheck();
            if (empty($this->goodPools)) {
                throw new Exception('No redis server can write in cluster');
            } else {
                foreach ($this->goodPools as $pool => $weight) {
                    $this->addTarget($pool, $weight);
                }
            }
        } catch (Exception $e) {
            Logger::getInstance()->log('error', 'RedisProxyCluster', [
                'data' => 'Redis Proxy ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 检测可用的连接池
     *
     * @return $this
     */
    public function startCheck()
    {
        $this->syncCheck();
        return $this;
    }

    /**
     * 启动时同步检测可用的连接池
     *
     * @return $this
     */
    private function syncCheck()
    {
        $this->goodPools = [];
        foreach ($this->pools as $pool => $weight) {
            try {
                $poolInstance = Server::getInstance()->getAsynPool($pool);
                if (!$poolInstance) {
                    $poolInstance = new RedisAsynPool(Config::get('store.redis', null), $pool);
                    Server::getInstance()->addAsynPool($pool, $poolInstance, true);
                }

                if ($poolInstance->getSync()->set('msf_active_cluster_check_' . gethostname(), 1, 5)) {
                    $this->goodPools[$pool] = $weight;
                } else {
                    $host = Server::getInstance()->getAsynPool($pool)->getSync()->getHost();
                    $port = Server::getInstance()->getAsynPool($pool)->getSync()->getPort();
                    Server::getInstance()->getAsynPool($pool)->getSync()->connect($host, $port, 0.05);
                }
            } catch (\Exception $e) {
                Logger::getInstance()->log('error', 'RedisProxyCluster', [
                    'data' => 'Redis Proxy ' . $e->getMessage() . " {$pool}"
                ]);
            }
        }
    }


    /**
     * 发送异步Redis请求
     *
     * @param string $method Redis指令
     * @param array $arguments Redis指令参数
     * @return array|bool|mixed
     */
    public function handle(string $method, array $arguments)
    {
        /**
         * @var Context $arguments[0]
         */
        try {
            if ($this->isRandom) {
                return $this->random($method, $arguments);
            }

            if ($method === 'evalMock') {
                return $this->evalMock($arguments);
            } else {
                $key = $arguments[1];
                //单key操作
                if (!is_array($key)) {
                    return $this->single($method, $key, $arguments);
                    // 批量操作
                } else {
                    return $this->multi($method, $key, $arguments);
                }
            }
        } catch (\Exception $e) {
            return false;
        }
    }
}
