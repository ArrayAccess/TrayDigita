<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http\Factory;

use ArrayAccess\TrayDigita\Http\Uri;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

class UriFactory implements UriFactoryInterface
{
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}
