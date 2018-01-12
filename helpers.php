<?php

use Kernel\Coroutine\Signal;
use Kernel\Coroutine\SysCall;
use Kernel\Coroutine\Task;

if (!function_exists('clear_ob')) {
    function clear_ob()
    {
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
    }
}

if (! function_exists('t2ex')) {
    if (interface_exists("Throwable")) {
        /**
         * @param Throwable $t
         * @return Exception
         */
        function t2ex(\Throwable $t)
        {
            if ($t instanceof \Exception) {
                return $t;
            } elseif ($t instanceof \Error) {
                return new \Exception($t->getMessage(), $t->getCode(), $t);
            } else {
                assert(false);
            }
        }
    }
}




if (! function_exists('echo_exception')) {
    /**
     * @param \Throwable $t
     */
    function echo_exception($t)
    {
        // 兼容PHP7 & PHP5
        if ($t instanceof \Throwable || $t instanceof \Exception) {
            $time = date('Y-m-d H:i:s');
            $class = get_class($t);
            $code = $t->getCode();
            $msg = $t->getMessage();
            $trace = $t->getTraceAsString();
            $line = $t->getLine();
            $file = $t->getFile();
            $metaData = "[]";

            // $metaData = var_export($t->getMetadata(), true);
            echo <<<EOF


###################################################################################
          \033[1;31mGot an exception\033[0m
          time: $time
          class: $class
          code: $code
          message: $msg
          file: $file::$line
metaData:
$metaData

$trace
###################################################################################


EOF;

            if ($previous = $t->getPrevious()) {
                echo "caused by:\n";
                echo_exception($previous);
            }
        }
    }
}



function getContext($key, $default = null)
{
    return new SysCall(function (Task $task) use ($key, $default) {
        $context = $task->getContext();
        $task->send($context->get($key, $default));

        return Signal::TASK_CONTINUE;
    });
}

function setContext($key, $value)
{
    return new SysCall(function (Task $task) use ($key, $value) {
        $context = $task->getContext();
        $task->send($context->set($key, $value));

        return Signal::TASK_CONTINUE;
    });
}
