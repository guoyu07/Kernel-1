<?php

namespace Kernel\Server\Http\Foundation;

use Kernel\Utilities\Arr;
use Kernel\Utilities\Str;

/**
 * Class Request
 *
 * @package \App
 */
class Request
{
    protected static $httpMethodParameterOverride = false;

    /**
     * $_GET.
     *
     * @var array
     */
    public $query;

    /**
     * $_POST.
     *
     * @var array
     */
    public $request;

    /**
     * Custom parameters.
     *
     * @var array
     */
    public $attributes;

    /**
     * $_COOKIES.
     *
     * @var array
     */
    public $cookies;

    /**
     * $_FILES.
     *
     * @var array
     */
    public $files;

    /**
     * $_SERVER.
     *
     * @var array
     */
    public $server;

    /**
     * Headers (taken from the $_SERVER).
     *
     * @link https://wiki.swoole.com/wiki/page/332.html
     * @link http://php.net/manual/zh/function.apache-request-headers.php
     * @var array
     */
    public $headers;

    /**
     * @var string|resource
     */
    public $content;

    /**
     * @var string
     */
    protected $method;


    const JSON = 'Json';
    const XML = 'Xml';
    const HTML = 'Html';
    const IMAGE = 'Image';

    /**
     * @param array           $query      The GET parameters
     * @param array           $request    The POST parameters
     * @param array           $attributes The request attributes (parameters parsed from the PATH_INFO, ...)
     * @param array           $cookies    The COOKIES parameters
     * @param array           $files      The FILES parameters
     * @param array           $server     The SERVER parameters
     * @param array           $headers    The headers attributes (taken from the $_SERVER)
     * @param string|resource $content    The raw body data
     */
    public function __construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], array $headers = [], $content = null)
    {
        $this->initialize($query, $request, $attributes, $cookies, $files, $server, $headers, $content);
    }

    /**
     * @param array           $query      The GET parameters
     * @param array           $request    The POST parameters
     * @param array           $attributes The request attributes (parameters parsed from the PATH_INFO, ...)
     * @param array           $cookies    The COOKIES parameters
     * @param array           $files      The FILES parameters
     * @param array           $server     The SERVER parameters
     * @param array           $headers    The headers attributes (taken from the $_SERVER)
     * @param string|resource $content    The raw body data
     */
    public function initialize(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], array $headers = [], $content = null): void
    {
        $this->query = $query;
        $this->request = $request;
        $this->attributes = $attributes;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->server = $server;
        $this->headers = $headers;

        $this->content = $content;
        $this->method = null;
    }


    public function getContentType()
    {
        if ($this->wantsJson()) {
            return self::JSON;
        } elseif ($this->wantsXml()) {
            return self::XML;
        } elseif ($this->wantsImage()) {
            return self::IMAGE;
        } elseif ($this->wantsHtml()) {
            return self::HTML;
        } else {
            return self::HTML;
        }
    }

    /**
     *
     * @return bool
     */
    public function wantsJson()
    {
        $acceptable = preg_split('/\s*(?:,*("[^"]+"),*|,*(\'[^\']+\'),*|,+)\s*/', $this->server('HTTP_ACCEPT'), 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $isJson = isset($acceptable[0]) && Str::contains($acceptable[0], ['/json', '+json']);
        if (!$isJson) {
            $isJson = stripos($this->server('REQUEST_URI'), '.json') !== false;
        }
        return $isJson;
    }

    /**
     *
     * @return bool
     */
    public function wantsXml()
    {
        $acceptable = preg_split('/\s*(?:,*("[^"]+"),*|,*(\'[^\']+\'),*|,+)\s*/', $this->server('HTTP_ACCEPT'), 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $isXml = isset($acceptable[0]) && Str::contains($acceptable[0], ['/xml', '+xml']);
        if (!$isXml) {
            $isXml = stripos($this->server('REQUEST_URI'), '.xml') !== false;
        }
        return $isXml;
    }

    /**
     *
     * @return bool
     */
    public function wantsImage()
    {
        $acceptable = preg_split('/\s*(?:,*("[^"]+"),*|,*(\'[^\']+\'),*|,+)\s*/', $this->server('HTTP_ACCEPT'), 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $isImage = isset($acceptable[0]) && Str::contains($acceptable[0], ['/image', '+image']);
        return $isImage;
    }

    /**
     *
     * @return bool
     */
    public function wantsHtml()
    {
        $acceptable = preg_split('/\s*(?:,*("[^"]+"),*|,*(\'[^\']+\'),*|,+)\s*/', $this->server('HTTP_ACCEPT'), 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $isHtml = isset($acceptable[0]) && Str::contains($acceptable[0], ['/html', '+html']);
        if (!$isHtml) {
            $isHtml = (stripos($this->server('REQUEST_URI'), '.html') !== false  || stripos($this->server('REQUEST_URI'), '.htm') !== false || stripos($this->server('REQUEST_URI'), '.shtml') !== false);
        }
        return $isHtml;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        if (null === $this->method) {
            $this->method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

            if ('POST' === $this->method) {
                if ($method = $this->headers['X-HTTP-METHOD-OVERRIDE']) {
                    $this->method = strtoupper($method);
                } elseif (self::$httpMethodParameterOverride) {
                    $this->method = strtoupper($this->request['_method'] ?? ($this->query['_method'] ?? 'POST'));
                }
            }
        }

        return $this->method;
    }

    /**
     * @param string $method
     */
    public function setMethod(string $method): void
    {
        $this->method = null;
        $this->server['REQUEST_METHOD'] = $method;
    }

    /**
     * Checks if the request method is of specified type.
     *
     * @param string $method Uppercase request method (GET, POST etc)
     *
     * @return bool
     */
    public function isMethod($method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    /**
     * IS AJAX
     *
     * @return bool
     */
    public function ajax(): bool
    {
        return $this->headers['X-Requested-With'] == 'XMLHttpRequest';
    }

    /**
     * IS PAJX
     *
     * @return bool
     */
    public function pjax(): bool
    {
        return $this->headers['X-PJAX'] == true;
    }

    /**
     * Get POST
     *
     * @param null $key
     * @param null $default
     * @return mixed
     */
    public function post($key = null, $default = null)
    {
        return $this->retrieveItem('request', $key, $default);
    }

    /**
     * Get GET
     *
     * @param null $key
     * @param null $default
     * @return mixed
     */
    public function query($key = null, $default = null)
    {
        return $this->retrieveItem('query', $key, $default);
    }

    /**
     * Get REQUEST
     *
     * @param null $key
     * @param null $default
     * @return array|mixed|null
     */
    public function input($key = null, $default = null)
    {
        $request = Arr::merge($this->query, $this->request, $this->cookie);

        if (is_null($key)) {
            return $request;
        }

        return array_key_exists($key, $request) ? $request[$key] : $default;
    }

    /**
     * @param array ...$keys
     * @return array
     */
    public function only(...$keys)
    {
        $results = [];

        $placeholder = new \stdClass;

        foreach (is_array($keys) ? $keys : func_get_args() as $key) {
            $value = $this->input($key, $placeholder);

            if ($value !== $placeholder) {
                $results[] = [$key, $value];
            }
        }

        return $results;
    }

    /**
     * Get FILES
     *
     * @param null $key
     * @param null $default
     * @return mixed
     */
    public function file($key = null, $default = null)
    {
        return $this->retrieveItem('files', $key, $default);
    }

    /**
     * Get COOKIES
     *
     * @param null $key
     * @param null $default
     * @return mixed
     */
    public function cookie($key = null, $default = null)
    {
        return $this->retrieveItem('cookies', $key, $default);
    }

    /**
     * Get SERVER
     *
     * @param null $key
     * @param null $default
     * @return mixed
     */
    public function server($key = null, $default = null)
    {
        return $this->retrieveItem('server', $key, $default);
    }

    /**
     * Get Header
     *
     * @param null $key
     * @param null $default
     * @return mixed
     */
    public function header($key = null, $default = null)
    {
        return $this->retrieveItem('headers', $key, $default);
    }

    /**
     * @param $source
     * @param $key
     * @param $default
     * @return mixed
     */
    protected function retrieveItem($source, $key, $default)
    {
        if (is_null($key)) {
            return $this->$source;
        }

        return array_key_exists($key, $this->$source) ? $this->$source[$key] : $default;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function destroy()
    {
        $this->query = null;
        $this->request = null;
        $this->attributes = null;
        $this->cookies = null;
        $this->files = null;
        $this->server = null;
        $this->headers = null;
        $this->content = null;
        $this->method = null;
    }
}
