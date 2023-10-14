<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing\Interfaces;

use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerIndicateInterface;
use ArrayAccess\TrayDigita\Http\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface ControllerInterface extends ContainerIndicateInterface, ManagerIndicateInterface
{
    public function __construct(RouterInterface $router);

    /**
     * @return ?RouteInterface
     */
    public function getRoute() : ?RouteInterface;

    public function dispatch(
        RouteInterface $route,
        ServerRequestInterface $request,
        string $method,
        ...$arguments
    ) : ResponseInterface;

    /**
     * @param ServerRequest $request
     * @param string $method
     * @param ...$arguments
     * @return mixed|ResponseInterface
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function beforeDispatch(
        ServerRequestInterface $request,
        string $method,
        ...$arguments
    );

    /**
     * @param ResponseInterface $response
     * @param ServerRequest $request
     * @param string $method
     * @param ...$arguments
     * @return ResponseInterface
     */
    public function afterDispatch(
        ResponseInterface $response,
        ServerRequestInterface $request,
        string $method,
        ...$arguments
    ) : ResponseInterface;
}
