<?php

namespace Kernel\Server\Http;

use Kernel\Server\Server;
use Kernel\Config\Config;

/**
 *
 */
class HttpServer extends Server
{

    public function __construct($key)
    {
        $this->config = $key;
        $this->serviceName = Config::get($this->config_key.'.server_name');
        $this->swooleType = Server::TYPE_HTTP;

        parent::__construct();
    }
}
