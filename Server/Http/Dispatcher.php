<?php

namespace Kernel\Server\Http;

use Kernel\Server\Http\Foundation\Request\Request;
use Kernel\Coroutine\Context;

class Dispatcher
{
    public function dispatch(Request $request, Context $context)
    {
        $controllerName = $context->get('controller_name');
        $action = $context->get('action_name');
        $args   = $context->get('action_args');

        if ($args == null) {
            $args = [];
        }

        $controller = $this->getControllerClass($controllerName);
        if (!class_exists($controller)) {
            throw new \Exception("controller:{$controller} not found");
        }

        $controller = new $controller($request, $context);
        if (!is_callable([$controller, $action])) {
            throw new \Exception("action:{$action} is not callable in controller:" . get_class($controller));
        }
        yield $controller->$action(...array_values($args));
    }

    private function getControllerClass($controllerName)
    {
        return $controllerName;
    }
}
