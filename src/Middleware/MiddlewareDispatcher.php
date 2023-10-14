<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Middleware;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Handler\Interfaces\MiddlewareDispatcherInterface;
use ArrayAccess\TrayDigita\Handler\MiddlewareRequestHandler;
use ArrayAccess\TrayDigita\Traits\Service\CallStackTraceTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewareDispatcher implements MiddlewareDispatcherInterface
{
    use CallStackTraceTrait;

    /**
     * @var RequestHandlerInterface
     */
    protected RequestHandlerInterface $handler;

    protected array $registeredMiddleware = [];

    public function __construct(
        RequestHandlerInterface $requestHandler,
        protected ?ManagerInterface $manager = null
    ) {
        $this->seedMiddlewareStack($requestHandler);
    }

    public function getManager(): ?ManagerInterface
    {
        return $this->manager;
    }

    public function getRegisteredMiddlewareClasses() : array
    {
        return $this->registeredMiddleware;
    }

    public function seedMiddlewareStack(RequestHandlerInterface $handler): void
    {
        if ($handler instanceof MiddlewareInterface) {
            $this->handler = new MiddlewareRequestHandler(
                $this,
                $handler,
                $handler
            );
            return;
        }

        $this->handler = $handler;
    }

    public function addMiddleware(MiddlewareInterface $middleware): MiddlewareDispatcherInterface
    {
        $this->registeredMiddleware[] = $middleware::class;
        $this->handler = new MiddlewareRequestHandler(
            $this,
            $middleware,
            $this->handler
        );

        return $this;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertCallstack();
        $manager = $this->getManager();
        $manager?->dispatch(
            'middlewareDispatcher.beforeHandle',
            $this->handler,
            $request
        );
        try {
            $response = $this->handler->handle($request);
            $manager?->dispatch(
                'middlewareDispatcher.handle',
                $this->handler,
                $request
            );
            return $response;
        } finally {
            $manager?->dispatch(
                'middlewareDispatcher.afterHandle',
                $this->handler,
                $request
            );
            $this->resetCallstack();
        }
    }
}
