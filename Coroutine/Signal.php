<?php

namespace Kernel\Coroutine;

class Signal
{


    // 沉睡一段时间
    const TASK_SLEEP = 1;
    // 苏醒
    const TASK_AWAKE = 2;
    // 继续
    const TASK_CONTINUE = 3;
    // 结束
    const TASK_KILLED = 4;
    // 运行中
    const TASK_RUNNING = 5;
    // 等待一个事件完成
    const TASK_WAIT = 6;
    // 结束
    const TASK_DONE = 7;

    /**
    * 检查是否为信号
    *
    * @param $signal
    * @return bool
    */
    public static function isSignal($signal)
    {
        if (!$signal) {
            return false;
        }

        if (!is_int($signal)) {
            return false;
        }

        if ($signal < 1) {
            return false;
        }

        if ($signal > 7) {
            return false;
        }

        return true;
    }
}
