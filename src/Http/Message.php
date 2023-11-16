<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http;

use ArrayAccess\TrayDigita\Http\Exceptions\MalformedHeaderException;
use ArrayAccess\TrayDigita\Http\Factory\StreamFactory;
use ArrayAccess\TrayDigita\Http\Traits\HttpAssertionTrait;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;
use function array_map;
use function array_merge;
use function array_values;
use function get_class;
use function implode;
use function is_array;
use function is_object;
use function is_scalar;
use function sprintf;
use function strtolower;
use function trim;

class Message implements MessageInterface
{
    use HttpAssertionTrait;

    public const DEFAULT_PROTOCOL_VERSION = '1.1';

    protected string $protocolVersion = self::DEFAULT_PROTOCOL_VERSION;

    /**
     * @var array
     */
    protected array $headers = [];

    /**
     * @var array
     */
    protected array $headerNames = [];

    /**
     * @var ?StreamInterface $stream
     */
    protected ?StreamInterface $stream = null;

    /**
     * @return string
     */
    public function getProtocolVersion() : string
    {
        return $this->protocolVersion;
    }

    /**
     * @param string $version
     *
     * @return $this
     */
    public function withProtocolVersion(string $version) : static
    {
        $this->assertProtocolVersion($version);
        $obj = clone $this;
        $obj->protocolVersion = $version;
        return $obj;
    }

    /**
     * @param mixed $value
     *
     * @return string[]
     */
    private function normalizeHeaderValue(mixed $value) : array
    {
        if (!is_array($value)) {
            return $this->trimAndValidateHeaderValues([$value]);
        }

        if (count($value) === 0) {
            throw new MalformedHeaderException(
                'Header value can not be an empty array.'
            );
        }

        return $this->trimAndValidateHeaderValues($value);
    }

    /**
     * @param array $values Header values
     *
     * @return string[] Trimmed header values
     *
     * @throws MalformedHeaderException
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.4
     */
    protected function trimAndValidateHeaderValues(array $values) : array
    {
        return array_map(function ($value) {
            if (!is_scalar($value) && null !== $value) {
                throw new MalformedHeaderException(
                    sprintf(
                        'Header value must be scalar or null but %s provided.',
                        is_object($value) ? get_class($value) : gettype($value)
                    )
                );
            }

            $trimmed = trim((string) $value, " \t");
            $this->assertHeaderValue($trimmed);

            return $trimmed;
        }, array_values($values));
    }

    /**
     * @param array<string|int, string|string[]> $headers
     */
    protected function setHeaders(array $headers) : void
    {
        $this->headerNames = $this->headers = [];
        foreach ($headers as $header => $value) {
            // Numeric array keys are converted to int by PHP.
            $header = (string) $header;

            $this->assertHeaderName($header);
            $value = $this->normalizeHeaderValue($value);
            $normalized = strtolower($header);
            if (isset($this->headerNames[$normalized])) {
                $header = $this->headerNames[$normalized];
                $this->headers[$header] = array_merge($this->headers[$header], $value);
            } else {
                $this->headerNames[$normalized] = $header;
                $this->headers[$header] = $value;
            }
        }
    }

    public function getHeaders() : array
    {
        return $this->headers;
    }

    public function hasHeader($name) : bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    public function getHeader($name) : array
    {
        $header = strtolower($name);

        if (!isset($this->headerNames[$header])) {
            return [];
        }

        $header = $this->headerNames[$header];

        return $this->headers[$header];
    }

    public function getHeaderLine($name) : string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader($name, $value) : static
    {
        $this->assertHeaderName($name);
        $value = $this->normalizeHeaderValue($value);
        $normalized = strtolower($name);

        $obj = clone $this;
        if (isset($obj->headerNames[$normalized])) {
            unset($obj->headers[$obj->headerNames[$normalized]]);
        }
        $obj->headerNames[$normalized] = $name;
        $obj->headers[$name] = $value;

        return $obj;
    }

    public function withJson(?string $charset = null) : static
    {
        $contentType = 'application/json';
        if ($charset) {
            $contentType .= sprintf('; charset=%s', $charset);
        }
        return $this->withHeader('Content-Type', $contentType);
    }

    public function withAddedHeader($name, $value) : static
    {
        $this->assertHeaderName($name);
        $value = $this->normalizeHeaderValue($value);
        $normalized = strtolower($name);

        $obj = clone $this;
        if (isset($obj->headerNames[$normalized])) {
            $header = $this->headerNames[$normalized];
            $obj->headers[$header] = array_merge($this->headers[$header], $value);
            return $obj;
        }

        $obj->headerNames[$normalized] = $name;
        $obj->headers[$name] = $value;

        return $obj;
    }

    public function withoutHeader($name) : static
    {
        $normalized = strtolower($name);

        if (!isset($this->headerNames[$normalized])) {
            return $this;
        }

        $header = $this->headerNames[$normalized];

        $obj = clone $this;
        unset($obj->headers[$header], $obj->headerNames[$normalized]);

        return $obj;
    }

    public function getBody() : StreamInterface
    {
        if (!$this->stream) {
            $this->stream = (new StreamFactory())->createStream();
        }

        return $this->stream;
    }

    public function withBody(StreamInterface $body) : static
    {
        if ($body === $this->stream) {
            return $this;
        }

        $obj = clone $this;
        $obj->stream = $body;
        return $obj;
    }
}
