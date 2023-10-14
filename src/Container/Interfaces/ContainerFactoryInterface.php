<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Container\Interfaces;

use Psr\Container\ContainerInterface;

interface ContainerFactoryInterface
{
    public function createContainer(array $definitions = []) : ContainerInterface;

    public function createDefault(): ContainerInterface;
}
