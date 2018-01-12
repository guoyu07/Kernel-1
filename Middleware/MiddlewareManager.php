<?php

namespace Kernel\Middleware;

use Kernel\Server\Http\RequestExceptionHandlerChain;
use Kernel\Contracts\Foundation\ExceptionHandler;
use Kernel\Server\Http\Foundation\Request\Request;
use Kernel\Coroutine\Context;
use Kernel\Middleware\Network\RequestFilter;
use Kernel\Middleware\Network\RequestPostFilter;
use Kernel\Middleware\Network\RequestTerminator;

class MiddlewareManager
{
    private $middlewareConfig;
    private $request;
    private $context;
    private $middleware = [];

    public function __construct(Request $request, Context $context)
    {
        $this->middlewareConfig = MiddlewareConfig::getInstance();
        $this->request = $request;
        $this->context = $context;

        $this->initMiddleware();
    }

    public function executeFilters()
    {
        $middleware = $this->middleware;
        foreach ($middleware as $filter) {
            if (!$filter instanceof RequestFilter) {
                continue;
            }

            $response = (yield $filter->doFilter($this->request, $this->context));
            if (null !== $response) {
                yield $response;
                return;
            }
        }
    }

    public function handleHttpException($e = null)
    {
        try {
            $handlerChain = array_filter($this->middleware, function ($v) {
                return $v instanceof ExceptionHandler;
            });
            yield RequestExceptionHandlerChain::getInstance()->handle($e, $handlerChain);
        } catch (\Throwable $t) {
            echo_exception($t);
        } catch (\Exception $e) {
            echo_exception($e);
        }
    }

    public function handleException(\Exception $e)
    {
        $middleware = $this->middleware;

        foreach ($middleware as $filter) {
            if (!$filter instanceof ExceptionHandler) {
                continue;
            }

            try {
                $e = (yield $filter->handle($e));
            } catch (\Throwable $t) {
                yield t2ex($t);
                return;
            } catch (\Exception $handlerException) {
                yield $handlerException;
                return;
            }
        }
        yield $e;
    }

    public function executePostFilters($response)
    {
        $middleware = $this->middleware;
        foreach ($middleware as $filter) {
            if (!$filter instanceof RequestPostFilter) {
                continue;
            }
            yield $filter->postFilter($this->request, $response, $this->context);
        }
    }

    public function executeTerminators($response)
    {
        try {
            $middleware = $this->middleware;
            foreach ($middleware as $filter) {
                if (!$filter instanceof RequestTerminator) {
                    continue;
                }
                yield $filter->terminate($this->request, $response, $this->context);
            }
        } catch (\Throwable $t) {
            echo_exception($t);
        } catch (\Exception $e) {
            echo_exception($e);
        }
    }

    private function initMiddleware()
    {
        $middleware = [];
        $groupValues = $this->middlewareConfig->getRequestFilters($this->request);
        $groupValues = $this->middlewareConfig->addExceptionHandlers($this->request, $groupValues);
        $groupValues = $this->middlewareConfig->addBaseFilters($groupValues);
        $groupValues = $this->middlewareConfig->addBaseTerminators($groupValues);
        foreach ($groupValues as $groupValue) {
            $objectName = $this->getObject($groupValue);
            $obj = new $objectName();
            $middleware[$objectName] = $obj;
        }
        $this->middleware = $middleware;
    }

    private function getObject($objectName)
    {
        return $objectName;
    }
}
