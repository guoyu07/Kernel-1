<?php

namespace Kernel\Coroutine;

use Kernel\Coroutine\CallCC;
use Kernel\Coroutine\FutureTask;
use Kernel\Coroutine\Parallel;
use Kernel\Coroutine\Signal;
use Kernel\Coroutine\SysCall;
use Kernel\Coroutine\Task;
use Kernel\Timer\Timer;

class Command
{

    public static function taskSleep($ms)
    {
        return new SysCall(function (Task $task) use ($ms) {
            Timer::after($ms, function () use ($task) {
                $task->send(null);
                $task->run();
            });
            return Signal::TASK_SLEEP;
        });
    }

    public static function newTask(\Generator $gen = null)
    {
        return new SysCall(function (Task $task) use ($gen) {
            $context = $task->getContext();
            Task::execute($gen, $context, 0, $task);

            $task->send(null);
            return Signal::TASK_CONTINUE;
        });
    }

    public static function go(\Generator $coroutine)
    {
        return newTask($coroutine);
    }

    public static function defer(callable $callback)
    {
    }


    public static function killTask()
    {
        return new SysCall(function (Task $task) {
            return Signal::TASK_KILLED;
        });
    }

    public static function getTaskId()
    {
        return new SysCall(function (Task $task) {
            $task->send($task->getTaskId());

            return Signal::TASK_CONTINUE;
        });
    }



    public static function getContext($key, $default = null)
    {
        return new SysCall(function (Task $task) use ($key, $default) {
            $context = $task->getContext();
            $task->send($context->get($key, $default));

            return Signal::TASK_CONTINUE;
        });
    }

    public static function setContext($key, $value)
    {
        return new SysCall(function (Task $task) use ($key, $value) {
            $context = $task->getContext();
            $task->send($context->set($key, $value));

            return Signal::TASK_CONTINUE;
        });
    }

    public static function getContextObject()
    {
        return new SysCall(function (Task $task) {
            $context = $task->getContext();
            $task->send($context);

            return Signal::TASK_CONTINUE;
        });
    }

    public static function getContainer()
    {
        return new SysCall(function (Task $task) {
            $context = $task->getContainer();
            $task->send($context);

            return Signal::TASK_CONTINUE;
        });
    }

    public static function getContextArray()
    {
        return new SysCall(function (Task $task) {
            $context = $task->getContextArray();
            $task->send($context);

            return Signal::TASK_CONTINUE;
        });
    }

    public static function getTaskResult()
    {
        return new SysCall(function (Task $task) {
            $task->send($task->getSendValue());

            return Signal::TASK_CONTINUE;
        });
    }

    public static function getTaskStartTime($format = null)
    {
        return new SysCall(function (Task $task) use ($format) {
        });
    }

    public static function waitFor(\Generator $coroutine)
    {
        return new SysCall(function (Task $task) use ($coroutine) {
        });
    }

    public static function wait()
    {
        return new SysCall(function (Task $task) {
        });
    }

    public static function parallel($coroutines, &$fetchCtx = [])
    {
        return new SysCall(function (Task $task) use ($coroutines, &$fetchCtx) {
            (new Parallel($task))->call($coroutines, $fetchCtx);

            return Signal::TASK_WAIT;
        });
    }

    public static function async(callable $callback)
    {
        return new SysCall(function (Task $task) use ($callback) {
            $context = $task->getContext();
            $queue = $context->get('async_task_queue', []);
            $queue[] = $callback;
            $context->set('async_task_queue', $queue);
            $task->send(null);

            return Signal::TASK_CONTINUE;
        });
    }

    public static function callcc(callable $fun)
    {
        return new CallCC($fun);
    }

    public static function future($gen)
    {
        if (is_callable($gen)) {
            $gen = $gen();
        }

        if (!$gen instanceof \Generator) {
            return null;
        }

        return new SysCall(function (Task $task) use ($gen) {
            $ctx = $task->getContext();
            $future = new FutureTask($gen, $ctx, $task);
            $task->send($future);
            return Signal::TASK_CONTINUE;
        });
    }
}
