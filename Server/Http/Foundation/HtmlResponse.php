<?php

namespace Kernel\Server\Http\Foundation;

/**
 *
 */
class HtmlResponse extends Response
{

    public function __construct($content = '', $status = 200, $headers = array())
    {
        parent::__construct($content, $status, $headers);
        $this->setHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
