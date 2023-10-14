<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Kernel;

use ArrayAccess\TrayDigita\Container\Container;
use ArrayAccess\TrayDigita\Container\Factory\ContainerFactory;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Event\Manager;
use ArrayAccess\TrayDigita\Exceptions\Runtime\UnProcessableException;
use ArrayAccess\TrayDigita\Handler\Interfaces\MiddlewareDispatcherInterface;
use ArrayAccess\TrayDigita\Http\Interfaces\ResponseEmitterInterface;
use ArrayAccess\TrayDigita\Http\ResponseEmitter;
use ArrayAccess\TrayDigita\HttpKernel\Interfaces\HttpKernelInterface;
use ArrayAccess\TrayDigita\HttpKernel\Interfaces\TerminableInterface;
use ArrayAccess\TrayDigita\Middleware\MiddlewareDispatcher;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouterInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouteRunnerInterface;
use ArrayAccess\TrayDigita\Routing\Router;
use ArrayAccess\TrayDigita\Routing\RouteRunner;
use ArrayAccess\TrayDigita\Traits\Http\ResponseFactoryTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Service\CallStackTraceTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Throwable;
use function memory_get_usage;
use function microtime;
use function spl_object_hash;
use function strtoupper;

/**
 * @mixin RouterInterface
 */
class HttpKernel implements
    HttpKernelInterface,
    TerminableInterface,
    ManagerAllocatorInterface
{
    use ResponseFactoryTrait,
        ManagerAllocatorTrait,
        CallStackTraceTrait;

    private MiddlewareDispatcherInterface $middlewareDispatcher;

    private ContainerInterface $container;

    private RouterInterface $router;

    private ?ResponseInterface $lastResponse = null;

    private ?ServerRequestInterface $lastRequest = null;

    /**
     * Start memory @uses memory_get_usage()
     * @var int
     */
    private int $startMemory;

    /**
     * Start time @uses microtime(true)
     * @var float
     */
    private float $startTime;

    public function __construct(
        ContainerInterface $container = null,
        ManagerInterface $manager = null
    ) {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();

        $container ??= (new ContainerFactory())->createDefault();
        $this->container = $container;
        $manager ??= ContainerHelper::use(ManagerInterface::class, $container);
        if (!$manager) {
            $container->remove(ManagerInterface::class);
            $manager = new Manager();
            $container->set(ManagerInterface::class, $manager);
        }
        $this->managerObject = $manager;
        $this->container->set(HttpKernelInterface::class, fn () => $this);

        // router
        $definitions = [
            RouterInterface::class => Router::class,
            MiddlewareDispatcherInterface::class => MiddlewareDispatcher::class,
            RouteRunnerInterface::class => RouteRunner::class,
        ];

        foreach ($definitions as $key => $item) {
            try {
                if (!$container->has($key)) {
                    $container->set($key, $item);
                }
            } catch (Throwable) {
            }
        }

        $this->router = ContainerHelper::service(RouterInterface::class, $container);
        $this->middlewareDispatcher = ContainerHelper::service(MiddlewareDispatcherInterface::class, $container);
    }

    public function getStartMemory(): int
    {
        return $this->startMemory;
    }

    public function getStartTime(): float
    {
        return $this->startTime;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getRouter(): RouterInterface
    {
        return $this->router;
    }

    public function getMiddlewareDispatcher(): MiddlewareDispatcherInterface
    {
        return $this->middlewareDispatcher;
    }

    public function getManager(): ManagerInterface
    {
        return $this->managerObject;
    }

    public function addMiddleware(MiddlewareInterface $middleware): static
    {
        $this->getMiddlewareDispatcher()->addMiddleware($middleware);
        return $this;
    }

    public function getLastResponse(): ?ResponseInterface
    {
        return $this->lastResponse;
    }

    public function getLastRequest(): ?ServerRequestInterface
    {
        return $this->lastRequest;
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $this->assertCallstack();
        if ($this->lastResponse
            && $this->lastRequest
            && spl_object_hash($request) === spl_object_hash($this->lastRequest)
        ) {
            $this->resetCallstack();
            return $this->lastResponse;
        }

        $manager = $this->getManager();
        $manager->dispatch(
            'httpKernel.beforeHandle',
            $this
        );
        try {
            $this->lastRequest = $request;
            // add middleware
            $this->lastResponse = $this->getMiddlewareDispatcher()->handle($request);

            /**
             * This is to be in compliance with RFC 2616, Section 9.
             * If the incoming request method is HEAD, we need to ensure that the response body
             * is empty as the request may fall back on a GET route handler due to FastRoute's
             * routing logic which could potentially append content to the response body
             * https://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.4
             */
            $method = strtoupper($request->getMethod());
            if ($method === 'HEAD'
                // also empty on cli
                || $method === 'CLI'
            ) {
                $emptyBody = $this->getResponseFactory()->createResponse()->getBody();
                $this->lastResponse = $this->lastResponse->withBody($emptyBody);
            }
            $manager->dispatch(
                'httpKernel.handle',
                $this,
                $this->lastResponse
            );
            $this->resetCallstack();
            return $this->lastResponse;
        } finally {
            $manager->dispatch(
                'httpKernel.afterHandle',
                $this
            );
        }
    }

    public function dispatchResponse(ResponseInterface $response): ResponseInterface
    {
        $this->assertCallstack();

        $manager = $this->getManager();
        // @dispatch(httpKernel.beforeDispatch)
        $manager->dispatch(
            'httpKernel.beforeDispatch',
            $this,
            $response
        );

        /**
         * @var Container $container
         */
        $container = $this->getContainer();
        $doEmit = ! Consolidation::isCli() || $manager->dispatch(
            'httpKernel.emitResponse',
            false
        ) === true;
        $emitter = ContainerHelper::service(ResponseEmitterInterface::class, $container)
            ?? new ResponseEmitter();
        if ($emitter->isClosed()) {
            throw new UnProcessableException(
                'Emitter has been closed.'
            );
        }

        // @dispatch(httpKernel.dispatch)
        $newResponse = $manager->dispatch('httpKernel.dispatch', $response);
        if ($newResponse instanceof ResponseInterface) {
            $response = $newResponse;
        }

        // @dispatch(response.final)
        $newResponse = $manager->dispatch('response.final', $response);
        if ($newResponse instanceof ResponseInterface) {
            $response = $newResponse;
        }

        // @dispatch(httpKernel.afterDispatch)
        $manager->dispatch(
            'httpKernel.afterDispatch',
            $this,
            $response
        );

        if ($doEmit) {
            $reduceError = (bool) $manager
                ->dispatch('response.reduceError', true);
            $sendPreviousBuffer = (bool) $manager
                ->dispatch('response.sendPreviousBuffer', true);
            $emitter->emit($response, $reduceError, $sendPreviousBuffer);
        }

        // @dispatch(httpKernel.afterDispatch)
        $manager->dispatch(
            'httpKernel.dispatched',
            $this,
            $response
        );

        $this->resetCallstack();

        return $response;
    }

    public function run(ServerRequestInterface $request) : ResponseInterface
    {
        return $this->dispatchResponse($this->handle($request));
    }

    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void
    {
        $this->assertCallstack();
        $manager = $this->getManager();
        // @dispatch(httpKernel.terminate)
        $manager
            ->dispatch(
                'httpKernel.beforeTerminate',
                $this,
                $request,
                $response
            );
        try {
            $manager->dispatch(
                'httpKernel.terminate',
                $this,
                $request,
                $response
            );
            /*
             * @var Container $container
            $container = $this->getContainer();
            if (!$container->has(ResponseEmitterInterface::class)) {
                return;
            }
            try {
                $emitter = $container->get(ResponseEmitterInterface::class);
                !$emitter->isClosed() && $emitter->close();
            } catch (Throwable) {
            }*/
        } finally {
            $manager->dispatch(
                'httpKernel.afterTerminate',
                $this,
                $request,
                $response
            );
            $this->resetCallstack();
        }
    }

    public function __call(string $name, array $arguments)
    {
        return $this->getRouter()->$name(...$arguments);
    }

    public function __debugInfo(): ?array
    {
        return Consolidation::debugInfo(
            $this,
            excludeKeys: [
                'router',
                'lastResponse',
                'lastRequest'
            ]
        );
    }
}
