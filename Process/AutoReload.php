<?php

/**
 * 根据文件变化自动重启服务
 */

namespace Kernel\Process;

use Kernel\Utilities\Terminal;

class AutoReload
{
    public static function startProcess($server)
    {

        Terminal::drawStr("AutoReload start", 'green');


        $autoreload_process = new \swoole_process(
            function ($process) use ($server) {

                $process->name($server->serviceName.'_auto_reload');
                new AutoReloadProcess($server);
            },
            false,
            2
        );
        $server->swoole->addProcess($autoreload_process);
    }
}
