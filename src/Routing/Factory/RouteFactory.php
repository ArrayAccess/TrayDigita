<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing\Factory;

use ArrayAccess\TrayDigita\Routing\Interfaces\RouteFactoryInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouteInterface;
use ArrayAccess\TrayDigita\Routing\Route;

class RouteFactory implements RouteFactoryInterface
{
    /**
     * Create route instance
     *
     * @param array|string $methods
     * @param string $pattern
     * @param callable|array $controller
     * @param ?int $priority
     * @param string|null $name
     * @param string|null $hostName
     * @return RouteInterface
     */
    public function createRoute(
        array|string $methods,
        string $pattern,
        callable|array $controller,
        ?int $priority = RouteInterface::DEFAULT_PRIORITY,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface {
        return new Route($methods, $pattern, $controller, $priority, $name, $hostName);
    }
}
