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


use Kernel\Server\Http\Foundation\Cookie as CookieAlias;
use Kernel\Server\Http\Foundation\Request\Request;
use Kernel\Server\Http\Foundation\Response\BaseResponse;
use Kernel\Server\Http\Foundation\Response\InternalErrorResponse;
use Kernel\Server\Http\Foundation\Response\JsonResponse;
use Kernel\Server\Http\Foundation\Response\Response;
use Kernel\Middleware\MiddlewareManager;
use Kernel\Server\Http\RequestExceptionHandlerChain;
use Kernel\Log\Logger;

class RequestHandler
{

    /** @var null|Context  */
    private $context = null;

    /** @var MiddlewareManager */
    private $middleWareManager = null;

    /** @var Task */
    private $task = null;

    /** @var Event */
    private $event = null;

    /** @var Request */
    private $request = null;



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
        try {
            $request = Request::createFromSwooleHttpRequest($swooleRequest);

            if (false === $this->initContext($request, $swooleRequest, $swooleResponse)) {
                //filter ico file access
                return;
            }
            $this->middleWareManager = new MiddlewareManager($request, $this->context);

            $timeout = $this->context->get('request_timeout');
            $this->event->once($this->server->getRequestFinishJobId(), [$this, 'handleRequestFinish']);
            Timer::after($timeout, [$this, 'handleTimeout'], $this->server->getRequestTimeoutJobId());
            $requestTask = new RequestTask($request, $swooleResponse, $this->context, $this->middleWareManager);
            $coroutine = $requestTask->run();
            $this->task = new Task($coroutine, $this->context);
            $this->task->run();
            clear_ob();
            $e = null;
        } catch (\Throwable $t) {
            $e = t2ex($t);
        } catch (\Exception $e) {
        }
        clear_ob();
        if ($this->middleWareManager) {
            $coroutine = $this->middleWareManager->handleHttpException($e);
        } else {
            $coroutine = RequestExceptionHandlerChain::getInstance()->handle($e);
        }
        Task::execute($coroutine, $this->context);
        $this->event->fire($this->server->getRequestFinishJobId());
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

        $cookie = new CookieAlias($request, $swooleResponse);
        $this->context->set('cookie', $cookie);


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

    public function handleTimeout()
    {
        try {
            $this->task->setStatus(Signal::TASK_KILLED);
            $this->logTimeout();

            $request = $this->context->get('request');
            if ($request && $request->wantsJson()) {
                $data = [
                    'code' => 10000,
                    'msg' => '网络超时',
                    'data' => '',
                ];
                $response = new JsonResponse($data, BaseResponse::HTTP_GATEWAY_TIMEOUT);
            } else {
                $response = new InternalErrorResponse('服务器超时', BaseResponse::HTTP_GATEWAY_TIMEOUT);
            }

            $this->context->set('response', $response);
            $swooleResponse = $this->context->get('swoole_response');
            $response->sendBy($swooleResponse);
            $this->event->fire($this->server->getRequestFinishJobId());
        } catch (\Throwable $t) {
            echo_exception($t);
        } catch (\Exception $ex) {
            echo_exception($ex);
        }
    }

    private function logTimeout()
    {
        $remoteIp = $this->request->getClientIp();
        $route = $this->request->getRoute();
        $query = http_build_query($this->request->query->all());
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
