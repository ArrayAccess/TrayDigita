<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http\Factory;

use ArrayAccess\TrayDigita\Http\Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

class ResponseFactory implements ResponseFactoryInterface
{
    /**
     * @inheritdoc
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response($code, reason: $reasonPhrase);
    }
}
