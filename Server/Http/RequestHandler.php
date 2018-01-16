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

            $request = new Request($get, $post, [], $cookie, $files, $server, $header, $rawContent);


            if (false === $this->initContext($request, $swooleRequest, $swooleResponse)) {
                return;
            }
            var_dump($request);
            return ;

            $timeout = $this->context->get('request_timeout');
            $this->event->once($this->getRequestFinishJobId(), [$this, 'handleRequestFinish']);
            Timer::after($timeout, [$this, 'handleTimeout'], $this->getRequestTimeoutJobId());

            $requestTask = new RequestTask($request, $swooleResponse, $this->context);
            $coroutine = $requestTask->run();
            $this->task = new Task($coroutine, $this->context);
            $this->task->run();

            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $e = null;
        } catch (\Throwable $t) {
            $e = t2ex($t);
        } catch (\Exception $e) {
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        if ($this->middleWareManager) {
            $coroutine = $this->middleWareManager->handleHttpException($e);
        } else {
            $coroutine = RequestExceptionHandlerChain::getInstance()->handle($e);
        }


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
        $this->context->set('request_time', $request->server('REQUEST_TIME'));
        $this->context->set('request_timeout', 30 * 1000);
        $this->context->set('request_end_event_name', $this->getRequestFinishJobId());
    }

    public function handleRequestFinish()
    {
        Timer::clearAfterJob($this->getRequestTimeoutJobId());
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
            $this->event->fire($this->getRequestFinishJobId());
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
