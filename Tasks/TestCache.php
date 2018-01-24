<?php

namespace Kernel\Tasks;

use Kernel\CoreBase\Task;

/**
 * Class TestCache
 * @package Kernel\Tasks
 */
class TestCache extends Task
{
    public $map = [];

    public function addMap($value)
    {
        $this->map[] = $value;
        return true;
    }

    public function getAllMap()
    {
        return $this->map;
    }
}
