<?php

namespace Kernel\Proxy;

use Kernel\Server\Marco;

/**
 * Redis Proxy工厂类
 *
 */
class RedisProxyFactory
{
    /**
     * @var array Redis协程
     */
    public static $redisCoroutines = [];

    /**
     * 生成proxy对象
     *
     * @param string $name Redis代理名称
     * @param array $config 配置对象
     * @return bool|RedisProxyCluster|RedisProxyMasterSlave
     */
    public static function makeProxy(string $name, array $config)
    {
        $mode = $config['mode'];
        if ($mode == Marco::CLUSTER) {
            return new RedisProxyCluster($name, $config);
        } elseif ($mode == Marco::MASTER_SLAVE) {
            return new RedisProxyMasterSlave($name, $config);
        } else {
            return false;
        }
    }
}
