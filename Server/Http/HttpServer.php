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

/**
 *
 */
class HttpServer extends Server
{

    public function __construct($key)
    {
        $this->config = $key;
        $this->serviceName = Config::get($this->config.'.server_name');
        $this->swooleType = Config::get($this->config.'.swoole_type');

        Config::set($this->config.'.set.log_file', STORAGE_LOG_PATH.DS.$this->serviceName.'.log');
        Config::set($this->config.'.set.pid_file', STORAGE_PID_PATH.DS.$this->serviceName.'.pid');
        Config::set($this->config.'.set.task_tmpdir', STORAGE_TASK_PATH.DS.$this->serviceName);
        Config::set($this->config.'.set.document_root', HTDOCS_PATH);
        Config::set($this->config.'.set.upload_tmp_dir', HTDOCS_PATH.DS.'uploadfiles');
		$set = Config::get('swoole.http');
		$swoole_set = Config::get($this->config.'.set',[]);
        Config::set($this->config.'.set',,Arr::merge($set, $swoole_set));
		unset($set,$swoole_set);
        parent::__construct();
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
}
