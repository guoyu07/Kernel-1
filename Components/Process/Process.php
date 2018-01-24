<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-15
 * Time: 上午9:41
 */

namespace Kernel\Components\Process;

use Kernel\Components\Event\EventDispatcher;
use Kernel\Coroutine\Coroutine;
use Kernel\SwooleMarco;

abstract class Process extends ProcessRPC
{
    public $process;
    public $worker_id;
    protected $config;
    protected $log;
    protected $token = 0;
    /**
     * 协程支持
     * @var bool
     */
    protected $coroutine_need = true;

    /**
     * Process constructor.
     * @param $name
     * @param $worker_id
     * @param bool $coroutine_need
     */
    public function __construct($name, $worker_id, $coroutine_need = true)
    {
        parent::__construct();
        $this->name = $name;
        $this->worker_id = $worker_id;
        $this->coroutine_need = $coroutine_need;
        $this->process = new \swoole_process([$this, '__start'], false, 2);
        $this->config = getInstance()->config;
        $this->log = getInstance()->log;
        getInstance()->server->addProcess($this->process);
    }

    public function __start($process)
    {
        \swoole_process::signal(SIGTERM, [$this, "__shutDown"]);

        getInstance()->workerId = $this->worker_id;
        if (!isDarwin()) {
            $process->name($this->name);
        }
        swoole_event_add($process->pipe, [$this, 'onRead']);
        getInstance()->server->worker_id = $this->worker_id;
        getInstance()->server->taskworker = false;
        if ($this->coroutine_need) {
            //协成支持
            Coroutine::init();
            Coroutine::startCoroutine([$this, 'start'], [$process]);
        } else {
            $this->start($process);
        }
    }


    /**
     * @param $process
     */
    abstract public function start($process);

    /**
     * 关服处理
     */
    public function __shutDown()
    {
        $this->onShutDown();
        secho("Process:$this->worker_id", get_class($this) . "关闭成功");
        exit();
    }

    abstract protected function onShutDown();
    /**
     * onRead
     */
    public function onRead()
    {
        $recv = \swoole_serialize::unpack($this->process->read(64 * 1024));
        $this->readData($recv);
    }

    /**
     * @param $data
     */
    public function readData($data)
    {
        $message = $data['message'];
        switch ($data['type']) {
            case SwooleMarco::PROCESS_RPC:
                $this->processPpcRun($message);
                break;
            case SwooleMarco::PROCESS_RPC_RESULT:
                EventDispatcher::getInstance()->dispatch($message['token'], $message['result'], true);
                break;
        }
    }

    /**
     * 执行外部命令
     * @param $path
     * @param $params
     */
    protected function exec($path, $params)
    {
        $this->process->exec($path, $params);
    }
}
