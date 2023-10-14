<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing\Exceptions;

use ArrayAccess\TrayDigita\Routing\Router;
use RuntimeException;
use Throwable;

class RouteControllerException extends RuntimeException
{
    public function __construct(
        public readonly Router $router,
        public readonly Throwable $exception,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $message = $message ?: $this->exception->getMessage();
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * @return Throwable
     */
    public function getException(): Throwable
    {
        return $this->exception;
    }
}
