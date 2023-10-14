<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel\Interfaces;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface TerminableInterface
{
    public function terminate(ServerRequestInterface $request, ResponseInterface $response);
}
