<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel\Interfaces;

use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerIndicateInterface;
use ArrayAccess\TrayDigita\Handler\Interfaces\MiddlewareDispatcherInterface;
use ArrayAccess\TrayDigita\Http\Interfaces\ResponseDispatcherInterface;
use ArrayAccess\TrayDigita\Kernel\Interfaces\KernelInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouterInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @mixin RouterInterface
 */
interface HttpKernelInterface extends
    RequestHandlerInterface,
    RunnableInterface,
    ResponseDispatcherInterface,
    ManagerIndicateInterface,
    ContainerIndicateInterface
{
    public function getKernel() : KernelInterface;

    public function getStartMemory() : int;

    public function getStartTime() : float;

    public function getContainer(): ContainerInterface;

    public function handle(ServerRequestInterface $request) : ResponseInterface;

    public function getRouter(): RouterInterface;

    public function getMiddlewareDispatcher(): MiddlewareDispatcherInterface;

    public function addMiddleware(MiddlewareInterface $middleware);

    public function addDeferredMiddleware(MiddlewareInterface $middleware);

    /**
     * @return array<int, array<MiddlewareInterface>>
     */
    public function getDeferredMiddlewares() : array;

    public function dispatchDeferredMiddleware();

    /**
     * Clear the deferred middleware
     */
    public function clearDeferredMiddlewares();
}
