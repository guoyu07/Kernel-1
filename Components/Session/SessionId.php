<?php

/**
 * @category Cookie
 * @package Cookie
 * @link @Cookie
 * @author abulo.hoo
 */
namespace Kernel\Components\Session;

/**
 * Class SessionId
 *
 * @package FastD\Session
 */
class SessionId
{
    /**
     * @var string
     */
    protected $sessionId;

    /**
     * SessionId constructor.
     */
    public function __construct()
    {
        $this->sessionId = $this->buildId();
    }

    /**
     * @return string
     */
    protected function buildId()
    {

        $name = uniqid("", true)
                . mt_rand(5, 5900000000)
                . mt_rand(5, 5900000000)
                . '_'
                . mt_rand()
                . '_'
                . mt_rand(5, 5900000000)
                . '_'
                . microtime(true);

        $chars = md5($name);

        $uuid = substr($chars, 0, 8) . '-';
        $uuid .= substr($chars, 8, 4) . '-';
        $uuid .= substr($chars, 12, 4) . '-';
        $uuid .= substr($chars, 16, 4) . '-';
        $uuid .= substr($chars, 20, 12);

        return $uuid;
        // return md5(microtime(true) . mt_rand(000000, 999999));
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->sessionId;
    }
}
