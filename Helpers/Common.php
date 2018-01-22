<?php


/**
 * 获取实例
 * @return
 */
function &getInstance()
{
    return \Kernel\Server\Server::getInstance();
}


function getContainer()
{
    try {
        return \Kernel\Coroutine\Command::getContainer();
    } catch (\Exception $e) {
        try {
            return getInstance()->container;
        } catch (\Exception $e) {
            return new \Kernel\Container\Container;
        }
    }
}

function app($instancem, $param = null)
{
    return $container = getContainer()->make($instance, $param);
}
