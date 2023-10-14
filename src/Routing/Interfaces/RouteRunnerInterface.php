<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing\Interfaces;

use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface RouteRunnerInterface extends RequestHandlerInterface, ContainerIndicateInterface
{
    public function __construct(
        ContainerInterface $container,
        RouterInterface $router
    );
    public function getRouter(): RouterInterface;

    public function getMatchedRoute(): ?MatchedRouteInterface;
}
