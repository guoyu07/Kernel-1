<?php

namespace Kernel\Server\Http\Foundation\Response;

use Kernel\Contracts\Http\ResponseTrait;
use Kernel\Contracts\Network\Response;
use swoole_http_response as SwooleHttpResponse;

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
            throw new \Exception("Invalid uploading file : $filepath");
        }

        // no-replace
        // $this->headers->set("Content-Type", mime_content_type($filepath));
        $swooleHttpResponse->header("Content-Type", mime_content_type($filepath));
        if (!$swooleHttpResponse->sendfile($this->content)) {
            throw new \Exception("Failed to upload file: $filepath");
        }
        return $this;
    }
}
