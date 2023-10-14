<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Handler\Interfaces;

use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Middleware\ErrorMiddleware;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

interface ShutdownHandlerInterface extends ContainerIndicateInterface
{
    public function __construct(?ContainerInterface $container);

    public function process(
        ServerRequestInterface $request,
        Throwable $exception,
        ErrorMiddleware $middleware,
        bool $displayErrorDetails
    ) : ResponseInterface;
}
