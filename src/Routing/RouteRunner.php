<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerIndicateInterface;
use ArrayAccess\TrayDigita\Http\Interfaces\HttpExceptionInterface;
use ArrayAccess\TrayDigita\Http\RequestResponseExceptions\NotFoundException;
use ArrayAccess\TrayDigita\Middleware\AbstractMiddleware;
use ArrayAccess\TrayDigita\Middleware\RoutingMiddleware;
use ArrayAccess\TrayDigita\Routing\Exceptions\RouteErrorException;
use ArrayAccess\TrayDigita\Routing\Exceptions\RouteException;
use ArrayAccess\TrayDigita\Routing\Interfaces\MatchedRouteInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouterInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouteRunnerInterface;
use ArrayAccess\TrayDigita\Traits\Service\CallStackTraceTrait;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class RouteRunner extends AbstractMiddleware implements RouteRunnerInterface
{
    private bool $called = false;

    use CallStackTraceTrait;

    private ?MatchedRouteInterface $matchedRoute = null;

    public function __construct(
        protected ContainerInterface $container,
        protected RouterInterface $router
    ) {
        parent::__construct($this->container);
        if ($this instanceof ManagerAllocatorInterface
            && $this->router instanceof ManagerIndicateInterface
            && ($manager = $this->router->getManager())
        ) {
            $this->setManager($manager);
        }
    }

    public function getMatchedRoute(): ?MatchedRouteInterface
    {
        return $this->matchedRoute;
    }

    public function getRouter(): RouterInterface
    {
        return $this->router;
    }

    /**
     * @throws Throwable
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $this->assertCallstack();
        $manager = $this->getManager();
        if (!$manager && $this->router instanceof ManagerIndicateInterface) {
            $manager = $this->router->getManager();
        }
        $matchedRoute = $request->getAttribute('matchedRoute');
        $matchedRoute = $matchedRoute instanceof MatchedRouteInterface
        || $matchedRoute instanceof HttpExceptionInterface
            ? $matchedRoute
            : null;
        if (!$this->called && $matchedRoute === null) {
            $this->called = true;
            $routingMiddleware = new RoutingMiddleware(
                $this->getContainer(),
                $this->router
            );
            return $routingMiddleware->process($request, $this);
        }
        $this->called = true;
        try {
            // @dispatch(routeRunner.beforeHandle)
            $manager?->dispatch(
                'routeRunner.beforeHandle',
                $this,
                $request,
                $matchedRoute
            );

            if ($matchedRoute instanceof Throwable) {
                throw $matchedRoute;
            }

            if (!$matchedRoute instanceof MatchedRouteInterface) {
                throw new NotFoundException(
                    $request
                );
            }

            // set match route
            $this->matchedRoute = $matchedRoute;
            // handle
            $theResponse = $matchedRoute->handle($request);

            // @dispatch(routeRunner.handle)
            $response = $manager?->dispatch(
                'routeRunner.handle',
                $theResponse,
                $this
            );
            if ($response instanceof ResponseInterface) {
                $theResponse = $response;
            }
            return $theResponse;
        } catch (RouteException $exception) {
            if ($matchedRoute instanceof MatchedRouteInterface
                && !$exception instanceof RouteErrorException
            ) {
                throw new RouteErrorException(
                    $this->router,
                    $matchedRoute->getRoute(),
                    $request,
                    $exception,
                    $exception->getMessage(),
                    $exception->getCode()
                );
            }
            throw $exception;
        } finally {
            // @dispatch(routeRunner.afterHandle)
            $manager?->dispatch(
                'routeRunner.afterHandle',
                $this,
                $request,
                $matchedRoute??null,
                $exception??null
            );
            $this->resetCallstack();
        }
    }
}
