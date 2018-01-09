<?php

namespace Kernel\Server\Http\Foundation;

use swoole_http_response as SwooleHttpResponse;
use Kernel\Config\Config;
use Kernel\Server\Http\Foundation\Request\Request;

class Cookie
{
    private $configKey = 'cookie';
    private $config;

    /**
     * @var Request
     */
    private $request;
    private $response;

    private static $domainWhiteList;

    public function __construct(Request $request, SwooleHttpResponse $swooleResponse)
    {
        $this->init($request, $swooleResponse);
    }

    private function init(Request $request, SwooleHttpResponse $swooleResponse)
    {

        $config = Config::get($this->configKey, null);
        if (!$config) {
            throw new \InvalidArgumentException('Cookie config is required, see: http://zanphpdoc.zanphp.io/config/cookie.html');
        }
        $this->config = $config;
        $this->request = $request;
        $this->response = $swooleResponse;
    }

    public function get($key, $default = null)
    {
        $cookies = $this->request->cookies;
        if (!$key) {
            yield $default;
        }

        yield $cookies->get($key, $default);
    }

    public function set($key, $value = null, $expire = null, $path = null, $domain = null, $secure = null, $httpOnly = null)
    {
        if (!$key) {
            return false;
        }
        if (null === $expire) {
            $expire = isset($this->config['expire']) ? $this->config['expire'] : 0;
        }
        $expire = time() + (int)$expire;

        $path = (null !== $path) ? $path : $this->config['path'];
        $domain = (null !== $domain) ? $domain : $this->getDomain($this->request->getHost() ?: "");
        $secure = (null !== $secure) ? $secure : $this->config['secure'];
        $httpOnly = (null !== $httpOnly) ? $httpOnly : $this->config['httponly'];

        /** @noinspection PhpUndefinedMethodInspection */
        $this->response->cookie($key, $value, $expire, $path, $domain, $secure, $httpOnly);
    }

    private function getDomain($host)
    {
        if (static::$domainWhiteList === null) {
            static::initDomainWhiteList();
        }

        $hostLen = strlen($host);
        foreach (static::$domainWhiteList as $domain => $len) {
            if ($hostLen < $len) {
                continue;
            }

            if (substr(rtrim($host), -$len) === $domain) {
                return $domain;
            }
        }
        return $host;
    }

    private function initDomainWhiteList()
    {
        $domainList = [
            '.koudaitong.com',
            '.youzan.com',
            '.qima-inc.com',
            '.kdt.im',
        ];


        array_push($domainList, ...(array)Config::get("cookie.domain", []));

        foreach ($domainList as $domain) {
            static::$domainWhiteList[$domain] = strlen($domain);
        }

        arsort(static::$domainWhiteList);
    }
}
