<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http;

use ArrayAccess\TrayDigita\Http\Factory\ResponseFactory;
use ArrayAccess\TrayDigita\Http\Factory\StreamFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

class Client implements ClientInterface
{
    private ClientInterface|Psr18Client $client;

    public function __construct(
        array $defaultOptions = [],
        int $maxHostConnections = 6,
        ResponseFactoryInterface $responseFactory = null,
        StreamFactoryInterface $streamFactory = null
    ) {
        $this->client = new Psr18Client(
            HttpClient::create($defaultOptions, $maxHostConnections),
            $responseFactory??new ResponseFactory(),
            $streamFactory??new StreamFactory()
        );
    }

    public function getClient(): Psr18Client|ClientInterface
    {
        return $this->client;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->getClient()->sendRequest($request);
    }

    public function send(string $method, UriInterface|string $uri): ResponseInterface
    {
        $request = $this->getClient()->createRequest($method, $uri);
        return $this->sendRequest($request);
    }
}
