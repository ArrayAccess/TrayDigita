<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\Interfaces;

use ArrayAccess\TrayDigita\Handler\Interfaces\ErrorHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

interface ErrorRendererInterface
{
    public function __construct(ErrorHandlerInterface $errorHandler);

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails
    ) : ?string;
}
