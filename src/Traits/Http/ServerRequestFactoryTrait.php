<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Traits\Http;

use ArrayAccess\TrayDigita\Http\Factory\ServerRequestFactory;
use ArrayAccess\TrayDigita\Http\Factory\StreamFactory;
use ArrayAccess\TrayDigita\Http\ServerRequest;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

trait ServerRequestFactoryTrait
{
    abstract public function getContainer() : ?ContainerInterface;

    public function getServerRequestFactory() : ServerRequestFactoryInterface
    {
        return ContainerHelper::use(
            ServerRequestFactoryInterface::class,
            $this->getContainer()
        )??new ServerRequestFactory();
    }

    public function getDefaultServerRequest()
    {
        $container = $this->getContainer();
        $request = ContainerHelper::getNull(
            ServerRequestInterface::class,
            $container
        );
        if (!$request instanceof ServerRequestInterface) {
            $request = $this->getServerRequestFromGlobals(
                ContainerHelper::use(StreamFactoryInterface::class, $container)
            );
        }

        return $request;
    }

    public function getServerRequestFromGlobals(
        ?StreamFactoryInterface $streamFactory = null
    ): ServerRequestInterface {
        return ServerRequest::fromGlobals(
            $this->getServerRequestFactory(),
            $streamFactory??new StreamFactory()
        );
    }

    public function convertRequestToServerRequest(RequestInterface $request) : ServerRequestInterface
    {
        if ($request instanceof ServerRequestInterface) {
            return $request;
        }

        $serverRequest = $this->getServerRequestFactory()
            ->createServerRequest(
                $request->getMethod(),
                $request->getUri(),
                $_SERVER
            )
            ->withRequestTarget($request->getRequestTarget())
            ->withBody($request->getBody())
            ->withParsedBody($_POST)
            ->withCookieParams($_COOKIE)
            ->withQueryParams($_GET);
        foreach ($serverRequest->getHeaders() as $headerName => $value) {
            $serverRequest = $serverRequest->withoutHeader($headerName);
        }
        foreach ($request->getHeaders() as $headerName => $value) {
            $serverRequest = $serverRequest->withHeader($headerName, $value);
        }
        return $serverRequest;
    }
}
