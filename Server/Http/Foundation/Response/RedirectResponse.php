<?php

namespace ZanPHP\HttpFoundation\Response;

use ZanPHP\Contracts\Http\ResponseTrait;
use ZanPHP\Contracts\Network\Response as ResponseContract;

class RedirectResponse extends BaseRedirectResponse implements ResponseContract
{
    use ResponseTrait;
}
