<?php

/**
 * 根据文件变化自动重启服务
 */

namespace Kernel\Process;

use Kernel\Utilities\Terminal;

class Inotify
{
    public static function startProcess($server)
    {
        if (!extension_loaded('inotify')) {
            return false;
        }
        Terminal::drawStr("AutoReload start", 'green');


        $inotify_process = new \swoole_process(
            function ($process) use ($server) {

                $process->name($server->serviceName.'_inotify');
                new InotifyProcess($server);
            },
            false,
            2
        );
        $server->swoole->addProcess($inotify_process);
    }
}
