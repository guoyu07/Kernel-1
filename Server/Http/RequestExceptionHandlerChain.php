<?php

namespace Kernel\Server\Http;

use Kernel\Server\Http\Foundation\Response\BaseResponse;

class RequestExceptionHandlerChain
{
    private static $_instance = null;

    protected $handlerChain = [

    ];

    /**
     * @return static
     */
    public static function instance()
    {
        return static::singleton();
    }

    public static function singleton()
    {
        if (null === static::$_instance) {
            static::$_instance = new static();
        }
        return static::$_instance;
    }

    /**
     * @return static
     */
    public static function getInstance()
    {
        return static::singleton();
    }

    public static function swap($instance)
    {
        static::$_instance = $instance;
    }


    public function handle($e, $extraHandlerChain = [])
    {
        try {
            /** @var ExceptionHandler[] $handlerChain */
            $handlerChain = array_merge(array_values($extraHandlerChain), $this->handlerChain);
            if (empty($handlerChain)) {
                echo_exception($e);
                return;
            }

            $response = null;

            //at less one handler handle the exception
            //else throw the exception out
            $exceptionHandled = false;
            foreach ($handlerChain as $handler) {
                $response = (yield $handler->handle($e));
                if ($response) {
                    $resp = (yield getContext('response'));
                    if (!$resp) {
                        yield setContext('response', $response);
                    }
                    $exceptionHandled = true;
                    break;
                }
            }

            if ($response instanceof BaseResponse) {
                $swooleResponse = (yield getContext('swoole_response'));
                $response->exception = $e->getMessage();
                /** @var $response ResponseTrait */
                yield $response->sendBy($swooleResponse);
                return;
            }

            if (false === $exceptionHandled) {
                echo_exception($e);
                return;
            }

            yield null;
        } catch (\Throwable $t) {
            echo_exception($t);
        } catch (\Exception $e) {
            echo_exception($e);
        }
    }
}
