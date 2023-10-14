<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\i18n\Records;

use JsonSerializable;
use Serializable;
use function serialize;
use function unserialize;

final class Continent implements Serializable, JsonSerializable
{
    public function __construct(protected string $code, protected string $name)
    {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function serialize() : string
    {
        return serialize($this->__serialize());
    }

    public function unserialize(string $data): void
    {
        $this->__unserialize(unserialize($data));
    }

    public function __serialize(): array
    {
        return [
            'code' => $this->getCode(),
            'name' => $this->getName(),
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->code = $data['code'];
        $this->name = $data['name'];
    }

    public function jsonSerialize(): array
    {
        return $this->__serialize();
    }
}
