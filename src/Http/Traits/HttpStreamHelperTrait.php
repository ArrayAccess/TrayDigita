<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http\Traits;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\UnsupportedArgumentException;
use ArrayAccess\TrayDigita\Http\Factory\StreamFactory;
use Psr\Http\Message\StreamInterface;
use function is_object;
use function is_scalar;
use function method_exists;

trait HttpStreamHelperTrait
{
    protected function determineBodyStream($body) : StreamInterface
    {
        if (is_scalar($body) || is_object($body) && method_exists($body, '__tostring')) {
            $body = (new StreamFactory())->createStream((string) $body);
            $body->seek(0);
        } elseif (!$body instanceof StreamInterface) {
            throw new UnsupportedArgumentException(
                'Invalid resource type: ' . gettype($body)
            );
        }
        return $body;
    }
}
