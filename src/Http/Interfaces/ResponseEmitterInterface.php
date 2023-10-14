<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http\Interfaces;

use Psr\Http\Message\ResponseInterface;

interface ResponseEmitterInterface
{
    public function emit(ResponseInterface $response, bool $reduceError = false);

    public function close();

    public function getEmitCount() : int;

    public function emitted() : bool;

    public function isClosed() : bool;
}
