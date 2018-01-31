<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-15
 * Time: 上午9:41
 */

namespace Kernel\Components\Process;

use Kernel\Memory\Pool;

/**
 * 进程管理
 * Class ProcessManager
 * @package Kernel\Components\Process
 */
class ProcessManager
{
    /**
     * @var ProcessManager
     */
    protected static $instance;
    protected $atomic;
    protected $map = [];
    public $oneWayFucName = [];
    /**
     * ProcessManager constructor.
     */
    public function __construct()
    {
        $this->atomic = new \swoole_atomic();
    }

    /**
     * @param $class_name
     * @param bool $needCoroutine
     * @param string $name
     * @return Process
     * @throws \Exception
     */
    public function addProcess($class_name, $needCoroutine = true, $name = '')
    {
        $worker_id = getInstance()->worker_num + getInstance()->task_num + $this->atomic->get();
        $this->atomic->add();
        $names = explode("\\", $class_name);
        $process = new $class_name(getServerName() . "-" . $names[count($names) - 1], $worker_id, $needCoroutine);
        if (array_key_exists($class_name . $name, $this->map)) {
            throw new \Exception('存在相同类型的进程，需要设置别名');
        }
        $this->map[$class_name . $name] = $process;
        return $process;
    }

    /**
     * @return ProcessManager
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new ProcessManager();
        }
        return self::$instance;
    }

    /**
     * @param $class_name
     * @param bool|string $oneWay
     * @param string $name
     * @return mixed
     * @throws \Exception
     */
    public function getRpcCall($class_name, $oneWay = 'auto', $name = '')
    {
        if (!array_key_exists($class_name . $name, $this->map)) {
            throw new \Exception("不存在$class_name 进程");
        }
        return Pool::getInstance()->get(RPCCall::class)->init($this->map[$class_name . $name], $oneWay);
    }

    /**
     * 通过wokerId发起RPC
     * @param $wokerId
     * @param bool|string $oneWay
     * @return mixed
     */
    public function getRpcCallWorker($wokerId, $oneWay = 'auto')
    {
        return Pool::getInstance()->get(RPCCall::class)->initworker($wokerId, $oneWay);
    }

    /**
     * @param $class_name
     * @param $name
     * @return ProcessRPC
     * @throws \Exception
     */
    public function getProcess($class_name, $name = '')
    {
        if (!array_key_exists($class_name . $name, $this->map)) {
            throw new \Exception("不存在$class_name 进程");
        }
        return $this->map[$class_name . $name];
    }

    /**
     * @param $workerId
     * @return Process
     */
    public function getProcessFromID($workerId)
    {
        foreach ($this->map as $process) {
            if ($process->worker_id == $workerId) {
                return $process;
            }
        }
        return null;
    }
}
