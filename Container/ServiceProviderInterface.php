<?php

namespace Kernel\Container;

use Kernel\Container\ContainerInterface;

/**
 * service provider interface.
 *
 * @author  abulo.hoo
 */
interface ServiceProviderInterface
{
    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $container A container instance
     */
    public function register(ContainerInterface $container);
}
