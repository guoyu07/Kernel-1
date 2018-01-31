<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-29
 * Time: 下午12:55
 */

namespace Kernel\CoreBase;

use Kernel\Xss\AntiXSS;

class XssClean
{
    protected static $xss_clean;

    public static function getXssClean()
    {
        if (self::$xss_clean == null) {
            self::$xss_clean = new AntiXSS();
        }
        return self::$xss_clean;
    }
}
