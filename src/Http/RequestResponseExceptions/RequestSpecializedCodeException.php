<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http\RequestResponseExceptions;

use ArrayAccess\TrayDigita\Http\Code;
use ArrayAccess\TrayDigita\Http\Exceptions\HttpException;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use function sprintf;

abstract class RequestSpecializedCodeException extends HttpException
{
    public function __construct(
        ServerRequestInterface $request,
        ?string $message = null,
        Throwable $previousException = null
    ) {
        $message ??= Code::statusMessage($this->code)??null;
        if ($message) {
            $this->message = $message;
        }
        parent::__construct($request, $this->message, $this->code, $previousException);

        if ($this->title === null && ($status = Code::statusMessage($this->code))) {
            $this->title = sprintf('%d %s', $this->code, $status);
        }
    }
}
