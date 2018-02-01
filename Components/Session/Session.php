<?php

/**
 * @category Cookie
 * @package Cookie
 * @link @Cookie
 * @author abulo.hoo
 */
namespace Kernel\Components\Session;

/**
 * Class Session
 *
 * @package FastD\Session
 */
class Session
{
    /**
     * @var string
     */
    const SESSION_KEY = 'X-Session-Id';

    /**
     * @var static
     */
    protected static $session;

    /**
     * @var SessionHandler
     */
    protected $sessionHandler;

    /**
     * @var bool
     */
    protected $started = false;

    /**
     * Session constructor.
     *
     * @param null $sessionId
     * @param SessionHandler|null $sessionHandler
     */
    public function __construct($sessionId = null, SessionHandler $sessionHandler = null)
    {
        if (null === $sessionHandler) {
            $sessionHandler = new SessionRedisHandler($sessionId);
        }

        $this->sessionHandler = $sessionHandler;

        $this->withSessionId($sessionId);
    }

    /**
     * @param null $sessionId
     * @param SessionHandler|null $sessionHandler
     * @return static
     */
    public static function start($sessionId = null, SessionHandler $sessionHandler = null)
    {
        if (null === static::$session) {
            static::$session = new static($sessionId, $sessionHandler);
        }

        return static::$session;
    }

    /**
     * @return string
     */
    public static function getSessionKey()
    {
        return static::SESSION_KEY;
    }

    /**
     * @return array
     */
    public function getSessionHeader()
    {
        return [
            $this->getSessionKey() => $this->getSessionId()
        ];
    }

    /**
     * @param $sessionId
     * @return $this
     */
    public function withSessionId($sessionId)
    {
        $this->sessionHandler->setSessionId($sessionId);

        return $this;
    }

    /**
     * @return string
     */
    public function getSessionId()
    {
        return $this->sessionHandler->getSessionId();
    }


    public static function getSessionIdByUid()
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
    }

    /**
     * @param $name
     * @return bool
     */
    public function get($name = null)
    {
        return $this->sessionHandler->get($name);
    }

    /**
     * @param $name
     * @param $value
     * @return $this
     */
    public function set($name, $value)
    {
        $this->sessionHandler->set($name, $value);

        return $this;
    }

    /**
     * @param $name
     * @return $this
     */
    public function delete($name)
    {
        $this->sessionHandler->delete($name);

        return $this;
    }

    /**
     * @return $this
     */
    public function clear()
    {
        $this->sessionHandler->clear();

        return $this;
    }

    /**
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->sessionHandler->get(null), JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->sessionHandler->get(null);
    }
}
