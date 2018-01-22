<?php

namespace Kernel\Async\Tests;

// use Test;
use AsyncHttp;

class AsyncHttpTest extends Test
{
    public function unitget()
    {
        // $http = new AsyncHttp('http://127.0.0.1:80');
        // $http->setHost('baidu.com');
        // $res = (yield $http->get('/'));
        // $this->assertEquals('200', $res->statusCode);
        // $this->assertEquals('hello world!', $res->body);
    }

    public function unitpost()
    {
        // $http = new AsyncHttp('https://baidu.com');
        // $res = (yield $http->post('/', []));
        // $http = new AsyncHttp('127.0.0.1', 443, true);
        // $http->setHost('baidu.com');
        // $res = (yield $http->post('/test', ['postId' => 52]));
    }
}
