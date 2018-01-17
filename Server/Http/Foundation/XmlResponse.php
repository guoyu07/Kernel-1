<?php

namespace Kernel\Server\Http\Foundation;

/**
 *
 */
class XmlResponse extends Response
{

    public function __construct($content = '', $status = 200, $headers = array())
    {
        parent::__construct($content, $status, $headers);
        $this->setHeader('Content-Type', 'application/xml; charset=utf-8');
    }
}
