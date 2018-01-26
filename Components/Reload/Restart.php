<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-19
 * Time: 上午9:17
 */

namespace Kernel\Components\Reload;

class Restart
{

    public function __construct()
    {

        $handle= getInstance();
        setTimezone();
        //添加定时器
        $timer = $handle->config('auto_restart_timer', 3600);
        swoole_timer_tick(1000*$timer, function () use ($handle) {
            $handle->server->reload();//重启服务
            $handle->settle();//重新写入 PID 数据
            secho("Restart", date('Y-m-d H:i:s'));
        });
    }
}
