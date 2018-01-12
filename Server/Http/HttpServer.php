<?php

namespace Kernel\Server\Http;

use Kernel\Server\Server;
use Kernel\Config\Config;
use \Swoole\Http\Server as SwooleHttpServer;
use \Swoole\Server as SwooleServer;
use \Swoole\Http\Request as SwooleHttpRequest;
use \Swoole\Http\Response as SwooleHttpResponse;
use Kernel\Utilities\Arr;
use Kernel\Process\Inotify;
use Kernel\Process\AutoReload;
use Kernel\Utilities\Terminal;
use FastRoute;

/**
 *
 */
class HttpServer extends Server
{


    public $router = null;
    public $routes = [];

    public $dispatcher;

    public function __construct($key)
    {
        $this->config = $key;
        $this->serviceName = Config::get($this->config.'.service_name');
        $this->swooleType = Config::get($this->config.'.swoole_type');

        Config::set($this->config.'.set.log_file', STORAGE_LOG_PATH.DS.$this->serviceName.'.log');
        Config::set($this->config.'.set.pid_file', STORAGE_PID_PATH.DS.$this->serviceName.'.pid');
        Config::set($this->config.'.set.task_tmpdir', STORAGE_TASK_PATH.DS.$this->serviceName);
        Config::set($this->config.'.set.document_root', HTDOCS_PATH);
        Config::set($this->config.'.set.upload_tmp_dir', HTDOCS_PATH.DS.'uploadfiles');
        $set = Config::get('swoole.http');
        $swoole_set = Config::get($this->config.'.set', []);
        Config::set($this->config.'.set', Arr::merge($set, $swoole_set));
        unset($set, $swoole_set);
        parent::__construct();

        $this->initialize();
    }



    public function initialize()
    {
        $this->router = $this->createRouter();
        $this->registerRoutes();
    }

    /**
     * FastRoute
     * @return FastRoute
     */
    protected function createRouter()
    {
        return new FastRoute\RouteCollector(
            new FastRoute\RouteParser\Std(),
            new FastRoute\DataGenerator\GroupCountBased()
        );
    }


    /**
     * @return FastRoute\Dispatcher\GroupCountBased
     */
    public function getDispatcher()
    {
        if ($this->dispatcher === null) {
            $this->dispatcher = new FastRoute\Dispatcher\GroupCountBased($this->router->getData());
        }

        return $this->dispatcher;
    }

    /**
     * route.php
     * @return routes
     */
    protected function registerRoutes()
    {

        $files = glob(ROUTE_PATH.DS."*.route.php");
        // print_r($files);
        foreach ($files as $file) {
            $routes = is_file($file) ? include_once $file : array();
            $this->routes = array_merge($this->routes, $routes);
        }
        // print_r($this->routes);


        foreach ($this->routes as $value) {
            if (!is_array($value[0]) && is_array($value[1])) {
                $this->group($value[0], $value[1]);
            } else {
                $this->route($value[0], $value[1], $value[2]);
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
    protected function route($method, $route, $handler)
    {
        $this->router->addRoute($method, $route, $handler);

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
        $this->router->addGroup($group, function (FastRoute\RouteCollector $router) use ($routes) {
            foreach ($routes as list($method, $route, $handler)) {
                $router->addRoute($method, $route, $handler);
            }
        });

        return $this;
    }

    /**
     * FastRoute dispatch
     * @param  $request
     * @return
     */
    public function dispatch($request)
    {
        $requestUri = $request->server->get('REQUEST_URI');
        $requestMethod = $request->server->get('REQUEST_METHOD');
        if (false !== $pos = strpos($requestUri, '?')) {
            $requestUri = substr($requestUri, 0, $pos);
        }
        $requestUri = rawurldecode($requestUri);
        $info = $this->getDispatcher()->dispatch($requestMethod, $requestUri);
        switch ($info[0]) {
            case FastRoute\Dispatcher::NOT_FOUND://0
                return false;
                break;
            case FastRoute\Dispatcher::METHOD_NOT_ALLOWED://2
                return true;
                break;
            case FastRoute\Dispatcher::FOUND://1
            // $this->context->set('controller_name', $route['controller_name']);
            // $this->context->set('action_name', $route['action_name']);
            // $this->context->set('action_args', $route[1]);
                $r = explode('@', $info[1]);
                $request->setRoute($requestUri);
                return ['controller_name'=>$r[0],'action_name'=>$r[1],'action_args'=>$info[2]];//[$info[1], $info[2]];
                break;
        }
    }


    /**
     * 启动服务函数
     * @return
     */
    public function start()
    {

        $this->swoole = new SwooleHttpServer(Config::get($this->config.'.ip'), Config::get($this->config.'.port'), SWOOLE_PROCESS);

        $this->bind();
        $this->setSwooleConfig();
        if (Config::get($this->config.'.inotify', false)) {
            Inotify::startProcess($this);
        }
        if (Config::get($this->config.'.auto_reload', false)) {
            AutoReload::startProcess($this);
        }
        $this->swoole->start();
    }



    /**
     * 设置 Swoole 服务配置
     */
    public function setSwooleConfig()
    {
        $set = Config::get($this->config.'.set');
        $this->swoole->set($set);
        unset($set);
    }


    /**
     * Server启动在主进程的主线程回调此函数
     * @param  SwooleServer $server
     * @return
     */
    public function onSwooleStart(SwooleServer $swoole)
    {
        Terminal::drawStr(__METHOD__, 'red');
        parent::onSwooleStart($swoole);
    }

    /**
     * Server结束时发生
     * @param  SwooleServer $server
     * @return
     */
    public function onSwooleShutdown(SwooleServer $swoole)
    {
        Terminal::drawStr(__METHOD__, 'red');
        parent::onSwooleShutdown($swoole);
    }




    /**
     * worker进程/task进程启动时发生
     * @param  SwooleServer $swoole
     * @param  int          $worker_id
     * @return
     */
    public function onSwooleWorkerStart(SwooleServer $swoole, int $worker_id)
    {
        parent::onSwooleWorkerStart($swoole, $worker_id);

        //非任务投递进程
        // if (!$swoole->taskworker) {
            // $files = glob(ROUTE_PATH.DS."*.route.php");
            // foreach ($files as $file) {
            //     $data = is_file($file) ? include_once $file : array();
            //     Route::getInstance()->parseGroupRoutes($data);
            // }
            // Route::getInstance()->registerRoutes();
        // }
        Terminal::drawStr(__METHOD__, 'red');
    }



    /**
     * worker进程终止时发生
     * @param  SwooleServer $swoole
     * @param  int          $worker_id
     * @return
     */
    public function onSwooleWorkerStop(SwooleServer $swoole, int $worker_id)
    {
        Terminal::drawStr(__METHOD__, 'red');
        parent::onSwooleWorkerStop($swoole, $worker_id);
    }


    /**
     * 有新的连接进入时，在worker进程中回调
     * @param  SwooleServer $swoole
     * @param  int          $fd
     * @param  int          $from_id
     * @return
     */
    public function onSwooleConnect(SwooleServer $swoole, int $fd, int $from_id)
    {
        Terminal::drawStr(__METHOD__, 'red');
        parent::onSwooleConnect($swoole, $fd, $from_id);
    }

    /**
     * TCP客户端连接关闭后，在worker进程中回调此函数
     * @param  SwooleServer $swoole
     * @param  int          $fd
     * @param  int          $reactor_id
     * @return
     */
    public function onSwooleClose(SwooleServer $swoole, int $fd, int $reactor_id)
    {
        Terminal::drawStr(__METHOD__, 'red');
        parent::onSwooleClose($swoole, $fd, $reactor_id);
    }


    /**
     * task_worker进程内被调用
     * @param  SwooleServer $serv
     * @param  int          $task_id
     * @param  int          $src_worker_id
     * @param  mixed        $data
     * @return
     */
    public function onSwooleTask(SwooleServer $swoole, int $task_id, int $src_worker_id, $data)
    {
        parent::onSwooleTask($swoole, $task_id, $src_worker_id, $data);
        // Terminal::drawStr(__METHOD__, 'red');
        // $adapter = Arr::get($data, 'adapter', null);
        // if ($adapter) {
        //     $classAry = explode('@', $adapter);
        //     $handle = str_replace('/', '\\', $classAry[0]);
        //     $func = $classAry[1];
        //     $class = new $handle();
        //     return call_user_func_array(array($class, $func), $data['params']);
        // }
        return false;
    }
    /**
     * 当worker进程投递的任务在task_worker中完成时
     * @param  SwooleServer $swoole
     * @param  int          $task_id
     * @param  string       $data
     * @return
     */
    public function onSwooleFinish(SwooleServer $swoole, int $task_id, $data)
    {
        parent::onSwooleFinish($swoole, $task_id, $data);
        Terminal::drawStr(__METHOD__, 'red');
        Terminal::drawStr("$task_id task finish", 'red');
    }



    /**
     * 当工作进程收到由sendMessage发送的管道消息时会触发PipeMessage事件。worker/task进程都可能会触发PipeMessage事件
     * @param  SwooleServer $swoole
     * @param  int          $from_worker_id
     * @param  string       $message
     * @return
     */
    public function onSwoolePipeMessage(SwooleServer $swoole, int $from_worker_id, string $message)
    {
        Terminal::drawStr(__METHOD__, 'red');
        parent::onSwoolePipeMessage($swoole, $from_worker_id, $message);
    }



    /**
     * 当worker/task_worker进程发生异常后会在Manager进程内回调此函数
     * @param  SwooleServer $swoole
     * @param  int          $worker_id
     * @param  int          $worker_pid
     * @param  int          $exit_code
     * @param  int          $signal
     * @return
     */
    public function onSwooleWorkerError(SwooleServer $swoole, int $worker_id, int $worker_pid, int $exit_code, int $signal)
    {
        Terminal::drawStr(__METHOD__, 'red');
        parent::onSwooleWorkerError($swoole, $worker_id, $worker_pid, $exit_code, $signal);
    }



    /**
     * 当管理进程启动时调用它
     * @param  SwooleServer $swoole
     * @return
     */
    public function onSwooleManagerStart(SwooleServer $swoole)
    {
        Terminal::drawStr(__METHOD__, 'red');
        parent::onSwooleManagerStart($swoole);
    }


    /**
     * 当管理进程结束时调用它
     * @param  SwooleServer $swoole
     * @return
     */
    public function onSwooleManagerStop(SwooleServer $swoole)
    {
        Terminal::drawStr(__METHOD__, 'red');
        parent::onSwooleManagerStop($swoole);
    }


    /**
     * Http 回调函数
     * @param  SwooleHttpRequest  $request
     * @param  SwooleHttpResponse $response
     * @return
     */
    public function onSwooleRequest(SwooleHttpRequest $swooleHttpRequest, SwooleHttpResponse $swooleHttpResponse)
    {
        (new RequestHandler($this))->handle($swooleHttpRequest, $swooleHttpResponse);

        // $request = Request::createFromSwooleHttpRequest($swooleHttpRequest);
        // var_dump($request);
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
