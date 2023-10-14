<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Handler\Interfaces;

use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

interface ErrorHandlerInterface extends ContainerIndicateInterface
{
    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails
    ) : ResponseInterface;
}
