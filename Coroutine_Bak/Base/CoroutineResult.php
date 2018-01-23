<?php

namespace Kernel\Coroutine\Base;

class CoroutineResult
{
    private static $instance;

    public function __construct()
    {
        self::$instance = &$this;
    }

    public static function &getInstance()
    {
        if (self::$instance == null) {
            new CoroutineResult();
        }
        return self::$instance;
    }
}
