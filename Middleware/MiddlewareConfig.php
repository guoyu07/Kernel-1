<?php

namespace Kernel\Middleware;

use InvalidArgumentException;
// use Kernel\Contracts\Network\Request;
use Kernel\Server\Http\Foundation\Request\Request;

class MiddlewareConfig
{


    private $config = null;
    private $exceptionHandlerConfig = [];
    private $zanFilters = [];
    private $zanTerminators = [];


    /**
     * @var static
     */
    private static $_instance = null;

    /**
     * @return static
     */
    public static function instance()
    {
        return static::singleton();
    }

    public static function singleton()
    {
        if (null === static::$_instance) {
            static::$_instance = new static();
        }
        return static::$_instance;
    }

    /**
     * @return static
     */
    public static function getInstance()
    {
        return static::singleton();
    }

    public static function swap($instance)
    {
        static::$_instance = $instance;
    }

    public function setConfig($config)
    {
        $this->config = $config;
    }

    public function setExceptionHandlerConfig(array $exceptionHandlerConfig)
    {
        $this->exceptionHandlerConfig = $exceptionHandlerConfig;
    }

    public function getExceptionHandlerConfig()
    {
        return $this->exceptionHandlerConfig;
    }

    public function setZanFilters(array $zanFilters)
    {
        $this->zanFilters = $zanFilters;
    }

    public function setZanTerminators(array $zanTerminators)
    {
        $this->zanTerminators = $zanTerminators;
    }

    public function getGroupValue(Request $request, $config)
    {
        $genericRoute = null;

        $route = $request->getRoute();
        $groupKey = null;

        for ($i = 0;; $i++) {
            if (!isset($config['match'][$i])) {
                break;
            }
            $match = $config['match'][$i];
            $pattern = $this->setDelimit($match[0]);
            if ($this->match($pattern, $route)) {
                $groupKey = $match[1];
                break;
            }

            if (!empty($genericRoute) && $this->match($pattern, $genericRoute)) {
                $groupKey = $match[1];
                break;
            }
        }

        if (null === $groupKey) {
            return [];
        }
        if (!isset($config['group'][$groupKey])) {
            throw new InvalidArgumentException('Invalid Group name in MiddlewareManager, see: http://zanphpdoc.zanphp.io/libs/middleware/filters.html#tcp');
        }

        return $config['group'][$groupKey];
    }

    public function getRequestFilters($request)
    {
        return $this->getGroupValue($request, $this->config);
    }

    public function addExceptionHandlers($request, $filter)
    {
        $exceptionHandlers = $this->getGroupValue($request, $this->exceptionHandlerConfig);
        return array_merge($filter, $exceptionHandlers);
    }

    public function match($pattern, $route)
    {
        if (preg_match($pattern, $route)) {
            return true;
        }
        return false;
    }

    private function setDelimit($pattern)
    {
        return '#' . $pattern . '#i';
    }

    public function addBaseFilters($filters)
    {
        return array_merge($this->zanFilters, $filters);
    }

    public function addBaseTerminators($terminators)
    {
        return array_merge($this->zanTerminators, $terminators);
    }
}
