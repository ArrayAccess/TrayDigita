<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Container;

use Psr\Container\ContainerInterface;

abstract class ContainerInvokable
{
    final public function __construct()
    {
    }

    abstract public function getId() : string;

    abstract public function __invoke(ContainerInterface $container);
}
