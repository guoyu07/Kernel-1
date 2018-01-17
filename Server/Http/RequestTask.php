<?php
namespace Kernel\Server\Http;

use \Swoole\Http\Request as SwooleHttpRequest;
use \Swoole\Http\Response as SwooleHttpResponse;

use Kernel\Server\Http\Foundation\Response;
use Kernel\Server\Http\Foundation\HtmlResponse;
use Kernel\Server\Http\Foundation\XmlResponse;
use Kernel\Server\Http\Foundation\JsonResponse;
use Kernel\Server\Http\Foundation\ImageResponse;
use Kernel\Coroutine\Context;
use Kernel\Coroutine\Task;
use Kernel\Server\Http\Foundation\Request;

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

    private $server;
    public function __construct(Request $request, SwooleHttpResponse $swooleResponse, Context $context)
    {
        $this->request = $request;
        $this->swooleResponse = $swooleResponse;
        $this->context = $context;
        $this->server = $this->context->get('server');
    }

    public function run()
    {
        try {
            yield $this->doRun();
            return;
        } catch (\Exception $e) {
        }
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        $coroutine = $this->handleHttpException($e);
        Task::execute($coroutine, $this->context);
        $this->context->getEvent()->fire($this->context->get('request_end_event_name'));
    }


    public function handleHttpException($e)
    {
        $content = $e->getMessage();
        $response = $this->responseError($content);
        yield $response->sendBy($this->swooleResponse);
        return;
    }

    public function doRun()
    {
        $response = (yield $this->middleware());
        if (null !== $response) {
            $this->context->set('response', $response);
            yield $response->sendBy($this->swooleResponse);
            $this->context->getEvent()->fire($this->context->get('request_end_event_name'));
            return;
        }

        $response = (yield $this->dispatch());
        $this->context->set('response', $response);
        yield $response->sendBy($this->swooleResponse);
        $this->context->getEvent()->fire($this->context->get('request_end_event_name'));
    }

    public function dispatch()
    {
        $controller_handle = $this->context->get('controller_handle');
        $controller_handle_args = $this->context->get('controller_handle_args');
        $controller_handle = explode('@', $controller_handle);

        $controller = $controller_handle[0];
        $action = $controller_handle[1];
        unset($controller_handle);

        if (!class_exists($controller)) {
            yield $this->responseError("controller:{$controller} not found");
            return;
        }
        // $controller = new $controller($this->request, $this->context);
        $controller = $this->server->container->make($controller, [$this->request, $this->context]);
        if (!is_callable([$controller, $action])) {
            yield $this->responseError("action:{$action} is not callable in controller:" . get_class($controller));
            return;
        }

        if ($controller_handle_args == null) {
            $controller_handle_args = [];
        }
        yield $controller->$action(...array_values($controller_handle_args));
    }

    /**
     * 执行中间件
     * @return
     */
    public function middleware()
    {
        $middleware = $this->context->get('middleware');
        if (false === $middleware) {
            yield null;
            return ;
        }
        //执行$middleware
        $middleware = explode('@', $middleware);
        $controller = $middleware[0];
        $action = $middleware[1];
        unset($middleware);
        if (!class_exists($controller)) {
            yield $this->responseError("controller:{$controller} not found");
            return;
        }
        // $controller = new $controller($this->request, $this->context);
        $controller = $this->server->container->make($controller, [$this->request, $this->context]);
        if (!is_callable([$controller, $action])) {
            yield $this->responseError("action:{$action} is not callable in controller:" . get_class($controller));
            return;
        }
        yield $controller->$action();
    }

    public function responseError($content, $code = 500)
    {
        $contentType = $this->request->getContentType();
        switch ($contentType) {
            case Response::XML:
                $response = $this->server->container->make('Kernel\Server\Http\Foundation\XmlResponse', [$content, $code]);
                break;
            case Response::JSON:
                $response = $this->server->container->make('Kernel\Server\Http\Foundation\JsonResponse', [$content, $code]);
                break;
            case Response::IMAGE:
                $response = $this->server->container->make('Kernel\Server\Http\Foundation\HtmlResponse', [$content, $code]);
                break;
            case Response::HTML:
                $response = $this->server->container->make('Kernel\Server\Http\Foundation\HtmlResponse', [$content, $code]);
                break;
            default:
                $response = $this->server->container->make('Kernel\Server\Http\Foundation\HtmlResponse', [$content, $code]);
                break;
        }
        yield $response;
    }
}
