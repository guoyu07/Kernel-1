<?php

namespace Kernel\Coroutine;

use Kernel\Exception\KException;
use Kernel\Pools\MysqlAsynPool;
use Kernel\Server\Marco;
use Kernel\Coroutine\Command;

class MySql
{
    /**
     * @var MysqlAsynPool MySQL连接池对象
     */
    public $mysqlAsynPool;

    /**
     * @var string|null 绑定ID
     */
    public $bindId;

    /**
     * @var string|null 执行的SQL
     */
    public $sql;

    /**
     * @var string 请求参数
     */
    public $request;

    /**
     * @var string|null 整个请求标识
     */
    public $requestId = null;

    /**
     * @var mixed IO协程运行的结束
     */
    public $result;


    /**
     * @var bool IO协程是否返回数据
     */
    public $ioBack = false;


	/**
     * @var int 协程执行的超时时间精确到ms
     */
    public $timeout;

    /**
     * @var float 协程执行请求开始时间
     */
    public $requestTime = 0.0;


	/**
     * @var int 协程运行的最大超时时间
     */
    public static $maxTimeout = 5000;


    /**
     * MySql constructor.
     *
     * @param MysqlAsynPool $_mysqlAsynPool MySQL连接池对象
     * @param int|null $_bind_id bind ID
     * @param string|null $_sql 执行的SQL
     */
    public function __construct($_mysqlAsynPool, $_bind_id = null, $_sql = null)
    {

        $this->mysqlAsynPool = $_mysqlAsynPool;
        $this->bindId        = $_bind_id;
        $this->sql           = $_sql;
        $this->request       = $this->mysqlAsynPool->getAsynName() . '(' . str_replace("\n", " ", $_sql) . ')';
        $this->requestId      = Command::getTaskId();
        $requestId           = $this->requestId;

		$this->result      = CNull::getInstance();


		$this->requestTime = microtime(true);

		$this->timeout = self::$maxTimeout;

		$this->send(function ($result) use ($requestId) {
			if (empty(Command::getContextObject()) {
                return;
            }


			$this->result = $result;
            $this->ioBack = true;
            $this->nextRun();
		});
    }


	/**
     * 获取协程执行结果
     *
     * @return mixed|null
     */
    public function Result()
    {
        if ($this->isTimeout() && !$this->ioBack) {
            return null;
        }

        return $this->result;
    }


	/**
     * 获取执行结果
     *
     * @return mixed|null
     * @throws Exception
     */
    public function getResult()
    {
        $result = $this->Result();
        if (is_array($result) && isset($result['error'])) {
            throw new Exception($result['error']);
        }
        return $result;
    }


	/**
     * 判断协程是否超时
     *
     * @return bool
     */
    public function isTimeout()
    {
        if ((1000 * (microtime(true) - $this->requestTime)) > $this->timeout) {
            return true;
        }

        return false;
    }


	/**
     * 发送异步的MySQL请求
     *
     * @param callable $callback 执行SQL后的回调函数
     */
    public function send($callback)
    {
        $this->mysqlAsynPool->query($callback, $this->bindId, $this->sql, Command::getContextObject());
    }



	/**
     * 属性不用于序列化
     *
     * @return array
     */
    public function __unsleep()
    {
        return ['mysqlAsynPool'];
    }

    /**
     * 销毁
     */
    public function destroy()
    {
		$this->ioBack    = false;

		$this->requestId = null;
    }
}
