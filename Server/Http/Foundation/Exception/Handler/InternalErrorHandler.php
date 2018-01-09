<?php

namespace ZanPHP\HttpFoundation\Exception\Handler;

use ZanPHP\Contracts\Config\Repository;
use ZanPHP\Contracts\Foundation\ExceptionHandler;
use ZanPHP\Exception\BusinessException;
use ZanPHP\HttpFoundation\Response\BaseResponse;
use ZanPHP\HttpFoundation\Response\RedirectResponse;
use ZanPHP\HttpFoundation\Response\Response;

class InternalErrorHandler implements ExceptionHandler
{
    private $configKey = 'error';

    public function handle(\Exception $e)
    {
        if (!is_a($e, \Exception::class)) {
            return false;
        }

        // prevent fatal error
        try {
            $repository = make(Repository::class);
            $config = $repository->get($this->configKey, null);
            if (!$config) {
                $runMode = getenv("runMode");
                if ($repository->get('debug') && !in_array($runMode, ["pre", "online"], true)) {
                    $errorInfo = [
                        'code' => 99999,
                        'msg' => $e->getMessage(),
                        'file' => $e->getFile() . ":" . $e->getLine(),
                        'trace' => $e->getTrace()
                    ];
                    return new Response($errorInfo);
                } else {
                    $code = $e->getCode();
                    $msg = $e instanceof BusinessException ? $e->getMessage() : '网络错误';
                    return new Response("{$msg}({$code})");
                }
            }
            // 跳转到配置的500页面
            if (isset($config['500'])) {
                return RedirectResponse::create($config['500'], BaseResponse::HTTP_INTERNAL_SERVER_ERROR);
            }

            $errMsg = '对不起，页面被霸王龙吃掉了... ';
            $errorPagePath = getenv("path.root") . '/vendor/zanphp/http-view/src/Pages/Error.php';
            $errorPage = require $errorPagePath;
            return new Response($errorPage);
        } catch (\Throwable $t) {
            return $this->handle(t2ex($t));
        } catch (\Exception $e) {
            return $this->handle($e);
        }
    }
}
