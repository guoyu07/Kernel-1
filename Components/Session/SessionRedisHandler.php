<?php

/**
 * @category Cookie
 * @package Cookie
 * @link @Cookie
 * @author abulo.hoo
 */
namespace Kernel\Components\Session;

use Kernel\Utilities\Arr;

/**
 * Class SessionRedisHandler
 *
 * @package FastD\Session
 */
class SessionRedisHandler extends SessionHandler
{
    const SESSION_PREFIX = 'session:';

    /**
     * @var Redis
     */
    protected $redis;


    /**
     * SessionRedisHandler constructor.
     *
     * @param array $config
     * @param null $sessionId
     */
    public function __construct($sessionId = null)
    {

        parent::__construct($sessionId, '/tmp');
    }

    /**
     * @param $savePath
     * @return mixed
     */
    public function open($savePath)
    {
        $this->connect();

        return true;
    }

    /**
     *
     */
    protected function connect()
    {
        if (null === $this->redis) {
            $this->redis = Store::getInstance()->cache('session');//Redis::connect($this->config);

            // if (isset($this->config['dbindex'])) {
            //     $this->redis->select($this->config['dbindex']);
            // }
        }
    }

    /**
     * @return mixed
     */
    public function close()
    {
    }

    /**
     * @return mixed
     */
    public function destroy()
    {
        $this->redis->delete($this->getSessionId(static::SESSION_PREFIX));
        $this->close();
    }

    /**
     * @param $key
     * @param null $value
     * @return mixed
     */
    public function set($key, $value = null)
    {

        //get session by sessionId
        $session = $this->get();
        if (!$session) {
            $session = [];
        }
        $data = [];
        if (is_array($key)) {
            $data = $key;
            //$this->redis->hmset($this->getSessionId(static::SESSION_PREFIX), $key);
        } else {
            $data = [
                $key => $value
            ];
            // $this->redis->hmset($this->getSessionId(static::SESSION_PREFIX), [
            //     $key => $value
            // ]);
        }

        $newSession = Arr::merge($session, $data);
        // var_dump($newSession);

        $this->redis->set($this->getSessionId(static::SESSION_PREFIX), $newSession, 1800);
    }

    /**
     * @param null $key
     * @return mixed
     */
    public function get($key = null)
    {

        $session = $this->redis->get($this->getSessionId(static::SESSION_PREFIX));
        if (!$session) {
            return false;
        }

        if (null === $key) {
            return $session;
        }
        return $session[$key] ?? false;
    }

    /**
     * [delete description]
     * @param  [type] $key [description]
     * @return [type]      [description]
     */
    public function delete($key = null)
    {
        if (!$key) {
            return $this->clear();
        } else {
            $data = $this->get();
            unset($data[$key]);
            return $this->redis->set($this->getSessionId(static::SESSION_PREFIX), $data, 1800);
        }
    }

    /**
     * @return mixed
     */
    public function clear()
    {
        $this->redis->delete($this->getSessionId(static::SESSION_PREFIX));

        $this->set([]);
    }
}
