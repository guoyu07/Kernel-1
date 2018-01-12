<?php

namespace Kernel\Server\Http;

use swoole_http_response as SwooleHttpResponse;
use Kernel\Contracts\Http\ResponseTrait;
use Kernel\Server\Http\Foundation\Request\Request;
use Kernel\Coroutine\Context;
use Kernel\Coroutine\Task;
use Kernel\Server\Http\Foundation\Response\BaseResponse;
use Kernel\Server\Http\Foundation\Response\InternalErrorResponse;
use Kernel\Middleware\MiddlewareManager;
use Kernel\Container\Container;

// use ZanPHP\Support\Di;

class RequestTask
{
    /**
     * @var Request
     */
    private $request;
    /**
     * @var SwooleHttpResponse
     */
    private $swooleResponse;
    /**
     * @var Context
     */
    private $context;

    private $middleWareManager;

    public function __construct(Request $request, SwooleHttpResponse $swooleResponse, Context $context, MiddlewareManager $middlewareManager)
    {
        $this->request = $request;
        $this->swooleResponse = $swooleResponse;
        $this->context = $context;
        $this->middleWareManager = $middlewareManager;
    }

    public function run()
    {
        try {
            yield $this->doRun();
            return;
        } catch (\Throwable $t) {
            $e = t2ex($t);
        } catch (\Exception $e) {
        }
        clear_ob();


        $coroutine = $this->middleWareManager->handleHttpException($e);
        Task::execute($coroutine, $this->context);
        $this->context->getEvent()->fire($this->context->get('request_end_event_name'));
    }

    public function doRun()
    {
        $response = (yield $this->middleWareManager->executeFilters());
        if (null !== $response) {
            $this->context->set('response', $response);
            /** @var ResponseTrait $response */
            yield $response->sendBy($this->swooleResponse);
            $this->context->getEvent()->fire($this->context->get('request_end_event_name'));
            return;
        }

        $dispatcher = (new Container())->make(Dispatcher::class);
        $response = (yield $dispatcher->dispatch($this->request, $this->context));
        if (null === $response) {
            $code = BaseResponse::HTTP_INTERNAL_SERVER_ERROR;
            $response = new InternalErrorResponse("网络错误($code)", $code);
        }

        yield $this->middleWareManager->executePostFilters($response);

        $this->context->set('response', $response);
        yield $response->sendBy($this->swooleResponse);

        $this->context->getEvent()->fire($this->context->get('request_end_event_name'));
        clear_ob();
    }
}
