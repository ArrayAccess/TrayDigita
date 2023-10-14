<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Middleware;

use ArrayAccess\TrayDigita\Http\Interfaces\HttpExceptionInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\MatchedRouteInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouterInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

class RoutingMiddleware extends AbstractMiddleware
{
    public function __construct(
        ContainerInterface $container,
        protected RouterInterface $router
    ) {
        parent::__construct($container);
    }

    protected function doProcess(
        ServerRequestInterface $request
    ): ServerRequestInterface {
        $matchedRoute = $this->performRouting($request);
        return $request
            ->withAttribute(
                'matchedRoute',
                $matchedRoute
            );
    }

    public function performRouting(
        ServerRequestInterface $request
    ): MatchedRouteInterface|HttpExceptionInterface {
        return $this->router->dispatch($request);
    }
}
