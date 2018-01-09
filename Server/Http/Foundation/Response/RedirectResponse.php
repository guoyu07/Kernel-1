<?php

namespace Kernel\Server\Http\Foundation\Response;

use Kernel\Contracts\Http\ResponseTrait;
use Kernel\Contracts\Network\Response as ResponseContract;

class RedirectResponse extends BaseRedirectResponse implements ResponseContract
{
    use ResponseTrait;
}
