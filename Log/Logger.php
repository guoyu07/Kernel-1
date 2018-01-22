<?php


/**
 * Loger 日志操作模块
 */

namespace Kernel\Log;

use MongoDB\Client;
use Monolog\ErrorHandler;
use Monolog\Handler\MongoDBHandler;
// use Monolog\Handler\RotatingFileHandler;
use DateTimeZone;
use Kernel\Config\Config;

class Logger
{
    /**
     * 日志对象
     * @var object
     */
    private static $logHandle;

    /**
     * 初始化 Loger
     * @return
     */
    public static function init()
    {
        return false;

        //构建日志监听频道
        $logHandle = new \Monolog\Logger(Config::get('logger.channel'));
        $logHandle->setTimezone(new \DateTimeZone(Config::get('common.timezone')));

        $uri = 'mongodb://'.implode(Config::get('store.logger.host'), ',').'/';
        $client = new Client($uri, Config::get('store.logger.uriOptions'), Config::get('store.logger.driverOptions'));
        $mongodb = new MongoDBHandler($client, Config::get('store.logger.database'), Config::get('store.logger.collection'));
        $logHandle->pushHandler($mongodb);

        ErrorHandler::register($logHandle);

        static::setInstance($logHandle);

        unset($logHandle, $uri, $mongodb, $client);
    }



    /**
     * 设置
     * @param  $app
     */
    public static function setInstance($app)
    {
        return false;
        static::$logHandle = $app;
    }

    /**
     * 获取实例化
     * @return
     */
    public static function getInstance()
    {
        return false;
        return static::$logHandle;
    }



    /**
     * 添加到日志
     * @param   string $type
     * @param  array|string $message
     * @return
     */
    public function log($type, $message, $context = null)
    {

        $type = strtolower($type);
        $level = '';
        // 日志等级
        // DEBUG (100): 详细的debug信息。
        // INFO (200): 关键事件。
        // NOTICE (250): 普通但是重要的事件。
        // WARNING (300): 出现非错误的异常。
        // ERROR (400): 运行时错误，但是不需要立刻处理。
        // CRITICA (500): 严重错误。
        // EMERGENCY (600): 系统不可用。
        $levelAry = [
            'debug','info','notice','warning','error','critical','alert','emergency'
        ];
        if (in_array($type, $levelAry)) {
            $level = $type;
        } else {
            $level = 'info';
        }

        return static::$logHandle->$level($message, $context);
    }
}
