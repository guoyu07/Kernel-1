<?php
namespace Kernel\Test;

use Kernel\Server\Http\Foundation\Request\Request;
use Kernel\Server\Http\Foundation\Response\Response;
use Kernel\Coroutine\Context;

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





    public function output($content)
    {
        return new Response($content);
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
