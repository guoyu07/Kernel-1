<?php

/**
 * 根据文件变化自动重启服务
 */

namespace Kernel\Process;

use Kernel\Utilities\Terminal;

class InotifyProcess
{
    public $swoole;

    public function __construct($server)
    {
        $this->swoole = $server->swoole;
        //添加定时器
        Terminal::drawStr("swoole_timer_tick".date('Y-m-d H:i:s'), 'green');
        swoole_timer_tick(1000*3600*1, function () use ($server) {

            $server->swoole->reload();
            sleep(2);
            $server->settle();
            Terminal::drawStr("SwooleServer Reload".date('Y-m-d H:i:s'), 'green');
        });
    }
}
