<?php

namespace ZanPHP\HttpFoundation\Response;

use ZanPHP\Contracts\Http\ResponseTrait;
use ZanPHP\Contracts\Network\Response;
use swoole_http_response as SwooleHttpResponse;
use ZanPHP\Exception\System\InvalidArgumentException;
use ZanPHP\HttpFoundation\Exception\FileUploadFailException;

class FileResponse extends BaseResponse implements Response
{
    use ResponseTrait;

    public function __construct($filepath, $status = 200, array $headers = [])
    {
        parent::__construct($filepath, $status, $headers);
    }

    public function sendBy(SwooleHttpResponse $swooleHttpResponse)
    {
        $this->sendHeadersBy($swooleHttpResponse);
        $filepath = realpath($this->content);
        if (!$filepath || !is_readable($filepath)) {
            throw new InvalidArgumentException("Invalid uploading file : $filepath");
        }

        // no-replace
        // $this->headers->set("Content-Type", mime_content_type($filepath));
        $swooleHttpResponse->header("Content-Type", mime_content_type($filepath));
        if (!$swooleHttpResponse->sendfile($this->content)) {
            throw new FileUploadFailException("Failed to upload file: $filepath");
        }
        return $this;
    }
}