<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http\Interfaces;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;

interface HttpExceptionInterface extends Throwable
{
    public function getRequest() : ServerRequestInterface;
}
