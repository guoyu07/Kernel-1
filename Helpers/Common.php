<?php

/**
 * @return string
 */
function getConfigDir()
{
    return CONFIG_PATH.DS.ENV;
}


function secho($tile, $message)
{
    if (is_string($message)) {
        $message = ltrim($message);
        $message = str_replace(PHP_EOL, '', $message);
    } else {
        $message = var_export($message, true);
    }

    \Kernel\Utilities\Terminal::drawStr($tile, $color = 'default');
    \Kernel\Utilities\Terminal::drawStr($message, $color = 'default');
}
