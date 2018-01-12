<?php

namespace Kernel\Middleware\Network;

use Kernel\Contracts\Network\Request;
use Kernel\Coroutine\Context;

interface RequestFilter
{
    /**
     * @param Request $request
     * @param Context $context
     * @return \Zan\Framework\Contract\Network\Response
     */
    public function doFilter(Request $request, Context $context);
}
