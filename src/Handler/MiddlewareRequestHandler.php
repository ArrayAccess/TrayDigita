<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Handler;

use ArrayAccess\TrayDigita\Handler\Interfaces\MiddlewareDispatcherInterface;
use ArrayAccess\TrayDigita\Traits\Service\CallStackTraceTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewareRequestHandler implements RequestHandlerInterface
{
    use CallStackTraceTrait;

    private $middleware;

    public function __construct(
        private readonly MiddlewareDispatcherInterface $dispatcher,
        MiddlewareInterface|callable $middleware,
        private RequestHandlerInterface $next
    ) {
        $this->middleware = $middleware;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertCallstack();

        $manager = $this->dispatcher->getManager();
        $manager?->dispatch(
            'middleware.beforeHandle',
            $this->middleware,
            $request
        );
        try {
            $response =  $this->middleware instanceof MiddlewareInterface
                ? $this->middleware->process($request, $this->next)
                : ($this->middleware)($request, $this->next);
            $manager?->dispatch(
                'middleware.handle',
                $this->middleware,
                $request,
                $response
            );
            return $response;
        } finally {
            $manager?->dispatch(
                'middleware.afterHandle',
                $this->middleware,
                $request,
                $response??null
            );
            $this->resetCallstack();
        }
    }
}
