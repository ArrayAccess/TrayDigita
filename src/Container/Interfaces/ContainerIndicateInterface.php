<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Container\Interfaces;

use Psr\Container\ContainerInterface;

interface ContainerIndicateInterface
{
    /**
     * Get current container
     *
     * @return ?ContainerInterface
     */
    public function getContainer() : ?ContainerInterface;
}
