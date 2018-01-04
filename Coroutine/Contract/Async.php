<?php

namespace Kernel\Coroutine\Contract;

interface Async
{
    public function execute(callable $callback, $task);
}
