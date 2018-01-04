<?php
/**
 * Config 配置类
 *
 */
namespace Kernel\Config;

class Config
{
    private static $configMap = [];


    public static function init()
    {

        $configDir = CONFIG_PATH.DS.ENV;
        $config = new \Noodlehaus\Config($configDir);
        self::$configMap = $config->all();
        return ;
    }

    public static function get($key = null, $default = null)
    {
        if ($key===null) {
            return self::$configMap;
        }
        $routes = explode('.', $key);
        if (empty($routes)) {
            return $default;
        }

        $result = &self::$configMap;
        $hasConfig = true;
        foreach ($routes as $route) {
            if (!isset($result[$route])) {
                $hasConfig = false;
                break;
            }
            $result = &$result[$route];
        }
        if ($hasConfig) {
            return $result;
        } else {
            return $default;
        }
    }

    public static function set($key, $value)
    {
        $routes = explode('.', $key);
        if (empty($routes)) {
            return false;
        }

        $newConfigMap = Arr::createTreeByList($routes, $value);
        self::$configMap = Arr::merge(self::$configMap, $newConfigMap);

        return true;
    }

    public static function clear()
    {
        self::$configMap = [];
    }
}
