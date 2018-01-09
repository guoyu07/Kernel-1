<?php

namespace Kernel\Server\Http\Foundation\Response;

use Kernel\Contracts\Http\ResponseTrait;
use Kernel\Contracts\Network\Response as ResponseContract;

class InternalErrorResponse extends BaseResponse implements ResponseContract
{
    use ResponseTrait;
}
