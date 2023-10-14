<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\ErrorRenderer;

use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Handler\Interfaces\ErrorHandlerInterface;
use ArrayAccess\TrayDigita\Http\Exceptions\HttpException;
use ArrayAccess\TrayDigita\View\Interfaces\ErrorRendererInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

abstract class AbstractErrorRenderer implements ErrorRendererInterface, ContainerIndicateInterface
{
    protected string $defaultErrorTitle = 'Application Error';

    protected string $defaultErrorDescription = 'A website error has occurred. Sorry for the temporary inconvenience.';

    public function __construct(protected ErrorHandlerInterface $errorHandler)
    {
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails
    ): ?string {
        return $this->format($request, $exception, $displayErrorDetails);
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->errorHandler->getContainer();
    }

    public function getErrorDescription(Throwable $e) : string
    {
        if ($e instanceof HttpException) {
            return $e->getDescription();
        }
        return $this->defaultErrorDescription;
    }

    public function getErrorTitle(Throwable $e) : string
    {
        if ($e instanceof HttpException) {
            return $e->getTitle();
        }

        return $this->defaultErrorTitle;
    }

    abstract protected function format(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails
    ) : ?string;
}
