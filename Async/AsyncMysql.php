<?php

namespace Kernel\Async;

use Config;
use \Kernel\Async\Pool\MysqlProxy;
use \Kernel\Async\Client\Mysql;

class AsyncMysql
{
    protected static $timeout = 1;

    protected static $userPool = true;

    /**
     * 设置超时时间
     * @param  int $timeout
     */
    public static function setTimeout($timeout)
    {
        self::$timeout = $timeout;
    }

    /**
     * 异步执行一条sql
     * @param  string $sql
     * @param  boolean 是否使用连接池资源
     * @return array|boolean
     */
    public static function query($sql, $userPool = true)
    {
        if ($userPool && self::$userPool) {
            $pool = app('Kernel\Async\Pool\MysqlPool');
            $mysql = new MysqlProxy($pool);
        } else {
            $container = (yield getContainer());
            $timeout = self::$timeout;
            $mysql = $container->singleton('Kernel\Async\Client\Mysql', function () use ($timeout) {
                $mysql = new Kernel\Async\Client\Mysql();
                $mysql->setTimeout($timeout);
                return $mysql;
            });
        }

        $mysql->query($sql);
        $res = (yield $mysql);
        if ($res && $res['response']) {
            yield $res['response'];
        }

        yield false;
    }

    /**
     * 事务begin
     * @return boolean
     */
    public static function begin()
    {
        self::$userPool = false;
        $res = (yield self::query('begin', false));
        yield $res;
    }

    /**
     * 事务commit
     * @return boolean
     */
    public static function commit()
    {
        $res = (yield self::query('commit', false));
        self::$userPool = true;
        yield $res;
    }

    /**
     * 事务rollback
     * @return boolean
     */
    public static function rollback()
    {
        $res = (yield self::query('rollback', false));
        self::$userPool = true;
        yield $res;
    }
}
