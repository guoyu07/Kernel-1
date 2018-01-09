<?php

namespace ZanPHP\HttpFoundation\Exception\Handler;

use ZanPHP\Contracts\Foundation\ExceptionHandler;
use ZanPHP\HttpFoundation\Response\JsonResponse;
use ZanPHP\HttpFoundation\Response\Response;

class ServerUnavailableHandler implements ExceptionHandler
{
    public function handle(\Exception $e)
    {
        $code = $e->getCode();
        if ($code != 503) {
            yield false;
            return;
        }

        $errMsg = $e->getMessage();
        $errorPagePath = getenv("path.root") . '/vendor/zanphp/http-view/src/Pages/Error.php';
        $errorPage = require $errorPagePath;

        $request = (yield getContext('request'));
        if ($request->wantsJson()) {
            $context = [
                'code' => $code,
                'msg' => $e->getMessage(),
                'data' => '',
            ];
            yield new JsonResponse($context);
        } else {
            //html
            yield new Response($errorPage, Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
