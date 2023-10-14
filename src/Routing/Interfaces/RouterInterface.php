<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing\Interfaces;

use ArrayAccess\TrayDigita\Http\Interfaces\HttpExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

interface RouterInterface extends RouteMethodInterface
{
    /**
     * @param bool $dispatchAllHttpMethod
     */
    public function setDispatchAllHttpMethod(bool $dispatchAllHttpMethod);

    /**
     * @return bool
     */
    public function isDispatchAllHttpMethod() : bool;

    /**
     * Dispatch route by request request
     *
     * @param ServerRequestInterface $request
     * @return MatchedRouteInterface|HttpExceptionInterface
     */
    public function dispatch(ServerRequestInterface $request) : MatchedRouteInterface|HttpExceptionInterface;

    /**
     * Set base path of request
     *
     * @param string $path
     */
    public function setBasePath(string $path);

    /**
     * @return string
     */
    public function getBasePath() : string;
}
