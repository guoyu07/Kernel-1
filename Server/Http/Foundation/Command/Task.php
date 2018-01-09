<?php

use ZanPHP\Coroutine\Signal;
use ZanPHP\Coroutine\SysCall;
use ZanPHP\Coroutine\Task;
use ZanPHP\HttpFoundation\Cookie;


function getCookieHandler()
{
    return new SysCall(function (Task $task) {
        $context = $task->getContext();
        $cookie = $context->get('cookie');
        $task->send($cookie);

        return Signal::TASK_CONTINUE;
    });
}

function cookieGet($key, $default = null)
{
    return new SysCall(function (Task $task) use ($key, $default) {
        $context = $task->getContext();
        $request = $context->get('request');
        $cookies = $request->cookies;
        $value = isset($key) ? $cookies->get($key, $default) : null;
        $task->send($value);

        return Signal::TASK_CONTINUE;
    });
}

function cookieSet($key, $value = null, $expire = 0, $path = null, $domain = null, $secure = null, $httpOnly = null)
{
    $args = func_get_args();
    return new SysCall(function (Task $task) use ($args) {
        $context = $task->getContext();
        /** @var Cookie $cookie */
        $cookie = $context->get('cookie');
        $ret = $cookie->set(...$args);
        $task->send($ret);

        return Signal::TASK_CONTINUE;
    });
}

function getSessionHandler()
{
    return new SysCall(function (Task $task) {
        $context = $task->getContext();
        $session = $context->get('session');
        $value = $session ? $session : null;
        $task->send($value);
        return Signal::TASK_CONTINUE;
    });
}

function getServerHandler()
{
    return new SysCall(function (Task $task) {
        $context = $task->getContext();
        $request = $context->get('request');
        $value = $request ? $request->server : null;
        $task->send($value);
        return Signal::TASK_CONTINUE;
    });
}