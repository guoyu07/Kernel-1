<?php

namespace Kernel\Pools;

abstract class AsynPool implements IAsynPool
{
    /**
     * TOKEN最大值
     */
    const MAX_TOKEN = 655360;

    /**
     * @var Config 配置对象
     */
    public $config;

    /**
     * @var \SplQueue 待执行命令队列
     */
    protected $commands;

    /**
     * @var \SplQueue 连接池
     */
    protected $pool;

    /**
     * @var array 回调函数
     */
    protected $callBacks;

    /**
     * @var int worker进程ID
     */
    protected $workerId;

    /**
     * @var \swoole_server swoole_server实例
     */
    protected $server;

    /**
     * @var MSFServer server运行实例
     */
    protected $swooleServer;

    /**
     * @var int 回调Token
     */
    protected $token = 0;

    /**
     * @var int 待连接数量
     */
    protected $waitConnectNum = 0;

    /**
     * @var AsynPoolManager 连接池管理器
     */
    protected $asynManager;

    /**
     * AsynPool constructor.
     *
     * @param Config $config 配置对象
     */
    public function __construct($config)
    {
        $this->callBacks = [];
        $this->commands  = new \SplQueue();
        $this->pool      = new \SplQueue();
        $this->config    = $config;
    }

    /**
     * 注册回调
     *
     * @param callable $callback 回调函数
     * @return int
     */
    public function addTokenCallback($callback)
    {
        $token                   = $this->token;
        $this->callBacks[$token] = $callback;
        $this->token++;
        if ($this->token >= self::MAX_TOKEN) {
            $this->token = 0;
        }

        return $token;
    }

    /**
     * 分发消息
     *
     * @param array $data 待分发数据
     * @return $this
     */
    public function distribute($data)
    {
        $callback = $this->callBacks[$data['token']];
        unset($this->callBacks[$data['token']]);
        if ($callback != null) {
            $callback($data['result']);
        }

        return $this;
    }

    /**
     * 初始化
     *
     * @param MSFServer $swooleServer Server实例
     * @param AsynPoolManager $asynManager 异步连接池管理器
     * @return $this
     */
    public function serverInit($swooleServer, $asynManager)
    {
        $this->swooleServer = $swooleServer;
        $this->server       = $swooleServer->server;
        $this->asynManager  = $asynManager;

        return $this;
    }

    /**
     * 初始化workerId
     *
     * @param int $workerId worker进程ID
     * @return $this
     */
    public function workerInit($workerId)
    {
        $this->workerId = $workerId;

        return $this;
    }

    /**
     * 归还连接
     *
     * @param mixed $client 连接对象
     * @return $this
     */
    public function pushToPool($client)
    {
        $this->pool->push($client);
        if (count($this->commands) > 0) {//有残留的任务
            $command = $this->commands->shift();
            $this->execute($command);
        }
        return $this;
    }

    /**
     * 获取同步
     *
     * @return mixed
     */
    abstract public function getSync();
}
