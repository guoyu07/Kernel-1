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
use Kernel\Server\Http\Foundation\Request;

class RequestHandler
{

    /** @var null|Context  */
    private $context = null;

    /** @var Task */
    private $task = null;

    /** @var Event */
    private $event = null;

    /** @var Request */
    private $request = null;
    /** @var Swoole */
    private $server = null;



    public function __construct(HttpServer $HttpServer)
    {
        $this->server = $HttpServer;
        $this->context = $this->server->container->make('Kernel\Coroutine\Context');
        // $this->context = new Context();
        $this->event = $this->context->getEvent();
    }
    /**
     * 请求句柄
     * @param  SwooleHttpRequest  $swooleRequest
     * @param  SwooleHttpResponse $swooleResponse
     * @return
     */
    public function handle(SwooleHttpRequest $swooleRequest, SwooleHttpResponse $swooleResponse)
    {


        $get = isset($swooleRequest->get) ? $swooleRequest->get : [];
        $post = isset($swooleRequest->post) ? $swooleRequest->post : [];
        $cookie = isset($swooleRequest->cookie) ? $swooleRequest->cookie : [];
        $files = isset($swooleRequest->files) ? $swooleRequest->files : [];
        $header = isset($swooleRequest->header) ? $swooleRequest->header : [];
        $server = isset($swooleRequest->server) ? array_change_key_case($swooleRequest->server, CASE_UPPER) : [];
        $rawContent = $swooleRequest->rawContent();

        foreach ($header as $key => $value) {
            $newKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
            $server[$newKey] = $value;
        }
        //添加内存值
        $server['REQUEST_MEM_START'] = memory_get_usage(false);

        $request = $this->server->container->make('Kernel\Server\Http\Foundation\Request', [
            $get, $post, [], $cookie, $files, $server, $header, $rawContent
        ]);
        if (false === $this->initContext($request, $swooleRequest, $swooleResponse)) {
            return ;
        }
        try {
            $timeout = $this->context->get('request_timeout');
            $this->event->once($this->getRequestFinishJobId(), [$this, 'handleRequestFinish']);
            Timer::after($timeout, [$this, 'handleTimeout'], $this->getRequestTimeoutJobId());

            $coroutine_handle = $this->server->container->make('Kernel\Server\Http\RequestTask', [
                $request, $swooleResponse, $this->context
            ]);

            $coroutine = $coroutine_handle->run();
            $this->task = $this->server->container->make('Kernel\Coroutine\Task', [
                $coroutine,
                $this->context
            ]);

            $this->task->run();

            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            return ;
        } catch (\Exception $e) {
        }
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $coroutine_handle = $this->server->container->make('Kernel\Server\Http\RequestTask', [
            $request, $swooleResponse, $this->context
        ]);

        $coroutine = $coroutine_handle->handleHttpException($e);
        Task::execute($coroutine, $this->context);
        $this->event->fire($this->getRequestFinishJobId());
    }



    /**
     * initContext
     * @param  SwooleHttpRequest  $swooleRequest
     * @param  SwooleHttpResponse $swooleResponse
     * @return
     */
    private function initContext($request, SwooleHttpRequest $swooleRequest, SwooleHttpResponse $swooleResponse)
    {
        $route = $this->server->dispatch($request);

        if (false===$route) {
            $httpCode = 404;
            $this->sendFile($swooleResponse, $httpCode);
            return false;
        }
        if (true===$route) {
            $httpCode = 405;
            $this->sendFile($swooleResponse, $httpCode);
            return false;
        }

        $this->request = $request;
        $this->context->set('swoole_request', $swooleRequest);
        $this->context->set('request', $request);
        $this->context->set('swoole_response', $swooleResponse);
        $this->context->set('server', $this->server);

        $this->context->set('controller_handle', $route[0]);
        $this->context->set('controller_handle_args', $route[1]);
        $this->context->set('middleware', $this->server->dispatchMiddleware($route[0]));
        $this->context->set('request_time', $request->server('REQUEST_TIME'));
        $this->context->set('request_timeout', 3 * 1000);
        $this->context->set('request_end_event_name', $this->getRequestFinishJobId());
    }

    public function handleRequestFinish()
    {
        Timer::clearAfterJob($this->getRequestTimeoutJobId());
        return;
    }

    public function handleTimeout()
    {

        $this->task->setStatus(Signal::TASK_KILLED);

        $request = $this->context->get('request');

        $swooleResponse = $this->context->get('swoole_response');

        $coroutine_handle = $this->server->container->make('Kernel\Server\Http\RequestTask', [
            $request, $swooleResponse, $this->context
        ]);
        $response =  $coroutine_handle->responseError('服务器超时', 502);
        $this->context->set('response', $response);
        $response->sendBy($swooleResponse);
        $this->event->fire($this->getRequestFinishJobId());
        return ;
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



    public function getRequestFinishJobId()
    {
        return spl_object_hash($this) . '_request_finish';
    }

    public function getRequestTimeoutJobId()
    {
        return spl_object_hash($this) . '_handle_timeout';
    }
}
