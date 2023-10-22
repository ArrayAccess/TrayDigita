<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois\Abstracts;

use Serializable;
use Stringable;
use function serialize;
use function trim;
use function unserialize;

abstract class AddressStorage implements Serializable, Stringable
{
    protected string $address;

    public function __construct(string $address)
    {
        $this->address = trim($address);
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    abstract public function isValid(): bool;

    abstract public function isLocal() : bool;

    public function serialize(): ?string
    {
        return serialize($this->__serialize());
    }

    public function unserialize(string $data): void
    {
        $this->__unserialize(unserialize($data));
    }

    abstract public function __serialize(): array;

    abstract public function __unserialize(array $data): void;

    public function __toString(): string
    {
        return $this->getAddress();
    }
}
