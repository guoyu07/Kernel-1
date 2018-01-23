<?php
/**
 * Config 配置类
 *
 */
namespace Kernel\Config;

use Noodlehaus\Config;
use Kernel\Container\Container;

class Config
{
    /**
     * 实例化对象
     * @var  object
     */
    private static $handle;

    private function __construct()
    {
    }
    /**
     * 初始化一个Model实例
     * 用单列模式替换
     */
    public static function getInstance()
    {
        if (!isset(self::$handle)) {
            $configDir = CONFIG_PATH.DS.ENV;
            self::$handle=Container::getInstance()->make('Noodlehaus\Config', [$configDir]);
        }
        return self::$handle;
    }
    public static function get($key, $default = null)
    {
        return self::getInstance()->get($key, $default);
    }
    public static function set($key, $value)
    {
        return self::getInstance()->set($key, $value);
    }
}
