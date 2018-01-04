<?php
/**
 * Config 配置类
 *
 */
namespace Kernel\Config;

class Config
{


    /**
     * 初始化配置
     * @return
     */
    public static function init()
    {
        $configDir = CONFIG_PATH.DS.ENV;
        $config = new \Noodlehaus\Config($configDir);
        return $config;
    }
}
