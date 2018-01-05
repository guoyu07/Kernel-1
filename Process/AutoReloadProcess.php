<?php

/**
 * 根据文件变化自动重启服务
 */

namespace Kernel\Process;

use Kernel\Utilities\Terminal;
use Kernel\Config\Config;

class AutoReloadProcess
{
    public $swoole;

    public function __construct($server)
    {
        $server->setTimezone();
        $this->swoole = $server->swoole;
        //添加定时器
        Terminal::drawStr("swoole_timer_tick:".date('Y-m-d H:i:s'), 'green');
        $timer = Config::get($server->config.'.auto_reload_time', 3600);
        swoole_timer_tick(1000*$timer*1, function () use ($server) {

            $server->swoole->reload();
            sleep(2);
            $server->settle();
            Terminal::drawStr("SwooleServer Reload:".date('Y-m-d H:i:s'), 'green');
        });
    }
}
