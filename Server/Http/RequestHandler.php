<?php

namespace Kernel\Server\Http;

use \Swoole\Http\Request as SwooleHttpRequest;
use \Swoole\Http\Response as SwooleHttpResponse;

use Kernel\Coroutine\Context;
use Kernel\Coroutine\Event;
use Kernel\Coroutine\Signal;
use Kernel\Coroutine\Task;
use Kernel\Coroutine\TaskId;
use Kernel\Utilities\Time;
use Kernel\Timer\Timer;

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
    /**
     * 请求句柄
     * @param  SwooleHttpRequest  $swooleRequest
     * @param  SwooleHttpResponse $swooleResponse
     * @return
     */
    public function handle(SwooleHttpRequest $swooleRequest, SwooleHttpResponse $swooleResponse)
    {


        $this->initContext($swooleRequest, $swooleResponse);

        $timeout = $this->context->get('request_timeout');
        $this->event->once($this->server->getRequestFinishJobId(), [$this, 'handleRequestFinish']);
        Timer::after($timeout, [$this, 'handleTimeout'], $this->server->getRequestTimeoutJobId());
    }

    /**
     * initContext
     * @param  SwooleHttpRequest  $swooleRequest
     * @param  SwooleHttpResponse $swooleResponse
     * @return
     */
    private function initContext(SwooleHttpRequest $swooleRequest, SwooleHttpResponse $swooleResponse)
    {

        $server = $this->getServer($swooleRequest);
        $route = $this->server->dispatch($server);

        if (false===$route) {
            $httpCode = 404;
            $this->sendFile($swooleResponse, $httpCode);
            return ;
        }
        if (true===$route) {
            $httpCode = 405;
            $this->sendFile($swooleResponse, $httpCode);
            return ;
        }


        $this->context->set('swoole_request', $swooleRequest);
        $this->context->set('swoole_response', $swooleResponse);

        $this->context->set('controller_name', $route['controller_name']);
        $this->context->set('action_name', $route['action_name']);
        $this->context->set('action_args', $route['action_args']);
        $this->context->set('request_time', Time::stamp());
        $this->context->set('request_timeout', 30 * 1000);
        $this->context->set('request_end_event_name', $this->server->getRequestFinishJobId());
    }


    public function handleRequestFinish()
    {
        Timer::clearAfterJob($this->server->getRequestTimeoutJobId());
        $response = $this->context->get('response');
        if ($response === null) {
            //伪造响应,避免terminate接口传入null导致fatal error
            $response = new Response();
        }
        $coroutine = $this->middleWareManager->executeTerminators($response);
        Task::execute($coroutine, $this->context);
    }


    /**
     * 获取server信息
     * @param  SwooleHttpRequest $swooleRequest
     * @return
     */
    public function getServer(SwooleHttpRequest $swooleHttpRequest)
    {
        $header = isset($swooleHttpRequest->header) ? $swooleHttpRequest->header : [];
        $server = isset($swooleHttpRequest->server) ? array_change_key_case($swooleHttpRequest->server, CASE_UPPER) : [];
        if (isset($swooleHttpRequest->header)) {
            foreach ($swooleHttpRequest->header as $key => $value) {
                $newKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
                $server[$newKey] = $value;
            }
        }
        $server['REQUEST_URI'] = preg_replace('/\/{2,}/', '/', $server['REQUEST_URI']);
        $server['PATH_INFO'] = preg_replace('/\/{2,}/', '/', $server['PATH_INFO']);
        $server['REQUEST_URI'] = preg_replace('#/$#', '', $server['REQUEST_URI']);
        $server['PATH_INFO'] = preg_replace('#/$#', '', $server['PATH_INFO']);
        return $server;
    }

    /**
     *
     * @param \swoole_http_request $request 请求对象
     * @return string
     */
    public function getRequestId(SwooleHttpRequest $request)
    {
        static $i = 0;
        $i || $i = mt_rand(1, 0x7FFFFF);
        $id = sprintf(
            "%08x%06x%04x%06x",
            time() & 0xFFFFFFFF,
            crc32(substr((string)gethostname(), 0, 256)) >> 8 & 0xFFFFFF,
            getmypid() & 0xFFFF,
            $i = $i > 0xFFFFFE ? 1 : $i + 1
        );
        return $id;
    }


    /**
     * 直接响应静态文件
     *
     * @param string $path
     * @param \swoole_http_response $response 响应对象
     * @param \$httpCode $httpCode
     * @return int
     */
    public function sendFile($response, $httpCode)
    {
        $path = __DIR__ . '/View/'.$httpCode.'.html';
        if (!file_exists($path)) {
            $response->status($httpCode);
            $response->end('');
            return true;
        } else {
            swoole_async_readfile($path, function ($filename, $content) use ($response, $httpCode) {
                $response->status($httpCode);
                $response->end($content);
            });
            return true;
        }
    }
}
