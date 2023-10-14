<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing\Exceptions;

use ArrayAccess\TrayDigita\Routing\AbstractController;
use ArrayAccess\TrayDigita\Routing\Router;
use Throwable;
use function sprintf;

class RouteMethodNotExistsException extends RouteException
{
    public function __construct(
        Router $router,
        public readonly AbstractController $controller,
        public readonly string $method,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $message = $message?:sprintf(
            'Method %s on class %s is not exists',
            $this->method,
            $this->controller::class
        );
        parent::__construct($router, $message, $code, $previous);
    }
}
