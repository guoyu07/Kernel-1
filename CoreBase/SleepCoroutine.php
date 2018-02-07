<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Kernel\CoreBase;

use Kernel\Coroutine\CoroutineBase;
use Kernel\Memory\Pool;

/**
 * 用于并发选择1个结果，相当于go的select
 * Class SelectCoroutine
 * @package Kernel\CoreBase
 */
class SleepCoroutine extends CoroutineBase
{
    public function init()
    {
        $this->getCount = getTickTime();
        return $this;
    }
    public function getResult()
    {
        if ((getTickTime() - $this->getCount) > $this->MAX_TIMERS) {
            return true;
        }
        return $this->result;
    }

    public function send($callback)
    {
    }
    public function destroy()
    {
        parent::destroy();
        Pool::getInstance()->push($this);
    }
}
