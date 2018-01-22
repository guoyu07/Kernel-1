<?php

namespace Kernel\Async;

use Kernel\Config\Config;
use \Kernel\Async\Pool\MysqlProxy;
use \Kernel\Async\Client\Mysql;

class AsyncMysql
{
    /**
     * 超时时间
     * @var  integer
     */
    public $timeout = 1;

    /**
     * 是否使用连接池
     * @var  bool
     */
    public $userPool = true;

    /**
     * 连接池标识
     * @var string
     */
    public $active;

    /**
     * 实例化对象
     * @var  object
     */
    protected static $instance;

    /**
     * 是否使用 mysql proxy
     * @var bool
     *
     */
    public $userMysqlProxy = false;

    /**
     * 数据配置文件
     * @var
     */
    public $config;

    public function __construct()
    {
        static::setInstance($this);
    }


    /**
     * 设置数据实例
     * @param  $app
     */
    public static function setInstance($app)
    {
        static::$instance = $app;
    }
    /**
     * 获取实例化
     * @return
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance=new self();
        }
        return static::$instance;
    }

    /**
     * 从 mysql 集群中获取一个连接
     * @param   string $active  集群
     * @return
     */
    public function getMysqlProxy($active)
    {
        $this->config = Config::get($active);
        $this->active = $active;
        $this->userMysqlProxy = true;
        return $this;
    }

    public function query($sql)
    {
        if ($this->$userPool) {
            $pool = app('Kernel\Async\Pool\MysqlPool', [
                $this->config
            ]);
            $mysql = new MysqlProxy($pool);
        }
    }

    public function clean()
    {
        $this->timeout = 1;
        $this->userPool=true;
        $this->active = null;
        $this->config = null;
        $this->userMysqlProxy = false;
        static::$instance=null;
    }
}
