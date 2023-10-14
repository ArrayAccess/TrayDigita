<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http\Interfaces;

use Psr\Http\Message\ResponseInterface;

interface ResponseDispatcherInterface
{
    public function dispatchResponse(ResponseInterface $response): ResponseInterface;
}
