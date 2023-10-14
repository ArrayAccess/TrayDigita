<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel\Interfaces;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface RunnableInterface
{
    public function run(ServerRequestInterface $request) : ResponseInterface;
}
