<?php

namespace Kernel\Server\Http\Foundation;

/**
 *
 */
class JsonResponse extends Response
{

    public function __construct($content = '', $status = 200, $headers = array())
    {
        parent::__construct($content, $status, $headers);
        $this->setHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
