<?php

namespace Kernel\Server;

class Marco
{

    /**
     * 进程为WORKER
     */
    const PROCESS_WORKER                            = 1;
    /**
     * 进程为TASKER
     */
    const PROCESS_TASKER                            = 2;
    /**
     * 进程为RELOAD
     */
    const PROCESS_RELOAD                            = 3;
    /**
     * 进程为CONFIG
     */
    const PROCESS_CONFIG                            = 4;
    /**
     * 进程为TIMER
     */
    const PROCESS_TIMER                             = 5;
    /**
     * 进程为MASTER
     */
    const PROCESS_MASTER                            = 4094;
    /**
     * 进程为MANAGER
     */
    const PROCESS_MANAGER                           = 4095;
    /**
     * 进程为USER（默认）
     */
    const PROCESS_USER                              = 4096;
}
