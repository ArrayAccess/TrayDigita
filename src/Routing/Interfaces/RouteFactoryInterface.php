<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing\Interfaces;

interface RouteFactoryInterface
{
    public function createRoute(
        array|string $methods,
        string $pattern,
        callable|array $controller,
        ?int $priority = RouteInterface::DEFAULT_PRIORITY,
        ?string $name = null,
        ?string $hostName = null
    ) : RouteInterface;
}
