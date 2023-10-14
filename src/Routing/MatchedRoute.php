<?php
/** @noinspection PhpClassCanBeReadonlyInspection */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing;

use ArrayAccess\TrayDigita\Routing\Interfaces\ControllerInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\MatchedRouteInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouteInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use function is_array;
use function is_string;
use function is_subclass_of;

class MatchedRoute implements MatchedRouteInterface
{
    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly Router $router,
        private readonly RouteInterface $route,
        private readonly array $params
    ) {
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getRoute(): RouteInterface
    {
        return $this->route;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function handle(?ServerRequestInterface $request = null): ResponseInterface
    {
        $request ??= $this->getRequest();
        $route = $this->getRoute();
        $callback = $route->getCallback();
        if (is_array($callback)
            && isset($callback[0])
            && is_subclass_of($callback[0], ControllerInterface::class)
        ) {
            if (is_string($callback[0])) {
                $callback[0] = new $callback[0]($this->getRouter());
                $route->setController($callback[0], $callback[1]);
            }
        } else {
            $callback = [
                CallableAbstractController::attach($this->getRouter(), $callback),
                'route'
            ];
        }

        return $callback[0]->dispatch(
            $route,
            $request,
            $callback[1],
            ...$this->getParams()
        );
    }
}
