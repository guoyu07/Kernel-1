<?php

namespace Kernel\Server\Http;

use \Swoole\Http\Request as SwooleHttpRequest;
use \Swoole\Http\Response as SwooleHttpResponse;

use Kernel\Coroutine\Context;
use Kernel\Coroutine\Event;
use Kernel\Coroutine\Signal;
use Kernel\Coroutine\Task;

class RequestHandler
{

    /** @var null|Context  */
    private $context = null;

    /** @var Event */
    private $event = null;

    /** @var HttpServer */
    private $server = null;



    public function __construct(HttpServer $HttpServer)
    {
        $this->context = new Context();
        $this->event = $this->context->getEvent();
        $this->server = $HttpServer;
    }

    public function handle(SwooleHttpRequest $swooleRequest, SwooleHttpResponse $swooleResponse)
    {
    }
}
