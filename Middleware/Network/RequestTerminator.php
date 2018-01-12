<?php

namespace Kernel\Middleware\Network;

use Kernel\Contracts\Network\Request;
use Kernel\Contracts\Network\Response;
use Kernel\Coroutine\Context;

interface RequestTerminator
{
    /**
     * @param Request $request
     * @param Response $response
     * @param Context $context
     * @return void
     */
    public function terminate(Request $request, Response $response, Context $context);
}
