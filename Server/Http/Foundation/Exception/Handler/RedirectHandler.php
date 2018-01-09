<?php

namespace ZanPHP\HttpFoundation\Exception\Handler;

use ZanPHP\Contracts\Foundation\ExceptionHandler;
use ZanPHP\HttpFoundation\Exception\RedirectException;
use ZanPHP\HttpFoundation\Response\BaseResponse;
use ZanPHP\HttpFoundation\Response\RedirectResponse;

class RedirectHandler implements ExceptionHandler
{
    public function handle(\Exception $e)
    {
        if (!isset($e->redirectUrl) && !is_a($e, RedirectException::class)) {
            return null;
        }

        return RedirectResponse::create($e->redirectUrl, BaseResponse::HTTP_FOUND);
    }
}
