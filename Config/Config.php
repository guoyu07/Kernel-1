<?php
/**
 * Config 配置类
 *
 */
namespace Kernel\Config;

class Config
{
    private static $_config = null;


    public static function init()
    {
        $configDir = CONFIG_PATH.DS.ENV;
        static::$_config = new \Noodlehaus\Config($configDir);
    }

    public static function get($key, $default = null)
    {
        return static::$_config->get($key, $default);
    }

    public static function set($key, $value)
    {
        return static::$_config->set($key, $value);
    }
}
