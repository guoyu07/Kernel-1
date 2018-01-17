<?php
namespace Kernel\Test;

use Kernel\Coroutine\Context;
use Kernel\Server\Http\Foundation\Request;
use Kernel\Server\Http\Foundation\Response;
use Kernel\Server\Http\Foundation\HtmlResponse;
use Kernel\Server\Http\Foundation\XmlResponse;
use Kernel\Server\Http\Foundation\JsonResponse;
use Kernel\Server\Http\Foundation\ImageResponse;

class TestController
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Context;
     */
    protected $context;

    public function __construct(Request $request, Context $context)
    {
        $this->request = $request;
        $this->context = $context;
    }

    public function callback($v)
    {
        yield $this->display();
    }

    public function callback1()
    {
        yield $this->display();
    }





    public function output($content)
    {
        return new HtmlResponse($content);
    }



    public function display()
    {

        $content = '<!DOCTYPE html>
<html>
<head>
    <title>Welcome to PHP!</title>
    <style>
        body {
            width: 35em;
            margin: 0 auto;
            font-family: Tahoma, Verdana, Arial, sans-serif;
        }
    </style>
</head>
<body>
<h1>Welcome to PHP!</h1>

</body>
</html>';
        return $this->output($content);
    }
}
