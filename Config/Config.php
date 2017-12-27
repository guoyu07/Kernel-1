<?php
/**
 * Config 配置类
 *
 */
namespace Kernel\Config;

use Kernel\Utilities\Arr;

class Config
{
    /**
     * Config 数据
     * @var array
     */
    private static $configMap = [];

    /**
     * 初始化配置
     * @return
     */
    public static function init()
    {

        $configDir = CONFIG_PATH.DS.ENV;
        $config = new \Noodlehaus\Config($configDir);
        self::$configMap = $config->all();
        unset($config, $configDir);
        return ;
    }

    /**
     * 获取配置信息
     * @param  string $key     键
     * @param  string $default 默认值
     * @return $var
     */
    public static function get($key = null, $default = null)
    {
        if ($key===null) {
            return self::$configMap;
        }
        $routes = explode('.', $key);
        if (empty($routes)) {
            unset($key);
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
            unset($key, $default, $hasConfig);
            return $result;
        } else {
            unset($key, $hasConfig, $result);
            return $default;
        }
    }

    /**
     * 保存配置
     * @param  string $key
     * @param string/array $value
     */
    public static function set($key, $value)
    {
        $routes = explode('.', $key);
        if (empty($routes)) {
            unset($key, $value);
            return false;
        }

        $newConfigMap = Arr::createTreeByList($routes, $value);
        self::$configMap = Arr::merge(self::$configMap, $newConfigMap);

        unset($key, $value, $newConfigMap, $routes);

        return true;
    }

    /**
     * 清理
     * @return
     */
    public static function clear()
    {
        self::$configMap = [];
    }
}
