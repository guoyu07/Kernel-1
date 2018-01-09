<?php

namespace Kernel\Contracts\Http;

interface Url
{
    public function setDomain($domain);

    public function getDomain();

    public function setPath($path);

    public function getPath();
}
