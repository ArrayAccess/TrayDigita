<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Responder\Interfaces;

use Psr\Http\Message\ResponseInterface;

interface JsonResponderInterface extends ResponderInterface
{
    public function decode(string $data, bool $assoc = true);

    public function encode($data) : string;

    public function format(int $code, $data, bool $forceDebug = false) : array;

    public function serveJsonMetadata(
        JsonDataResponderInterface $metadataResponder,
        ?ResponseInterface $response = null
    ) : ResponseInterface;
}
