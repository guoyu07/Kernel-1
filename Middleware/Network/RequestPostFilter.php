<?php

namespace Kernel\Middleware\Network;

use Kernel\Contracts\Network\Request;
use Kernel\Contracts\Network\Response;
use Kernel\Coroutine\Context;

interface RequestPostFilter
{
    /**
     * @param Request $request
     * @param Response $response
     * @param Context $context
     * @return void
     */
    public function postFilter(Request $request, Response $response, Context $context);
}
