<?php

namespace Kernel\Server\Http\Foundation;

/**
 *
 */
class ImageResponse extends Response
{

    public function __construct($content = '', $status = 200, $headers = array(), $type = 'jpeg')
    {
        parent::__construct($content, $status, $headers);
        $this->setHeader('Content-Type', 'image/'.$type);
    }
}
