<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http\Traits;

use ArrayAccess\TrayDigita\Exceptions\Runtime\UnsupportedRuntimeException;
use ArrayAccess\TrayDigita\Http\Exceptions\MalformedHeaderException;
use ArrayAccess\TrayDigita\Http\Exceptions\MalformedMethodException;
use Psr\Http\Message\UriInterface;
use function get_class;
use function is_object;
use function is_string;
use function preg_match;
use function sprintf;
use function trim;

trait HttpAssertionTrait
{
    protected function assertUri(mixed $uri): void
    {
        if (!is_string($uri) && ! $uri instanceof UriInterface) {
            throw new MalformedHeaderException(
                sprintf(
                    'Uri must be as a string or instance of %s but %s provided.',
                    $uri,
                    is_object($uri) ? get_class($uri) : gettype($uri)
                )
            );
        }
    }

    /**
     * @see https://tools.ietf.org/html/rfc7230#section-3.2
     *
     * field-value    = *( field-content / obs-fold )
     * field-content  = field-vchar [ 1*( SP / HTAB ) field-vchar ]
     * field-vchar    = VCHAR / obs-text
     * VCHAR          = %x21-7E
     * obs-text       = %x80-FF
     * obs-fold       = CRLF 1*( SP / HTAB )
     */
    protected function assertHeaderValue(string $value) : void
    {
        // The regular expression intentionally does not support the obs-fold production, because as
        // per RFC 7230#3.2.4:
        //
        // A sender MUST NOT generate a message that includes
        // line folding (i.e., that has any field-value that contains a match to
        // the obs-fold rule) unless the message is intended for packaging
        // within the message/http media type.
        //
        // Clients must not send a request with line folding and a server sending folded headers is
        // likely very rare. Line folding is a fairly obscure feature of HTTP/1.1 and thus not accepting
        // folding is not likely to break any legitimate use case.
        if (! preg_match('/^[\x20\x09\x21-\x7E\x80-\xFF]*$/', $value)) {
            throw new MalformedHeaderException(
                sprintf('"%s" is not valid header value', $value)
            );
        }
    }

    /**
     * @see https://tools.ietf.org/html/rfc7230#section-3.2
     *
     * @param mixed $header
     */
    protected function assertHeaderName(mixed $header): void
    {
        if (!is_string($header)) {
            throw new MalformedHeaderException(
                sprintf(
                    'Header name must be a string but %s provided.',
                    is_object($header) ? get_class($header) : gettype($header)
                )
            );
        }

        if (! preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $header)) {
            throw new MalformedHeaderException(
                sprintf(
                    '"%s" is not valid header name',
                    $header
                )
            );
        }
    }

    /**
     * @param mixed $method
     */
    protected function assertMethod(mixed $method): void
    {
        if (!is_string($method) || trim($method) === '') {
            throw new MalformedMethodException(
                'Method must be a non-empty string.'
            );
        }
    }

    protected function assertProtocolVersion($version): void
    {
        if (!is_string($version)) {
            throw new UnsupportedRuntimeException(
                sprintf(
                    'Protocol version must be as a string but "%s" given.',
                    is_object($version) ? get_class($version) : $version
                )
            );
        }
    }
}
