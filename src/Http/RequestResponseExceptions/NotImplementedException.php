<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http\RequestResponseExceptions;

class NotImplementedException extends RequestSpecializedCodeException
{
    protected $code = 501;

    protected string $description = 'The server does not support the functionality required to fulfill the request.';
}
