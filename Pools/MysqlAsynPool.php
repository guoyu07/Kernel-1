<?php

namespace Kernel\Pools;

/**
 * Class MysqlAsynPool
 */
class MysqlAsynPool extends AsynPool
{
    /**
     * 连接池类型名称
     */
    const ASYN_NAME = 'mysql.';
    /**
     * @var int 连接峰值
     */
    protected $mysqlMaxCount = 0;

    /**
     * @var string 连接池标识
     */
    private $active;


    /**
     * MysqlAsynPool constructor.
     *
     * @param Config $config 配置对象
     * @param string $active 连接池名称
     */
    public function __construct($config, $active)
    {
        if (!$config) {
            throw new Exception("config mysql $active not exists");
        }
        parent::__construct($config);
        $this->active         = $active;
        $this->bindPool       = [];
    }

    public function getSync()
    {
    }

    public function getAsynName()
    {
    }

    public function execute($data)
    {
    }
    public function prepareOne()
    {
    }
}
