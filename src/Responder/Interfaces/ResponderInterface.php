<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Responder\Interfaces;

use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use Psr\Http\Message\ResponseInterface;

interface ResponderInterface extends ManagerAllocatorInterface, ContainerAllocatorInterface
{
    public function setContentType(string $contentType);

    public function setCharset(?string $charset);

    public function getContentType() : string;

    public function getCharset() : ?string;

    public function serve(int $code, mixed $data = null, ?ResponseInterface $response = null) : ResponseInterface;
}
