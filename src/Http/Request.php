<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use ArrayAccess\TrayDigita\Http\Traits\HttpAssertionTrait;
use ArrayAccess\TrayDigita\Http\Traits\HttpStreamHelperTrait;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use function preg_match;
use function strtoupper;

class Request extends Message implements RequestInterface
{
    use HttpAssertionTrait,
        HttpStreamHelperTrait;

    /**
     * @var UriInterface
     */
    protected UriInterface $uri;
    /**
     * @var string
     */
    protected string $method;

    /**
     * @var ?string
     */
    protected ?string $requestTarget = null;

    /**
     * @param string $method
     * @param $uri
     * @param array $headers
     * @param null $body
     * @param string $version
     */
    public function __construct(
        string $method,
        $uri,
        array $headers = [],
        $body = null,
        string $version = self::DEFAULT_PROTOCOL_VERSION
    ) {
        $this->assertMethod($method);
        $this->assertUri($uri);

        if (!$uri instanceof UriInterface) {
            $uri = new Uri($uri);
        }

        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->setHeaders($headers);
        $this->protocolVersion = $version;

        if (isset($this->headerNames['host'])) {
            $this->updateHostFromUri();
        }

        if ($body !== '' && $body !== null) {
            $this->stream = $this->determineBodyStream($body);
        }
    }

    /**
     * @return string
     */
    public function getRequestTarget() : string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }
        if ($this->uri->getQuery() !== '') {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    public function withRequestTarget($requestTarget) : static
    {
        if (preg_match('#\s#', $requestTarget)) {
            throw new InvalidArgumentException(
                'Invalid request target provided; cannot contain whitespace'
            );
        }

        $new = clone $this;
        $new->requestTarget = $requestTarget;
        return $new;
    }

    public function getMethod() : string
    {
        return $this->method;
    }

    public function withMethod($method) : static
    {
        $this->assertMethod($method);
        $obj = clone $this;
        $obj->method = strtoupper($method);
        return $obj;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false) : static
    {
        if ($uri === $this->uri) {
            return $this;
        }

        $new = clone $this;
        $new->uri = $uri;

        if (!$preserveHost || !isset($this->headerNames['host'])) {
            $new->updateHostFromUri();
        }

        return $new;
    }

    private function updateHostFromUri() : void
    {
        $host = $this->uri->getHost();

        if ($host === '') {
            return;
        }

        if (($port = $this->uri->getPort()) !== null) {
            $host .= ':' . $port;
        }

        if (isset($this->headerNames['host'])) {
            $header = $this->headerNames['host'];
        } else {
            $header = 'Host';
            $this->headerNames['host'] = 'Host';
        }

        // Ensure Host is the first header.
        // See: http://tools.ietf.org/html/rfc7230#section-5.4
        $this->headers = [$header => [$host]] + $this->headers;
    }
}
