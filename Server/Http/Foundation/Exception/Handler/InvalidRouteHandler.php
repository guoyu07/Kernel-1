<?php

namespace ZanPHP\HttpFoundation\Exception\Handler;

use ZanPHP\Contracts\Config\Repository;
use ZanPHP\Contracts\Foundation\ExceptionHandler;
use ZanPHP\HttpFoundation\Exception\InvalidRouteException;
use ZanPHP\HttpFoundation\Response\BaseResponse;
use ZanPHP\HttpFoundation\Response\RedirectResponse;

class InvalidRouteHandler implements ExceptionHandler
{
    private $configKey = 'error';

    public function handle(\Exception $e)
    {
        if (!is_a($e, InvalidRouteException::class)) {
            return false;
        }
        $repository = make(Repository::class);
        $config = $repository->get($this->configKey, null);
        if (!$config) {
            return false;
        }
        // 跳转到配置的404页面
        return RedirectResponse::create($config['404'], BaseResponse::HTTP_NOT_FOUND);
    }
}
