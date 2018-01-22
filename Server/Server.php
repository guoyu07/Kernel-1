<?php
/**
 * SwooleServer
 *
 */
namespace Kernel\Server;

use Kernel\Config\Config;
use Kernel\Log\Logger;
use Kernel\Utilities\Terminal;

use \Swoole\Server as SwooleServer;
use \Swoole\Http\Request as SwooleHttpRequest;
use \Swoole\Http\Response as SwooleHttpResponse;
use \Swoole\Websocket\Server as SwooleWebsocketServer;
use \Swoole\Websocket\Frame as SwooleWebsocketFrame;
use Kernel\Container\Container;

use Kernel\Async\Pool\MysqlPool;
use Kernel\Async\Pool\RedisPool;

// use Kernel\Pool\Pool;

abstract class Server
{
    const TYPE_TCP = 'tcp';
    const TYPE_UDP = 'udp';
    const TYPE_HTTP = 'http';
    const TYPE_WEBSOCKET = 'websocket';
    /**
     * 服务名称
     * @var string
     */
    public $serviceName;
    /**
     * 实例化对象
     * @var  object
     */
    protected static $instance;
    /**
     * 启动时间
     * @var int
     */
    public $startTime;
    /**
     * 启动时间，含毫秒
     * @var float
     */
    public $startTimeFloat;
    /**
     * PID文件路径
     * @var string
     */
    public $pidFilePath;
    /**
     * 主进程进程号
     * @var integer
     */
    public $masterPid = 0;
    /**
     * 管理进程号
     * @var integer
     */
    public $managerPid = 0;
    /**
     * 监控
     * @var monitor
     */
    public $monitor;
    /**
     * swoole 对象
     * @var
     */
    public $swoole;
    /**
     * 该服务的 swoole 配置信息
     */
    public $config;
    /**
     * 区分 tcp,udp,http,WebSocket
     * @var string
     */
    public $swooleType;

    /**
     * 容器
     * @var  obj
     */
    public $container;

    /**
     * @var int 进程类型
     */
    public $processType = Marco::PROCESS_MASTER;

    /**
     * @var array 连接池
     */
    protected $redisPools = [];
    protected $mysqlPools = [];


    /**
     * Server constructor.
     *
     * @throws Exception
     */
    public function __construct()
    {
        // 检查系统配置
        $this->checkSystem();
        static::setInstance($this);
        $this->setTimezone();
        $this->container = new Container;
        $this->startTimeFloat = microtime(1);
        $this->startTime      = time();
        $this->pidFilePath = STORAGE_PID_PATH.DS.$this->serviceName.'.pid';
        $this->masterPid = ServerPid::getMasterPid($this->pidFilePath);
        $this->managerPid = ServerPid::getManagerPid($this->pidFilePath);
        ServerPid::init($this->pidFilePath);
        $this->monitor = new Monitor($this->serviceName, $this->pidFilePath);

        //注册连接池
        $this->initAsynPool();
    }


    /**
     * 注册连接池
     * @return
     */
    public function initAsynPool()
    {
        $redisPools = [];
        $redisPoolsConf = Config::get('store.redis');
        $redisActivePools = array_keys($redisPoolsConf);
        foreach ($redisActivePools as $poolKey) {
            $redisPools[RedisPool::ASYN_NAME . $poolKey] = new RedisPool($redisPoolsConf, $poolKey);
        }


        $mysqlPools = [];
        $mysqlPoolsConf = Config::get('store.mysql');
        $mysqlActivePools = array_keys($mysqlPoolsConf);
        foreach ($mysqlActivePools as $poolKey) {
            $mysqlPools[MysqlPool::ASYN_NAME . $poolKey] = new MysqlPool($mysqlPoolsConf, $poolKey);
        }

        $this->redisPools = $redisPools;
        $this->mysqlPools = $mysqlPools;
    }





    /**
     * 设置时区
     * @return void
     */
    public function setTimezone()
    {
        $timeZone = Config::get('common.timezone', 'Asia/Shanghai');
        \date_default_timezone_set($timeZone);
    }
    /**
     * 检查系统配置
     * @return  void
     */
    protected function checkSystem()
    {

        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cgi-fcgi') {
            exit("必须命令行启动本服务");
        }
        if (!defined('SWOOLE_VERSION')) {
            exit("必须安装 swoole 插件, see http://www.swoole.com/");
        }
        if (!class_exists('\\Swoole\\Server', false)) {
            exit("你没有开启 swoole 的命名空间模式, 请修改 ini 文件增加 swoole.use_namespace = true 参数. \n 操作方式: 先执行 php --ini 看 swoole 的扩展配置在哪个文件, 然后编辑对应文件加入即可, 如果没有则加入 php.ini 里");
        }
        if (!function_exists('exec')) {
            exit("需要exec执行命令 ");
        }
    }
    /**
     * 设置数据实例
     * @param  $app
     */
    public static function setInstance($app)
    {
        static::$instance = $app;
    }
    /**
     * 获取实例化
     * @return
     */
    public static function &getInstance()
    {
        return static::$instance;
    }
    
    /**
     * 解析命令行
     * @return  void
     */
    public function run()
    {
        $this->parseCommand();
    }
    /**
     * 命令执行
     * @return vodi
     */
    public function parseCommand()
    {
        global $argv;
        if (empty($argv[1])||!in_array($argv[1], ['stop','start','reload','restart','status','settle'])) {
            Terminal::drawStr("=========================================================================", 'green');
            Terminal::drawStr("Usage: php {$argv[0]} start|stop|reload|restart|status|settle", 'default');
            Terminal::drawStr("=========================================================================", 'green');
            exit;
        } else {
            $command = trim($argv[1]);

            $start_file = $argv[0];

            $master_is_alive = false;
            Terminal::drawStr("Command : Swoole[$start_file] $command ", 'cyan');
            //主进程
            $master_pid = ServerPid::getMasterPid($this->pidFilePath);
            //管理进程
            $manager_pid = ServerPid::getManagerPid($this->pidFilePath);
            $server_name = $this->serviceName;
            if (!$master_pid) {
                $master_pid = exec("ps -ef | grep $server_name:master | grep -v 'grep ' | awk '{print $2}'");
            }
            if (!$manager_pid) {
                $manager_pid = exec("ps -ef | grep $server_name:manager | grep -v 'grep ' | awk '{print $2}'");
            }


            $master_is_alive = $master_pid && @posix_kill($master_pid, 0);

            if ($master_is_alive) {
                if ($command === 'start') {
                    Terminal::drawStr("Swoole[$start_file] already running", 'red');
                    exit;
                }
            } elseif ($command !== 'start') {
                Terminal::drawStr("Swoole[$start_file] not run", 'red');
                exit;
            }

            switch ($command) {
                case 'stop':
                    @unlink($this->pidFilePath);
                    Terminal::drawStr("Swoole[$start_file] is stoping ...", 'green');
                    $master_pid && posix_kill($master_pid, SIGTERM);
                    $timeout = 5;
                    $start_time = time();
                    while (1) {
                        $master_is_alive = $master_pid && posix_kill($master_pid, 0);
                        if ($master_is_alive) {
                            if (time() - $start_time >= $timeout) {
                                Terminal::drawStr("Swoole[$start_file] stop fail", 'red');
                                exit;
                            }
                            usleep(10000);
                            continue;
                        }
                        Terminal::drawStr("Swoole[$start_file] stop success", 'green');
                        break;
                    }
                    exit(0);
                    break;
                case 'reload':
                    posix_kill($manager_pid, SIGUSR1);
                    Terminal::drawStr("Swoole[$start_file] reload", 'green');
                    sleep(2);
                    $this->settle();
                    exit;
                    break;
                case 'start':
                    $this->startSwooles();
                    break;
                case 'status':
                    $this->monitor->outPutNowStatus();
                    break;
                case 'restart':
                    @unlink($this->pidFilePath);
                    Terminal::drawStr("Swoole[$start_file] is stoping ...", 'green');
                    $master_pid && posix_kill($master_pid, SIGTERM);
                    $timeout = 5;
                    $start_time = time();
                    while (1) {
                        $master_is_alive = $master_pid && posix_kill($master_pid, 0);
                        if ($master_is_alive) {
                            if (time() - $start_time >= $timeout) {
                                Terminal::drawStr("Swoole[$start_file] stop fail", 'red');
                                exit;
                            }
                            usleep(10000);
                            continue;
                        }
                        Terminal::drawStr("Swoole[$start_file] stop success", 'green');
                        break;
                    }
                    $this->startSwooles();
                    sleep(2);
                    $this->settle();
                    Terminal::drawStr("Swoole[$start_file] start success", 'green');
                    exit(0);
                    break;
                case 'settle':
                    $this->settle();
                    exit(0);
                    break;
                default:
                    Terminal::drawStr("=========================================================================", 'green');
                    Terminal::drawStr("Usage: php {$argv[0]} start|stop|reload|restart|status", 'default');
                    Terminal::drawStr("=========================================================================", 'green');
                    break;
            }
        }
    }
    /**
     * 重置 pidFilePath 的内容
     * @return  void
     */
    public function settle()
    {
        $this->setTimezone();
        $ps_name = $this->serviceName;
        exec("ps -ef | grep $ps_name | grep -v 'grep' | awk '{print $2,$8}'", $pidList);
        $data = [];
        foreach ($pidList as $key => $item) {
            $tmp = explode(" ", $item);
            if (strpos($tmp[1], 'work') !== false) {
                $data['work'][$tmp[1]] = [
                    'pid' => $tmp[0],
                    'datetime' => date('Y-m-d H:i:s'),
                ];
            } elseif (strpos($tmp[1], 'master') !== false) {
                $data['master'][$tmp[1]] = [
                    'pid' => $tmp[0],
                    'datetime' => date('Y-m-d H:i:s'),
                ];
            } elseif (strpos($tmp[1], 'task') !== false) {
                $data['task'][$tmp[1]] = [
                    'pid' => $tmp[0],
                    'datetime' => date('Y-m-d H:i:s'),
                ];
            } elseif (strpos($tmp[1], 'manager') !== false) {
                $data['manager'][$tmp[1]] = [
                    'pid' => $tmp[0],
                    'datetime' => date('Y-m-d H:i:s'),
                ];
            } else {
            }
        }
        ServerPid::reSavePid($data);
    }
    /**
     * 启动服务
     * @return
     */
    public function startSwooles()
    {
        $this->serviceStartBefore();
        $this->start();
    }
    /**
     * 服务开始前启动函数
     * @return void
     */
    public function serviceStartBefore()
    {
        $dir = dirname($this->pidFilePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        unset($dir);
        //无论 pid 里面有什么,都先清空
        @unlink($this->pidFilePath);
        if (!file_exists($this->pidFilePath)) {
            file_put_contents($this->pidFilePath, '');
        }
        //@TODO: 清理一些目录
    }

    /**
     * 绑定回调函数
     * @return void
     */
    public function bind()
    {
        $this->swoole->on('Start', [$this, 'onSwooleStart']);
        $this->swoole->on('Shutdown', [$this, 'onSwooleShutdown']);
        $this->swoole->on('WorkerStart', [$this, 'onSwooleWorkerStart']);
        $this->swoole->on('WorkerStop', [$this, 'onSwooleWorkerStop']);
        $this->swoole->on('Connect', [$this, 'onSwooleConnect']);
        $this->swoole->on('Close', [$this, 'onSwooleClose']);
        $this->swoole->on('Task', [$this, 'onSwooleTask']);
        $this->swoole->on('Finish', [$this, 'onSwooleFinish']);
        $this->swoole->on('PipeMessage', [$this, 'onSwoolePipeMessage']);
        $this->swoole->on('WorkerError', [$this, 'onSwooleWorkerError']);
        $this->swoole->on('ManagerStart', [$this, 'onSwooleManagerStart']);
        $this->swoole->on('ManagerStop', [$this, 'onSwooleManagerStop']);

        switch ($this->swooleType) {
            case self::TYPE_TCP:
                $this->swoole->on('Receive', [$this, 'onSwooleReceive']);
                break;
            case self::TYPE_HTTP:
                $this->swoole->on('Request', [$this, 'onSwooleRequest']);
                break;
            case self::TYPE_WEBSOCKET:
                $this->swoole->on('Open', [$this, 'onSwooleOpen']);
                $this->swoole->on('Message', [$this, 'onSwooleMessage']);
                $this->swoole->on('Request', [$this, 'onSwooleRequest']);
                break;
            case self::TYPE_UDP:
                $this->swoole->on('Timer', [$this, 'onSwooleTimer']);
                $this->swoole->on('Packet', [$this, 'onSwoolePacket']);
                break;
        }
    }

    /**
     * 设置进程的名称
     *
     * @param $name
     */
    public function setProcessName($name)
    {
        if (function_exists('\cli_set_process_title')) {
            @cli_set_process_title($name);
        } else {
            if (function_exists('\swoole_set_process_name')) {
                @swoole_set_process_name($name);
            } else {
                trigger_error(__METHOD__ .' failed. require cli_set_process_title or swoole_set_process_name.');
            }
        }
    }


    /**
     * Server启动在主进程的主线程回调此函数
     * @param  SwooleServer $server
     * @return
     */
    public function onSwooleStart(SwooleServer $swoole)
    {
        $this->setTimezone();
        Terminal::drawStr(__METHOD__, 'red');
        $processName = $this->serviceName .':master';
        //设置主进程名称
        $this->setProcessName($processName);
        $this->processType = Marco::PROCESS_MASTER;
        //刷新进程文件
        $pidList = ServerPid::makePidList('master', $swoole->master_pid, $processName);
        ServerPid::putPidList($pidList);
        Terminal::drawStr("Create Master Process Name->".$processName, 'green');
    }

    /**
     * Server结束时发生
     * @param  SwooleServer $server
     * @return
     */
    public function onSwooleShutdown(SwooleServer $swoole)
    {
        Terminal::drawStr(__METHOD__, 'red');
        //删除 pid 文件
        if (!empty($this->pidFilePath) && is_file($this->pidFilePath)) {
            @unlink($this->pidFilePath);
        }
        Terminal::drawStr("Service is Shutdown", 'green');
    }


    /**
     * worker进程/task进程启动时发生
     * @param  SwooleServer $swoole
     * @param  int          $worker_id
     * @return
     */
    public function onSwooleWorkerStart(SwooleServer $swoole, int $worker_id)
    {

        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
        }

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        $this->setTimezone();
        Terminal::drawStr(__METHOD__, 'red');
        $processName = $this->serviceName;

        $key = $this->config.'.set.worker_num';
        $workNum = Config::get($key);
        if ($swoole->taskworker) {
            //启动 task 投递任务进程
            $taskId = $swoole->worker_id - $workNum;
            $taskProcessName = $processName. ":task-num-:{$taskId}";
            $this->setProcessName($taskProcessName);
            $pidList = ServerPid::makePidList('task', $swoole->worker_pid, $taskProcessName);
            Terminal::drawStr("Create Task Process Name->".$processName. "-task-num:{$taskId}", 'green');

            $this->processType = Marco::PROCESS_TASKER;
        } else {
            //启动 worker 进程
            $workerProcessName = $processName. ":work-num-:{$swoole->worker_id}";
            $this->setProcessName($workerProcessName);
            $pidList = ServerPid::makePidList('work', $swoole->worker_pid, $workerProcessName);
            Terminal::drawStr("Create Work Process Name->".$processName. "work-num-:{$swoole->worker_id}", 'green');

            $this->processType = Marco::PROCESS_WORKER;
        }






        ServerPid::putPidList($pidList);
    }


    /**
     * 包装SerevrMessageBody消息
     * @param $type
     * @param $message
     * @return string
     */
    public function packSerevrMessageBody($type, $message)
    {
        $data['type'] = $type;
        $data['message'] = $message;
        return serialize($data);
    }



    /**
     * worker进程终止时发生
     * @param  SwooleServer $swoole
     * @param  int          $worker_id
     * @return
     */
    public function onSwooleWorkerStop(SwooleServer $swoole, int $worker_id)
    {
        $type = !empty($swoole->taskworker)?'task':'work';
        Terminal::drawStr(__METHOD__.'===='.$type.'=='.$swoole->worker_pid, 'red');
        //删除进程存储中的 pid
        // ServerPid::delPidList($type, $swoole->worker_pid);
    }
    /**
     * 定时器触发
     * @param  SwooleServer $swoole
     * @param  int          $worker_id
     * @return
     */
    public function onSwooleTimer(SwooleServer $swoole, int $worker_id)
    {
        Terminal::drawStr(__METHOD__, 'red');
    }
    /**
     * 有新的连接进入时，在worker进程中回调
     * @param  SwooleServer $swoole
     * @param  int          $fd
     * @param  int          $reactor_id
     * @return
     */
    public function onSwooleConnect(SwooleServer $swoole, int $fd, int $reactor_id)
    {
        Terminal::drawStr(__METHOD__, 'red');
    }
    /**
     * 接收到数据时回调此函数，发生在worker进程中
     * @param  SwooleServer $swoole
     * @param  int          $fd
     * @param  int          $reactor_id
     * @param  string       $data
     * @return
     */
    public function onSwooleReceive(SwooleServer $swoole, int $fd, int $reactor_id, string $data)
    {
        Terminal::drawStr(__METHOD__, 'red');
    }
    /**
     * 接收到UDP数据包时回调此函数，发生在worker进程中
     * @param  SwooleServer $swoole
     * @param  string       $data
     * @param  array        $client_info
     * @return
     */
    public function onSwoolePacket(SwooleServer $swoole, string $data, array $client_info)
    {
        Terminal::drawStr(__METHOD__, 'red');
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
        $this->setTimezone();
        Terminal::drawStr(__METHOD__, 'red');
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
        $key = $this->config.'.set.worker_num';
        $workNum = Config::get($key);
        $type = $worker_pid>=$workNum?'task':'work';
        $data = [
            'worker_id' => $worker_id,
            'worker_pid' => $worker_pid,
            'exit_code' => $exit_code,
            'signal'=>$signal
        ];

        Logger::getInstance()->log('error', 'swoole', $data);
    }
    /**
     * 当管理进程启动时调用它
     * @param  SwooleServer $swoole
     * @return
     */
    public function onSwooleManagerStart(SwooleServer $swoole)
    {
        $this->setTimezone();
        //创建管理进程
        Terminal::drawStr(__METHOD__, 'red');
        $processName = $this->serviceName .':manager';
        $this->setProcessName($processName);
        $pidList = ServerPid::makePidList('manager', $swoole->manager_pid, $processName);
        ServerPid::putPidList($pidList);

        Terminal::drawStr("Create Manage Process Name->".$processName, 'green');

        $this->processType = Marco::PROCESS_MANAGER;
    }
    /**
     * 当管理进程结束时调用它
     * @param  SwooleServer $swoole
     * @return
     */
    public function onSwooleManagerStop(SwooleServer $swoole)
    {
        Terminal::drawStr(__METHOD__, 'red');
    }
    /**
     * Http 回调函数
     * @param  SwooleHttpRequest  $request
     * @param  SwooleHttpResponse $response
     * @return
     */
    public function onSwooleRequest(SwooleHttpRequest $request, SwooleHttpResponse $response)
    {
        Terminal::drawStr(__METHOD__, 'red');
    }
    /**
     * WebSocket建立连接后进行握手。WebSocket服务器已经内置了handshake，如果用户希望自己进行握手处理，可以设置onHandShake事件回调函数
     * @param  SwooleHttpRequest  $request
     * @param  SwooleHttpResponse $response
     * @return
     */
    public function onSwooleHandShake(SwooleHttpRequest $request, SwooleHttpResponse $response)
    {
        Terminal::drawStr(__METHOD__, 'red');
    }
    /**
     * 当WebSocket客户端与服务器建立连接并完成握手后会回调此函数
     * @param  SwooleWebsocketServer $swoole
     * @param  SwooleHttpRequest     $request
     * @return
     */
    public function onSwooleOpen(SwooleWebsocketServer $swoole, SwooleHttpRequest $request)
    {
        Terminal::drawStr(__METHOD__, 'red');
    }
    /**
     * 当服务器收到来自客户端的数据帧时会回调此函数
     * @param  SwooleServer         $swoole
     * @param  SwooleWebsocketFrame $frame
     * @return
     */
    public function onSwooleMessage(SwooleServer $swoole, SwooleWebsocketFrame $frame)
    {
        Terminal::drawStr(__METHOD__, 'red');
    }



    /**
     * 判断这个fd是不是一个WebSocket连接，用于区分tcp和websocket
     * 握手后才识别为websocket
     * @param $fd
     * @return bool
     * @throws \Exception
     */
    public function isWebSocket($fd)
    {
        $fdinfo = $this->swoole->connection_info($fd);
        if (empty($fdinfo)) {
            throw new \Exception('fd not exist');
        }
        if (array_key_exists('websocket_status', $fdinfo) && $fdinfo['websocket_status'] == WEBSOCKET_STATUS_FRAME) {
            return true;
        }
        return false;
    }



    /**
     * 输入 Pid
     * @param  array $pidList
     * @return void
     */
    // public function ServerPid::putPidList($pidList)
    // {
    //     $pidList = empty($pidList)?[]:$pidList;
    //     ServerPid::ServerPid::putPidList($pidList);
    // }
}
