<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http\RequestResponseExceptions;

class NotFoundException extends RequestSpecializedCodeException
{
    protected $code = 404;

    protected string $description = 'The page you requested was not found.';
}
