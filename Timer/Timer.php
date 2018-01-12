<?php

namespace Kernel\Timer;

class Timer
{
    private static $tickMap = [];
    private static $afterMap = [];
    private static $counter = 0;

    /**
     * 添加一个每隔 {$interval} 毫秒 执行一次的计时器任务
     * @param int        $interval  单位: 毫秒
     * @param callable   $callback
     * @param string     $jobId   标识任务的唯一标识符，必须唯一
     *
     * @return string    $jobId   timer job id
     * @throws InvalidArgumentException
     * @throws TimerExistException
     */
    public static function tick($interval, callable $callback, $jobId = '')
    {
        self::valid($interval);
        $jobId = self::formatJobId($jobId);

        if (isset(self::$tickMap[$jobId])) {
            // throw new TimerExistException('job name is exist! >>> ' . $jobId);
            // sys_error("timer id has existed: $jobId");
            return false;
        }

        $timerId = swoole_timer_tick($interval, self::formateTickCallback($jobId, $callback));
        self::$tickMap[$jobId] = $timerId;

        return $jobId;
    }

    /**
     * 添加一个 {$interval} 毫秒后仅执行一次的计时器任务
     * @param int        $interval  单位: 毫秒
     * @param callable   $callback
     * @param string     $jobId   标识任务的唯一标识符，必须唯一
     *
     * @return string    $jobId timer job id
     * @throws InvalidArgumentException
     * @throws TimerExistException
     */
    public static function after($interval, callable $callback, $jobId = '')
    {
        self::valid($interval);
        $jobId = self::formatJobId($jobId);

        if (isset(self::$afterMap[$jobId])) {
            // throw new TimerExistException('job name is exist! >>> ' . $jobId);
            // sys_error("timer id has existed: $jobId");
            return false;
        }

        $timerId = swoole_timer_after($interval, self::formateAfterCallback($jobId, $callback));
        self::$afterMap[$jobId] = $timerId;

        return $jobId;
    }

    /**
     * 根据tick timer job id 清除一个计时器任务
     *
     * @param string $jobId
     * @return bool
     */
    public static function clearTickJob($jobId)
    {
        if (!isset(self::$tickMap[$jobId])) {
            return false;
        }

        $isCleared = false;
        $timerId = self::$tickMap[$jobId];
        // if (swoole_timer_exists($timerId)) {
            swoole_timer_clear($timerId);
            $isCleared = true;
        // }
        unset(self::$tickMap[$jobId]);
        return $isCleared;
    }

    /**
     * 根据after timer job id 清除一个计时器任务
     *
     * @param string $jobId
     *
     * @return bool
     */
    public static function clearAfterJob($jobId)
    {
        if (!isset(self::$afterMap[$jobId])) {
            return false;
        }

        $timerId = self::$afterMap[$jobId];
        $isCleared = false;
        // if (swoole_timer_exists($timerId)) {
            swoole_timer_clear($timerId);
            $isCleared = true;
        // }

        unset(self::$afterMap[$jobId]);

        return $isCleared;
    }

    /**
     * @param $key
     * @return bool
     */
    public static function clearTickMap($key)
    {
        if (!$key) {
            return false;
        }

        unset(self::$tickMap[$key]);
    }

    /**
     * @param $key
     * @return bool
     */
    public static function clearAfterMap($key)
    {
        if (!$key) {
            return false;
        }

        unset(self::$afterMap[$key]);
    }

    /**
     * @param $jobId
     * @param callable $callback
     * @return \Closure
     */
    private static function formateTickCallback($jobId, callable $callback)
    {
        return function () use ($jobId, $callback) {
            call_user_func($callback, $jobId);
        };
    }

    /**
     * @param $jobId
     * @param callable $callback
     * @return \Closure
     */
    private static function formateAfterCallback($jobId, callable $callback)
    {
        return function () use ($jobId, $callback) {
            Timer::clearAfterMap($jobId);
            call_user_func($callback, $jobId);
        };
    }

    /**
     * @param $interval
     * @throws InvalidArgumentException
     */
    private static function valid($interval)
    {
        if (!is_int($interval)) {
            throw new \Exception('interval must be a int!');
        }

        if ($interval <= 0) {
            throw new \Exception('interval must be greater than 0!');
        }
    }

    /**
     * @param $jobId
     * @return string
     */
    private static function formatJobId($jobId)
    {
        if ($jobId) {
            return $jobId;
        }
        return self::createJobId();
    }


    /**
     * @return string
     */
    private static function createJobId()
    {
        if (self::$counter >= PHP_INT_MAX) {
            self::$counter = 0;
        }

        self::$counter++;
        return 'j_' . self::$counter;
    }
}

if (! function_exists('sys_echo')) {
    function sys_echo($context)
    {
        $workerId = isset($_SERVER["WORKER_ID"]) ? $_SERVER["WORKER_ID"] : "";
        $dataStr = date("Y-m-d H:i:s");
        echo "[$dataStr #$workerId] $context\n";
    }
}

if (! function_exists('sys_error')) {
    function sys_error($context)
    {
        $workerId = isset($_SERVER["WORKER_ID"]) ? $_SERVER["WORKER_ID"] : "";
        $dataStr = date("Y-m-d H:i:s");
        $context = str_replace("%", "%%", $context);
        fprintf(STDERR, "[$dataStr #$workerId] $context\n");
    }
}
