<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing\Interfaces;

use ArrayAccess\TrayDigita\Routing\Route;

interface RouteMethodInterface
{
    public function map(
        array|string $methods,
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface;

    public function cli(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface;

    public function get(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface;

    public function any(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface;
    public function post(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface;

    public function put(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface;

    public function delete(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface;

    public function options(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface;

    public function connect(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface;

    public function patch(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface;

    public function trace(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface;

    public function addRoute(
        Route $route
    ) : RouterInterface;

    public function removeRoute(
        Route $route
    ) : bool;

    public function hasRoute(Route $route): bool;

    /**
     * @param string|ControllerInterface $controller
     * @return array<Route>
     */
    public function addRouteController(string|ControllerInterface $controller): array;
}
