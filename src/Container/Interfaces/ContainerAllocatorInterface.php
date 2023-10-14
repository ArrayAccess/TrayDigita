<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Container\Interfaces;

use Psr\Container\ContainerInterface;

interface ContainerAllocatorInterface extends ContainerIndicateInterface
{
    /**
     * Set container
     *
     * @param ContainerInterface $container
     * @return ?ContainerInterface returning previous container if exists
     */
    public function setContainer(ContainerInterface $container) : ?ContainerInterface;
}
