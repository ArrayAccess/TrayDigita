<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Handler\Interfaces;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerIndicateInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface MiddlewareDispatcherInterface extends RequestHandlerInterface, ManagerIndicateInterface
{
    /**
     * @return array<class-string<MiddlewareInterface>>
     */
    public function getRegisteredMiddlewareClasses() : array;

    public function seedMiddlewareStack(RequestHandlerInterface $handler);

    public function addMiddleware(MiddlewareInterface $middleware) : MiddlewareDispatcherInterface;
}
