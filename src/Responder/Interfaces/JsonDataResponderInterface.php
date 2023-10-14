<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Responder\Interfaces;

interface JsonDataResponderInterface
{
    public function setStatusCode(int $code);

    public function getStatusCode() : int;

    public function setMessage($message);

    public function getMessage();

    public function setErrorMessage($message);

    public function getErrorMessage();

    public function setMetadata(array $meta);

    public function setMeta($name, $value);

    public function removeMeta($name);
    public function hasMeta($name);

    public function getMetadata(): array;
}
