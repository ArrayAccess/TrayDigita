<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing\Interfaces;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface MatchedRouteInterface extends RequestHandlerInterface
{
    public function getRequest(): ServerRequestInterface;

    public function getRoute(): RouteInterface;

    public function getParams(): array;
}
