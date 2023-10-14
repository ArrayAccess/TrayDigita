<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http\Factory;

use ArrayAccess\TrayDigita\Http\Request;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

class RequestFactory implements RequestFactoryInterface
{
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new Request($method, $uri);
    }
}
