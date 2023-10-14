<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing\Exceptions;

use ArrayAccess\TrayDigita\Routing\Router;
use RuntimeException;
use Throwable;

class RouteException extends RuntimeException
{
    public function __construct(
        public readonly Router $router,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }
}
