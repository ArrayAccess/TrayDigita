<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http\Exceptions;

use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\Http\Factory\StreamFactory;
use Psr\Http\Message\StreamInterface;
use Throwable;

class OutPutBufferingException extends RuntimeException
{
    protected StreamInterface $stream;

    public function __construct(
        ?StreamInterface $stream = null,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->stream = $stream??(new StreamFactory())->createStream(
            ob_get_contents()?:''
        );
        parent::__construct($message, $code, $previous);
    }

    public function getStream(): StreamInterface
    {
        return $this->stream;
    }
}
