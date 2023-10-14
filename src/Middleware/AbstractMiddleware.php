<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Middleware;

use ArrayAccess\TrayDigita\Container\Container;
use ArrayAccess\TrayDigita\Container\ContainerWrapper;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

abstract class AbstractMiddleware implements MiddlewareInterface, ManagerIndicateInterface, ContainerIndicateInterface
{
    const DEFAULT_PRIORITY = 10;

    /**
     * @var int The middleware priority
     */
    protected int $priority = self::DEFAULT_PRIORITY;

    protected ContainerInterface $container;

    protected ?ManagerInterface $manager;

    public function __construct(ContainerInterface $container)
    {
        $this->container = ContainerWrapper::maybeContainerOrCreate($container);
        try {
            $this->manager = $this->container->has(ManagerInterface::class)
                ? $this->container->get(ManagerInterface::class)
                : null;
        } catch (Throwable) {
            $this->manager = null;
        }
    }

    public function getManager(): ?ManagerInterface
    {
        return $this->manager;
    }

    /**
     * @return ContainerInterface|Container
     */
    public function getContainer(): ContainerInterface|Container
    {
        return $this->container;
    }

    public function getPriority() : int
    {
        return $this->priority;
    }

    /**
     * Process the middleware
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $manager = $this->getManager();
        try {
            $manager->dispatch(
                'middleware.beforeProcess',
                $this,
                $request
            );
            $requestOrResponse = $this->doProcess($request);
            $manager->dispatch(
                'middleware.process',
                $this,
                $request
            );
        } finally {
            $manager->dispatch(
                'middleware.afterProcess',
                $this,
                $request
            );
        }
        if ($requestOrResponse instanceof ResponseInterface) {
            $response = $requestOrResponse;
        } else {
            $response = $handler->handle($requestOrResponse);
        }
        return $response;
    }

    /**
     * @override
     *
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface|ResponseInterface
     */
    protected function doProcess(
        ServerRequestInterface $request
    ) : ServerRequestInterface|ResponseInterface {
        return $request;
    }
}
