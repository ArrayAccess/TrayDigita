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
    /**
     * Get the kernel
     *
     * @return KernelInterface
     */
    public function getKernel() : KernelInterface;

    /**
     * Get the start memory
     *
     * @return int
     */
    public function getStartMemory() : int;

    /**
     * Get the start time
     *
     * @return float The start time
     */
    public function getStartTime() : float;

    /**
     * Get the container
     *
     * @return ContainerInterface The container
     */
    public function getContainer(): ContainerInterface;

    /**
     * Handle the request
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface;

    /**
     * Get the router
     *
     * @return RouterInterface The router
     */
    public function getRouter(): RouterInterface;

    /**
     * Get the middleware dispatcher
     *
     * @return MiddlewareDispatcherInterface The middleware dispatcher
     */
    public function getMiddlewareDispatcher(): MiddlewareDispatcherInterface;

    /**
     * Add middleware
     *
     * @param MiddlewareInterface $middleware The middleware
     */
    public function addMiddleware(MiddlewareInterface $middleware);

    /**
     * Add deferred middleware, the middleware will be dispatched after the
     * application is run
     *
     * @param MiddlewareInterface $middleware
     */
    public function addDeferredMiddleware(MiddlewareInterface $middleware);

    /**
     * Get deferred middleware
     *
     * @return array<int, array<MiddlewareInterface>>
     */
    public function getDeferredMiddlewares() : array;

    /**
     * Dispatch deferred middleware
     */
    public function dispatchDeferredMiddleware();

    /**
     * Clear the deferred middleware
     */
    public function clearDeferredMiddlewares();
}
