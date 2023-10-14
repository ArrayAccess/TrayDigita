<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Handler;

use ArrayAccess\TrayDigita\Handler\Interfaces\ShutdownHandlerInterface;
use ArrayAccess\TrayDigita\Middleware\ErrorMiddleware;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class ShutdownHandler implements ShutdownHandlerInterface
{
    public function __construct(protected ?ContainerInterface $container)
    {
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    public function process(
        ServerRequestInterface $request,
        Throwable $exception,
        ErrorMiddleware $middleware,
        bool $displayErrorDetails
    ): ResponseInterface {
        return $middleware->getErrorHandler($exception::class)(
            $request,
            $exception,
            $displayErrorDetails
        );
    }
}
