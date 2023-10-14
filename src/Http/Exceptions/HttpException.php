<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http\Exceptions;

use ArrayAccess\TrayDigita\Exceptions\Runtime\UnProcessableException;
use ArrayAccess\TrayDigita\Http\Interfaces\HttpExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class HttpException extends UnProcessableException implements HttpExceptionInterface
{
    protected ?string $title = null;

    protected string $description = '';

    public function __construct(
        protected ServerRequestInterface $request,
        string $message = '',
        int $code = 0,
        Throwable $previousException = null
    ) {
        parent::__construct($message, $code, $previousException);
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getTitle(): string
    {
        return $this->title??'';
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
