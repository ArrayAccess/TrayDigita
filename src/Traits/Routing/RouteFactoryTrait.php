<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Traits\Routing;

use ArrayAccess\TrayDigita\Routing\Factory\RouteFactory;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouteFactoryInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Container\ContainerInterface;

trait RouteFactoryTrait
{
    abstract public function getContainer(): ContainerInterface;

    public function getRouteFactory() : RouteFactoryInterface
    {
        return ContainerHelper::use(
            RouteFactoryInterface::class,
            $this->getContainer()
        )??new RouteFactory();
    }
}
