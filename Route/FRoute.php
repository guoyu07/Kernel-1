<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午3:11
 */

namespace Kernel\Route;

use Kernel\CoreBase\SwooleException;
use FastRoute;

class FRoute implements IRoute
{
    private $client_data;
    public $router = null;
    public $routes = [];
    public $routeAlias = [];
    public $middleware = [];

    public function __construct()
    {
        $this->client_data = new \stdClass();
        $this->initialize();
    }




    public function initialize()
    {
        $this->router = $this->createRouter();
        $this->registerRoutes();
        // $this->registerMiddleware();
    }



    /**
     * FastRoute
     * @return FastRoute
     */
    protected function createRouter()
    {
        return getInstance()->container->make(
            'FastRoute\RouteCollector',
            [
                new FastRoute\RouteParser\Std(),
                new FastRoute\DataGenerator\GroupCountBased()
            ]
        );
    }



    public function getDispatcher()
    {
        return getInstance()->container->make(
            'FastRoute\Dispatcher\GroupCountBased',
            [
                $this->router->getData()
            ]
        );
    }






    /**
     * Middleware.php
     * @return Middleware
     */
    protected function registerMiddleware()
    {
        $files = glob(MIDDLEWARE_PATH.DS."*.middleware.php");
        foreach ($files as $file) {
            $middleware = is_file($file) ? include_once $file : array();
            $this->middleware = array_merge($this->middleware, $middleware);
        }
    }




    /**
     * 解析middleware
     * @param  string $router
     * @return middleware or bool
     */
    public function dispatchMiddleware($router)
    {
        if (!isset($this->middleware[$router])) {
            return false;
        }
        return $this->middleware[$router];
    }




    /**
     * route.php
     * @return routes
     */
    protected function registerRoutes()
    {
        $files = glob(ROUTE_PATH.DS."*.route.php");
        foreach ($files as $file) {
            $routes = is_file($file) ? include_once $file : array();
            $this->routes = array_merge($this->routes, $routes);
        }
        foreach ($this->routes as $value) {
            if (!is_array($value[0]) && is_array($value[1])) {
                $this->group($value[0], $value[1]);
            } else {
                $this->route($value[0], $value[1], $value[2], $value[3]);
            }
        }
    }



    /**
     * FastRoute addRoute
     * @param  string $method
     * @param  string $route
     * @param  string $handler
     * @return
     */
    protected function route($method, $route, $handler, $routeAlias)
    {
        $this->router->addRoute($method, $route, $handler);
        $this->routeAlias[$routeAlias] = $route;
        return $this;
    }
    /**
     * FastRoute addRoute
     * @param  string $method
     * @param  string $route
     * @param  string $handler
     * @return
     */
    protected function group($group, $routes)
    {
        $this->router->addGroup($group, function (FastRoute\RouteCollector $router) use ($group, $routes) {
            foreach ($routes as list($method, $route, $handler,$routeAlias)) {
                $router->addRoute($method, $route, $handler);
                $this->routeAlias[$routeAlias] = $group.$route;
            }
        });
        return $this;
    }

    /**
     * 设置反序列化后的数据 Object
     * @param $data
     * @return \stdClass
     * @throws SwooleException
     */
    public function handleClientData($data)
    {
        $this->client_data = $data;
        if (isset($this->client_data->controller_name) && isset($this->client_data->method_name)) {
            return $this->client_data;
        } else {
            throw new SwooleException('route 数据缺少必要字段');
        }
    }

    /**
     * 处理http request
     * @param $request
     */
    public function handleClientRequest($request)
    {
        // $this->client_data->path = $request->server['path_info'];
        // $route = explode('/', $request->server['path_info']);
        // $count = count($route);
        // if ($count == 2) {
        //     $this->client_data->controller_name = $route[$count - 1] ?? null;
        //     $this->client_data->method_name = null;
        //     return;
        // }
        // $this->client_data->method_name = $route[$count - 1] ?? null;
        // unset($route[$count - 1]);
        // unset($route[0]);
        // $this->client_data->controller_name = implode("\\", $route);




        $requestUri = $request->server['request_uri'];
        $requestMethod = $request->server['request_method'];
        if (false !== $pos = strpos($requestUri, '?')) {
            $requestUri = substr($requestUri, 0, $pos);
        }
        $requestUri = rawurldecode($requestUri);
        $info = $this->getDispatcher()->dispatch($requestMethod, $requestUri);
        switch ($info[0]) {
            case FastRoute\Dispatcher::NOT_FOUND://0
                throw new SwooleException('没有找到路由404');
                break;
            case FastRoute\Dispatcher::METHOD_NOT_ALLOWED://2
                throw new SwooleException('没有权限访问403');
                break;
            case FastRoute\Dispatcher::FOUND:
                $route = explode('@', $info[1]);

                $this->client_data->path = $requestUri;

                $this->client_data->controller_name = $route[0];
                $this->client_data->method_name = $route[1];
                // return [$info[1],$info[2]];
                break;
        }
    }

    /**
     * 获取控制器名称
     * @return string
     */
    public function getControllerName()
    {
        return $this->client_data->controller_name;
    }

    /**
     * 获取方法名称
     * @return string
     */
    public function getMethodName()
    {
        return $this->client_data->method_name;
    }

    public function getPath()
    {
        return $this->client_data->path ?? "";
    }

    public function getParams()
    {
        return $this->client_data->params??null;
    }

    public function errorHandle(\Exception $e, $fd)
    {
        getInstance()->send($fd, "Error:" . $e->getMessage(), true);
        getInstance()->close($fd);
    }

    public function errorHttpHandle(\Exception $e, $request, $response)
    {
        //重定向到404
        $response->status(302);
        $location = 'http://' . $request->header['host'] . "/" . '404';
        $response->header('Location', $location);
        $response->end('');
    }
}
